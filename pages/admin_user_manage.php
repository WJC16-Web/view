<?php
require_once '../config/database.php';
require_once '../includes/header.php';

// 관리자 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 검색 조건
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';

// 사용자 처리 (활성화/비활성화)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        if ($action === 'activate') {
            $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = "사용자가 활성화되었습니다.";
        } elseif ($action === 'deactivate') {
            $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = "사용자가 비활성화되었습니다.";
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND user_type != 'admin'");
            $stmt->execute([$user_id]);
            $success = "사용자가 삭제되었습니다.";
        }
    }
}

// 검색 쿼리 구성
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($user_type) {
    $where_conditions[] = "user_type = ?";
    $params[] = $user_type;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 전체 사용자 수
$count_sql = "SELECT COUNT(*) FROM users $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();

// 사용자 목록 조회
$sql = "SELECT id, name, email, phone, user_type, is_active, created_at, last_login 
        FROM users $where_clause 
        ORDER BY created_at DESC 
        LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// 페이징 정보
$total_pages = ceil($total_users / $per_page);

// 사용자 통계
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN user_type = 'customer' THEN 1 ELSE 0 END) as customers,
    SUM(CASE WHEN user_type = 'business' THEN 1 ELSE 0 END) as businesses,
    SUM(CASE WHEN user_type = 'teacher' THEN 1 ELSE 0 END) as teachers,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN created_at >= CURDATE() THEN 1 ELSE 0 END) as today_joins
FROM users";
$stmt = $db->prepare($stats_sql);
$stmt->execute();
$stats = $stmt->fetch();
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    border-left: 4px solid #667eea;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.search-filters {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.user-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table {
    margin: 0;
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

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
}

.user-details h6 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
}

.user-details small {
    color: #666;
    display: block;
}

.badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-customer { background: #e3f2fd; color: #1976d2; }
.badge-business { background: #f3e5f5; color: #7b1fa2; }
.badge-teacher { background: #e8f5e8; color: #388e3c; }
.badge-admin { background: #fff3e0; color: #f57c00; }

.status-active { color: #28a745; }
.status-inactive { color: #dc3545; }

.btn-action {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 6px;
    margin: 0 0.25rem;
}

.btn-activate { background: #28a745; color: white; border: none; }
.btn-deactivate { background: #ffc107; color: #212529; border: none; }
.btn-delete { background: #dc3545; color: white; border: none; }

.no-data {
    text-align: center;
    padding: 3rem;
    color: #666;
}

.pagination {
    margin: 0;
}

.page-link {
    color: #667eea;
    border-color: #dee2e6;
}

.page-item.active .page-link {
    background-color: #667eea;
    border-color: #667eea;
}
</style>

<div class="admin-header">
    <div class="container">
        <h1 class="admin-title">👥 사용자 관리</h1>
        <p class="admin-subtitle">전체 사용자를 관리하고 모니터링합니다</p>
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
            <div class="stat-label">전체 사용자</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['customers']); ?></span>
            <div class="stat-label">일반 고객</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['businesses']); ?></span>
            <div class="stat-label">업체 관리자</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['teachers']); ?></span>
            <div class="stat-label">선생님</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['active_users']); ?></span>
            <div class="stat-label">활성 사용자</div>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['today_joins']); ?></span>
            <div class="stat-label">오늘 가입</div>
        </div>
    </div>
    
    <!-- 검색 필터 -->
    <div class="search-filters">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" 
                       placeholder="이름, 이메일, 전화번호 검색" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="user_type" class="form-select">
                    <option value="">전체 회원 유형</option>
                    <option value="customer" <?php echo $user_type === 'customer' ? 'selected' : ''; ?>>일반 고객</option>
                    <option value="business" <?php echo $user_type === 'business' ? 'selected' : ''; ?>>업체 관리자</option>
                    <option value="teacher" <?php echo $user_type === 'teacher' ? 'selected' : ''; ?>>선생님</option>
                    <option value="admin" <?php echo $user_type === 'admin' ? 'selected' : ''; ?>>관리자</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> 검색
                </button>
            </div>
            <div class="col-md-2">
                <a href="admin_user_manage.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-refresh"></i> 초기화
                </a>
            </div>
        </form>
    </div>
    
    <!-- 사용자 목록 -->
    <div class="user-table">
        <?php if (empty($users)): ?>
            <div class="no-data">
                <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h5>사용자가 없습니다</h5>
                <p>검색 조건을 확인해주세요.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>사용자</th>
                        <th>회원 유형</th>
                        <th>상태</th>
                        <th>가입일</th>
                        <th>최근 로그인</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <h6><?php echo htmlspecialchars($user['name']); ?></h6>
                                        <small>📧 <?php echo htmlspecialchars($user['email']); ?></small>
                                        <?php if ($user['phone']): ?>
                                            <small>📱 <?php echo htmlspecialchars($user['phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['user_type']; ?>">
                                    <?php
                                    $type_names = [
                                        'customer' => '일반 고객',
                                        'business' => '업체 관리자',
                                        'teacher' => '선생님',
                                        'admin' => '관리자'
                                    ];
                                    echo $type_names[$user['user_type']] ?? $user['user_type'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <i class="fas fa-circle <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"></i>
                                <?php echo $user['is_active'] ? '활성' : '비활성'; ?>
                            </td>
                            <td>
                                <div><?php echo date('Y.m.d', strtotime($user['created_at'])); ?></div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($user['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <div><?php echo date('Y.m.d', strtotime($user['last_login'])); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($user['last_login'])); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">로그인 기록 없음</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['user_type'] !== 'admin'): ?>
                                    <a href="admin_user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-action btn-sm" style="background: #007bff; color: white; margin-right: 5px;">
                                        <i class="fas fa-edit"></i> 편집
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if ($user['is_active']): ?>
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-action btn-deactivate" 
                                                    onclick="return confirm('이 사용자를 비활성화하시겠습니까?')">
                                                비활성화
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-action btn-activate">
                                                활성화
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-action btn-delete" 
                                                onclick="return confirm('이 사용자를 완전히 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')">
                                            삭제
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="admin_user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-action btn-sm" style="background: #007bff; color: white;">
                                        <i class="fas fa-edit"></i> 편집
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- 페이징 -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center p-3">
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&user_type=<?php echo urlencode($user_type); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&user_type=<?php echo urlencode($user_type); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&user_type=<?php echo urlencode($user_type); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 