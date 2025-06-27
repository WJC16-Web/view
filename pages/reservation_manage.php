<?php
session_start();
$page_title = '예약 관리 - 뷰티북';

// 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business_owner') {
    header('Location: login.php');
    exit;
}

require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();

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

// 예약 상태 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];
    $action = $_POST['action'];
    
    if ($action === 'confirm') {
        $update_stmt = $db->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ? AND business_id = ?");
        $update_stmt->execute([$reservation_id, $business['id']]);
    } elseif ($action === 'reject') {
        $rejection_reason = $_POST['rejection_reason'] ?? '';
        $update_stmt = $db->prepare("UPDATE reservations SET status = 'rejected', rejection_reason = ? WHERE id = ? AND business_id = ?");
        $update_stmt->execute([$rejection_reason, $reservation_id, $business['id']]);
    }
}

// 필터링 파라미터
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';

// 예약 목록 쿼리 구성
$where_conditions = ["r.business_id = ?"];
$params = [$business['id']];

if ($status_filter !== 'all') {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "r.reservation_date = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$reservations_stmt = $db->prepare("
    SELECT r.*, u.name as customer_name, u.phone as customer_phone,
           t.user_id as teacher_user_id, tu.name as teacher_name,
           bs.service_name, bs.price
    FROM reservations r
    JOIN users u ON r.customer_id = u.id
    JOIN teachers t ON r.teacher_id = t.id
    JOIN users tu ON t.user_id = tu.id
    LEFT JOIN business_services bs ON r.service_id = bs.id
    WHERE $where_clause
    ORDER BY r.reservation_date DESC, r.start_time DESC
    LIMIT 50
");
$reservations_stmt->execute($params);
$reservations = $reservations_stmt->fetchAll();
?>

<style>
.reservation-manage-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.page-title {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 10px;
}

.filters {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}

.filter-select, .filter-input {
    padding: 10px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 16px;
}

.filter-btn {
    background: #ff4757;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.reservations-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.reservation-card {
    border: 2px solid #e1e5e9;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.reservation-card:hover {
    border-color: #ff4757;
    box-shadow: 0 5px 15px rgba(255, 71, 87, 0.1);
}

.reservation-header {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    margin-bottom: 15px;
}

.reservation-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.info-value {
    font-weight: bold;
    color: #333;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-confirmed {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.status-completed {
    background: #d1ecf1;
    color: #0c5460;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.action-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.btn-confirm {
    background: #28a745;
    color: white;
}

.btn-reject {
    background: #dc3545;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
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

@media (max-width: 768px) {
    .reservation-info {
        grid-template-columns: 1fr;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        justify-content: center;
    }
}
</style>

<div class="reservation-manage-container">
    <a href="business_dashboard.php" class="back-btn">← 대시보드로 돌아가기</a>
    
    <div class="page-header">
        <div class="page-title">예약 관리</div>
        <p><?= htmlspecialchars($business['name']) ?>의 예약 현황을 관리하세요</p>
    </div>

    <!-- 필터 -->
    <div class="filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">상태</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>전체</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>대기중</option>
                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>확정</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>거절</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>취소</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>완료</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">예약 날짜</label>
                    <input type="date" name="date" class="filter-input" value="<?= htmlspecialchars($date_filter) ?>">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="filter-btn">필터 적용</button>
                </div>
            </div>
        </form>
    </div>

    <!-- 예약 목록 -->
    <div class="reservations-section">
        <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 20px;">📋</div>
                <h3>예약이 없습니다</h3>
                <p>조건에 맞는 예약이 없습니다.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reservations as $reservation): ?>
                <div class="reservation-card">
                    <div class="reservation-header">
                        <h4>예약 #<?= $reservation['id'] ?></h4>
                        <span class="status-badge status-<?= $reservation['status'] ?>">
                            <?php
                            $status_names = [
                                'pending' => '대기중',
                                'confirmed' => '확정',
                                'rejected' => '거절',
                                'cancelled' => '취소',
                                'completed' => '완료'
                            ];
                            echo $status_names[$reservation['status']] ?? $reservation['status'];
                            ?>
                        </span>
                    </div>
                    
                    <div class="reservation-info">
                        <div class="info-item">
                            <div class="info-label">고객명</div>
                            <div class="info-value"><?= htmlspecialchars($reservation['customer_name']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">연락처</div>
                            <div class="info-value"><?= htmlspecialchars($reservation['customer_phone']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">선생님</div>
                            <div class="info-value"><?= htmlspecialchars($reservation['teacher_name']) ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">예약 일시</div>
                            <div class="info-value"><?= $reservation['reservation_date'] ?> <?= $reservation['start_time'] ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">서비스</div>
                            <div class="info-value"><?= htmlspecialchars($reservation['service_name'] ?? '서비스') ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">금액</div>
                            <div class="info-value"><?= number_format($reservation['total_amount']) ?>원</div>
                        </div>
                    </div>
                    
                    <?php if ($reservation['customer_request']): ?>
                        <div style="margin-top: 15px;">
                            <div class="info-label">고객 요청사항</div>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-top: 5px;">
                                <?= htmlspecialchars($reservation['customer_request']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($reservation['status'] === 'pending'): ?>
                        <div class="action-buttons">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('예약을 승인하시겠습니까?')">
                                <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="action-btn btn-confirm">승인</button>
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirmReject()">
                                <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="rejection_reason" id="rejection_reason_<?= $reservation['id'] ?>">
                                <button type="submit" class="action-btn btn-reject" onclick="setRejectionReason(<?= $reservation['id'] ?>)">거절</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function setRejectionReason(reservationId) {
    const reason = prompt('거절 사유를 입력해주세요:');
    if (reason) {
        document.getElementById('rejection_reason_' + reservationId).value = reason;
        return true;
    }
    return false;
}

function confirmReject() {
    return confirm('예약을 거절하시겠습니까?');
}
</script>

<?php require_once '../includes/footer.php'; ?> 