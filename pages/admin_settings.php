<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

// 설정 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name'] ?? '뷰티북');
    $site_description = trim($_POST['site_description'] ?? '');
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $allow_registration = isset($_POST['allow_registration']) ? 1 : 0;
    
    $success = "설정이 업데이트되었습니다.";
}

// 현재 설정값
$settings = [
    'site_name' => '뷰티북',
    'site_description' => '뷰티 예약 플랫폼',
    'maintenance_mode' => 0,
    'allow_registration' => 1,
    'max_upload_size' => '10MB',
    'session_timeout' => '30분'
];
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}
.settings-section {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}
.setting-item {
    padding: 1rem 0;
    border-bottom: 1px solid #e9ecef;
}
.setting-item:last-child {
    border-bottom: none;
}
</style>

<div class="admin-header">
    <div class="container">
        <h1>⚙️ 시스템 설정</h1>
        <p>시스템 전반의 설정을 관리합니다</p>
    </div>
</div>

<div class="container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <!-- 기본 설정 -->
        <div class="settings-section">
            <h4>기본 설정</h4>
            
            <div class="setting-item">
                <label class="form-label">사이트 이름</label>
                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
            </div>
            
            <div class="setting-item">
                <label class="form-label">사이트 설명</label>
                <textarea name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
            </div>
        </div>
        
        <!-- 운영 설정 -->
        <div class="settings-section">
            <h4>운영 설정</h4>
            
            <div class="setting-item">
                <div class="form-check">
                    <input type="checkbox" name="maintenance_mode" class="form-check-input" id="maintenance" 
                           <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="maintenance">
                        <strong>점검 모드</strong>
                        <div class="text-muted small">활성화 시 관리자 외 접근 차단</div>
                    </label>
                </div>
            </div>
            
            <div class="setting-item">
                <div class="form-check">
                    <input type="checkbox" name="allow_registration" class="form-check-input" id="registration" 
                           <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="registration">
                        <strong>신규 가입 허용</strong>
                        <div class="text-muted small">비활성화 시 신규 회원가입 차단</div>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- 시스템 정보 -->
        <div class="settings-section">
            <h4>시스템 정보</h4>
            
            <div class="setting-item">
                <div class="row">
                    <div class="col-md-6">
                        <strong>PHP 버전:</strong> <?php echo PHP_VERSION; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>최대 업로드 크기:</strong> <?php echo $settings['max_upload_size']; ?>
                    </div>
                </div>
            </div>
            
            <div class="setting-item">
                <div class="row">
                    <div class="col-md-6">
                        <strong>세션 타임아웃:</strong> <?php echo $settings['session_timeout']; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>시스템 상태:</strong> <span class="text-success">정상</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> 설정 저장
            </button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?> 