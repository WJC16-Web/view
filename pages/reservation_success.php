<?php
session_start();
$page_title = '예약 완료 - 뷰티북';

// 로그인 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = getDB();
$reservation_id = $_GET['reservation_id'] ?? 0;

if (!$reservation_id) {
    header('Location: customer_mypage.php?tab=reservations');
    exit;
}

// 예약 정보 확인
$stmt = $db->prepare("
    SELECT r.*, b.name as business_name, b.address as business_address, b.phone as business_phone,
           t.name as teacher_name, bs.service_name, bs.price, bs.duration,
           u.name as customer_name, u.phone as customer_phone,
           p.payment_method, p.amount as payment_amount, p.transaction_id
    FROM reservations r
    JOIN businesses b ON r.business_id = b.id
    JOIN teachers t ON r.teacher_id = t.id
    JOIN business_services bs ON r.service_id = bs.id
    JOIN users u ON r.customer_id = u.id
    LEFT JOIN payments p ON r.id = p.reservation_id
    WHERE r.id = ? AND r.customer_id = ?
");
$stmt->execute([$reservation_id, $_SESSION['user_id']]);
$reservation = $stmt->fetch();

if (!$reservation) {
    header('Location: customer_mypage.php?tab=reservations');
    exit;
}

include '../includes/header.php';
?>

<style>
.success-container {
    max-width: 700px;
    margin: 0 auto;
    padding: 20px;
    text-align: center;
}

.success-header {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    padding: 40px 20px;
    border-radius: 15px 15px 0 0;
    margin-bottom: 0;
}

.success-icon {
    font-size: 60px;
    margin-bottom: 20px;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-10px);
    }
    60% {
        transform: translateY(-5px);
    }
}

.success-title {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 10px;
}

.success-subtitle {
    font-size: 16px;
    opacity: 0.9;
}

.reservation-details {
    background: white;
    border-radius: 0 0 15px 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    padding: 30px;
    text-align: left;
}

.detail-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    align-items: center;
}

.detail-label {
    color: #666;
    font-weight: 500;
}

.detail-value {
    font-weight: bold;
    color: #2c3e50;
}

.highlight-amount {
    color: #ff4757;
    font-size: 18px;
}

.payment-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.payment-completed {
    background: #28a745;
}

.payment-onsite {
    background: #ffc107;
    color: #333;
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 30px;
}

