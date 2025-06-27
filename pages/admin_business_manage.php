<?php
require_once '../includes/header.php';
require_once '../config/database.php';

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
$status = isset($_GET['status']) ? $_GET['status'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

// 업체 처리 (승인/거절/활성화)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['business_id'])) {
        $business_id = (int)$_POST['business_id'];
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE businesses SET is_approved = 1, approval_date = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $business_id]);
            $success = "업체가 승인되었습니다.";
        } elseif ($action === 'reject') {
            $reason = $_POST['reason'] ?? '관리자 판단';
            $stmt = $db->prepare("UPDATE businesses SET is_rejected = 1, rejection_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
            $stmt->execute([$reason, $_SESSION['user_id'], $business_id]);
            $success = "업체가 거절되었습니다.";
        } elseif ($action === 'activate') {
            $stmt = $db->prepare("UPDATE businesses SET is_active = 1 WHERE id = ?");
            $stmt->execute([$business_id]);
            $success = "업체가 활성화되었습니다.";
        } elseif ($action === 'deactivate') {
            $stmt = $db->prepare("UPDATE businesses SET is_active = 0 WHERE id = ?");
            $stmt->execute([$business_id]);
            $success = "업체가 비활성화되었습니다.";
        }
    }
}

// 검색 쿼리 구성
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(b.name LIKE ? OR b.address LIKE ? OR b.phone LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status === 'pending') {
    $where_conditions[] = "b.is_approved = 0 AND b.is_rejected = 0";
} elseif ($status === 'approved') {
    $where_conditions[] = "b.is_approved = 1";
} elseif ($status === 'rejected') {
    $where_conditions[] = "b.is_rejected = 1";
} elseif ($status === 'active') {
    $where_conditions[] = "b.is_active = 1";
} elseif ($status === 'inactive') {
    $where_conditions[] = "b.is_active = 0";
}

if ($category) {
    $where_conditions[] = "b.category = ?";
    $params[] = $category;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 전체 업체 수
$count_sql = "SELECT COUNT(*) FROM businesses b 
              LEFT JOIN business_owners bo ON b.owner_id = bo.id 
              LEFT JOIN users u ON bo.user_id = u.id $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_businesses = $stmt->fetchColumn();

// 업체 목록 조회
$sql = "SELECT b.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone,
               ba.name as admin_name
        FROM businesses b 
        LEFT JOIN business_owners bo ON b.owner_id = bo.id 
        LEFT JOIN users u ON bo.user_id = u.id 
        LEFT JOIN users ba ON b.approved_by = ba.id
        $where_clause 
        ORDER BY b.created_at DESC 
        LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

// 페이징 정보
$total_pages = ceil($total_businesses / $per_page);

// 업체 통계
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN is_approved = 0 AND is_rejected = 0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN is_rejected = 1 THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN created_at >= CURDATE() THEN 1 ELSE 0 END) as today_registered
FROM businesses";
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

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

.business-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.business-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.business-logo {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    background: #f8f9fa;
    background-size: cover;
    background-position: center;
    flex-shrink: 0;
}

.business-details h6 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
}

