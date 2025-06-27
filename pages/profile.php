<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// 로그인 확인
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// 프로필 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // 기본 정보 검증
    if (empty($name)) {
        $errors[] = "이름을 입력해주세요.";
    }
    
    // 비밀번호 변경 시 검증
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "현재 비밀번호를 입력해주세요.";
        } else {
            // 현재 비밀번호 확인
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "현재 비밀번호가 일치하지 않습니다.";
            }
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "새 비밀번호는 6자 이상이어야 합니다.";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "새 비밀번호 확인이 일치하지 않습니다.";
        }
    }
    
    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                // 비밀번호 변경 포함
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $hashed_password, $user_id]);
            } else {
                // 기본 정보만 업데이트
                $stmt = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $user_id]);
            }
            
            $_SESSION['user_name'] = $name;
            $success = "프로필이 성공적으로 업데이트되었습니다.";
        } catch (Exception $e) {
            $errors[] = "업데이트 중 오류가 발생했습니다.";
        }
    }
}

// 사용자 정보 조회
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// 사용자 활동 통계 (예약 관련)
$stats = [];
if ($user['user_type'] === 'customer') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_reservations'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE customer_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['completed_reservations'] = $stmt->fetchColumn();
}
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3rem 0;
    text-align: center;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 3rem;
    font-weight: 700;
    border: 4px solid rgba(255,255,255,0.3);
}

.profile-name {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.profile-type {
    font-size: 1.1rem;
    opacity: 0.9;
}

.profile-content {
    margin-top: -2rem;
    position: relative;
    z-index: 1;
}

.profile-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-item {
    text-align: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 10px;
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

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 0.75rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-update {
    background: #667eea;
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.3s ease;
}

.btn-update:hover {
    background: #5a6fd8;
}

.alert {
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.user-type-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.badge-customer { background: #e3f2fd; color: #1976d2; }
.badge-business { background: #f3e5f5; color: #7b1fa2; }
.badge-teacher { background: #e8f5e8; color: #388e3c; }
.badge-admin { background: #fff3e0; color: #f57c00; }
</style>

<div class="profile-header">
    <div class="container">
        <div class="profile-avatar">
            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
        </div>
        <h1 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h1>
        <p class="profile-type">
            <?php
            $type_names = [
                'customer' => '일반 고객',
                'business' => '업체 관리자',
                'teacher' => '선생님',
                'admin' => '관리자'
            ];
            echo $type_names[$user['user_type']] ?? $user['user_type'];
            ?>
        </p>
        <span class="user-type-badge badge-<?php echo $user['user_type']; ?>">
            <?php echo $type_names[$user['user_type']] ?? $user['user_type']; ?>
        </span>
    </div>
</div>

<div class="container profile-content">
    <?php if (isset($success)): ?>
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
    
    <!-- 통계 (고객인 경우) -->
    <?php if ($user['user_type'] === 'customer' && !empty($stats)): ?>
        <div class="profile-card">
            <h4><i class="fas fa-chart-bar"></i> 나의 활동</h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['total_reservations']); ?></span>
                    <div class="stat-label">총 예약 수</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['completed_reservations']); ?></span>
                    <div class="stat-label">완료된 예약</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo date('Y.m.d', strtotime($user['created_at'])); ?></span>
                    <div class="stat-label">가입일</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- 프로필 수정 폼 -->
    <div class="profile-card">
        <form method="POST">
            <div class="form-section">
                <h4><i class="fas fa-user"></i> 기본 정보</h4>
                
                <div class="form-group">
                    <label class="form-label">이메일</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    <small class="text-muted">이메일은 변경할 수 없습니다.</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">이름 *</label>
                    <input type="text" name="name" class="form-control" 
                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">전화번호</label>
                    <input type="tel" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                           placeholder="010-1234-5678">
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-lock"></i> 비밀번호 변경</h4>
                <p class="text-muted">비밀번호를 변경하지 않으려면 아래 필드를 비워두세요.</p>
                
                <div class="form-group">
                    <label class="form-label">현재 비밀번호</label>
                    <input type="password" name="current_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">새 비밀번호</label>
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="6자 이상 입력">
                </div>
                
                <div class="form-group">
                    <label class="form-label">새 비밀번호 확인</label>
                    <input type="password" name="confirm_password" class="form-control">
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-update">
                    <i class="fas fa-save"></i> 프로필 업데이트
                </button>
            </div>
        </form>
    </div>
    
    <!-- 계정 정보 -->
    <div class="profile-card">
        <h4><i class="fas fa-info-circle"></i> 계정 정보</h4>
        <div class="row">
            <div class="col-md-6">
                <p><strong>가입일:</strong> <?php echo date('Y년 m월 d일', strtotime($user['created_at'])); ?></p>
                <p><strong>계정 상태:</strong> 
                    <span class="<?php echo $user['is_active'] ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $user['is_active'] ? '활성' : '비활성'; ?>
                    </span>
                </p>
            </div>
            <div class="col-md-6">
                <?php if (isset($user['last_login']) && $user['last_login']): ?>
                    <p><strong>최근 로그인:</strong> <?php echo date('Y년 m월 d일 H:i', strtotime($user['last_login'])); ?></p>
                <?php else: ?>
                    <p><strong>최근 로그인:</strong> 정보 없음</p>
                <?php endif; ?>
                <p><strong>회원 유형:</strong> <?php echo $type_names[$user['user_type']] ?? $user['user_type']; ?></p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 