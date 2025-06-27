<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// 관리자 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

// 신고 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $report_id = (int)$_POST['report_id'];
    $action = $_POST['action'];
    
    if ($action === 'resolve') {
        $stmt = $db->prepare("UPDATE reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $report_id]);
        $success = "신고가 해결 처리되었습니다.";
    } elseif ($action === 'dismiss') {
        $stmt = $db->prepare("UPDATE reports SET status = 'dismissed', resolved_by = ?, resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $report_id]);
        $success = "신고가 기각되었습니다.";
    }
}

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// 필터 조건
$status = isset($_GET['status']) ? $_GET['status'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// 임시 신고 데이터 (실제로는 reports 테이블에서 조회)
$reports = [
    [
        'id' => 1,
        'type' => 'business',
        'reporter_name' => '김고객',
        'reporter_email' => 'customer@example.com',
        'target_name' => '뷰티살롱 ABC',
        'reason' => '서비스 불만',
        'description' => '예약 시간을 지키지 않고 서비스 품질이 매우 낮았습니다.',
        'status' => 'pending',
        'created_at' => '2024-01-15 14:30:00',
        'resolved_at' => null
    ],
    [
        'id' => 2,
        'type' => 'teacher',
        'reporter_name' => '이고객',
        'reporter_email' => 'user2@example.com',
        'target_name' => '김선생님',
        'reason' => '부적절한 행동',
        'description' => '예약 시간에 늦게 와서 서비스 시간이 단축되었습니다.',
        'status' => 'resolved',
        'created_at' => '2024-01-14 10:15:00',
        'resolved_at' => '2024-01-14 16:20:00'
    ],
    [
        'id' => 3,
        'type' => 'review',
        'reporter_name' => '박업체',
        'reporter_email' => 'business@example.com',
        'target_name' => '악성 리뷰',
        'reason' => '허위 리뷰',
        'description' => '서비스를 받지도 않고 악의적인 리뷰를 작성했습니다.',
        'status' => 'pending',
        'created_at' => '2024-01-13 09:45:00',
        'resolved_at' => null
    ]
];

// 필터링
if ($status) {
    $reports = array_filter($reports, function($report) use ($status) {
        return $report['status'] === $status;
    });
}

if ($type) {
    $reports = array_filter($reports, function($report) use ($type) {
        return $report['type'] === $type;
    });
}

$total_reports = count($reports);
$total_pages = ceil($total_reports / $per_page);
$reports = array_slice($reports, $offset, $per_page);

// 통계
$stats = [
    'total' => 3,
    'pending' => 2,
    'resolved' => 1,
    'dismissed' => 0,
    'today' => 1
];
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.admin-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.admin-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 4px solid #dc3545;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #dc3545;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.filters {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.reports-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table th {
    background: #f8f9fa;
    border: none;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}

.table td {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    vertical-align: middle;
}

.report-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.report-type {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    width: fit-content;
}

.type-business { background: #e3f2fd; color: #1976d2; }
.type-teacher { background: #e8f5e8; color: #388e3c; }
.type-review { background: #fff3e0; color: #f57c00; }

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-resolved { background: #d4edda; color: #155724; }
.status-dismissed { background: #f8d7da; color: #721c24; }

.btn-action {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 6px;
    margin: 0 0.25rem;
}

.btn-resolve { background: #28a745; color: white; border: none; }
.btn-dismiss { background: #dc3545; color: white; border: none; }

.report-description {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>

<div class="admin-header">
    <div class="container">
        <h1 class="admin-title">🚨 신고 관리</h1>
        <p class="admin-subtitle">사용자 신고를 처리하고 관리합니다</p>
    </div>
</div>

<div class="container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- 통계 카드 -->
    <div class="stats-cards">
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['total']); ?></span>
            <div class="stat-label">전체 신고</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['pending']); ?></span>
            <div class="stat-label">처리 대기</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['resolved']); ?></span>
            <div class="stat-label">해결 완료</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['dismissed']); ?></span>
            <div class="stat-label">기각</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['today']); ?></span>
            <div class="stat-label">오늘 신고</div>
        </div>
    </div>
    
    <!-- 필터 -->
    <div class="filters">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">전체 상태</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>처리 대기</option>
                    <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>해결 완료</option>
                    <option value="dismissed" <?php echo $status === 'dismissed' ? 'selected' : ''; ?>>기각</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">전체 유형</option>
                    <option value="business" <?php echo $type === 'business' ? 'selected' : ''; ?>>업체 신고</option>
                    <option value="teacher" <?php echo $type === 'teacher' ? 'selected' : ''; ?>>선생님 신고</option>
                    <option value="review" <?php echo $type === 'review' ? 'selected' : ''; ?>>리뷰 신고</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> 필터 적용
                </button>
            </div>
            <div class="col-md-3">
                <a href="admin_report_manage.php" class="btn btn-outline-secondary">
                    <i class="fas fa-refresh"></i> 초기화
                </a>
            </div>
        </form>
    </div>
    
    <!-- 신고 목록 -->
    <div class="reports-table">
        <?php if (empty($reports)): ?>
            <div class="text-center p-5">
                <i class="fas fa-shield-alt" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h5>신고가 없습니다</h5>
                <p>현재 처리할 신고가 없습니다.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>신고 정보</th>
                        <th>신고자</th>
                        <th>대상</th>
                        <th>사유</th>
                        <th>상태</th>
                        <th>신고일</th>
                        <th>처리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <div class="report-info">
                                    <span class="report-type type-<?php echo $report['type']; ?>">
                                        <?php
                                        $type_names = [
                                            'business' => '업체 신고',
                                            'teacher' => '선생님 신고',
                                            'review' => '리뷰 신고'
                                        ];
                                        echo $type_names[$report['type']] ?? $report['type'];
                                        ?>
                                    </span>
                                    <small class="text-muted">#<?php echo $report['id']; ?></small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($report['reporter_name']); ?></strong>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($report['reporter_email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($report['target_name']); ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($report['reason']); ?></strong>
                                    <div class="report-description" title="<?php echo htmlspecialchars($report['description']); ?>">
                                        <?php echo htmlspecialchars($report['description']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $report['status']; ?>">
                                    <?php
                                    $status_names = [
                                        'pending' => '처리 대기',
                                        'resolved' => '해결 완료',
                                        'dismissed' => '기각'
                                    ];
                                    echo $status_names[$report['status']] ?? $report['status'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div><?php echo date('Y.m.d', strtotime($report['created_at'])); ?></div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($report['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php if ($report['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <input type="hidden" name="action" value="resolve">
                                        <button type="submit" class="btn btn-action btn-resolve" 
                                                onclick="return confirm('이 신고를 해결 처리하시겠습니까?')">
                                            해결
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <input type="hidden" name="action" value="dismiss">
                                        <button type="submit" class="btn btn-action btn-dismiss" 
                                                onclick="return confirm('이 신고를 기각하시겠습니까?')">
                                            기각
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <small class="text-muted">
                                        <?php echo $report['resolved_at'] ? date('Y.m.d H:i', strtotime($report['resolved_at'])) : '처리됨'; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 