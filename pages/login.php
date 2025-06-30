<?php
$page_title = '로그인 - 뷰티북';
require_once '../includes/header.php';

// 이미 로그인된 경우 리다이렉트
if ($user = getCurrentUser()) {
    switch ($user['user_type']) {
        case 'customer':
            redirect('/view/pages/customer_mypage.php');
            break;
        case 'business_owner':
            redirect('/view/pages/business_dashboard.php');
            break;
        case 'teacher':
            redirect('/view/pages/teacher_mypage.php');
            break;
        case 'admin':
            redirect('/view/pages/admin_dashboard.php');
            break;
        default:
            redirect('/view/');
    }
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($email) || empty($password)) {
        $error_message = '이메일과 비밀번호를 입력해주세요.';
    } elseif (!validateEmail($email)) {
        $error_message = '올바른 이메일 형식이 아닙니다.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // 최근 로그인 시간 업데이트
                $update_stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$user['id']]);
                
                // 로그인 성공
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['user_name'] = $user['name'];
                
                // Remember me 처리
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    // 실제 구현에서는 remember_tokens 테이블에 저장
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30일
                }
                
                // 사용자 타입별 리다이렉트
                switch ($user['user_type']) {
                    case 'customer':
                        redirect('/view/pages/customer_mypage.php', '환영합니다!');
                        break;
                    case 'business_owner':
                        redirect('/view/pages/business_dashboard.php', '관리자로 환영합니다!');
                        break;
                    case 'teacher':
                        redirect('/view/pages/teacher_mypage.php', '선생님으로 환영합니다!');
                        break;
                    case 'admin':
                        redirect('/view/pages/admin_dashboard.php', '관리자로 환영합니다!');
                        break;
                    default:
                        redirect('/view/', '로그인되었습니다.');
                }
            } else {
                $error_message = '이메일 또는 비밀번호가 일치하지 않습니다.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = '로그인 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
        }
    }
}
?>

<style>
/* 로그인 페이지 전용 스타일 */
.login-container {
    min-height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
    background: white;
    border-radius: 24px;
    padding: 40px 30px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
    margin: 0 auto;
    width: 100%;
    max-width: 400px;
    position: relative;
    overflow: hidden;
}

.login-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #ff4757 0%, #ff6b7a 100%);
}

.login-header {
    text-align: center;
    margin-bottom: 40px;
}

.login-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #ff4757 0%, #ff6b7a 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    margin: 0 auto 20px;
    box-shadow: 0 8px 20px rgba(255, 71, 87, 0.3);
}

.login-title {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
}

.login-subtitle {
    font-size: 16px;
    color: #666;
}

.form-group {
    margin-bottom: 24px;
    position: relative;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.form-input-wrapper {
    position: relative;
}

.form-input {
    width: 100%;
    padding: 16px 20px 16px 50px;
    border: 2px solid #e9ecef;
    border-radius: 16px;
    font-size: 16px;
    background: #f8f9fa;
    transition: all 0.3s;
    -webkit-appearance: none;
}

.form-input:focus {
    outline: none;
    border-color: #ff4757;
    background: white;
    box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
}

.form-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 18px;
    transition: color 0.3s;
}

.form-input:focus + .form-icon {
    color: #ff4757;
}

.password-toggle {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    font-size: 18px;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: color 0.3s;
}

.password-toggle:hover {
    color: #ff4757;
}

.checkbox-group {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 24px 0;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid #ddd;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    position: relative;
    transition: all 0.3s;
}

.checkbox input {
    opacity: 0;
    position: absolute;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.checkbox input:checked + .checkmark {
    background: #ff4757;
    border-color: #ff4757;
}

.checkbox input:checked + .checkmark::after {
    display: block;
}

.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 16px;
    width: 16px;
    background: transparent;
    border: 2px solid #ddd;
    border-radius: 4px;
    transition: all 0.3s;
}

.checkmark::after {
    content: "";
    position: absolute;
    display: none;
    left: 4px;
    top: 1px;
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.checkbox-label {
    font-size: 14px;
    color: #666;
    cursor: pointer;
    user-select: none;
}

.forgot-password {
    color: #ff4757;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: opacity 0.3s;
}

.forgot-password:hover {
    opacity: 0.8;
    text-decoration: underline;
}

.login-btn {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #ff4757 0%, #ff6b7a 100%);
    color: white;
    border: none;
    border-radius: 16px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);
    margin-bottom: 24px;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 71, 87, 0.4);
}

.login-btn:active {
    transform: translateY(0);
}

.login-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.social-login {
    text-align: center;
    margin: 30px 0;
}

.social-title {
    font-size: 14px;
    color: #999;
    margin-bottom: 20px;
    position: relative;
}

.social-title::before,
.social-title::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 45%;
    height: 1px;
    background: #ddd;
}

.social-title::before {
    left: 0;
}

.social-title::after {
    right: 0;
}

.social-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.social-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 2px solid #e9ecef;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 20px;
    transition: all 0.3s;
}

.social-btn.kakao {
    background: #FEE500;
    border-color: #FEE500;
    color: #3C1E1E;
}

.social-btn.naver {
    background: #03C75A;
    border-color: #03C75A;
    color: white;
}

.social-btn.google {
    background: #4285F4;
    border-color: #4285F4;
    color: white;
}

.social-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.register-link {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 16px;
    margin-top: 20px;
}

.register-text {
    font-size: 14px;
    color: #666;
    margin-bottom: 8px;
}

.register-btn {
    color: #ff4757;
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
}

.register-btn:hover {
    text-decoration: underline;
}

