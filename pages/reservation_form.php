<?php
session_start();
$page_title = '예약하기 - 뷰티북';

// 로그인 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();
$business_id = $_GET['business_id'] ?? 0;
$teacher_id = $_GET['teacher_id'] ?? 0;

if (!$business_id) {
    header('Location: business_list.php');
    exit;
}

// 업체 정보
$business_stmt = $db->prepare("SELECT * FROM businesses WHERE id = ? AND is_active = 1 AND is_approved = 1");
$business_stmt->execute([$business_id]);
$business = $business_stmt->fetch();

if (!$business) {
    header('Location: business_list.php');
    exit;
}

// 선생님 목록
$teachers_stmt = $db->prepare("
    SELECT t.*, u.name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.business_id = ? AND t.is_active = 1 AND t.is_approved = 1
");
$teachers_stmt->execute([$business_id]);
$teachers = $teachers_stmt->fetchAll();

// 서비스 목록
$services_stmt = $db->prepare("SELECT * FROM business_services WHERE business_id = ? AND is_active = 1");
$services_stmt->execute([$business_id]);
$services = $services_stmt->fetchAll();

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_teacher_id = $_POST['teacher_id'] ?? '';
    $service_id = $_POST['service_id'] ?? '';
    $reservation_date = $_POST['reservation_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $customer_request = trim($_POST['customer_request'] ?? '');
    
    // 유효성 검사
    if (empty($selected_teacher_id)) {
        $errors[] = '선생님을 선택해주세요.';
    }
    if (empty($service_id)) {
        $errors[] = '서비스를 선택해주세요.';
    }
    if (empty($reservation_date)) {
        $errors[] = '예약 날짜를 선택해주세요.';
    }
    if (empty($start_time)) {
        $errors[] = '예약 시간을 선택해주세요.';
    }
    
    // 날짜 유효성 검사
    if (!empty($reservation_date)) {
        $selected_date = new DateTime($reservation_date);
        $today = new DateTime();
        if ($selected_date < $today) {
            $errors[] = '오늘 이후의 날짜를 선택해주세요.';
        }
    }
    
    if (empty($errors)) {
        try {
            // 서비스 정보 가져오기
            $service_stmt = $db->prepare("SELECT * FROM business_services WHERE id = ?");
            $service_stmt->execute([$service_id]);
            $service = $service_stmt->fetch();
            
            if (!$service) {
                $errors[] = '선택한 서비스를 찾을 수 없습니다.';
            } else {
                // 예약 시간 계산
                $start_datetime = new DateTime($reservation_date . ' ' . $start_time);
                $end_datetime = clone $start_datetime;
                $end_datetime->add(new DateInterval('PT' . $service['duration'] . 'M'));
                
                // 예약 저장
                $reservation_stmt = $db->prepare("
                    INSERT INTO reservations (
                        customer_id, business_id, teacher_id, service_id,
                        reservation_date, start_time, end_time, total_amount,
                        customer_request, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $reservation_stmt->execute([
                    $_SESSION['user_id'],
                    $business_id,
                    $selected_teacher_id,
                    $service_id,
                    $reservation_date,
                    $start_time,
                    $end_datetime->format('H:i:s'),
                    $service['price'],
                    $customer_request
                ]);
                
                $reservation_id = $db->lastInsertId();
                
                // 결제 페이지로 리다이렉트
                header('Location: reservation_checkout.php?reservation_id=' . $reservation_id);
                exit;
            }
            
        } catch (Exception $e) {
            $errors[] = '예약 신청 중 오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}
?>

<style>
.reservation-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.business-info {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.business-name {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 10px;
}

.reservation-form {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.form-title {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    margin-bottom: 10px;
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

.time-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.time-slot {
    padding: 10px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.time-slot:hover {
    border-color: #ff4757;
    background: #fff5f5;
}

.time-slot.selected {
    border-color: #ff4757;
    background: #ff4757;
    color: white;
}

.service-grid {
    display: grid;
    gap: 15px;
    margin-top: 10px;
}

.service-item {
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.service-item:hover {
    border-color: #ff4757;
    background: #fff5f5;
}

.service-item.selected {
    border-color: #ff4757;
    background: #fff5f5;
}

.service-name {
    font-weight: bold;
    margin-bottom: 5px;
}

.service-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    color: #666;
}

.service-price {
    font-weight: bold;
    color: #ff4757;
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

.hidden {
    display: none;
}

@media (max-width: 768px) {
    .time-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .service-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<div class="reservation-container">
    <a href="business_detail.php?id=<?= $business_id ?>" class="back-btn">← 업체로 돌아가기</a>
    
    <div class="business-info">
        <div class="business-name"><?= htmlspecialchars($business['name']) ?></div>
        <div style="color: #666;"><?= htmlspecialchars($business['address']) ?></div>
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

    <div class="reservation-form">
        <div class="form-title">예약하기</div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">선생님 선택 *</label>
                <select name="teacher_id" class="form-select" required>
                    <option value="">선생님을 선택하세요</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= $teacher['id'] ?>" 
                                <?= ($_POST['teacher_id'] ?? $teacher_id) == $teacher['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($teacher['name']) ?>
                            <?= $teacher['specialty'] ? ' - ' . htmlspecialchars($teacher['specialty']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">서비스 선택 *</label>
                <?php if (empty($services)): ?>
                    <p>등록된 서비스가 없습니다.</p>
                <?php else: ?>
                    <div class="service-grid">
                        <?php foreach ($services as $service): ?>
                            <div class="service-item" onclick="selectService(<?= $service['id'] ?>)">
                                <input type="radio" name="service_id" value="<?= $service['id'] ?>" 
                                       id="service_<?= $service['id'] ?>" class="hidden"
                                       <?= ($_POST['service_id'] ?? '') == $service['id'] ? 'checked' : '' ?>>
                                <div class="service-name"><?= htmlspecialchars($service['service_name']) ?></div>
                                <div class="service-info">
                                    <span><?= $service['duration'] ?>분</span>
                                    <span class="service-price"><?= number_format($service['price']) ?>원</span>
                                </div>
                                <?php if ($service['description']): ?>
                                    <div style="font-size: 14px; color: #666; margin-top: 5px;">
                                        <?= htmlspecialchars($service['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">예약 날짜 *</label>
                <input type="date" name="reservation_date" class="form-input" 
                       value="<?= htmlspecialchars($_POST['reservation_date'] ?? '') ?>" 
                       min="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">예약 시간 *</label>
                <input type="time" name="start_time" class="form-input" 
                       value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">요청사항</label>
                <textarea name="customer_request" class="form-textarea" rows="4" 
                          placeholder="특별한 요청사항이 있으시면 적어주세요"><?= htmlspecialchars($_POST['customer_request'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="submit-btn">예약 신청하기</button>
        </form>
    </div>
</div>

<script>
function selectService(serviceId) {
    // 모든 서비스 아이템에서 selected 클래스 제거
    document.querySelectorAll('.service-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // 선택된 서비스 아이템에 selected 클래스 추가
    event.currentTarget.classList.add('selected');
    
    // 라디오 버튼 체크
    document.getElementById('service_' + serviceId).checked = true;
}

// 페이지 로드 시 이미 선택된 서비스가 있다면 표시
document.addEventListener('DOMContentLoaded', function() {
    const checkedService = document.querySelector('input[name="service_id"]:checked');
    if (checkedService) {
        const serviceItem = checkedService.closest('.service-item');
        if (serviceItem) {
            serviceItem.classList.add('selected');
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 