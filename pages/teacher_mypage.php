<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// ? ์??๊ถํ ?์ธ
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'dashboard';
$db = getDB();

// ? ์???๋ณด ์กฐํ
$stmt = $db->prepare("
    SELECT t.*, u.name, u.email, u.phone, b.name as business_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    JOIN businesses b ON t.business_id = b.id
    WHERE t.user_id = ?
");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    header('Location: login.php');
    exit;
}

try {
    // ??๋ณด???ต๊ณ
    if ($tab === 'dashboard') {
        // ?ค๋ ?์ฝ ?ํฉ
        $stmt = $db->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
            FROM reservations 
            WHERE teacher_id = ? AND reservation_date = CURDATE()
        ");
        $stmt->execute([$teacher['id']]);
        $today_stats = $stmt->fetch();
        
        // ?ด๋ฒ???ต๊ณ
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_reservations,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                AVG(r.overall_rating) as avg_rating
            FROM reservations res
            LEFT JOIN reviews r ON res.id = r.reservation_id
            WHERE res.teacher_id = ? 
            AND MONTH(res.reservation_date) = MONTH(CURDATE()) 
            AND YEAR(res.reservation_date) = YEAR(CURDATE())
            AND res.status = 'completed'
        ");
        $stmt->execute([$teacher['id']]);
        $monthly_stats = $stmt->fetch();
        
        // ?ค๋ ?์ฝ ๋ชฉ๋ก
        $stmt = $db->prepare("
            SELECT r.*, u.name as customer_name, u.phone as customer_phone, bs.service_name
            FROM reservations r
            JOIN users u ON r.customer_id = u.id
            JOIN business_services bs ON r.service_id = bs.id
            WHERE r.teacher_id = ? AND r.reservation_date = CURDATE()
            ORDER BY r.start_time ASC
        ");
        $stmt->execute([$teacher['id']]);
        $today_reservations = $stmt->fetchAll();
        
        // ? ๊ท ?์ฝ ? ์ฒญ
        $stmt = $db->prepare("
            SELECT r.*, u.name as customer_name, u.phone as customer_phone, bs.service_name
            FROM reservations r
            JOIN users u ON r.customer_id = u.id
            JOIN business_services bs ON r.service_id = bs.id
            WHERE r.teacher_id = ? AND r.status = 'pending'
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$teacher['id']]);
        $pending_reservations = $stmt->fetchAll();
    }
    
    // ?ค์?์ค?๊ด๋ฆ?
    if ($tab === 'schedule') {
        // ?๊ธฐ ?ค์?์ค?์กฐํ
        $stmt = $db->prepare("
            SELECT * FROM teacher_schedules 
            WHERE teacher_id = ? 
            ORDER BY day_of_week ASC
        ");
        $stmt->execute([$teacher['id']]);
        $regular_schedules = $stmt->fetchAll();
        
        // ?์ธ ?ผ์  ์กฐํ (?ค์ 30??
        $stmt = $db->prepare("
            SELECT * FROM teacher_exceptions 
            WHERE teacher_id = ? 
            AND date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$teacher['id']]);
        $exceptions = $stmt->fetchAll();
    }
    
    // ?์ฝ ๊ด๋ฆ?
    if ($tab === 'reservations') {
        $status_filter = $_GET['status'] ?? 'all';
        $date_filter = $_GET['date'] ?? '';
        
        $where_clause = "WHERE r.teacher_id = ?";
        $params = [$teacher['id']];
        
        if ($status_filter !== 'all') {
            $where_clause .= " AND r.status = ?";
            $params[] = $status_filter;
        }
        
        if ($date_filter) {
            $where_clause .= " AND r.reservation_date = ?";
            $params[] = $date_filter;
        }
        
        $stmt = $db->prepare("
            SELECT r.*, u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
                   bs.service_name, bs.price
            FROM reservations r
            JOIN users u ON r.customer_id = u.id
            JOIN business_services bs ON r.service_id = bs.id
            $where_clause
            ORDER BY r.reservation_date DESC, r.start_time DESC
        ");
        $stmt->execute($params);
        $reservations = $stmt->fetchAll();
    }
    
    // ๊ณ ๊ฐ ๊ด๋ฆ?
    if ($tab === 'customers') {
        $stmt = $db->prepare("
            SELECT u.*, 
                   COUNT(r.id) as total_visits,
                   MAX(r.reservation_date) as last_visit,
                   AVG(rv.overall_rating) as avg_rating,
                   SUM(r.total_amount) as total_spent
            FROM users u
            JOIN reservations r ON u.id = r.customer_id
            LEFT JOIN reviews rv ON r.id = rv.reservation_id
            WHERE r.teacher_id = ? AND r.status = 'completed'
            GROUP BY u.id
            ORDER BY total_visits DESC, last_visit DESC
        ");
        $stmt->execute([$teacher['id']]);
        $customers = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $error = "?ฐ์ด??์กฐํ ์ค??ค๋ฅ๊ฐ ๋ฐ์?์ต?๋ค.";
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-3">
            <!-- ?ฌ์ด?๋ฐ ๋ฉ๋ด -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-tie text-primary"></i>
                        <?= htmlspecialchars($teacher['name']) ?>??
                    </h5>
                    <small class="text-muted"><?= htmlspecialchars($teacher['business_name']) ?></small>
                </div>
                <div class="list-group list-group-flush">
                    <a href="?tab=dashboard" class="list-group-item list-group-item-action <?= $tab === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt"></i> ??๋ณด??
                    </a>
                    <a href="?tab=schedule" class="list-group-item list-group-item-action <?= $tab === 'schedule' ? 'active' : '' ?>">
                        <i class="fas fa-calendar"></i> ?ค์?์ค?๊ด๋ฆ?
                    </a>
                    <a href="?tab=reservations" class="list-group-item list-group-item-action <?= $tab === 'reservations' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i> ?์ฝ ๊ด๋ฆ?
                    </a>
                    <a href="?tab=customers" class="list-group-item list-group-item-action <?= $tab === 'customers' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> ๊ณ ๊ฐ ๊ด๋ฆ?
                    </a>
                    <a href="?tab=reviews" class="list-group-item list-group-item-action <?= $tab === 'reviews' ? 'active' : '' ?>">
                        <i class="fas fa-star"></i> ๋ฐ์? ?๊ธฐ
                    </a>
                    <a href="?tab=profile" class="list-group-item list-group-item-action <?= $tab === 'profile' ? 'active' : '' ?>">
                        <i class="fas fa-user-cog"></i> ๊ฐ์ธ?ค์ 
                    </a>
                </div>
            </div>
            
            <!-- ๋น ๋ฅธ ?ํ ๋ณ๊ฒ?-->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-power-off"></i> ๋น ๋ฅธ ?ํ ๋ณ๊ฒ?/h6>
                </div>
                <div class="card-body">
                    <div class="btn-group-vertical btn-group-sm d-grid gap-2">
                        <button class="btn btn-success" onclick="changeStatus('available')">
                            <i class="fas fa-check"></i> ?์ฝ ๊ฐ??
                        </button>
                        <button class="btn btn-warning" onclick="changeStatus('break')">
                            <i class="fas fa-coffee"></i> ?ด๊ฒ?๊ฐ
                        </button>
                        <button class="btn btn-danger" onclick="changeStatus('unavailable')">
                            <i class="fas fa-times"></i> ?์ฝ ๋ถ๊?
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <?php if ($tab === 'dashboard'): ?>
                <!-- ??๋ณด??-->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tachometer-alt text-primary"></i> ? ์????๋ณด??/h2>
                    <div class="btn-group">
                        <a href="?tab=schedule" class="btn btn-outline-primary">
                            <i class="fas fa-calendar"></i> ?ค์?์ค?๊ด๋ฆ?
                        </a>
                        <a href="?tab=reservations&status=pending" class="btn btn-outline-warning">
                            <i class="fas fa-clock"></i> ?น์ธ ?๊ธ?
                        </a>
                    </div>
                </div>
                
                <!-- ?ค๋ ?ต๊ณ ์นด๋ -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">?น์ธ ?๊ธ?/div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $today_stats['pending_count'] ?? 0 ?>๊ฑ?/div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">?ค๋ ?์ </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $today_stats['confirmed_count'] ?? 0 ?>๊ฑ?/div>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">?ด๋ฒ???๋ฃ</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $monthly_stats['total_reservations'] ?? 0 ?>๊ฑ?/div>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">?๊ท  ?์ </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $monthly_stats['avg_rating'] ? number_format($monthly_stats['avg_rating'], 1) : '0.0' ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-star fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ?ค๋ ?ผ์  -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">?ค๋ ?ผ์  (<?= date('Y??m??d??) ?>)</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($today_reservations)): ?>
                                    <p class="text-muted text-center py-4">?ค๋ ?์ฝ???์ต?๋ค.</p>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($today_reservations as $reservation): ?>
                                            <div class="timeline-item mb-3 p-3 border-left border-<?= getStatusColor($reservation['status']) ?> bg-light">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <strong><?= date('H:i', strtotime($reservation['start_time'])) ?> - <?= date('H:i', strtotime($reservation['end_time'])) ?></strong>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <div class="font-weight-bold"><?= htmlspecialchars($reservation['customer_name']) ?></div>
                                                        <div class="text-muted small">
                                                            <?= htmlspecialchars($reservation['service_name']) ?> ??
                                                            <?= htmlspecialchars($reservation['customer_phone']) ?>
                                                        </div>
                                                        <span class="badge badge-<?= getStatusColor($reservation['status']) ?>">
                                                            <?= getStatusLabel($reservation['status']) ?>
                                                        </span>
                                                        <?php if ($reservation['status'] === 'pending'): ?>
                                                            <div class="mt-2">
                                                                <button class="btn btn-sm btn-success" onclick="approveReservation(<?= $reservation['id'] ?>)">
                                                                    <i class="fas fa-check"></i> ?น์ธ
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" onclick="rejectReservation(<?= $reservation['id'] ?>)">
                                                                    <i class="fas fa-times"></i> ๊ฑฐ์ 
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- ?น์ธ ?๊ธ?๋ชฉ๋ก -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-warning">?น์ธ ?๊ธ?/h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_reservations)): ?>
                                    <p class="text-muted text-center">?น์ธ ?๊ธ?์ค์ธ ?์ฝ???์ต?๋ค.</p>
                                <?php else: ?>
                                    <?php foreach ($pending_reservations as $reservation): ?>
                                        <div class="mb-3 p-2 border rounded">
                                            <div class="font-weight-bold"><?= htmlspecialchars($reservation['customer_name']) ?></div>
                                            <div class="text-muted small">
                                                <?= date('m/d H:i', strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time'])) ?> ??
                                                <?= htmlspecialchars($reservation['service_name']) ?>
                                            </div>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-success" onclick="approveReservation(<?= $reservation['id'] ?>)">?น์ธ</button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectReservation(<?= $reservation['id'] ?>)">๊ฑฐ์ </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($tab === 'schedule'): ?>
                <!-- ?ค์?์ค?๊ด๋ฆ?-->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-calendar text-primary"></i> ?ค์?์ค?๊ด๋ฆ?/h3>
                    <div class="btn-group">
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addExceptionModal">
                            <i class="fas fa-plus"></i> ?์ธ ?ผ์  ์ถ๊?
                        </button>
                        <button class="btn btn-outline-primary" data-toggle="modal" data-target="#editScheduleModal">
                            <i class="fas fa-edit"></i> ?๊ธฐ ?ค์?์ค??์ 
                        </button>
                    </div>
                </div>
                
                <!-- ?๊ธฐ ?ค์?์ค?-->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">?๊ธฐ ?ค์?์ค?/h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>?์ผ</th>
                                        <th>๊ทผ๋ฌด?๊ฐ</th>
                                        <th>?ด๊ฒ?๊ฐ</th>
                                        <th>?ํ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $days = ['?์??, '?์??, '?์??, '๋ชฉ์??, '๊ธ์??, '? ์??, '?ผ์??];
                                    for ($i = 1; $i <= 7; $i++):
                                        $schedule = null;
                                        foreach ($regular_schedules as $s) {
                                            if ($s['day_of_week'] == $i) {
                                                $schedule = $s;
                                                break;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $days[$i-1] ?></td>
                                        <td>
                                            <?php if ($schedule): ?>
                                                <?= date('H:i', strtotime($schedule['start_time'])) ?> ~ 
                                                <?= date('H:i', strtotime($schedule['end_time'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">?ด๋ฌด</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($schedule && $schedule['break_start']): ?>
                                                <?= date('H:i', strtotime($schedule['break_start'])) ?> ~ 
                                                <?= date('H:i', strtotime($schedule['break_end'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">?์</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($schedule): ?>
                                                <span class="badge badge-success">๊ทผ๋ฌด</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">?ด๋ฌด</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- ?์ธ ?ผ์  -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">?์ธ ?ผ์ </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($exceptions)): ?>
                            <p class="text-muted text-center py-3">?ฑ๋ก???์ธ ?ผ์ ???์ต?๋ค.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>? ์ง</th>
                                            <th>? ํ</th>
                                            <th>?ฌ์ </th>
                                            <th>?์</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exceptions as $exception): ?>
                                        <tr>
                                            <td><?= date('Y.m.d (D)', strtotime($exception['date'])) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $exception['exception_type'] === 'holiday' ? 'danger' : 'warning' ?>">
                                                    <?= $exception['exception_type'] === 'holiday' ? '?ด๋ฌด' : '?๊ฐ๋ณ๊ฒ? ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($exception['reason']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteException(<?= $exception['id'] ?>)">
                                                    <i class="fas fa-trash"></i> ?? 
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($tab === 'reservations'): ?>
                <!-- ?์ฝ ๊ด๋ฆ?-->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="fas fa-calendar-check text-primary"></i> ?์ฝ ๊ด๋ฆ?/h3>
                </div>
                
                <!-- ?ํฐ -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row">
                            <input type="hidden" name="tab" value="reservations">
                            <div class="col-md-3">
                                <select name="status" class="form-control">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>?์ฒด ?ํ</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>?น์ธ ?๊ธ?/option>
                                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>?์ </option>
                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>?๋ฃ</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>์ทจ์</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" value="<?= $date_filter ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> ?ํฐ ?์ฉ
                                </button>
                                <a href="?tab=reservations" class="btn btn-outline-secondary">์ด๊ธฐ??/a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- ?์ฝ ๋ชฉ๋ก -->
                <?php if (empty($reservations)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">?ด๋น?๋ ?์ฝ???์ต?๋ค</h5>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>๊ณ ๊ฐ๋ช?/th>
                                            <th>?๋น??/th>
                                            <th>?์ฝ?ผ์</th>
                                            <th>๊ธ์ก</th>
                                            <th>?ํ</th>
                                            <th>?์</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td>
                                                <div class="font-weight-bold"><?= htmlspecialchars($reservation['customer_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($reservation['customer_phone']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($reservation['service_name']) ?></td>
                                            <td>
                                                <?= date('Y.m.d (D)', strtotime($reservation['reservation_date'])) ?><br>
                                                <span class="text-muted"><?= date('H:i', strtotime($reservation['start_time'])) ?> ~ <?= date('H:i', strtotime($reservation['end_time'])) ?></span>
                                            </td>
                                            <td class="font-weight-bold"><?= number_format($reservation['total_amount']) ?>??/td>
                                            <td>
                                                <span class="badge badge-<?= getStatusColor($reservation['status']) ?>">
                                                    <?= getStatusLabel($reservation['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($reservation['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveReservation(<?= $reservation['id'] ?>)">?น์ธ</button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectReservation(<?= $reservation['id'] ?>)">๊ฑฐ์ </button>
                                                <?php elseif ($reservation['status'] === 'confirmed'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="completeReservation(<?= $reservation['id'] ?>)">?๋ฃ</button>
                                                    <button class="btn btn-sm btn-warning" onclick="cancelReservation(<?= $reservation['id'] ?>)">์ทจ์</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-info" onclick="viewCustomerInfo(<?= $reservation['customer_id'] ?>)">
                                                    <i class="fas fa-info"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($tab === 'customers'): ?>
                <!-- ๊ณ ๊ฐ ๊ด๋ฆ?-->
                <h3><i class="fas fa-users text-primary"></i> ๊ณ ๊ฐ ๊ด๋ฆ?/h3>
                <p class="text-muted mb-4">?๋? ?ด์ฉ??๊ณ ๊ฐ?ค์ ?๋ณด๋ฅ??์ธ?์ธ??</p>
                
                <?php if (empty($customers)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">?์ง ๊ณ ๊ฐ???์ต?๋ค</h5>
                            <p class="text-muted">์ฒ??์ฝ??๊ธฐ๋ค๋ฆฌ๊ณ  ?์ด??</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($customers as $customer): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($customer['name']) ?></h5>
                                        <p class="card-text">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($customer['phone']) ?><br>
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($customer['email']) ?>
                                        </p>
                                        
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="h5 text-primary"><?= $customer['total_visits'] ?></div>
                                                <div class="small text-muted">์ด?๋ฐฉ๋ฌธ</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="h5 text-success"><?= number_format($customer['total_spent']) ?>??/div>
                                                <div class="small text-muted">์ด?๊ฒฐ์ </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="h5 text-warning">
                                                    <?= $customer['avg_rating'] ? number_format($customer['avg_rating'], 1) : '0.0' ?>
                                                </div>
                                                <div class="small text-muted">?๊ท  ?์ </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="text-muted small">
                                            <i class="fas fa-calendar"></i> ์ต๊ทผ ๋ฐฉ๋ฌธ: 
                                            <?= $customer['last_visit'] ? date('Y.m.d', strtotime($customer['last_visit'])) : '?์' ?>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewCustomerHistory(<?= $customer['id'] ?>)">
                                            <i class="fas fa-history"></i> ?ด์ฉ ๊ธฐ๋ก
                                        </button>
                                        <a href="../reservation_form.php?customer_id=<?= $customer['id'] ?>&teacher_id=<?= $teacher['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-plus"></i> ?์ฝ ?ฑ๋ก
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
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
.timeline-item { border-left-width: 3px !important; }
</style>

<script>
function changeStatus(status) {
    fetch('../api/teacher_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('?ํ๊ฐ ๋ณ๊ฒฝ๋?์ต?๋ค.');
        } else {
            alert('?ํ ๋ณ๊ฒฝ์ ?คํจ?์ต?๋ค: ' + data.message);
        }
    });
}

function approveReservation(reservationId) {
    if (confirm('?์ฝ???น์ธ?์๊ฒ ์ต?๊น?')) {
        updateReservationStatus(reservationId, 'confirmed');
    }
}

function rejectReservation(reservationId) {
    const reason = prompt('๊ฑฐ์  ?ฌ์ ๋ฅ??๋ ฅ?์ธ??');
    if (reason) {
        updateReservationStatus(reservationId, 'rejected', reason);
    }
}

function completeReservation(reservationId) {
    if (confirm('?๋น?ค๋? ?๋ฃ ์ฒ๋ฆฌ?์๊ฒ ์ต?๊น?')) {
        updateReservationStatus(reservationId, 'completed');
    }
}

function cancelReservation(reservationId) {
    const reason = prompt('์ทจ์ ?ฌ์ ๋ฅ??๋ ฅ?์ธ??');
    if (reason) {
        updateReservationStatus(reservationId, 'cancelled', reason);
    }
}

function updateReservationStatus(reservationId, status, reason = '') {
    fetch('../api/update_reservation_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            reservation_id: reservationId,
            status: status,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('?์ฝ ?ํ๊ฐ ๋ณ๊ฒฝ๋?์ต?๋ค.');
            location.reload();
        } else {
            alert('?ํ ๋ณ๊ฒฝ์ ?คํจ?์ต?๋ค: ' + data.message);
        }
    });
}

function viewCustomerInfo(customerId) {
    // ๊ณ ๊ฐ ?๋ณด ๋ชจ๋ฌ ?์
    alert('๊ณ ๊ฐ ?๋ณด ์กฐํ ๊ธฐ๋ฅ? ์ค๋น?์ค์?๋ค.');
}

function viewCustomerHistory(customerId) {
    // ๊ณ ๊ฐ ?ด์ฉ ๊ธฐ๋ก ์กฐํ
    alert('๊ณ ๊ฐ ?ด์ฉ ๊ธฐ๋ก ์กฐํ ๊ธฐ๋ฅ? ์ค๋น?์ค์?๋ค.');
}
</script>

<?php
function getStatusColor($status) {
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
        case 'pending': return '?น์ธ ?๊ธ?;
        case 'confirmed': return '?์ ';
        case 'completed': return '?๋ฃ';
        case 'cancelled': return '์ทจ์';
        case 'rejected': return '๊ฑฐ์ ';
        default: return $status;
    }
}

include '../includes/footer.php';
?> 
