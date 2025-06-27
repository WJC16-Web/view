<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 고객 권한 ?�인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'dashboard';
$db = getDB();

// ?�용???�보 조회
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

try {
    // ?�?�보???�계
    if ($tab === 'dashboard') {
        // ?�약 ?�황
        $stmt = $db->prepare("
            SELECT 
                COUNT(CASE WHEN status IN ('pending', 'confirmed') THEN 1 END) as upcoming_count,
                COUNT(CASE WHEN status = 'completed' AND MONTH(reservation_date) = MONTH(CURDATE()) THEN 1 END) as monthly_completed,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as total_completed
            FROM reservations 
            WHERE customer_id = ?
        ");
        $stmt->execute([$user_id]);
        $reservation_stats = $stmt->fetch();
        
        // 즐겨찾기 ?�체 ??
        $stmt = $db->prepare("SELECT COUNT(*) as favorite_count FROM customer_favorites WHERE customer_id = ?");
        $stmt->execute([$user_id]);
        $favorite_count = $stmt->fetch()['favorite_count'];
        
        // 보유 쿠폰 ??
        $stmt = $db->prepare("
            SELECT COUNT(*) as coupon_count 
            FROM customer_coupons cc
            JOIN coupons c ON cc.coupon_id = c.id
            WHERE cc.customer_id = ? AND cc.is_used = 0 AND c.valid_until >= CURDATE()
        ");
        $stmt->execute([$user_id]);
        $coupon_count = $stmt->fetch()['coupon_count'];
        
        // ?�립�?
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(CASE WHEN point_type = 'earned' THEN amount ELSE -amount END), 0) as total_points
            FROM points 
            WHERE customer_id = ?
        ");
        $stmt->execute([$user_id]);
        $total_points = $stmt->fetch()['total_points'];
        
        // 최근 ?�약
        $stmt = $db->prepare("
            SELECT r.*, b.name as business_name, t.name as teacher_name, bs.service_name
            FROM reservations r
            JOIN businesses b ON r.business_id = b.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN business_services bs ON r.service_id = bs.id
            WHERE r.customer_id = ?
            ORDER BY r.reservation_date DESC, r.start_time DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_reservations = $stmt->fetchAll();
    }
    
    // ?�약 관�?
    if ($tab === 'reservations') {
        $status_filter = $_GET['status'] ?? 'all';
        
        $where_clause = "WHERE r.customer_id = ?";
        $params = [$user_id];
        
        if ($status_filter !== 'all') {
            if ($status_filter === 'upcoming') {
                $where_clause .= " AND r.status IN ('pending', 'confirmed')";
            } else {
                $where_clause .= " AND r.status = ?";
                $params[] = $status_filter;
            }
        }
        
        $stmt = $db->prepare("
            SELECT r.*, b.name as business_name, b.address as business_address, b.phone as business_phone,
                   t.name as teacher_name, bs.service_name, bs.price
            FROM reservations r
            JOIN businesses b ON r.business_id = b.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN business_services bs ON r.service_id = bs.id
            $where_clause
            ORDER BY r.reservation_date DESC, r.start_time DESC
        ");
        $stmt->execute($params);
        $reservations = $stmt->fetchAll();
    }
    
    // 즐겨찾기
    if ($tab === 'favorites') {
        $stmt = $db->prepare("
            SELECT b.*, cf.created_at as favorited_at,
                   AVG(r.overall_rating) as avg_rating,
                   COUNT(r.id) as review_count
            FROM customer_favorites cf
            JOIN businesses b ON cf.business_id = b.id
            LEFT JOIN reviews r ON b.id = r.business_id
            WHERE cf.customer_id = ?
            GROUP BY b.id
            ORDER BY cf.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $favorites = $stmt->fetchAll();
    }
    
    // 쿠폰??
    if ($tab === 'coupons') {
        $stmt = $db->prepare("
            SELECT cc.*, c.*, b.name as business_name
            FROM customer_coupons cc
            JOIN coupons c ON cc.coupon_id = c.id
            LEFT JOIN businesses b ON c.business_id = b.id
            WHERE cc.customer_id = ?
            ORDER BY cc.is_used ASC, c.valid_until ASC
        ");
        $stmt->execute([$user_id]);
        $coupons = $stmt->fetchAll();
    }
    
    // ?�기 관�?
    if ($tab === 'reviews') {
        $stmt = $db->prepare("
            SELECT rv.*, b.name as business_name, t.name as teacher_name, bs.service_name
            FROM reviews rv
            JOIN reservations r ON rv.reservation_id = r.id
            JOIN businesses b ON r.business_id = b.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN business_services bs ON r.service_id = bs.id
            WHERE rv.customer_id = ?
            ORDER BY rv.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $reviews = $stmt->fetchAll();
    }
    
    // ?�립�??�역
    if ($tab === 'points') {
        $stmt = $db->prepare("
            SELECT p.*, r.id as reservation_id, b.name as business_name
            FROM points p
            LEFT JOIN reservations r ON p.reservation_id = r.id
            LEFT JOIN businesses b ON r.business_id = b.id
            WHERE p.customer_id = ?
            ORDER BY p.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id]);
        $point_history = $stmt->fetchAll();
        
        // 만료 ?�정 ?�립�?조회
        $stmt = $db->prepare("
            SELECT SUM(amount) as expiring_points
            FROM points 
            WHERE customer_id = ? AND point_type = 'earn' 
            AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$user_id]);
        $expiring_points = $stmt->fetch()['expiring_points'] ?? 0;
    }
    
} catch (PDOException $e) {
    $error = "?�이??조회 �??�류가 발생?�습?�다.";
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-3">
            <!-- ?�이?�바 메뉴 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user text-primary"></i>
                        <?= htmlspecialchars($user['name']) ?>??
                    </h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="?tab=dashboard" class="list-group-item list-group-item-action <?= $tab === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt"></i> ?�?�보??
                    </a>
                    <a href="?tab=reservations" class="list-group-item list-group-item-action <?= $tab === 'reservations' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i> ?�약 관�?
                    </a>
                    <a href="?tab=favorites" class="list-group-item list-group-item-action <?= $tab === 'favorites' ? 'active' : '' ?>">
                        <i class="fas fa-heart"></i> 즐겨찾기
                    </a>
                    <a href="?tab=coupons" class="list-group-item list-group-item-action <?= $tab === 'coupons' ? 'active' : '' ?>">
                        <i class="fas fa-ticket-alt"></i> 쿠폰??
                    </a>
                    <a href="?tab=reviews" class="list-group-item list-group-item-action <?= $tab === 'reviews' ? 'active' : '' ?>">
                        <i class="fas fa-star"></i> ???�기
                    </a>
                    <a href="?tab=points" class="list-group-item list-group-item-action <?= $tab === 'points' ? 'active' : '' ?>">
                        <i class="fas fa-coins"></i> ?�립�?
                    </a>
                    <a href="?tab=profile" class="list-group-item list-group-item-action <?= $tab === 'profile' ? 'active' : '' ?>">
                        <i class="fas fa-user-cog"></i> 개인?�정
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <?php if ($tab === 'dashboard'): ?>
                <!-- ?�?�보??-->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tachometer-alt text-primary"></i> 마이?�이지</h2>
                    <a href="../business_list.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> ?�체 찾기
                    </a>
                </div>
                
                <!-- ?�계 카드 -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">?�정???�약</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $reservation_stats['upcoming_count'] ?? 0 ?>�?/div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">?�번???�용</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $reservation_stats['monthly_completed'] ?? 0 ?>??/div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">보유 쿠폰</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $coupon_count ?? 0 ?>??/div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">?�립�?/div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_points ?? 0) ?>??/div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-coins fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 최근 ?�약 -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">최근 ?�약</h6>
                        <a href="?tab=reservations" class="btn btn-sm btn-outline-primary">?�체 보기</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_reservations)): ?>
                            <p class="text-muted text-center py-4">?�약 ?�역???�습?�다.</p>
                            <div class="text-center">
                                <a href="../business_list.php" class="btn btn-primary">�??�약?�기</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_reservations as $reservation): ?>
                                <div class="mb-3 p-3 border rounded">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="mb-1"><?= htmlspecialchars($reservation['business_name']) ?></h6>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($reservation['teacher_name']) ?> ?�생????
                                                <?= htmlspecialchars($reservation['service_name']) ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?= date('Y.m.d (D) H:i', strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time'])) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <div class="mb-1">
                                                <span class="badge badge-<?= getStatusBadgeClass($reservation['status']) ?>">
                                                    <?= getStatusLabel($reservation['status']) ?>
                                                </span>
                                            </div>
                                            <div class="font-weight-bold">
                                                <?= number_format($reservation['total_amount']) ?>??
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($tab === 'reservations'): ?>
                <!-- ?�약 관�?-->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-calendar-check text-primary"></i> ?�약 관�?/h3>
                    <a href="../business_list.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> ???�약
                    </a>
                </div>
                
                <!-- ?�태 ?�터 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <a href="?tab=reservations&status=all" class="btn btn-<?= $status_filter === 'all' ? 'primary' : 'outline-primary' ?>">?�체</a>
                            <a href="?tab=reservations&status=upcoming" class="btn btn-<?= $status_filter === 'upcoming' ? 'warning' : 'outline-warning' ?>">?�정</a>
                            <a href="?tab=reservations&status=completed" class="btn btn-<?= $status_filter === 'completed' ? 'success' : 'outline-success' ?>">?�료</a>
                            <a href="?tab=reservations&status=cancelled" class="btn btn-<?= $status_filter === 'cancelled' ? 'danger' : 'outline-danger' ?>">취소</a>
                        </div>
                    </div>
                </div>
                
                <!-- ?�약 목록 -->
                <?php if (empty($reservations)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">?�약 ?�역???�습?�다</h5>
                            <p class="text-muted">�??�약??진행?�보?�요!</p>
                            <a href="../business_list.php" class="btn btn-primary">?�체 찾기</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($reservations as $reservation): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5 class="card-title"><?= htmlspecialchars($reservation['business_name']) ?></h5>
                                        <p class="card-text">
                                            <strong><?= htmlspecialchars($reservation['teacher_name']) ?> ?�생??/strong><br>
                                            <i class="fas fa-cut"></i> <?= htmlspecialchars($reservation['service_name']) ?><br>
                                            <i class="fas fa-calendar"></i> <?= date('Y??m??d??(D)', strtotime($reservation['reservation_date'])) ?><br>
                                            <i class="fas fa-clock"></i> <?= date('H:i', strtotime($reservation['start_time'])) ?> ~ <?= date('H:i', strtotime($reservation['end_time'])) ?><br>
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reservation['business_address']) ?><br>
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($reservation['business_phone']) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <div class="mb-2">
                                            <span class="badge badge-<?= getStatusBadgeClass($reservation['status']) ?> badge-lg">
                                                <?= getStatusLabel($reservation['status']) ?>
                                            </span>
                                        </div>
                                        <h4 class="text-primary"><?= number_format($reservation['total_amount']) ?>??/h4>
                                        
                                        <div class="mt-3">
                                            <?php if ($reservation['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-danger" onclick="cancelReservation(<?= $reservation['id'] ?>)">
                                                    <i class="fas fa-times"></i> 취소
                                                </button>
                                            <?php elseif ($reservation['status'] === 'completed'): ?>
                                                <a href="review_write.php?reservation_id=<?= $reservation['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-star"></i> ?�기 ?�성
                                                </a>
                                                <a href="reservation_form.php?business_id=<?= $reservation['business_id'] ?>&teacher_id=<?= $reservation['teacher_id'] ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-redo"></i> ?�예??
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            <?php elseif ($tab === 'favorites'): ?>
                <!-- 즐겨찾기 -->
                <h3><i class="fas fa-heart text-danger"></i> 즐겨찾기</h3>
                <p class="text-muted mb-4">?�주 ?�용?�는 ?�체�??�?�해?�세??</p>
                
                <?php if (empty($favorites)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">즐겨찾기???�체가 ?�습?�다</h5>
                            <p class="text-muted">마음???�는 ?�체�?즐겨찾기??추�??�보?�요!</p>
                            <a href="../business_list.php" class="btn btn-primary">?�체 찾기</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($favorites as $business): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h5 class="card-title"><?= htmlspecialchars($business['name']) ?></h5>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removeFavorite(<?= $business['id'] ?>)">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </div>
                                        <p class="card-text"><?= htmlspecialchars($business['description']) ?></p>
                                        <div class="mb-2">
                                            <?php if ($business['avg_rating']): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="text-warning mr-1">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?= $i <= round($business['avg_rating']) ? '' : '-o' ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <span class="text-muted small"><?= number_format($business['avg_rating'], 1) ?> (<?= $business['review_count'] ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-heart"></i> <?= date('Y.m.d 추�?', strtotime($business['favorited_at'])) ?>
                                        </small>
                                    </div>
                                    <div class="card-footer">
                                        <a href="../business_detail.php?id=<?= $business['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i> ?�체 보기
                                        </a>
                                        <a href="../reservation_form.php?business_id=<?= $business['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-calendar-plus"></i> ?�약?�기
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($tab === 'coupons'): ?>
                <!-- 쿠폰??-->
                <h3><i class="fas fa-ticket-alt text-warning"></i> 쿠폰??/h3>
                <p class="text-muted mb-4">보유??쿠폰???�인?�고 ?�용?�세??</p>
                
                <?php if (empty($coupons)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">보유??쿠폰???�습?�다</h5>
                            <p class="text-muted">?�약???�료?�면 쿠폰??받을 ???�어??</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($coupons as $coupon): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 <?= $coupon['is_used'] ? 'text-muted' : '' ?> <?= strtotime($coupon['valid_until']) < time() ? 'bg-light' : '' ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="card-title"><?= htmlspecialchars($coupon['coupon_name']) ?></h6>
                                            <?php if ($coupon['is_used']): ?>
                                                <span class="badge badge-secondary">?�용?�료</span>
                                            <?php elseif (strtotime($coupon['valid_until']) < time()): ?>
                                                <span class="badge badge-danger">기간만료</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">?�용가??/span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="my-2">
                                            <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                                <span class="h4 text-primary"><?= $coupon['discount_value'] ?>% ?�인</span>
                                            <?php else: ?>
                                                <span class="h4 text-primary"><?= number_format($coupon['discount_value']) ?>???�인</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($coupon['business_name']): ?>
                                            <div class="text-muted small">
                                                <i class="fas fa-store"></i> <?= htmlspecialchars($coupon['business_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="text-muted small">
                                            <i class="fas fa-calendar"></i> 
                                            <?= date('Y.m.d', strtotime($coupon['valid_from'])) ?> ~ 
                                            <?= date('Y.m.d', strtotime($coupon['valid_until'])) ?>까�?
                                        </div>
                                        
                                        <?php if ($coupon['minimum_amount'] > 0): ?>
                                            <div class="text-muted small">
                                                <i class="fas fa-info-circle"></i> 
                                                <?= number_format($coupon['minimum_amount']) ?>???�상 구매??
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($tab === 'reviews'): ?>
                <!-- ???�기 -->
                <h3><i class="fas fa-star text-warning"></i> ???�기</h3>
                <p class="text-muted mb-4">?�성???�기�?관리하?�요.</p>
                
                <?php if (empty($reviews)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">?�성???�기가 ?�습?�다</h5>
                            <p class="text-muted">?�비???�용 ???�기�??�겨주세??</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6><?= htmlspecialchars($review['business_name']) ?> - <?= htmlspecialchars($review['teacher_name']) ?> ?�생??/h6>
                                        <div class="text-muted small mb-2">
                                            <?= htmlspecialchars($review['service_name']) ?> ??
                                            <?= date('Y.m.d', strtotime($review['created_at'])) ?>
                                        </div>
                                        <div class="mb-2">
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= $review['overall_rating'] ? '' : '-o' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($review['content'])) ?></p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <a href="review_edit.php?id=<?= $review['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> ?�정
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteReview(<?= $review['id'] ?>)">
                                            <i class="fas fa-trash"></i> ??��
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            <?php elseif ($tab === 'profile'): ?>
                <!-- 개인?�정 -->
                <h3><i class="fas fa-user-cog text-secondary"></i> 개인?�정</h3>
                <p class="text-muted mb-4">개인?�보�??�정?�세??</p>
                
                <div class="card">
                    <div class="card-body">
                        <form id="profileForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>?�름</label>
                                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>?�메??/label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>?�화번호</label>
                                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>?�년?�일</label>
                                        <input type="date" class="form-control" name="birth_date" value="<?= $user['birth_date'] ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>주소</label>
                                <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                            </div>
                            
                            <hr>
                            
                            <h5>비�?번호 변�?/h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>?�재 비�?번호</label>
                                        <input type="password" class="form-control" name="current_password">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>??비�?번호</label>
                                        <input type="password" class="form-control" name="new_password">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>??비�?번호 ?�인</label>
                                        <input type="password" class="form-control" name="confirm_password">
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h5>?�림 ?�정</h5>
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sms_enabled" id="sms_enabled" checked>
                                    <label class="form-check-label" for="sms_enabled">
                                        SMS ?�림 ?�신
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="email_enabled" id="email_enabled" checked>
                                    <label class="form-check-label" for="email_enabled">
                                        ?�메???�림 ?�신
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="marketing_enabled" id="marketing_enabled">
                                    <label class="form-check-label" for="marketing_enabled">
                                        마�????�보 ?�신 ?�의
                                    </label>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> ?�??
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($tab === 'points'): ?>
                <!-- ?�립�?관�?-->
                <h3><i class="fas fa-coins text-warning"></i> ?�립�?관�?/h3>
                <p class="text-muted mb-4">?�립�??�용 ?�역???�인?�고 관리하?�요.</p>
                
                <!-- ?�립�??�약 -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">보유 ?�립�?/div>
                                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?= number_format($total_points ?? 0) ?>??/div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-coins fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">만료 ?�정 (30??</div>
                                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?= number_format($expiring_points ?? 0) ?>??/div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">?�번???�립</div>
                                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                                            <?php
                                            $monthly_earned = 0;
                                            foreach ($point_history ?? [] as $point) {
                                                if ($point['point_type'] === 'earn' && date('Y-m', strtotime($point['created_at'])) === date('Y-m')) {
                                                    $monthly_earned += $point['amount'];
                                                }
                                            }
                                            echo number_format($monthly_earned);
                                            ?>??
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ?�립�??�역 -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">?�립�??�용 ?�역</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($point_history)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-coins fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">?�립�??�역???�습?�다</h5>
                                <p class="text-muted">?�약???�료?�면 ?�립금을 받을 ???�어??</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>?�짜</th>
                                            <th>구분</th>
                                            <th>?�용</th>
                                            <th>금액</th>
                                            <th>관???�체</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($point_history as $point): ?>
                                            <tr>
                                                <td><?= date('Y.m.d H:i', strtotime($point['created_at'])) ?></td>
                                                <td>
                                                    <?php if ($point['point_type'] === 'earn'): ?>
                                                        <span class="badge badge-success">?�립</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">?�용</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($point['description']) ?></td>
                                                <td>
                                                    <span class="<?= $point['point_type'] === 'earn' ? 'text-success' : 'text-danger' ?>">
                                                        <?= $point['point_type'] === 'earn' ? '+' : '-' ?><?= number_format(abs($point['amount'])) ?>??
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($point['business_name']): ?>
                                                        <?= htmlspecialchars($point['business_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ?�립�??�내 -->
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle"></i> ?�립�??�내</h6>
                    <ul class="mb-0">
                        <li><strong>?�립 기�?:</strong> ?�약 ?�료 ??결제 금액??1-3% ?�동 ?�립</li>
                        <li><strong>?�기 보상:</strong> ?�기 ?�성 ??1,000??추�? ?�립</li>
                        <li><strong>?�용 기�?:</strong> 1,000???�상 1,000???�위�??�용 가??/li>
                        <li><strong>?�효기간:</strong> ?�립?�로부??2??(만료 30?????�림)</li>
                    </ul>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.text-gray-800 { color: #5a5c69 !important; }
.text-gray-300 { color: #dddfeb !important; }
.badge-lg { font-size: 0.9em; padding: 0.5em 0.75em; }
</style>

<script>
function cancelReservation(reservationId) {
    if (confirm('?�말 ?�약??취소?�시겠습?�까?')) {
        // AJAX ?�청?�로 ?�약 취소 처리
        fetch('../api/cancel_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                reservation_id: reservationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('?�약??취소?�었?�니??');
                location.reload();
            } else {
                alert('?�약 취소???�패?�습?�다: ' + data.message);
            }
        });
    }
}

function removeFavorite(businessId) {
    if (confirm('즐겨찾기?�서 ?�거?�시겠습?�까?')) {
        // AJAX ?�청?�로 즐겨찾기 ?�거
        fetch('../api/toggle_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                business_id: businessId,
                action: 'remove'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('?�거???�패?�습?�다: ' + data.message);
            }
        });
    }
}

function deleteReview(reviewId) {
    if (confirm('?�기�???��?�시겠습?�까?')) {
        // AJAX ?�청?�로 ?�기 ??��
        fetch('../api/delete_review.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                review_id: reviewId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('?�기가 ??��?�었?�니??');
                location.reload();
            } else {
                alert('??��???�패?�습?�다: ' + data.message);
            }
        });
    }
}

// ?�로?????�출
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../api/update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('?�보가 ?�공?�으�??�데?�트?�었?�니??');
            location.reload();
        } else {
            alert('?�데?�트???�패?�습?�다: ' + data.message);
        }
    });
});
</script>

<?php
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'confirmed': return 'primary';
        case 'completed': return 'success';
        case 'cancelled': return 'danger';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

function getStatusLabel($status) {
    switch ($status) {
        case 'pending': return '?�청�?;
        case 'confirmed': return '?�정';
        case 'completed': return '?�료';
        case 'cancelled': return '취소';
        case 'rejected': return '거절';
        default: return $status;
    }
}

include '../includes/footer.php';
?> 