.action-btn {
    padding: 15px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    text-align: center;
    transition: all 0.3s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-outline {
    background: white;
    color: #007bff;
    border: 2px solid #007bff;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    text-decoration: none;
    color: inherit;
}

.info-card {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.warning-card {
    background: #fff8e1;
    border-left: 4px solid #ff9800;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<div class="success-container">
    <!-- 성공 헤더 -->
    <div class="success-header">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="success-title">예약이 완료되었습니다!</div>
        <div class="success-subtitle">
            <?php if ($reservation['payment_type'] === 'onsite'): ?>
                방문 시 현장에서 결제해주세요
            <?php else: ?>
                결제가 성공적으로 처리되었습니다
            <?php endif; ?>
        </div>
    </div>

    <!-- 예약 상세 정보 -->
    <div class="reservation-details">
        <!-- 업체 정보 -->
        <div class="detail-section">
            <div class="section-title">
                <i class="fas fa-store"></i> 업체 정보
            </div>
            <div class="detail-row">
                <span class="detail-label">업체명</span>
                <span class="detail-value"><?= htmlspecialchars($reservation['business_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">주소</span>
                <span class="detail-value"><?= htmlspecialchars($reservation['business_address']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">연락처</span>
                <span class="detail-value"><?= htmlspecialchars($reservation['business_phone']) ?></span>
            </div>
        </div>

        <!-- 예약 정보 -->
        <div class="detail-section">
            <div class="section-title">
                <i class="fas fa-calendar-check"></i> 예약 정보
            </div>
            <div class="detail-row">
                <span class="detail-label">예약번호</span>
                <span class="detail-value">#<?= $reservation['id'] ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">담당 선생님</span>
                <span class="detail-value"><?= htmlspecialchars($reservation['teacher_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">서비스</span>
                <span class="detail-value"><?= htmlspecialchars($reservation['service_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">예약일시</span>
                <span class="detail-value">
                    <?= date('Y년 m월 d일 (D) H:i', strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time'])) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">소요시간</span>
                <span class="detail-value"><?= $reservation['duration'] ?>분</span>
            </div>
        </div>

        <!-- 결제 정보 -->
        <div class="detail-section">
            <div class="section-title">
                <i class="fas fa-credit-card"></i> 결제 정보
            </div>
            <div class="detail-row">
                <span class="detail-label">서비스 금액</span>
                <span class="detail-value"><?= number_format($reservation['total_amount']) ?>원</span>
            </div>
            
            <?php if ($reservation['discount_amount'] > 0): ?>
            <div class="detail-row">
                <span class="detail-label">할인 금액</span>
                <span class="detail-value" style="color: #dc3545;">-<?= number_format($reservation['discount_amount']) ?>원</span>
            </div>
            <?php endif; ?>
            
            <?php if ($reservation['points_used'] > 0): ?>
            <div class="detail-row">
                <span class="detail-label">적립금 사용</span>
                <span class="detail-value" style="color: #dc3545;">-<?= number_format($reservation['points_used']) ?>원</span>
            </div>
            <?php endif; ?>
            
            <div class="detail-row">
                <span class="detail-label">결제 방식</span>
                <span class="detail-value">
                    <?php
                    $payment_types = [
                        'onsite' => '현장결제',
                        'full' => '전액 선결제',
                        'deposit' => '예약금 결제'
                    ];
                    echo $payment_types[$reservation['payment_type']] ?? $reservation['payment_type'];
                    ?>
                </span>
            </div>
            
            <?php if ($reservation['payment_amount'] > 0): ?>
            <div class="detail-row">
                <span class="detail-label">결제 상태</span>
                <span class="detail-value">
                    <span class="payment-badge payment-completed">결제완료</span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">결제 금액</span>
                <span class="detail-value highlight-amount"><?= number_format($reservation['payment_amount']) ?>원</span>
            </div>
            <?php if ($reservation['transaction_id']): ?>
            <div class="detail-row">
                <span class="detail-label">거래번호</span>
                <span class="detail-value"><?= htmlspecialchars($reservation['transaction_id']) ?></span>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="detail-row">
                <span class="detail-label">결제 상태</span>
                <span class="detail-value">
                    <span class="payment-badge payment-onsite">현장결제 예정</span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">현장 결제 금액</span>
                <span class="detail-value highlight-amount"><?= number_format($reservation['total_amount'] - $reservation['discount_amount'] - $reservation['points_used']) ?>원</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- 안내사항 -->
        <?php if ($reservation['payment_type'] === 'onsite'): ?>
        <div class="warning-card">
            <h6><i class="fas fa-exclamation-triangle"></i> 현장결제 안내</h6>
            <ul class="mb-0" style="text-align: left;">
                <li>예약시간 10분 전에 도착해주세요</li>
                <li>결제는 현금 또는 카드로 가능합니다</li>
                <li>예약 취소는 1시간 전까지 가능합니다</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="info-card">
            <h6><i class="fas fa-info-circle"></i> 예약 안내</h6>
            <ul class="mb-0" style="text-align: left;">
                <li>예약시간 10분 전에 도착해주세요</li>
                <li>결제가 완료되어 별도 현장결제는 없습니다</li>
                <li>예약 변경이 필요한 경우 업체에 문의해주세요</li>
            </ul>
        </div>
        <?php endif; ?>

        <!-- 액션 버튼들 -->
        <div class="action-buttons">
            <a href="customer_mypage.php?tab=reservations" class="action-btn btn-primary">
                <i class="fas fa-list"></i> 내 예약 관리
            </a>
            <a href="../business_list.php" class="action-btn btn-outline">
                <i class="fas fa-plus"></i> 추가 예약하기
            </a>
        </div>
        
        <div class="action-buttons" style="margin-top: 15px;">
            <a href="tel:<?= $reservation['business_phone'] ?>" class="action-btn btn-secondary">
                <i class="fas fa-phone"></i> 업체에 전화하기
            </a>
            <a href="business_detail.php?id=<?= $reservation['business_id'] ?>" class="action-btn btn-outline">
                <i class="fas fa-store"></i> 업체 페이지
            </a>
        </div>
    </div>
</div>

<script>
// 자동으로 마이페이지 새로고침 (예약 상태 업데이트)
setTimeout(() => {
    if (confirm('예약이 완료되었습니다. 마이페이지에서 예약 현황을 확인하시겠습니까?')) {
        window.location.href = 'customer_mypage.php?tab=reservations';
    }
}, 3000);

// 페이지 로드 시 알림
document.addEventListener('DOMContentLoaded', function() {
    // 성공 효과음 (브라우저에서 지원하는 경우)
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime); // C5
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    } catch (e) {
        // 오디오 컨텍스트 생성 실패 시 무시
    }
});
</script>

<?php include '../includes/footer.php'; ?> 