<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

startSession();

// 관리자 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    redirect('/pages/login.php?error=권한이 없습니다');
}

$db = getDB();
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 승인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_id = intval($_POST['business_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if ($business_id && in_array($action, ['approve', 'reject'])) {
        $db->beginTransaction();
        
        try {
            if ($action === 'approve') {
                // 업체 승인
                $stmt = $db->prepare("
                    UPDATE businesses 
                    SET is_approved = 1, approval_date = NOW(), approved_by = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $business_id]);
                
                // 업체 관리자에게 알림
                $stmt = $db->prepare("SELECT owner_id, name FROM businesses WHERE id = ?");
                $stmt->execute([$business_id]);
                $business = $stmt->fetch();
                
                addNotification(
                    $business['owner_id'],
                    'business_approved',
                    '업체 승인 완료',
                    "{$business['name']} 업체가 승인되었습니다. 이제 정상적으로 서비스를 이용할 수 있습니다."
                );
                
                // SMS 발송
                $stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
                $stmt->execute([$business['owner_id']]);
                $owner = $stmt->fetch();
                
                if ($owner && $owner['phone']) {
                    sendSMSNotification(
                        $business['owner_id'],
                        $owner['phone'],
                        "[뷰티북] {$business['name']} 업체가 승인되었습니다. 이제 정상적으로 서비스를 이용할 수 있습니다.",
                        'business_approved'
                    );
                }
                
                $message = '업체가 승인되었습니다.';
                
            } else {
                // 업체 거절
                $stmt = $db->prepare("
                    UPDATE businesses 
                    SET is_rejected = 1, rejection_reason = ?, rejected_by = ?, rejected_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$reason, $_SESSION['user_id'], $business_id]);
                
                // 업체 관리자에게 알림
                $stmt = $db->prepare("SELECT owner_id, name FROM businesses WHERE id = ?");
                $stmt->execute([$business_id]);
                $business = $stmt->fetch();
                
                addNotification(
                    $business['owner_id'],
                    'business_rejected',
                    '업체 승인 거절',
                    "{$business['name']} 업체가 거절되었습니다. 사유: {$reason}"
                );
                
                $message = '업체가 거절되었습니다.';
            }
            
            $db->commit();
            redirect('/pages/admin_business_approve.php?success=' . urlencode($message));
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = '처리 중 오류가 발생했습니다.';
        }
    }
}

// 업체 목록 조회
$stmt = $db->prepare("
    SELECT b.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
    FROM businesses b
    JOIN users u ON b.owner_id = u.id
    WHERE b.is_approved = 0 AND (b.is_rejected = 0 OR b.is_rejected IS NULL)
    ORDER BY b.created_at ASC
    LIMIT ? OFFSET ?
");
$stmt->execute([$per_page, $offset]);
$businesses = $stmt->fetchAll();

// 전체 개수
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM businesses 
    WHERE is_approved = 0 AND (is_rejected = 0 OR is_rejected IS NULL)
");
$stmt->execute();
$total_count = $stmt->fetchColumn();

$pagination = getPagination($page, $total_count, $per_page);

require_once '../includes/header.php';
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px 0;
    margin-bottom: 30px;
}

.admin-title {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 10px;
}

.admin-subtitle {
    font-size: 16px;
    opacity: 0.9;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #667eea;
    display: block;
}

.stat-label {
    color: #666;
    margin-top: 5px;
}

.business-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.table {
    margin: 0;
}

.table th {
    background: #f8f9fa;
    border: none;
    font-weight: 600;
    color: #333;
    padding: 15px;
}

.table td {
    padding: 15px;
    vertical-align: middle;
    border-top: 1px solid #eee;
}

.business-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.business-logo {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    background: #f0f0f0;
    background-size: cover;
    background-position: center;
}

.business-details h6 {
    margin: 0;
    font-weight: 600;
    color: #333;
}

.business-details small {
    color: #666;
}

.owner-info {
    font-size: 14px;
}

.owner-info strong {
    color: #333;
}

.owner-info div {
    margin: 2px 0;
    color: #666;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-approve {
    background: #28a745;
    border-color: #28a745;
    color: white;
}

.btn-approve:hover {
    background: #218838;
    border-color: #1e7e34;
}

.btn-reject {
    background: #dc3545;
    border-color: #dc3545;
    color: white;
}

.btn-reject:hover {
    background: #c82333;
    border-color: #bd2130;
}

.modal-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.no-data {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-data i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 20px;
}
</style>

<div class="admin-header">
    <div class="container">
        <h1 class="admin-title">💼 업체 승인 관리</h1>
        <p class="admin-subtitle">신규 업체 가입 승인을 관리합니다</p>
    </div>
</div>

<div class="container">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- 통계 카드 -->
    <div class="stats-cards">
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($total_count); ?></span>
            <div class="stat-label">승인 대기</div>
        </div>
        
        <?php
        // 추가 통계
        $stmt = $db->prepare("SELECT COUNT(*) FROM businesses WHERE is_approved = 1");
        $stmt->execute();
        $approved_count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM businesses WHERE is_rejected = 1");
        $stmt->execute();
        $rejected_count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM businesses WHERE created_at >= CURDATE()");
        $stmt->execute();
        $today_count = $stmt->fetchColumn();
        ?>
        
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($approved_count); ?></span>
            <div class="stat-label">승인 완료</div>
        </div>
        
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($rejected_count); ?></span>
            <div class="stat-label">승인 거절</div>
        </div>
        
        <div class="stat-card">
            <span class="stat-number"><?php echo number_format($today_count); ?></span>
            <div class="stat-label">오늘 신청</div>
        </div>
    </div>
    
    <!-- 업체 목록 -->
    <div class="business-table">
        <?php if (empty($businesses)): ?>
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <h5>승인 대기 중인 업체가 없습니다</h5>
                <p>모든 업체가 처리되었습니다.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>업체명</th>
                        <th>관리자</th>
                        <th>연락처</th>
                        <th>신청일</th>
                        <th>처리</th>
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
                                        <small><?php echo htmlspecialchars($business['address']); ?></small>
                                        <div><small>📞 <?php echo htmlspecialchars($business['phone']); ?></small></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="owner-info">
                                    <strong><?php echo htmlspecialchars($business['owner_name']); ?></strong>
                                    <div>📧 <?php echo htmlspecialchars($business['owner_email']); ?></div>
                                    <div>📱 <?php echo htmlspecialchars($business['owner_phone']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo date('Y.m.d', strtotime($business['created_at'])); ?></div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($business['created_at'])); ?></small>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="business_id" value="<?php echo $business['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve btn-sm">승인</button>
                                </form>
                                <button type="button" class="btn btn-reject btn-sm" 
                                        onclick="rejectBusiness(<?php echo $business['id']; ?>)">거절</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- 페이징 -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="d-flex justify-content-center p-3">
                    <nav>
                        <ul class="pagination">
                            <?php if ($pagination['has_previous']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($pagination['has_next']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
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

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">업체 거절</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="reject_business_id" name="business_id">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label for="reason" class="form-label">거절 사유</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger">거절하기</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function rejectBusiness(businessId) {
    document.getElementById('reject_business_id').value = businessId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?> 