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
    SELECT b.*, bo.id as owner_id
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

// 업체의 세부 카테고리 가져오기
$available_specialties = [];
if ($business['subcategories']) {
    $subcategories = json_decode($business['subcategories'], true);
    if (is_array($subcategories)) {
        $available_specialties = $subcategories;
    }
}

// 기본 카테고리도 추가
$main_category = $business['category'];
if (!in_array($main_category, $available_specialties)) {
    array_unshift($available_specialties, $main_category);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialties = $_POST['specialties'] ?? [];
    $experience_years = (int)($_POST['experience_years'] ?? 0);
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
    if (empty($specialties)) {
        $errors[] = '전문분야를 하나 이상 선택해주세요.';
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
            
            // 사용자 계정 생성 (패스워드는 임시로 생성)
            $temp_password = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 12);
            $user_stmt = $db->prepare("
                INSERT INTO users (email, password, name, phone, user_type, is_active) 
                VALUES (?, ?, ?, ?, 'teacher', 1)
            ");
            $user_stmt->execute([
                $email,
                password_hash($temp_password, PASSWORD_DEFAULT),
                $name,
                $phone
            ]);
            
            $user_id = $db->lastInsertId();
            
            // 선생님 정보 저장
            $specialties_json = json_encode($specialties);
            $teacher_stmt = $db->prepare("
                INSERT INTO teachers (user_id, business_id, specialties, experience_years, introduction, is_active, is_approved) 
                VALUES (?, ?, ?, ?, ?, 1, 1)
            ");
            $teacher_stmt->execute([
                $user_id,
                $business['id'],
                $specialties_json,
                $experience_years,
                $introduction
            ]);
            
            $db->commit();
            $success_message = '선생님이 성공적으로 등록되었습니다. 임시 비밀번호가 생성되었습니다: ' . $temp_password;
            
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

.form-input, .form-textarea, .form-select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-input:focus, .form-textarea:focus, .form-select:focus {
    outline: none;
    border-color: #ff4757;
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.specialty-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.specialty-option {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.specialty-option:hover {
    border-color: #ff4757;
    background-color: #fff5f5;
}

.specialty-option input[type="checkbox"] {
    margin-right: 10px;
}

.specialty-option.selected {
    border-color: #ff4757;
    background-color: #fff5f5;
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

.temp-password {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
    color: #856404;
    font-weight: bold;
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
            <div class="temp-password">
                <strong>주의:</strong> 선생님에게 임시 비밀번호를 전달하고, 첫 로그인 시 비밀번호를 변경하도록 안내해주세요.
            </div>
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
                <label class="form-label">전문분야 * (복수 선택 가능)</label>
                <div class="specialty-options">
                    <?php foreach ($available_specialties as $specialty): ?>
                        <label class="specialty-option">
                            <input type="checkbox" name="specialties[]" value="<?= htmlspecialchars($specialty) ?>"
                                   <?= (isset($_POST['specialties']) && in_array($specialty, $_POST['specialties'])) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($specialty) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">업체의 카테고리에 등록된 분야만 선택 가능합니다.</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">경력 (년)</label>
                <select name="experience_years" class="form-select">
                    <option value="0" <?= ($_POST['experience_years'] ?? 0) == 0 ? 'selected' : '' ?>>신입</option>
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                        <option value="<?= $i ?>" <?= ($_POST['experience_years'] ?? 0) == $i ? 'selected' : '' ?>><?= $i ?>년</option>
                    <?php endfor; ?>
                    <option value="21" <?= ($_POST['experience_years'] ?? 0) > 20 ? 'selected' : '' ?>>20년 이상</option>
                </select>
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

<script>
// 체크박스 선택 시 스타일 변경
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.specialty-option input[type="checkbox"]');
    
    checkboxes.forEach(checkbox => {
        const option = checkbox.closest('.specialty-option');
        
        function updateStyle() {
            if (checkbox.checked) {
                option.classList.add('selected');
            } else {
                option.classList.remove('selected');
            }
        }
        
        updateStyle(); // 초기 상태 설정
        checkbox.addEventListener('change', updateStyle);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?> 