<?php
session_start();
$page_title = '업체 정보 수정 - 뷰티북';

// 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business_owner') {
    header('Location: login.php');
    exit;
}

require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();

// 업체 정보 가져오기
$business_stmt = $db->prepare("
    SELECT b.*, bo.business_license 
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

$success_message = '';
$error_message = '';

// 폼 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $opening_hours = sanitize($_POST['opening_hours'] ?? '');
    $closing_hours = sanitize($_POST['closing_hours'] ?? '');
    
    if (empty($name) || empty($phone) || empty($address)) {
        $error_message = '필수 정보를 모두 입력해주세요.';
    } else {
        try {
            $update_stmt = $db->prepare("
                UPDATE businesses 
                SET name = ?, phone = ?, address = ?, description = ?, 
                    category = ?, opening_hours = ?, closing_hours = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $update_stmt->execute([
                $name, $phone, $address, $description, 
                $category, $opening_hours, $closing_hours, 
                $business['id']
            ]);
            
            if ($result) {
                $success_message = '업체 정보가 성공적으로 수정되었습니다.';
                // 업데이트된 정보 다시 가져오기
                $business_stmt->execute([$_SESSION['user_id']]);
                $business = $business_stmt->fetch();
            } else {
                $error_message = '업체 정보 수정에 실패했습니다.';
            }
        } catch (Exception $e) {
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
        <h1>업체 정보 수정</h1>
        <p>업체의 기본 정보를 수정하여 고객들에게 정확한 정보를 제공하세요</p>
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
                    <label class="form-label" for="name">업체명 *</label>
                    <input type="text" id="name" name="name" class="form-input" 
                           value="<?= htmlspecialchars($business['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="phone">전화번호 *</label>
                    <input type="tel" id="phone" name="phone" class="form-input" 
                           value="<?= htmlspecialchars($business['phone']) ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="address">주소 *</label>
                <input type="text" id="address" name="address" class="form-input" 
                       value="<?= htmlspecialchars($business['address']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="category">카테고리</label>
                <select id="category" name="category" class="form-select">
                    <option value="">카테고리 선택</option>
                    <option value="hair" <?= $business['category'] === 'hair' ? 'selected' : '' ?>>헤어</option>
                    <option value="nail" <?= $business['category'] === 'nail' ? 'selected' : '' ?>>네일</option>
                    <option value="skincare" <?= $business['category'] === 'skincare' ? 'selected' : '' ?>>피부관리</option>
                    <option value="massage" <?= $business['category'] === 'massage' ? 'selected' : '' ?>>마사지</option>
                    <option value="waxing" <?= $business['category'] === 'waxing' ? 'selected' : '' ?>>왁싱</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">영업 시간</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="opening_hours">오픈 시간</label>
                    <input type="time" id="opening_hours" name="opening_hours" class="form-input" 
                           value="<?= htmlspecialchars($business['opening_hours']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="closing_hours">마감 시간</label>
                    <input type="time" id="closing_hours" name="closing_hours" class="form-input" 
                           value="<?= htmlspecialchars($business['closing_hours']) ?>">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="section-title">업체 소개</h3>
            
            <div class="form-group">
                <label class="form-label" for="description">업체 설명</label>
                <textarea id="description" name="description" class="form-textarea" 
                          placeholder="업체에 대한 자세한 소개를 작성해주세요..."><?= htmlspecialchars($business['description']) ?></textarea>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">정보 수정</button>
            <a href="business_dashboard.php" class="btn btn-secondary">취소</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?> 