/* 로딩 상태 */
.loading {
    display: none;
    width: 20px;
    height: 20px;
    border: 2px solid #ffffff;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 8px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 에러 메시지 */
.error-message {
    background: #ffe6e6;
    color: #d63031;
    padding: 16px;
    border-radius: 12px;
    font-size: 14px;
    margin-bottom: 20px;
    border: 1px solid #ff7675;
    display: flex;
    align-items: center;
    gap: 8px;
}

.error-icon {
    font-size: 16px;
}

/* 반응형 */
@media (max-width: 480px) {
    .login-container {
        padding: 20px 15px;
    }
    
    .login-card {
        padding: 30px 20px;
        border-radius: 20px;
    }
    
    .login-title {
        font-size: 24px;
    }
}

/* 다크모드 지원 */
@media (prefers-color-scheme: dark) {
    .login-card {
        background: #2d2d2d;
        color: white;
    }
    
    .login-title {
        color: white;
    }
    
    .form-input {
        background: #404040;
        border-color: #555;
        color: white;
    }
    
    .form-input:focus {
        background: #4a4a4a;
        border-color: #ff4757;
    }
    
    .register-link {
        background: #404040;
    }
}
</style>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-user"></i>
            </div>
            <h1 class="login-title">환영합니다!</h1>
            <p class="login-subtitle">뷰티북에 로그인하세요</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle error-icon"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="email" class="form-label">이메일</label>
                <div class="form-input-wrapper">
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="이메일을 입력하세요"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required>
                    <i class="fas fa-envelope form-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">비밀번호</label>
                <div class="form-input-wrapper">
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="비밀번호를 입력하세요" required>
                    <i class="fas fa-lock form-icon"></i>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="checkbox-group">
                <div class="checkbox-wrapper">
                    <div class="checkbox">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <span class="checkmark"></span>
                    </div>
                    <label for="remember_me" class="checkbox-label">로그인 상태 유지</label>
                </div>
                <a href="#" class="forgot-password">비밀번호 찾기</a>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                <span class="loading" id="loading"></span>
                <span id="btnText">로그인</span>
            </button>
        </form>

        <!-- 소셜 로그인 -->
        <div class="social-login">
            <div class="social-title">간편 로그인</div>
            <div class="social-buttons">
                <a href="#" class="social-btn kakao" onclick="loginWithKakao()">
                    <i class="fas fa-comment"></i>
                </a>
                <a href="#" class="social-btn naver" onclick="loginWithNaver()">
                    <span style="font-weight: bold;">N</span>
                </a>
                <a href="#" class="social-btn google" onclick="loginWithGoogle()">
                    <i class="fab fa-google"></i>
                </a>
            </div>
        </div>

        <!-- 회원가입 링크 -->
        <div class="register-link">
            <div class="register-text">아직 계정이 없으신가요?</div>
            <a href="register.php" class="register-btn">회원가입하기</a>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 폼 제출 처리
    $('#loginForm').submit(function(e) {
        const email = $('#email').val().trim();
        const password = $('#password').val();
        
        if (!email || !password) {
            e.preventDefault();
            showError('이메일과 비밀번호를 모두 입력해주세요.');
            return false;
        }
        
        if (!isValidEmail(email)) {
            e.preventDefault();
            showError('올바른 이메일 형식이 아닙니다.');
            $('#email').focus();
            return false;
        }
        
        // 로딩 시작
        showLoading(true);
    });
    
    // 입력 필드 실시간 검증
    $('#email').on('input', function() {
        const email = $(this).val().trim();
        if (email && !isValidEmail(email)) {
            $(this).css('border-color', '#dc3545');
        } else {
            $(this).css('border-color', '');
        }
    });
    
    // 비밀번호 필드 엔터키 처리
    $('#password').keypress(function(e) {
        if (e.which === 13) {
            $('#loginForm').submit();
        }
    });
    
    // 자동 포커스
    setTimeout(() => {
        $('#email').focus();
    }, 500);
});

// 비밀번호 표시/숨김 토글
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// 이메일 유효성 검사
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// 에러 메시지 표시
function showError(message) {
    const existingError = document.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-circle error-icon"></i>
        ${message}
    `;
    
    const form = document.getElementById('loginForm');
    form.parentNode.insertBefore(errorDiv, form);
    
    // 3초 후 자동 제거
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}

// 로딩 상태 표시
function showLoading(show) {
    const loading = document.getElementById('loading');
    const btnText = document.getElementById('btnText');
    const loginBtn = document.getElementById('loginBtn');
    
    if (show) {
        loading.style.display = 'inline-block';
        btnText.textContent = '로그인 중...';
        loginBtn.disabled = true;
    } else {
        loading.style.display = 'none';
        btnText.textContent = '로그인';
        loginBtn.disabled = false;
    }
}

// 소셜 로그인 함수들 (추후 구현)
function loginWithKakao() {
    alert('카카오 로그인 기능 준비 중입니다.');
}

function loginWithNaver() {
    alert('네이버 로그인 기능 준비 중입니다.');
}

function loginWithGoogle() {
    alert('구글 로그인 기능 준비 중입니다.');
}

// 터치 피드백
document.addEventListener('touchstart', function(e) {
    if (e.target.classList.contains('login-btn') || 
        e.target.classList.contains('social-btn') ||
        e.target.closest('.login-btn') ||
        e.target.closest('.social-btn')) {
        e.target.style.opacity = '0.8';
    }
});

document.addEventListener('touchend', function(e) {
    if (e.target.classList.contains('login-btn') || 
        e.target.classList.contains('social-btn') ||
        e.target.closest('.login-btn') ||
        e.target.closest('.social-btn')) {
        setTimeout(() => {
            e.target.style.opacity = '';
        }, 150);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 