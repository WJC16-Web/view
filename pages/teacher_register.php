<?php
session_start();
$page_title = '선생님 등록 - 뷰티북';

// 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business_owner') {
    header('Location: login.php');
    exit;
}

require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();
$errors = [];
$success_message = '';

// 업체 정보 확인
$business_stmt = $db->prepare("
    SELECT b.* 
    FROM businesses b 
    JOIN business_owners bo ON b.owner_id = bo.id 
    WHERE bo.user_id = ?
");
$business_stmt->execute([$_SESSION['user_id']]);
$business = $business_stmt->fetch();

if (!$business) {
    header('Location: business_register.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $specialty = trim($_POST['specialty'] ?? '');
    $career = trim($_POST['career'] ?? '');
    $introduction = trim($_POST['introduction'] ?? '');
    
    // 유효성 검사
    if (empty($name)) {
        $errors[] = '이름을 입력해주세요.';
    }
    if (empty($email)) {
        $errors[] = '이메일을 입력해주세요.';
    }
    if (empty($phone)) {
        $errors[] = '전화번호를 입력해주세요.';
    }
    if (empty($password)) {
        $errors[] = '비밀번호를 입력해주세요.';
    }
    
    // 이메일 중복 체크
    if (!empty($email)) {
        $email_check_stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $email_check_stmt->execute([$email]);
        if ($email_check_stmt->fetch()) {
            $errors[] = '이미 등록된 이메일입니다.';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // 사용자 계정 생성
            $user_stmt = $db->prepare("
                INSERT INTO users (email, password, name, phone, user_type, is_active) 
                VALUES (?, ?, ?, ?, 'teacher', 1)
            ");
            $user_stmt->execute([
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $name,
                $phone
            ]);
            
            $user_id = $db->lastInsertId();
            
            // 선생님 정보 저장
            $teacher_stmt = $db->prepare("
                INSERT INTO teachers (user_id, business_id, specialty, career, introduction, is_active, is_approved) 
                VALUES (?, ?, ?, ?, ?, 1, 1)
            ");
            $teacher_stmt->execute([
                $user_id,
                $business['id'],
                $specialty,
                $career,
                $introduction
            ]);
            
            $db->commit();
            $success_message = '선생님이 성공적으로 등록되었습니다.';
            
            // 폼 초기화
            $_POST = [];
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = '등록 중 오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}
?>

<style>
.teacher-register-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 40px 20px;
}

.register-header {
    text-align: center;
    margin-bottom: 40px;
}

.register-header h1 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.form-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #333;
}

.form-input, .form-textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: #ff4757;
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.submit-btn {
    background: linear-gradient(135deg, #ff4757, #ff6b7a);
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 8px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    width: 100%;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.back-btn {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    margin-bottom: 20px;
    display: inline-block;
}
</style>

<div class="teacher-register-container">
    <a href="business_dashboard.php" class="back-btn">← 대시보드로 돌아가기</a>
    
    <div class="register-header">
        <h1>선생님 등록</h1>
        <p><?= htmlspecialchars($business['name']) ?>에 새로운 선생님을 등록하세요</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-section">
            <div class="form-group">
                <label class="form-label">이름 *</label>
                <input type="text" name="name" class="form-input" 
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">이메일 *</label>
                <input type="email" name="email" class="form-input" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">전화번호 *</label>
                <input type="tel" name="phone" class="form-input" 
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                       placeholder="010-1234-5678" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">비밀번호 *</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">전문분야</label>
                <input type="text" name="specialty" class="form-input" 
                       value="<?= htmlspecialchars($_POST['specialty'] ?? '') ?>" 
                       placeholder="예: 네일아트, 헤어컷, 피부관리">
            </div>
            
            <div class="form-group">
                <label class="form-label">경력</label>
                <input type="text" name="career" class="form-input" 
                       value="<?= htmlspecialchars($_POST['career'] ?? '') ?>" 
                       placeholder="예: 5년차, OO아카데미 수료">
            </div>
            
            <div class="form-group">
                <label class="form-label">소개글</label>
                <textarea name="introduction" class="form-textarea" rows="4" 
                          placeholder="선생님을 소개해주세요"><?= htmlspecialchars($_POST['introduction'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="submit-btn">선생님 등록</button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?> 