.business-details small {
    color: #666;
    display: block;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-active { background: #d1ecf1; color: #0c5460; }
.status-inactive { background: #f8d7da; color: #721c24; }

.btn-action {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 6px;
    margin: 0 0.25rem;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}
</style>

<div class="admin-header">
    <div class="container">
        <h1><i class="fas fa-building"></i> 업체 관리</h1>
        <p>등록된 모든 업체의 정보를 조회하고 관리합니다</p>
    </div>
</div>

<div class="container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- 업체 통계 -->
    <div class="stats-cards">
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($stats['total']); ?></span>
            <div class="stat-label">전체 업체</div>
        </div>
        
        <div class="stat-card" style="border-left-color: #28a745;">
            <span class="stat-number" style="color: #28a745;"><?php echo number_format($stats['approved']); ?></span>
            <div class="stat-label">승인됨</div>
        </div>
        
        <div class="stat-card" style="border-left-color: #ffc107;">
            <span class="stat-number" style="color: #ffc107;"><?php echo number_format($stats['pending']); ?></span>
            <div class="stat-label">승인 대기</div>
        </div>
        
        <div class="stat-card" style="border-left-color: #dc3545;">
            <span class="stat-number" style="color: #dc3545;"><?php echo number_format($stats['rejected']); ?></span>
            <div class="stat-label">거절됨</div>
        </div>
        
        <div class="stat-card" style="border-left-color: #17a2b8;">
            <span class="stat-number" style="color: #17a2b8;"><?php echo number_format($stats['active']); ?></span>
            <div class="stat-label">활성 업체</div>
        </div>
        
        <div class="stat-card" style="border-left-color: #6f42c1;">
            <span class="stat-number" style="color: #6f42c1;"><?php echo number_format($stats['today_registered']); ?></span>
            <div class="stat-label">오늘 등록</div>
        </div>
    </div>
    
    <!-- 검색 필터 -->
    <div class="search-filters">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" 
                       placeholder="업체명, 주소, 전화번호, 사장명" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">전체 상태</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>승인 대기</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>승인됨</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>거절됨</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>활성</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>비활성</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select">
                    <option value="">전체 카테고리</option>
                    <option value="hair" <?php echo $category === 'hair' ? 'selected' : ''; ?>>헤어</option>
                    <option value="nail" <?php echo $category === 'nail' ? 'selected' : ''; ?>>네일</option>
                    <option value="skincare" <?php echo $category === 'skincare' ? 'selected' : ''; ?>>피부관리</option>
                    <option value="massage" <?php echo $category === 'massage' ? 'selected' : ''; ?>>마사지</option>
                    <option value="waxing" <?php echo $category === 'waxing' ? 'selected' : ''; ?>>왁싱</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> 검색
                </button>
            </div>
            <div class="col-md-2">
                <a href="admin_business_manage.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-refresh"></i> 초기화
                </a>
            </div>
        </form>
    </div>
    
    <!-- 업체 목록 -->
    <div class="business-table">
        <?php if (empty($businesses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                <h4>등록된 업체가 없습니다</h4>
                <p class="text-muted">검색 조건을 변경해보세요.</p>
            </div>
        <?php else: ?>
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>업체 정보</th>
                        <th>카테고리</th>
                        <th>사장 정보</th>
                        <th>상태</th>
                        <th>등록일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($businesses as $business): ?>
                        <tr>
                            <td>
                                <div class="business-info">
                                    <div class="business-logo" 
                                         style="background-image: url('<?php echo $business['logo_url'] ? BASE_URL . '/' . $business['logo_url'] : ''; ?>')">
                                    </div>
                                    <div class="business-details">
                                        <h6><?php echo htmlspecialchars($business['name']); ?></h6>
                                        <small>📍 <?php echo htmlspecialchars($business['address']); ?></small>
                                        <small>📞 <?php echo htmlspecialchars($business['phone']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="category-badge">
                                    <?php
                                    $categories = [
                                        'hair' => '헤어',
                                        'nail' => '네일',
                                        'skincare' => '피부관리',
                                        'massage' => '마사지',
                                        'waxing' => '왁싱'
                                    ];
                                    echo $categories[$business['category']] ?? $business['category'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($business['owner_name']); ?></strong>
                                </div>
                                <small>📧 <?php echo htmlspecialchars($business['owner_email']); ?></small>
                                <small>📱 <?php echo htmlspecialchars($business['owner_phone']); ?></small>
                            </td>
                            <td>
                                <div class="mb-1">
                                    <?php if ($business['is_rejected']): ?>
                                        <span class="status-badge status-rejected">거절됨</span>
                                    <?php elseif ($business['is_approved']): ?>
                                        <span class="status-badge status-approved">승인됨</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">승인 대기</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="status-badge <?php echo $business['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $business['is_active'] ? '활성' : '비활성'; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div><?php echo date('Y.m.d', strtotime($business['created_at'])); ?></div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($business['created_at'])); ?></small>
                                <?php if ($business['approval_date']): ?>
                                    <small class="text-success d-block">승인: <?php echo date('m.d', strtotime($business['approval_date'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="admin_business_edit.php?id=<?php echo $business['id']; ?>" 
                                   class="btn btn-action btn-sm" style="background: #007bff; color: white;">
                                    <i class="fas fa-edit"></i> 편집
                                </a>
                                
                                <?php if (!$business['is_approved'] && !$business['is_rejected']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="business_id" value="<?php echo $business['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-action" style="background: #28a745; color: white;"
                                                onclick="return confirm('이 업체를 승인하시겠습니까?')">
                                            승인
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-action" style="background: #dc3545; color: white;"
                                            onclick="rejectBusiness(<?php echo $business['id']; ?>)">
                                        거절
                                    </button>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="business_id" value="<?php echo $business['id']; ?>">
                                    <?php if ($business['is_active']): ?>
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="btn btn-action" style="background: #ffc107; color: #212529;">
                                            비활성화
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn btn-action" style="background: #17a2b8; color: white;">
                                            활성화
                                        </button>
                                    <?php endif; ?>
                                </form>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>">
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

<!-- 거절 모달 -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">업체 거절</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" name="business_id" id="rejectBusinessId">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-group">
                        <label class="form-label">거절 사유</label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="거절 사유를 입력해주세요"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger">거절</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function rejectBusiness(businessId) {
    document.getElementById('rejectBusinessId').value = businessId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?> 