<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// 관리자 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$business_id) {
    header('Location: admin_business_manage.php');
    exit;
}

// 업체 정보 조회
$stmt = $db->prepare("
    SELECT b.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone,
           bo.business_license, ba.name as admin_name
    FROM businesses b 
    LEFT JOIN business_owners bo ON b.owner_id = bo.id 
    LEFT JOIN users u ON bo.user_id = u.id 
    LEFT JOIN users ba ON b.approved_by = ba.id
    WHERE b.id = ?
");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: admin_business_manage.php');
    exit;
}

$errors = [];
$success = '';

// 업체 정보 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_approved = isset($_POST['is_approved']) ? 1 : 0;
    
    // 유효성 검사
    if (empty($name)) {
        $errors[] = "업체명을 입력해주세요.";
    }
    
    if (empty($phone)) {
        $errors[] = "전화번호를 입력해주세요.";
    }
    
    if (empty($address)) {
        $errors[] = "주소를 입력해주세요.";
    }
    
    if (!in_array($category, ['hair', 'nail', 'skincare', 'massage', 'waxing'])) {
        $errors[] = "올바른 카테고리를 선택해주세요.";
    }
    
    if (empty($errors)) {
        try {
            $update_fields = [];
            $update_params = [];
            
            // 승인 상태 변경 감지
            if ($is_approved != $business['is_approved']) {
                if ($is_approved) {
                    $update_fields[] = "is_approved = ?, approval_date = NOW(), approved_by = ?, is_rejected = 0, rejection_reason = NULL";
                    $update_params[] = 1;
                    $update_params[] = $_SESSION['user_id'];
                } else {
                    $update_fields[] = "is_approved = ?, approval_date = NULL, approved_by = NULL";
                    $update_params[] = 0;
                }
            }
            
            $stmt = $db->prepare("
                UPDATE businesses 
                SET name = ?, phone = ?, address = ?, category = ?, description = ?, is_active = ?" . 
                (!empty($update_fields) ? ', ' . implode(', ', $update_fields) : '') . "
                WHERE id = ?
            ");
            
            $params = array_merge([$name, $phone, $address, $category, $description, $is_active], $update_params, [$business_id]);
            $stmt->execute($params);
            
            $success = "업체 정보가 성공적으로 업데이트되었습니다.";
            
            // 업데이트된 정보 다시 조회
            $stmt = $db->prepare("
                SELECT b.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone,
                       bo.business_license, ba.name as admin_name
                FROM businesses b 
                LEFT JOIN business_owners bo ON b.owner_id = bo.id 
                LEFT JOIN users u ON bo.user_id = u.id 
                LEFT JOIN users ba ON b.approved_by = ba.id
                WHERE b.id = ?
            ");
            $stmt->execute([$business_id]);
            $business = $stmt->fetch();
            
        } catch (Exception $e) {
            $errors[] = "업데이트 중 오류가 발생했습니다.";
        }
    }
}

// 업체 통계
$stats = [];

// 예약 통계
$stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE business_id = ?");
$stmt->execute([$business_id]);
$stats['total_reservations'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE business_id = ? AND status = 'completed'");
$stmt->execute([$business_id]);
$stats['completed_reservations'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(total_amount) FROM reservations WHERE business_id = ? AND status = 'completed'");
$stmt->execute([$business_id]);
$stats['total_revenue'] = $stmt->fetchColumn() ?? 0;

// 선생님 수
$stmt = $db->prepare("SELECT COUNT(*) FROM teachers WHERE business_id = ?");
$stmt->execute([$business_id]);
$stats['teachers_count'] = $stmt->fetchColumn();

// 리뷰 통계
$stmt = $db->prepare("SELECT COUNT(*), AVG(rating) FROM reviews WHERE business_id = ?");
$stmt->execute([$business_id]);
$review_stats = $stmt->fetch();
$stats['reviews_count'] = $review_stats[0];
$stats['average_rating'] = round($review_stats[1], 1);
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.business-profile-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.business-logo-large {
    width: 120px;
    height: 120px;
    border-radius: 15px;
    background: #f8f9fa;
    background-size: cover;
    background-position: center;
    margin: 0 auto 1rem;
    border: 3px solid #e9ecef;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
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

.form-section {
    margin-bottom: 2rem;
}

.form-section h4 {
    color: #2c3e50;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-active { background: #d1ecf1; color: #0c5460; }
.status-inactive { background: #f8d7da; color: #721c24; }

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
}
</style>

<div class="admin-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-building-edit"></i> 업체 편집</h1>
                <p>업체 상세 정보 조회 및 수정</p>
            </div>
            <div>
                <a href="admin_business_manage.php" class="btn btn-light me-2">
                    <i class="fas fa-arrow-left"></i> 목록으로
                </a>
                <a href="business_detail.php?id=<?php echo $business['id']; ?>" class="btn btn-outline-light" target="_blank">
                    <i class="fas fa-external-link-alt"></i> 업체 보기
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- 업체 기본 정보 -->
    <div class="business-profile-card">
        <div class="text-center mb-4">
            <div class="business-logo-large" 
                 style="background-image: url('<?php echo $business['logo_url'] ? BASE_URL . '/' . $business['logo_url'] : ''; ?>')">
            </div>
            <h2><?php echo htmlspecialchars($business['name']); ?></h2>
            
            <div class="d-flex justify-content-center gap-2 mb-3">
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
            </div>
            
            <div class="d-flex justify-content-center gap-2">
                <?php if ($business['is_rejected']): ?>
                    <span class="status-badge status-rejected">거절됨</span>
                <?php elseif ($business['is_approved']): ?>
                    <span class="status-badge status-approved">승인됨</span>
                <?php else: ?>
                    <span class="status-badge status-pending">승인 대기</span>
                <?php endif; ?>
                
                <span class="status-badge <?php echo $business['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $business['is_active'] ? '활성' : '비활성'; ?>
                </span>
            </div>
        </div>

        <!-- 업체 통계 -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($stats['total_reservations']); ?></span>
                <div class="stat-label">총 예약 수</div>
            </div>
            
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($stats['completed_reservations']); ?></span>
                <div class="stat-label">완료된 예약</div>
            </div>
            
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($stats['total_revenue']); ?>원</span>
                <div class="stat-label">총 매출</div>
            </div>
            
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($stats['teachers_count']); ?></span>
                <div class="stat-label">등록 선생님</div>
            </div>
            
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['average_rating'] ?: '0'; ?></span>
                <div class="stat-label">평균 평점</div>
            </div>
            
            <div class="stat-card">
                <span class="stat-number"><?php echo date('Y.m.d', strtotime($business['created_at'])); ?></span>
                <div class="stat-label">등록일</div>
            </div>
        </div>
    </div>

    <!-- 업체 정보 수정 폼 -->
    <div class="business-profile-card">
        <form method="POST">
            <div class="form-section">
                <h4><i class="fas fa-building"></i> 업체 기본 정보</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">업체명 *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($business['name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">전화번호 *</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($business['phone']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">주소 *</label>
                    <input type="text" name="address" class="form-control" 
                           value="<?php echo htmlspecialchars($business['address']); ?>" required>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">카테고리 *</label>
                    <select name="category" class="form-select" required>
                        <option value="hair" <?php echo $business['category'] === 'hair' ? 'selected' : ''; ?>>헤어</option>
                        <option value="nail" <?php echo $business['category'] === 'nail' ? 'selected' : ''; ?>>네일</option>
                        <option value="skincare" <?php echo $business['category'] === 'skincare' ? 'selected' : ''; ?>>피부관리</option>
                        <option value="massage" <?php echo $business['category'] === 'massage' ? 'selected' : ''; ?>>마사지</option>
                        <option value="waxing" <?php echo $business['category'] === 'waxing' ? 'selected' : ''; ?>>왁싱</option>
                    </select>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label">업체 설명</label>
                    <textarea name="description" class="form-control" rows="4"
                              placeholder="업체에 대한 설명을 입력하세요"><?php echo htmlspecialchars($business['description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-cog"></i> 업체 상태 관리</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_approved" class="form-check-input" id="is_approved" 
                                       <?php echo $business['is_approved'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_approved">
                                    <strong>업체 승인</strong>
                                    <div class="text-muted small">승인된 업체만 예약 서비스 이용 가능</div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="is_active" 
                                       <?php echo $business['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    <strong>업체 활성화</strong>
                                    <div class="text-muted small">비활성화 시 검색 결과에 표시되지 않음</div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> 업체 정보 업데이트
                </button>
                <a href="admin_business_manage.php" class="btn btn-secondary btn-lg ms-2">
                    <i class="fas fa-times"></i> 취소
                </a>
            </div>
        </form>
    </div>

    <!-- 사장 정보 -->
    <div class="business-profile-card">
        <h4><i class="fas fa-user-tie"></i> 사장 정보</h4>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>이름:</strong></td>
                        <td><?php echo htmlspecialchars($business['owner_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>이메일:</strong></td>
                        <td><?php echo htmlspecialchars($business['owner_email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>전화번호:</strong></td>
                        <td><?php echo htmlspecialchars($business['owner_phone']); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>사업자등록번호:</strong></td>
                        <td><?php echo htmlspecialchars($business['business_license'] ?? '정보 없음'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>등록일:</strong></td>
                        <td><?php echo date('Y년 m월 d일 H:i', strtotime($business['created_at'])); ?></td>
                    </tr>
                    <?php if ($business['approval_date']): ?>
                        <tr>
                            <td><strong>승인일:</strong></td>
                            <td><?php echo date('Y년 m월 d일 H:i', strtotime($business['approval_date'])); ?></td>
                        </tr>
                        <?php if ($business['admin_name']): ?>
                            <tr>
                                <td><strong>승인자:</strong></td>
                                <td><?php echo htmlspecialchars($business['admin_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($business['is_rejected'] && $business['rejection_reason']): ?>
                        <tr>
                            <td><strong>거절 사유:</strong></td>
                            <td class="text-danger"><?php echo htmlspecialchars($business['rejection_reason']); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 