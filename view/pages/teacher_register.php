<?php
$pageTitle = "선생님 등록";
require_once '../includes/functions.php';

// 로그인 체크 및 업체 권한 확인
if (!isLoggedIn() || getUserRole() !== 'business') {
    redirect('../pages/login.php');
}

$business_id = getUserBusinessId();
$specialties = getBusinessSpecialties($business_id);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $specialty = sanitizeInput($_POST['specialty']);
    $introduction = sanitizeInput($_POST['introduction']);
    $career = sanitizeInput($_POST['career']);
    
    if (empty($name) || empty($email) || empty($phone) || empty($specialty)) {
        $error = "모든 필수 필드를 입력해주세요.";
    } elseif (!in_array($specialty, $specialties)) {
        $error = "선택한 전문분야가 유효하지 않습니다.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // 이메일 중복 체크
            $check_query = "SELECT id FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = "이미 등록된 이메일입니다.";
            } else {
                $db->beginTransaction();
                
                // users 테이블에 삽입
                $user_query = "INSERT INTO users (name, email, phone, role, business_id, status) VALUES (:name, :email, :phone, 'teacher', :business_id, 'active')";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->bindParam(':name', $name);
                $user_stmt->bindParam(':email', $email);
                $user_stmt->bindParam(':phone', $phone);
                $user_stmt->bindParam(':business_id', $business_id);
                $user_stmt->execute();
                
                $teacher_id = $db->lastInsertId();
                
                // teachers 테이블에 삽입
                $teacher_query = "INSERT INTO teachers (id, specialty, introduction, career, status) VALUES (:id, :specialty, :introduction, :career, 'active')";
                $teacher_stmt = $db->prepare($teacher_query);
                $teacher_stmt->bindParam(':id', $teacher_id);
                $teacher_stmt->bindParam(':specialty', $specialty);
                $teacher_stmt->bindParam(':introduction', $introduction);
                $teacher_stmt->bindParam(':career', $career);
                $teacher_stmt->execute();
                
                $db->commit();
                $message = "선생님이 성공적으로 등록되었습니다.";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error = "등록 중 오류가 발생했습니다: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">선생님 등록</h3>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">이름 *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">이메일 *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">연락처 *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="specialty" class="form-label">전문분야 *</label>
                                <select class="form-control" id="specialty" name="specialty" required>
                                    <option value="">전문분야 선택</option>
                                    <?php foreach ($specialties as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec); ?>">
                                            <?php echo htmlspecialchars($spec); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="career" class="form-label">경력</label>
                        <textarea class="form-control" id="career" name="career" rows="3" placeholder="경력사항을 입력해주세요"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="introduction" class="form-label">소개</label>
                        <textarea class="form-control" id="introduction" name="introduction" rows="3" placeholder="자기소개를 입력해주세요"></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="business_dashboard.php" class="btn btn-secondary me-md-2">취소</a>
                        <button type="submit" class="btn btn-primary">등록</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>