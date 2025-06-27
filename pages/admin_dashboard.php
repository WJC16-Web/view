<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 시스템 관리자 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();

// 대시보드 데이터 수집
try {
    // 전체 대기 업체 수
    $stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM businesses WHERE is_approved = 0");
    $stmt->execute();
    $pending_businesses = $stmt->fetch()['pending_count'];
    
    // 전체 회원 통계
    $stmt = $db->prepare("
        SELECT 
            user_type,
            COUNT(*) as count
        FROM users 
        WHERE is_active = 1
        GROUP BY user_type
    ");
    $stmt->execute();
    $user_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 오늘 가입자 수
    $stmt = $db->prepare("SELECT COUNT(*) as today_signups FROM users WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $today_signups = $stmt->fetch()['today_signups'];
    
    // 이번달 예약 통계
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM reservations 
        WHERE MONTH(reservation_date) = MONTH(CURDATE()) 
        AND YEAR(reservation_date) = YEAR(CURDATE())
        GROUP BY status
    ");
    $stmt->execute();
    $reservation_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 이번달 매출
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_revenue
        FROM reservations 
        WHERE status = 'completed'
        AND MONTH(reservation_date) = MONTH(CURDATE()) 
        AND YEAR(reservation_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $monthly_revenue = $stmt->fetch()['total_revenue'];
    
    // 최근 가입 업체
    $stmt = $db->prepare("
        SELECT b.*, u.name as owner_name
        FROM businesses b
        JOIN users u ON b.owner_id = u.id
        WHERE b.is_approved = 0
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_businesses = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$page_title = '관리자 대시보드 - 뷰티북';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-tachometer-alt text-primary"></i> 시스템 관리자 대시보드</h2>
                <div class="btn-group">
                    <a href="admin_business_approve.php" class="btn btn-outline-primary">
                        <i class="fas fa-building"></i> 업체 승인
                    </a>
                    <a href="admin_user_manage.php" class="btn btn-outline-success">
                        <i class="fas fa-users"></i> 회원 관리
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 주요 통계 카드 -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                승인 대기 업체</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_businesses); ?>개</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                오늘 가입자</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($today_signups); ?>명</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                이번달 예약</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format(array_sum($reservation_stats)); ?>건
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                이번달 매출</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($monthly_revenue); ?>원</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-won-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 회원 통계 & 예약 통계 -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">회원 유형별 통계</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $type_names = [
                        'customer' => '일반고객',
                        'business_owner' => '업체관리자',
                        'teacher' => '선생님',
                        'admin' => '관리자'
                    ];
                    
                    foreach ($user_stats as $type => $count): 
                    ?>
                        <div class="mb-2">
                            <span class="badge badge-<?= $type === 'customer' ? 'primary' : ($type === 'business_owner' ? 'success' : ($type === 'teacher' ? 'info' : 'danger')) ?>">
                                <?= $type_names[$type] ?? $type ?>
                            </span>
                            <span class="ml-2 font-weight-bold"><?= number_format($count) ?>명</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">이번달 예약 통계</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $status_labels = [
                        'pending' => '대기중',
                        'confirmed' => '확정',
                        'completed' => '완료',
                        'cancelled' => '취소',
                        'rejected' => '거절'
                    ];
                    
                    foreach ($reservation_stats as $status => $count): 
                    ?>
                        <div class="mb-2">
                            <span class="badge badge-<?= $status === 'completed' ? 'success' : ($status === 'confirmed' ? 'primary' : ($status === 'pending' ? 'warning' : 'danger')) ?>">
                                <?= $status_labels[$status] ?? $status ?>
                            </span>
                            <span class="ml-2 font-weight-bold"><?= number_format($count) ?>건</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 승인 대기 업체 목록 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">승인 대기 업체</h6>
                    <a href="admin_business_approve.php" class="btn btn-sm btn-primary">전체 보기</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_businesses)): ?>
                        <p class="text-muted text-center py-4">승인 대기중인 업체가 없습니다.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>업체명</th>
                                        <th>대표자명</th>
                                        <th>신청일</th>
                                        <th>작업</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_businesses as $business): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($business['name']) ?></td>
                                        <td><?= htmlspecialchars($business['owner_name']) ?></td>
                                        <td><?= date('Y-m-d', strtotime($business['created_at'])) ?></td>
                                        <td>
                                            <a href="admin_business_approve.php?id=<?= $business['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> 검토
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 빠른 액션 메뉴 -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">빠른 액션</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="admin_business_approve.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-building fa-2x mb-2"></i><br>
                                업체 승인
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="admin_user_manage.php" class="btn btn-outline-success btn-block">
                                <i class="fas fa-users fa-2x mb-2"></i><br>
                                회원 관리
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="admin_business_manage.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-store fa-2x mb-2"></i><br>
                                업체 관리
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="admin_report_manage.php" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                                신고 관리
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="admin_statistics.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                                통계 분석
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="admin_notice.php" class="btn btn-outline-secondary btn-block">
                                <i class="fas fa-bullhorn fa-2x mb-2"></i><br>
                                공지사항
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="admin_settings.php" class="btn btn-outline-dark btn-block">
                                <i class="fas fa-cogs fa-2x mb-2"></i><br>
                                설정
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.text-gray-800 {
    color: #5a5c69 !important;
}
.text-gray-300 {
    color: #dddfeb !important;
}
</style>

<?php require_once '../includes/footer.php'; ?> 
