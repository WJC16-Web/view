<?php
$page_title = '로그인 - 뷰티북';
require_once '../includes/header.php';

// 이미 로그인된 경우 리다이렉트
if (isLoggedIn()) {
    $user = getCurrentUser();
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
                        redirect('/view/pages/business_dashboard.php', '관리자님 환영합니다!');
                        break;
                    case 'teacher':
                        redirect('/view/pages/teacher_mypage.php', '선생님 환영합니다!');
                        break;
                    case 'admin':
                        redirect('/view/pages/admin_dashboard.php', '관리자님 환영합니다!');
                        break;
                    default:
                        redirect('/view/', '로그인되었습니다.');
                }
            } else {
                $error_message = '이메일 또는 비밀번호가 일치하지 않습니다.';
            }
        } catch (Exception $e) {
            $error_message = '로그인 중 오류가 발생했습니다. 다시 시도해주세요.';
        }
    }
}
?>

<style>
.login-container {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 0;
}

.login-form {
    background: white;
    padding: 50px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 450px;
}

.login-header {
    text-align: center;
    margin-bottom: 40px;
}

.login-header h1 {
    color: #2c3e50;
    font-size: 32px;
    margin-bottom: 10px;
}

.login-header p {
    color: #666;
    font-size: 16px;
}

.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: #2c3e50;
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-input:focus {
    outline: none;
    border-color: #ff4757;
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 30px;
}

.form-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.form-checkbox label {
    color: #666;
    font-size: 14px;
    cursor: pointer;
}

.login-btn {
    width: 100%;
    padding: 15px;
    background: #ff4757;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
    margin-bottom: 20px;
}

.login-btn:hover {
    background: #ff3742;
}

.login-btn:disabled {
    background: #bdc3c7;
    cursor: not-allowed;
}

.form-links {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.form-links a {
    color: #ff4757;
    text-decoration: none;
    margin: 0 10px;
}

.form-links a:hover {
    text-decoration: underline;
}

.social-login {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #eee;
}

.social-login-title {
    text-align: center;
    margin-bottom: 20px;
    color: #666;
    font-size: 14px;
}

.social-buttons {
    display: flex;
    gap: 10px;
}

.social-btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    text-align: center;
}

.social-btn.kakao {
    background: #fee500;
    color: #3c1e1e;
}

.social-btn.naver {
    background: #03c75a;
    color: white;
}

.social-btn.google {
    background: #4285f4;
    color: white;
}

.social-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
    border: 1px solid #f5c6cb;
}

.register-link {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.register-link p {
    margin-bottom: 15px;
    color: #666;
}

.register-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.register-btn {
    flex: 1;
    min-width: 120px;
    padding: 10px 20px;
    text-decoration: none;
    text-align: center;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s;
}

.register-btn.customer {
    background: #ff4757;
    color: white;
}

.register-btn.business {
    background: #2c3e50;
    color: white;
}

.register-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

/* 반응형 */
@media (max-width: 768px) {
    .login-form {
        padding: 30px 20px;
        margin: 20px;
    }
    
    .social-buttons {
        flex-direction: column;
    }
    
    .register-buttons {
        flex-direction: column;
    }
}
</style>

<div class="container">
    <div class="login-container">
        <form class="login-form" method="POST">
            <div class="login-header">
                <h1><i class="fas fa-spa"></i> 뷰티북</h1>
                <p>예약의 새로운 경험을 시작해보세요</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i> 이메일
                </label>
                <input type="email" id="email" name="email" class="form-input" 
                       placeholder="이메일을 입력해주세요" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> 비밀번호
                </label>
                <input type="password" id="password" name="password" class="form-input" 
                       placeholder="비밀번호를 입력해주세요" required>
            </div>
            
            <div class="form-checkbox">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">로그인 상태 유지</label>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> 로그인
            </button>
            
            <div class="form-links">
                <a href="<?php echo BASE_URL; ?>/pages/find_password.php">
                    <i class="fas fa-key"></i> 비밀번호 찾기
                </a>
                |
                <a href="<?php echo BASE_URL; ?>/pages/register.php">
                    <i class="fas fa-user-plus"></i> 회원가입
                </a>
            </div>
            
            <!-- 소셜 로그인 -->
            <div class="social-login">
                <div class="social-login-title">
                    <i class="fas fa-share-alt"></i> 간편 로그인
                </div>
                <div class="social-buttons">
                    <a href="#" class="social-btn kakao" onclick="alert('카카오 로그인 준비 중입니다.')">
                        <i class="fab fa-kakao"></i> 카카오
                    </a>
                    <a href="#" class="social-btn naver" onclick="alert('네이버 로그인 준비 중입니다.')">
                        <strong>N</strong> 네이버
                    </a>
                    <a href="#" class="social-btn google" onclick="alert('구글 로그인 준비 중입니다.')">
                        <i class="fab fa-google"></i> 구글
                    </a>
                </div>
            </div>
            
            <!-- 회원가입 안내 -->
            <div class="register-link">
                <p><strong>아직 뷰티북 회원이 아니신가요?</strong></p>
                <div class="register-buttons">
                    <a href="<?php echo BASE_URL; ?>/pages/register.php?type=customer" class="register-btn customer">
                        <i class="fas fa-user"></i> 고객 회원가입
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/register.php?type=business" class="register-btn business">
                        <i class="fas fa-store"></i> 업체 회원가입
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // 엔터키로 로그인
    $('.form-input').keypress(function(e) {
        if (e.which === 13) {
            $('.login-btn').click();
        }
    });
    
    // 폼 유효성 검사
    $('form').submit(function(e) {
        var email = $('#email').val().trim();
        var password = $('#password').val();
        
        if (!email || !password) {
            alert('이메일과 비밀번호를 모두 입력해주세요.');
            e.preventDefault();
            return false;
        }
        
        if (!isValidEmail(email)) {
            alert('올바른 이메일 형식이 아닙니다.');
            $('#email').focus();
            e.preventDefault();
            return false;
        }
        
        // 로그인 버튼 비활성화
        $('.login-btn').prop('disabled', true).text('로그인 중...');
    });
    
    // 이메일 유효성 검사 함수
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 