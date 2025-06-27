<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

// 기본 통계 데이터
$stats = [
    'users' => ['total' => 0, 'today' => 0],
    'businesses' => ['total' => 0, 'approved' => 0],
    'reservations' => ['total' => 0, 'today' => 0],
    'revenue' => ['total' => 0, 'today' => 0]
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $stats['users']['total'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM businesses");
    $stmt->execute();
    $stats['businesses']['total'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // 오류 무시
}
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}
.stat-card {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-align: center;
}
.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #28a745;
    margin-bottom: 0.5rem;
}
</style>

<div class="admin-header">
    <div class="container">
        <h1>📊 통계 대시보드</h1>
        <p>시스템 전반의 통계를 확인합니다</p>
    </div>
</div>

<div class="container">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['users']['total']); ?></div>
            <div>전체 사용자</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['businesses']['total']); ?></div>
            <div>등록 업체</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">0</div>
            <div>총 예약 수</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">₩0</div>
            <div>총 매출</div>
        </div>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        상세한 통계 기능은 추후 업데이트 예정입니다.
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 