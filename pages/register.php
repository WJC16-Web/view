<?php
$page_title = '회원가입 - 뷰티북';
require_once '../includes/header.php';

// 이미 로그인된 경우 리다이렉트
if (isLoggedIn()) {
    redirect('/view/');
}

$user_type = $_GET['type'] ?? 'customer';
$allowed_types = ['customer', 'business'];
if (!in_array($user_type, $allowed_types)) {
    $user_type = 'customer';
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');
    $agree_terms = isset($_POST['agree_terms']);
    $agree_privacy = isset($_POST['agree_privacy']);
    
    // 유효성 검사
    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        $error_message = '모든 필수 항목을 입력해주세요.';
    } elseif (!validateEmail($email)) {
        $error_message = '올바른 이메일 형식이 아닙니다.';
    } elseif (!validatePhone($phone)) {
        $error_message = '올바른 휴대폰 번호 형식이 아닙니다. (010-XXXX-XXXX)';
    } elseif (strlen($password) < 8) {
        $error_message = '비밀번호는 8자 이상이어야 합니다.';
    } elseif ($password !== $password_confirm) {
        $error_message = '비밀번호와 비밀번호 확인이 일치하지 않습니다.';
    } elseif (!$agree_terms || !$agree_privacy) {
        $error_message = '이용약관과 개인정보처리방침에 동의해주세요.';
    } else {
        try {
            $db = getDB();
            
            // 이메일 중복 체크
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = '이미 가입된 이메일입니다.';
            } else {
                // 회원가입 처리
                $db->beginTransaction();
                
                $hashed_password = hashPassword($password);
                $phone_cleaned = preg_replace('/[^0-9]/', '', $phone);
                
                if ($user_type === 'customer') {
                    // 일반 고객 회원가입
                    $stmt = $db->prepare("
                        INSERT INTO users (email, password, name, phone, user_type) 
                        VALUES (?, ?, ?, ?, 'customer')
                    ");
                    $stmt->execute([$email, $hashed_password, $name, $phone_cleaned]);
                    $user_id = $db->lastInsertId();
                    
                    // 고객 프로필 생성
                    $stmt = $db->prepare("
                        INSERT INTO customer_profiles (user_id) VALUES (?)
                    ");
                    $stmt->execute([$user_id]);
                    
                    // 알림 설정 생성
                    $stmt = $db->prepare("
                        INSERT INTO notification_settings (user_id) VALUES (?)
                    ");
                    $stmt->execute([$user_id]);
                    
                } elseif ($user_type === 'business') {
                    // 업체 관리자 회원가입
                    $business_license = sanitize($_POST['business_license'] ?? '');
                    $business_name = sanitize($_POST['business_name'] ?? '');
                    $representative_name = sanitize($_POST['representative_name'] ?? '');
                    
                    if (empty($business_license) || empty($business_name) || empty($representative_name)) {
                        throw new Exception('업체 정보를 모두 입력해주세요.');
                    }
                    
                    // 사업자등록번호 중복 체크
                    $stmt = $db->prepare("SELECT id FROM business_owners WHERE business_license = ?");
                    $stmt->execute([$business_license]);
                    if ($stmt->fetch()) {
                        throw new Exception('이미 등록된 사업자등록번호입니다.');
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO users (email, password, name, phone, user_type) 
                        VALUES (?, ?, ?, ?, 'business_owner')
                    ");
                    $stmt->execute([$email, $hashed_password, $name, $phone_cleaned]);
                    $user_id = $db->lastInsertId();
                    
                    // 업체 관리자 정보 생성
                    $stmt = $db->prepare("
                        INSERT INTO business_owners (user_id, business_license, business_name, representative_name) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $business_license, $business_name, $representative_name]);
                    
                    // 알림 설정 생성
                    $stmt = $db->prepare("
                        INSERT INTO notification_settings (user_id) VALUES (?)
                    ");
                    $stmt->execute([$user_id]);
                }
                
                $db->commit();
                
                // 환영 알림 추가
                addNotification(
                    $user_id, 
                    'welcome', 
                    '뷰티북에 오신 것을 환영합니다!', 
                    ($user_type === 'business' ? 
                        '업체 승인 후 서비스를 이용하실 수 있습니다.' : 
                        '이제 뷰티북의 모든 서비스를 이용하실 수 있습니다.')
                );
                
                $success_message = '회원가입이 완료되었습니다!' . 
                    ($user_type === 'business' ? ' 관리자 승인 후 서비스를 이용하실 수 있습니다.' : '');
                
                // 3초 후 로그인 페이지로 리다이렉트
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '" . BASE_URL . "/pages/login.php';
                    }, 3000);
                </script>";
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }
}
?>

<style>
.register-container {
    min-height: calc(100vh - 200px);
    padding: 40px 0;
}

.register-form {
    background: white;
    padding: 50px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    max-width: 600px;
    margin: 0 auto;
}

.register-header {
    text-align: center;
    margin-bottom: 40px;
}

.register-header h1 {
    color: #2c3e50;
    font-size: 32px;
    margin-bottom: 10px;
}

.register-header p {
    color: #666;
    font-size: 16px;
}

.user-type-selector {
    display: flex;
    gap: 20px;
    margin-bottom: 40px;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
}

.type-option {
    flex: 1;
    text-align: center;
    padding: 20px;
    border: 2px solid #ddd;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    color: #666;
}

.type-option.active {
    border-color: #ff4757;
    background: #fff;
    color: #ff4757;
}

.type-option .type-icon {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

.type-option .type-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
}

.type-option .type-desc {
    font-size: 14px;
}

.form-section {
    margin-bottom: 30px;
}

.section-title {
    color: #2c3e50;
    font-size: 20px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f8f9fa;
}

.form-row {
    display: flex;
    gap: 20px;
}

.form-col {
    flex: 1;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: #2c3e50;
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-input:focus {
    outline: none;
    border-color: #ff4757;
}

.form-input.error {
    border-color: #e74c3c;
}

.input-help {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.business-section {
    display: none;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.business-section.show {
    display: block;
}

.agreement-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.agreement-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 15px;
    padding: 15px;
    background: white;
    border-radius: 8px;
}

.agreement-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 2px;
}

.agreement-text {
    flex: 1;
}

.agreement-title {
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}

.agreement-desc {
    color: #666;
    font-size: 14px;
    line-height: 1.4;
}

.agreement-link {
    color: #ff4757;
    text-decoration: none;
    font-size: 12px;
}

.register-btn {
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
}

.register-btn:hover {
    background: #ff3742;
}

.register-btn:disabled {
    background: #bdc3c7;
    cursor: not-allowed;
}

.login-link {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
    border: 1px solid #f5c6cb;
}

.success-message {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
    border: 1px solid #c3e6cb;
}

/* 반응형 */
@media (max-width: 768px) {
    .register-form {
        padding: 30px 20px;
        margin: 20px;
    }
    
    .user-type-selector {
        flex-direction: column;
        gap: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<div class="container">
    <div class="register-container">
        <form class="register-form" method="POST">
            <div class="register-header">
                <h1><i class="fas fa-user-plus"></i> 회원가입</h1>
                <p>뷰티북과 함께 새로운 경험을 시작해보세요</p>
            </div>
            
            <!-- 회원 유형 선택 -->
            <div class="user-type-selector">
                <a href="<?php echo BASE_URL; ?>/pages/register.php?type=customer" 
                   class="type-option <?php echo $user_type === 'customer' ? 'active' : ''; ?>">
                    <span class="type-icon"><i class="fas fa-user"></i></span>
                    <div class="type-name">일반 고객</div>
                    <div class="type-desc">예약하고 서비스를 이용</div>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/register.php?type=business" 
                   class="type-option <?php echo $user_type === 'business' ? 'active' : ''; ?>">
                    <span class="type-icon"><i class="fas fa-store"></i></span>
                    <div class="type-name">업체 관리자</div>
                    <div class="type-desc">업체를 등록하고 관리</div>
                </a>
            </div>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <br><small>3초 후 로그인 페이지로 이동합니다...</small>
                </div>
            <?php endif; ?>
            
            <!-- 기본 정보 -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fas fa-user"></i> 기본 정보
                </h3>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="name" class="form-label">이름 *</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   placeholder="이름을 입력해주세요" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="phone" class="form-label">휴대폰 번호 *</label>
                            <input type="tel" id="phone" name="phone" class="form-input" 
                                   placeholder="010-1234-5678" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            <div class="input-help">'-' 없이 숫자만 입력하셔도 됩니다</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">이메일 *</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="example@email.com" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    <div class="input-help">로그인 시 사용할 이메일 주소입니다</div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="password" class="form-label">비밀번호 *</label>
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="8자 이상 입력해주세요" required>
                            <div class="input-help">영문, 숫자 조합으로 8자 이상</div>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="password_confirm" class="form-label">비밀번호 확인 *</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-input" 
                                   placeholder="비밀번호를 다시 입력해주세요" required>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 업체 정보 (업체 관리자만) -->
            <div class="business-section <?php echo $user_type === 'business' ? 'show' : ''; ?>">
                <h3 class="section-title">
                    <i class="fas fa-store"></i> 업체 정보
                </h3>
                
                <div class="form-group">
                    <label for="business_license" class="form-label">사업자등록번호 *</label>
                    <input type="text" id="business_license" name="business_license" class="form-input" 
                           placeholder="123-45-67890" 
                           value="<?php echo htmlspecialchars($_POST['business_license'] ?? ''); ?>"
                           <?php echo $user_type === 'business' ? 'required' : ''; ?>>
                    <div class="input-help">'-' 포함하여 입력해주세요</div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="business_name" class="form-label">업체명 *</label>
                            <input type="text" id="business_name" name="business_name" class="form-input" 
                                   placeholder="업체명을 입력해주세요" 
                                   value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>"
                                   <?php echo $user_type === 'business' ? 'required' : ''; ?>>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="representative_name" class="form-label">대표자명 *</label>
                            <input type="text" id="representative_name" name="representative_name" class="form-input" 
                                   placeholder="대표자명을 입력해주세요" 
                                   value="<?php echo htmlspecialchars($_POST['representative_name'] ?? ''); ?>"
                                   <?php echo $user_type === 'business' ? 'required' : ''; ?>>
                        </div>
                    </div>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <i class="fas fa-info-circle" style="color: #856404;"></i>
                    <strong style="color: #856404;">안내사항</strong>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <li>업체 정보는 관리자 승인 후 서비스를 이용하실 수 있습니다.</li>
                        <li>승인까지 1-2일 정도 소요될 수 있습니다.</li>
                        <li>허위 정보 입력 시 승인이 거절될 수 있습니다.</li>
                    </ul>
                </div>
            </div>
            
            <!-- 약관 동의 -->
            <div class="agreement-section">
                <h3 class="section-title">
                    <i class="fas fa-clipboard-check"></i> 약관 동의
                </h3>
                
                <div class="agreement-item">
                    <input type="checkbox" id="agree_terms" name="agree_terms" required>
                    <div class="agreement-text">
                        <div class="agreement-title">[필수] 이용약관 동의</div>
                        <div class="agreement-desc">
                            서비스 이용을 위한 기본 약관입니다.
                            <a href="#" class="agreement-link" onclick="window.open('<?php echo BASE_URL; ?>/pages/terms.php', '_blank')">자세히 보기</a>
                        </div>
                    </div>
                </div>
                
                <div class="agreement-item">
                    <input type="checkbox" id="agree_privacy" name="agree_privacy" required>
                    <div class="agreement-text">
                        <div class="agreement-title">[필수] 개인정보처리방침 동의</div>
                        <div class="agreement-desc">
                            개인정보 수집 및 이용에 대한 동의입니다.
                            <a href="#" class="agreement-link" onclick="window.open('<?php echo BASE_URL; ?>/pages/privacy.php', '_blank')">자세히 보기</a>
                        </div>
                    </div>
                </div>
                
                <div class="agreement-item">
                    <input type="checkbox" id="agree_marketing" name="agree_marketing">
                    <div class="agreement-text">
                        <div class="agreement-title">[선택] 마케팅 정보 수신 동의</div>
                        <div class="agreement-desc">
                            이벤트, 할인 혜택 등의 마케팅 정보를 받으시겠습니까?
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="register-btn">
                <i class="fas fa-user-plus"></i> 
                <?php echo $user_type === 'business' ? '업체 회원가입' : '회원가입'; ?>
            </button>
            
            <div class="login-link">
                <p>이미 뷰티북 회원이신가요? 
                    <a href="<?php echo BASE_URL; ?>/pages/login.php" style="color: #ff4757; text-decoration: none;">
                        <strong>로그인하기</strong>
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // 휴대폰 번호 자동 포맷팅
    $('#phone').on('input', function() {
        var value = $(this).val().replace(/[^0-9]/g, '');
        if (value.length >= 3 && value.length <= 7) {
            value = value.replace(/(\d{3})(\d+)/, '$1-$2');
        } else if (value.length >= 8) {
            value = value.replace(/(\d{3})(\d{4})(\d+)/, '$1-$2-$3');
        }
        $(this).val(value);
    });
    
    // 사업자등록번호 자동 포맷팅
    $('#business_license').on('input', function() {
        var value = $(this).val().replace(/[^0-9]/g, '');
        if (value.length >= 3 && value.length <= 5) {
            value = value.replace(/(\d{3})(\d+)/, '$1-$2');
        } else if (value.length >= 6) {
            value = value.replace(/(\d{3})(\d{2})(\d+)/, '$1-$2-$3');
        }
        $(this).val(value);
    });
    
    // 비밀번호 확인
    $('#password_confirm').on('input', function() {
        var password = $('#password').val();
        var confirm = $(this).val();
        
        if (password && confirm && password !== confirm) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
    });
    
    // 폼 유효성 검사
    $('form').submit(function(e) {
        var isValid = true;
        
        // 필수 필드 체크
        $('.form-input[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        // 이메일 형식 체크
        var email = $('#email').val();
        if (email && !isValidEmail(email)) {
            $('#email').addClass('error');
            alert('올바른 이메일 형식이 아닙니다.');
            isValid = false;
        }
        
        // 휴대폰 번호 체크
        var phone = $('#phone').val().replace(/[^0-9]/g, '');
        if (phone && !phone.match(/^01[0-9]{8,9}$/)) {
            $('#phone').addClass('error');
            alert('올바른 휴대폰 번호 형식이 아닙니다.');
            isValid = false;
        }
        
        // 비밀번호 체크
        var password = $('#password').val();
        var passwordConfirm = $('#password_confirm').val();
        if (password.length < 8) {
            $('#password').addClass('error');
            alert('비밀번호는 8자 이상이어야 합니다.');
            isValid = false;
        }
        if (password !== passwordConfirm) {
            $('#password_confirm').addClass('error');
            alert('비밀번호와 비밀번호 확인이 일치하지 않습니다.');
            isValid = false;
        }
        
        // 약관 동의 체크
        if (!$('#agree_terms').is(':checked') || !$('#agree_privacy').is(':checked')) {
            alert('필수 약관에 동의해주세요.');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            return false;
        }
        
        // 제출 버튼 비활성화
        $('.register-btn').prop('disabled', true).text('회원가입 처리 중...');
    });
    
    // 이메일 유효성 검사 함수
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 