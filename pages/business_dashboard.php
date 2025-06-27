<?php
session_start();
$page_title = '업체 관리 - 뷰티북';

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

// 선생님 목록 가져오기
$teachers_stmt = $db->prepare("
    SELECT t.*, u.name, u.email, u.phone 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.business_id = ?
    ORDER BY t.created_at DESC
");
$teachers_stmt->execute([$business['id']]);
$teachers = $teachers_stmt->fetchAll();

// 최근 예약 목록
$reservations_stmt = $db->prepare("
    SELECT r.*, u.name as customer_name, t.user_id as teacher_user_id, tu.name as teacher_name
    FROM reservations r
    JOIN users u ON r.customer_id = u.id
    JOIN teachers t ON r.teacher_id = t.id
    JOIN users tu ON t.user_id = tu.id
    WHERE r.business_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reservations_stmt->execute([$business['id']]);
$recent_reservations = $reservations_stmt->fetchAll();

// 통계 데이터
$today_reservations_stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM reservations 
    WHERE business_id = ? AND reservation_date = CURDATE()
");
$today_reservations_stmt->execute([$business['id']]);
$today_reservations = $today_reservations_stmt->fetchColumn();

$pending_reservations_stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM reservations 
    WHERE business_id = ? AND status = 'pending'
");
$pending_reservations_stmt->execute([$business['id']]);
$pending_reservations = $pending_reservations_stmt->fetchColumn();

// 이벤트 통계
$events_stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM events 
    WHERE business_id = ? AND is_active = 1
");
$events_stmt->execute([$business['id']]);
$active_events = $events_stmt->fetchColumn();
?>

<style>
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.business-name {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 10px;
}

.business-status {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    display: inline-block;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.quick-actions {
    margin-top: 20px;
}

.quick-actions a {
    background: #ff4757;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    margin-right: 10px;
    margin-bottom: 10px;
    display: inline-block;
    transition: all 0.3s;
}

.quick-actions a:hover {
    background: #ff3742;
    transform: translateY(-2px);
}

.quick-actions a.event-btn {
    background: #6f42c1;
}

.quick-actions a.event-btn:hover {
    background: #5a32a3;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 36px;
    margin-bottom: 15px;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 10px;
}

.stat-label {
    color: #666;
    font-size: 16px;
}

.section-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 22px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.add-btn {
    background: #ff4757;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.table th {
    background: #f8f9fa;
    font-weight: bold;
    color: #2c3e50;
}

.status-badge {
    padding: 4px 10px;
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

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 20px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 10px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .table {
        font-size: 14px;
    }
    
    .section-title {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<div class="dashboard-container">
    <!-- 업체 정보 헤더 -->
    <div class="dashboard-header">
        <div class="business-name"><?= htmlspecialchars($business['name']) ?></div>
        <div class="business-status <?= $business['is_approved'] ? 'status-approved' : 'status-pending' ?>">
            <?= $business['is_approved'] ? '승인 완료' : '승인 대기 중' ?>
        </div>
        <div style="margin-top: 10px; color: #666;">
            <?= htmlspecialchars($business['address']) ?>
        </div>
        
        <div class="quick-actions">
            <a href="teacher_register.php">선생님 등록</a>
            <a href="business_event_manage.php" class="event-btn">이벤트 관리</a>
            <a href="business_edit.php">업체 정보 수정</a>
            <a href="reservation_manage.php">예약 관리</a>
        </div>
    </div>

    <!-- 통계 카드 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-number"><?= $today_reservations ?></div>
            <div class="stat-label">오늘 예약</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-number"><?= $pending_reservations ?></div>
            <div class="stat-label">승인 대기</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">👩‍💼</div>
            <div class="stat-number"><?= count($teachers) ?></div>
            <div class="stat-label">등록된 선생님</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🎉</div>
            <div class="stat-number"><?= $active_events ?></div>
            <div class="stat-label">활성 이벤트</div>
        </div>
    </div>

    <!-- 선생님 관리 -->
    <div class="section-card">
        <div class="section-title">
            선생님 관리
            <a href="teacher_register.php" class="add-btn">+ 선생님 등록</a>
        </div>
        
        <?php if (empty($teachers)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">👩‍💼</div>
                <h3>등록된 선생님이 없습니다</h3>
                <p>선생님을 등록하여 예약 서비스를 시작하세요</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>이름</th>
                        <th>연락처</th>
                        <th>이메일</th>
                        <th>상태</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?= htmlspecialchars($teacher['name']) ?></td>
                            <td><?= htmlspecialchars($teacher['phone']) ?></td>
                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                            <td>
                                <span class="status-badge <?= $teacher['is_approved'] ? 'status-confirmed' : 'status-pending' ?>">
                                    <?= $teacher['is_approved'] ? '승인됨' : '대기중' ?>
                                </span>
                            </td>
                            <td>
                                <a href="teacher_edit.php?id=<?= $teacher['id'] ?>">수정</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- 최근 예약 -->
    <div class="section-card">
        <div class="section-title">
            최근 예약
            <a href="reservation_manage.php" class="add-btn">전체 보기</a>
        </div>
        
        <?php if (empty($recent_reservations)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <h3>예약이 없습니다</h3>
                <p>고객들의 예약을 기다리고 있습니다</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>고객명</th>
                        <th>선생님</th>
                        <th>예약일시</th>
                        <th>상태</th>
                        <th>금액</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_reservations as $reservation): ?>
                        <tr>
                            <td><?= htmlspecialchars($reservation['customer_name']) ?></td>
                            <td><?= htmlspecialchars($reservation['teacher_name']) ?></td>
                            <td><?= $reservation['reservation_date'] ?> <?= $reservation['start_time'] ?></td>
                            <td>
                                <span class="status-badge status-<?= $reservation['status'] ?>">
                                    <?php
                                    $status_names = [
                                        'pending' => '대기중',
                                        'confirmed' => '확정',
                                        'cancelled' => '취소',
                                        'completed' => '완료'
                                    ];
                                    echo $status_names[$reservation['status']] ?? $reservation['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?= number_format($reservation['total_amount']) ?>원</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 