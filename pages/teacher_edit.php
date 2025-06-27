<?php
session_start();
$page_title = '선생님 정보 수정 - 뷰티북';

// 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business_owner') {
    header('Location: login.php');
    exit;
}

require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();

// 선생님 ID 확인
$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$teacher_id) {
    header('Location: business_dashboard.php');
    exit;
}

// 업체 정보와 선생님 정보 가져오기
$business_stmt = $db->prepare("
    SELECT b.id 
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

// 선생님 정보 가져오기 (해당 업체의 선생님인지 확인)
$teacher_stmt = $db->prepare("
    SELECT t.*, u.name, u.email, u.phone 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ? AND t.business_id = ?
");
$teacher_stmt->execute([$teacher_id, $business['id']]);
$teacher = $teacher_stmt->fetch();

if (!$teacher) {
    header('Location: business_dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

// 폼 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $specialties = sanitize($_POST['specialties'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $introduction = sanitize($_POST['introduction'] ?? '');
    $is_approved = isset($_POST['is_approved']) ? 1 : 0;
    
    if (empty($name) || empty($email) || empty($phone)) {
        $error_message = '필수 정보를 모두 입력해주세요.';
    } elseif (!validateEmail($email)) {
        $error_message = '올바른 이메일 형식이 아닙니다.';
    } else {
        try {
            $db->beginTransaction();
            
            // 사용자 정보 업데이트
            $user_update_stmt = $db->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $user_update_stmt->execute([$name, $email, $phone, $teacher['user_id']]);
            
            // 선생님 정보 업데이트
            $teacher_update_stmt = $db->prepare("
                UPDATE teachers 
                SET specialties = ?, experience_years = ?, introduction = ?, 
                    is_approved = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $teacher_update_stmt->execute([
                $specialties, $experience_years, $introduction, 
                $is_approved, $teacher_id
            ]);
            
            $db->commit();
            
            $success_message = '선생님 정보가 성공적으로 수정되었습니다.';
            
            // 업데이트된 정보 다시 가져오기
            $teacher_stmt->execute([$teacher_id, $business['id']]);
            $teacher = $teacher_stmt->fetch();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = '오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}
?>

<style>
.edit-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.edit-header {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    text-align: center;
}

.edit-header h1 {
    color: #2c3e50;
    font-size: 28px;
    margin-bottom: 10px;
}

.edit-header p {
    color: #666;
    font-size: 16px;
}

.edit-form {
    background: white;
    border-radius: 15px;
    padding: 40px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.form-section {
    margin-bottom: 30px;
}

.section-title {
    font-size: 20px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f8f9fa;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: #2c3e50;
    font-weight: 500;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #ff4757;
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
}

.form-checkbox label {
    font-weight: normal;
    margin-bottom: 0;
}

.btn-group {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}

.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    transition: all 0.3s;
}

.btn-primary {
    background: #ff4757;
    color: white;
}

.btn-primary:hover {
    background: #ff3742;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
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

@media (max-width: 768px) {
    .edit-container {
        padding: 10px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .btn-group {
        flex-direction: column;
    }
}
</style>

<div class="edit-container">
    <div class="edit-header">
        <h1>선생님 정보 수정</h1>
        <p>선생님의 프로필 정보를 수정하여 고객들에게 정확한 정보를 제공하세요</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <form class="edit-form" method="POST">
        <div class="form-section">
            <h3 class="section-title">기본 정보</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="name">이름 *</label>
                    <input type="text" id="name" name="name" class="form-input" 
                           value="<?= htmlspecialchars($teacher['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">이메일 *</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?= htmlspecialchars($teacher['email']) ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="phone">전화번호 *</label>
                <input type="tel" id="phone" name="phone" class="form-input" 
                       value="<?= htmlspecialchars($teacher['phone']) ?>" required>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">전문 정보</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="specialties">전문 분야</label>
                    <input type="text" id="specialties" name="specialties" class="form-input" 
                           value="<?= htmlspecialchars($teacher['specialties']) ?>"
                           placeholder="예: 펌, 염색, 커트">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="experience_years">경력 (년)</label>
                    <input type="number" id="experience_years" name="experience_years" class="form-input" 
                           value="<?= $teacher['experience_years'] ?>" min="0" max="50">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="introduction">자기소개</label>
                <textarea id="introduction" name="introduction" class="form-textarea" 
                          placeholder="선생님에 대한 소개를 작성해주세요..."><?= htmlspecialchars($teacher['introduction']) ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">승인 상태</h3>
            
            <div class="form-group">
                <div class="form-checkbox">
                    <input type="checkbox" id="is_approved" name="is_approved" 
                           <?= $teacher['is_approved'] ? 'checked' : '' ?>>
                    <label for="is_approved">예약 접수 승인</label>
                </div>
                <small style="color: #666; margin-top: 5px; display: block;">
                    체크하면 고객들이 이 선생님에게 예약을 신청할 수 있습니다.
                </small>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">정보 수정</button>
            <a href="business_dashboard.php" class="btn btn-secondary">취소</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?> 