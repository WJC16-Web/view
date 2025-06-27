<?php
session_start();
$page_title = '업체 등록 - 뷰티북';

// 로그인 체크 및 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business_owner') {
    header('Location: login.php');
    exit;
}

require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();
$errors = [];
$success_message = '';

// 이미 등록된 업체가 있는지 확인
$check_stmt = $db->prepare("
    SELECT b.id, b.name, b.is_approved 
    FROM businesses b 
    JOIN business_owners bo ON b.owner_id = bo.id 
    WHERE bo.user_id = ?
");
$check_stmt->execute([$_SESSION['user_id']]);
$existing_business = $check_stmt->fetch();

// 업체 카테고리 정의
$business_categories = [
    'nail' => '네일샵',
    'hair' => '헤어샵',
    'waxing' => '왁싱샵',
    'skincare' => '피부관리실',
    'eyebrow' => '속눈썹/눈썹',
    'massage' => '마사지샵',
    'makeup' => '메이크업',
    'total' => '토탈뷰티샵'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_business) {
    $business_name = trim($_POST['business_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // 유효성 검사
    if (empty($business_name)) {
        $errors[] = '업체명을 입력해주세요.';
    }
    if (empty($category)) {
        $errors[] = '주 업종을 선택해주세요.';
    }
    if (empty($address)) {
        $errors[] = '주소를 입력해주세요.';
    }
    if (empty($phone)) {
        $errors[] = '전화번호를 입력해주세요.';
    }
    
    if (empty($errors)) {
        try {
            // business_owners 테이블에서 owner_id 가져오기
            $owner_stmt = $db->prepare("SELECT id FROM business_owners WHERE user_id = ?");
            $owner_stmt->execute([$_SESSION['user_id']]);
            $owner = $owner_stmt->fetch();
            
            if (!$owner) {
                $errors[] = '업체 관리자 정보를 찾을 수 없습니다.';
            } else {
                // 업체 정보 저장
                $business_stmt = $db->prepare("
                    INSERT INTO businesses (
                        owner_id, name, description, address, phone, 
                        category, is_active, is_approved
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, 0)
                ");
                
                $business_stmt->execute([
                    $owner['id'],
                    $business_name,
                    $description,
                    $address,
                    $phone,
                    $category
                ]);
                
                $success_message = '업체 등록이 완료되었습니다. 관리자 승인 후 서비스를 이용하실 수 있습니다.';
                
                // 등록된 업체 정보 다시 가져오기
                $existing_business = [
                    'id' => $db->lastInsertId(),
                    'name' => $business_name,
                    'is_approved' => 0
                ];
            }
            
        } catch (Exception $e) {
            $errors[] = '업체 등록 중 오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}
?>

<style>
.business-register-container {
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

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
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

.status-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.status-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.status-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 15px;
}

.status-desc {
    color: #666;
    line-height: 1.6;
}
</style>

<div class="business-register-container">
    <?php if ($existing_business): ?>
        <!-- 이미 등록된 업체가 있는 경우 -->
        <div class="status-card">
            <?php if ($existing_business['is_approved']): ?>
                <div class="status-icon">✅</div>
                <div class="status-title">업체 등록 완료</div>
                <div class="status-desc">
                    <strong><?= htmlspecialchars($existing_business['name']) ?></strong><br>
                    업체가 승인되어 서비스를 이용하실 수 있습니다.
                    <br><br>
                    <a href="business_dashboard.php" class="submit-btn">업체 관리하기</a>
                </div>
            <?php else: ?>
                <div class="status-icon">⏳</div>
                <div class="status-title">승인 대기 중</div>
                <div class="status-desc">
                    <strong><?= htmlspecialchars($existing_business['name']) ?></strong><br>
                    업체 등록이 완료되었습니다.<br>
                    관리자 승인 후 서비스를 이용하실 수 있습니다.
                    <br><br>
                    <a href="business_dashboard.php" class="submit-btn">업체 관리하기</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- 업체 등록 폼 -->
        <div class="register-header">
            <h1>업체 등록</h1>
            <p>뷰티북에 업체를 등록하고 더 많은 고객을 만나보세요</p>
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
                    <label class="form-label">업체명 *</label>
                    <input type="text" name="business_name" class="form-input" 
                           value="<?= htmlspecialchars($_POST['business_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">업체 소개</label>
                    <textarea name="description" class="form-textarea" rows="4" 
                              placeholder="업체를 소개해주세요"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">주 업종 *</label>
                    <select name="category" class="form-select" required>
                        <option value="">업종을 선택하세요</option>
                        <?php foreach ($business_categories as $key => $name): ?>
                            <option value="<?= $key ?>" <?= ($_POST['category'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">주소 *</label>
                    <input type="text" name="address" class="form-input" 
                           value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" 
                           placeholder="상세 주소를 입력해주세요" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">전화번호 *</label>
                    <input type="tel" name="phone" class="form-input" 
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                           placeholder="02-1234-5678" required>
                </div>
                
                <button type="submit" class="submit-btn">업체 등록하기</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?> 