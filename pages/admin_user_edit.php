<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// 관리자 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: admin_user_manage.php');
    exit;
}

// 사용자 정보 조회
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: admin_user_manage.php');
    exit;
}

$errors = [];
$success = '';

// 사용자 정보 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $user_type = $_POST['user_type'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';
    
    // 유효성 검사
    if (empty($name)) {
        $errors[] = "이름을 입력해주세요.";
    }
    
    if (empty($email)) {
        $errors[] = "이메일을 입력해주세요.";
    } else {
        // 이메일 중복 체크 (현재 사용자 제외)
        $email_check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->execute([$email, $user_id]);
        if ($email_check->fetch()) {
            $errors[] = "이미 사용 중인 이메일입니다.";
        }
    }
    
    if (!in_array($user_type, ['customer', 'business_owner', 'teacher', 'admin'])) {
        $errors[] = "올바른 회원 유형을 선택해주세요.";
    }
    
    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                // 비밀번호 변경 포함
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, user_type = ?, is_active = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $user_type, $is_active, $hashed_password, $user_id]);
            } else {
                // 기본 정보만 업데이트
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, user_type = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $user_type, $is_active, $user_id]);
            }
            
            $success = "사용자 정보가 성공적으로 업데이트되었습니다.";
            
            // 업데이트된 정보 다시 조회
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            $errors[] = "업데이트 중 오류가 발생했습니다.";
        }
    }
}

// 사용자 활동 통계
$stats = [];

// 예약 통계 (고객인 경우)
if ($user['user_type'] === 'customer') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_reservations'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE customer_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['completed_reservations'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT SUM(total_amount) FROM reservations WHERE customer_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['total_spent'] = $stmt->fetchColumn() ?? 0;
}

// 업체 통계 (업체 관리자인 경우)
if ($user['user_type'] === 'business_owner') {
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM businesses b 
        JOIN business_owners bo ON b.owner_id = bo.id 
        WHERE bo.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats['businesses'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM reservations r
        JOIN businesses b ON r.business_id = b.id
        JOIN business_owners bo ON b.owner_id = bo.id
        WHERE bo.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats['total_reservations'] = $stmt->fetchColumn();
}

// 선생님 통계 (선생님인 경우)
if ($user['user_type'] === 'teacher') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations r JOIN teachers t ON r.teacher_id = t.id WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_reservations'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations r JOIN teachers t ON r.teacher_id = t.id WHERE t.user_id = ? AND r.status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['completed_reservations'] = $stmt->fetchColumn();
}
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.user-profile-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.user-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 auto 1rem;
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

.user-type-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.badge-customer { background: #e3f2fd; color: #1976d2; }
.badge-business_owner { background: #f3e5f5; color: #7b1fa2; }
.badge-teacher { background: #e8f5e8; color: #388e3c; }
.badge-admin { background: #fff3e0; color: #f57c00; }

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.status-active { color: #28a745; }
.status-inactive { color: #dc3545; }
</style>

<div class="admin-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-user-edit"></i> 사용자 편집</h1>
                <p>사용자 상세 정보 조회 및 수정</p>
            </div>
            <a href="admin_user_manage.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> 목록으로
            </a>
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

    <!-- 사용자 기본 정보 -->
    <div class="user-profile-card">
        <div class="text-center mb-4">
            <div class="user-avatar-large">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
            <span class="user-type-badge badge-<?php echo $user['user_type']; ?>">
                <?php
                $type_names = [
                    'customer' => '일반 고객',
                    'business_owner' => '업체 관리자',
                    'teacher' => '선생님',
                    'admin' => '관리자'
                ];
                echo $type_names[$user['user_type']] ?? $user['user_type'];
                ?>
            </span>
            <div class="status-indicator">
                <i class="fas fa-circle <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"></i>
                <?php echo $user['is_active'] ? '활성 계정' : '비활성 계정'; ?>
            </div>
        </div>

        <!-- 사용자 통계 -->
        <?php if (!empty($stats)): ?>
            <div class="stats-grid">
                <?php if (isset($stats['total_reservations'])): ?>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($stats['total_reservations']); ?></span>
                        <div class="stat-label">총 예약 수</div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($stats['completed_reservations'])): ?>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($stats['completed_reservations']); ?></span>
                        <div class="stat-label">완료된 예약</div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($stats['total_spent'])): ?>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($stats['total_spent']); ?>원</span>
                        <div class="stat-label">총 결제 금액</div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($stats['businesses'])): ?>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo number_format($stats['businesses']); ?></span>
                        <div class="stat-label">등록한 업체</div>
                    </div>
                <?php endif; ?>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo date('Y.m.d', strtotime($user['created_at'])); ?></span>
                    <div class="stat-label">가입일</div>
                </div>
                
                <?php if ($user['last_login']): ?>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo date('m.d', strtotime($user['last_login'])); ?></span>
                        <div class="stat-label">최근 로그인</div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 사용자 정보 수정 폼 -->
    <div class="user-profile-card">
        <form method="POST">
            <div class="form-section">
                <h4><i class="fas fa-user"></i> 기본 정보 수정</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">이름 *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">이메일 *</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">전화번호</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="010-1234-5678">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">회원 유형 *</label>
                            <select name="user_type" class="form-select" required>
                                <option value="customer" <?php echo $user['user_type'] === 'customer' ? 'selected' : ''; ?>>일반 고객</option>
                                <option value="business_owner" <?php echo $user['user_type'] === 'business_owner' ? 'selected' : ''; ?>>업체 관리자</option>
                                <option value="teacher" <?php echo $user['user_type'] === 'teacher' ? 'selected' : ''; ?>>선생님</option>
                                <option value="admin" <?php echo $user['user_type'] === 'admin' ? 'selected' : ''; ?>>관리자</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" 
                               <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            <strong>계정 활성화</strong>
                            <div class="text-muted small">비활성화 시 로그인 불가</div>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-lock"></i> 비밀번호 변경</h4>
                <p class="text-muted">비밀번호를 변경하지 않으려면 아래 필드를 비워두세요.</p>
                
                <div class="form-group mb-3">
                    <label class="form-label">새 비밀번호</label>
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="6자 이상 입력">
                    <small class="text-muted">관리자는 사용자의 현재 비밀번호 없이도 변경 가능합니다.</small>
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> 정보 업데이트
                </button>
                <a href="admin_user_manage.php" class="btn btn-secondary btn-lg ms-2">
                    <i class="fas fa-times"></i> 취소
                </a>
            </div>
        </form>
    </div>

    <!-- 계정 세부 정보 -->
    <div class="user-profile-card">
        <h4><i class="fas fa-info-circle"></i> 계정 세부 정보</h4>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>사용자 ID:</strong></td>
                        <td><?php echo $user['id']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>가입일:</strong></td>
                        <td><?php echo date('Y년 m월 d일 H:i', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>마지막 업데이트:</strong></td>
                        <td><?php echo isset($user['updated_at']) ? date('Y년 m월 d일 H:i', strtotime($user['updated_at'])) : '정보 없음'; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>최근 로그인:</strong></td>
                        <td><?php echo $user['last_login'] ? date('Y년 m월 d일 H:i', strtotime($user['last_login'])) : '로그인 기록 없음'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>이메일 인증:</strong></td>
                        <td><?php echo $user['email_verified_at'] ? '인증됨' : '미인증'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>휴대폰 인증:</strong></td>
                        <td><?php echo $user['phone_verified_at'] ? '인증됨' : '미인증'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 