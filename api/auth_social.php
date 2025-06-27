<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../includes/functions.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않는 메소드입니다.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$provider = $input['provider'] ?? '';
$access_token = $input['access_token'] ?? '';
$user_type = $input['user_type'] ?? 'customer';

if (!$provider || !$access_token) {
    echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.']);
    exit;
}

try {
    $db = getDB();
    
    // 소셜 제공자별 사용자 정보 가져오기
    $social_user = getSocialUserInfo($provider, $access_token);
    
    if (!$social_user) {
        echo json_encode(['success' => false, 'message' => '소셜 로그인 정보를 가져올 수 없습니다.']);
        exit;
    }
    
    // 기존 사용자 확인
    $stmt = $db->prepare("
        SELECT u.*, 
               CASE 
                   WHEN u.user_type = 'customer' THEN cp.name
                   WHEN u.user_type = 'business' THEN bo.representative_name
                   WHEN u.user_type = 'teacher' THEN t.name
                   ELSE u.name
               END as display_name
        FROM users u
        LEFT JOIN customer_profiles cp ON u.id = cp.user_id
        LEFT JOIN business_owners bo ON u.id = bo.user_id  
        LEFT JOIN teachers t ON u.id = t.user_id
        WHERE u.social_provider = ? AND u.social_id = ? AND u.is_active = 1
    ");
    $stmt->execute([$provider, $social_user['id']]);
    $existing_user = $stmt->fetch();
    
    if ($existing_user) {
        // 기존 사용자 로그인
        $_SESSION['user_id'] = $existing_user['id'];
        $_SESSION['user_type'] = $existing_user['user_type'];
        $_SESSION['user_name'] = $existing_user['display_name'];
        
        // 로그인 시간 업데이트
        $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$existing_user['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => '로그인되었습니다.',
            'user' => [
                'id' => $existing_user['id'],
                'name' => $existing_user['display_name'],
                'email' => $existing_user['email'],
                'user_type' => $existing_user['user_type']
            ]
        ]);
        exit;
    }
    
    // 이메일로 기존 계정 확인
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$social_user['email']]);
    $email_user = $stmt->fetch();
    
    if ($email_user) {
        // 기존 계정에 소셜 정보 연동
        $stmt = $db->prepare("
            UPDATE users 
            SET social_provider = ?, social_id = ?, social_data = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $provider,
            $social_user['id'],
            json_encode($social_user),
            $email_user['id']
        ]);
        
        $_SESSION['user_id'] = $email_user['id'];
        $_SESSION['user_type'] = $email_user['user_type'];
        
        echo json_encode([
            'success' => true,
            'message' => '기존 계정과 연동되었습니다.',
            'linked' => true
        ]);
        exit;
    }
    
    // 신규 사용자 생성
    $db->beginTransaction();
    
    // 사용자 기본 정보 생성
    $stmt = $db->prepare("
        INSERT INTO users (
            email, name, phone, user_type, 
            social_provider, social_id, social_data,
            email_verified_at, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
    ");
    
    $stmt->execute([
        $social_user['email'],
        $social_user['name'],
        $social_user['phone'] ?? null,
        $user_type,
        $provider,
        $social_user['id'],
        json_encode($social_user)
    ]);
    
    $user_id = $db->lastInsertId();
    
    // 사용자 타입별 프로필 생성
    if ($user_type === 'customer') {
        $stmt = $db->prepare("
            INSERT INTO customer_profiles (
                user_id, name, profile_image, created_at, updated_at
            ) VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $user_id,
            $social_user['name'],
            $social_user['picture'] ?? null
        ]);
    }
    
    $db->commit();
    
    // 세션 설정
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_type'] = $user_type;
    $_SESSION['user_name'] = $social_user['name'];
    
    // 신규 가입 쿠폰 발급
    issueWelcomeCoupon($user_id);
    
    echo json_encode([
        'success' => true,
        'message' => '회원가입이 완료되었습니다.',
        'new_user' => true,
        'user' => [
            'id' => $user_id,
            'name' => $social_user['name'],
            'email' => $social_user['email'],
            'user_type' => $user_type
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    error_log("Social auth error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '소셜 로그인 중 오류가 발생했습니다.']);
}

/**
 * 소셜 제공자별 사용자 정보 가져오기
 */
function getSocialUserInfo($provider, $access_token) {
    switch ($provider) {
        case 'kakao':
            return getKakaoUserInfo($access_token);
        case 'naver':
            return getNaverUserInfo($access_token);
        case 'google':
            return getGoogleUserInfo($access_token);
        default:
            return null;
    }
}

/**
 * 카카오 사용자 정보 가져오기
 */
function getKakaoUserInfo($access_token) {
    $url = "https://kapi.kakao.com/v2/user/me";
    
    $headers = [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/x-www-form-urlencoded"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || isset($data['code'])) {
        return null;
    }
    
    return [
        'id' => $data['id'],
        'email' => $data['kakao_account']['email'] ?? '',
        'name' => $data['kakao_account']['profile']['nickname'] ?? '',
        'picture' => $data['kakao_account']['profile']['profile_image_url'] ?? null,
        'raw_data' => $data
    ];
}

/**
 * 네이버 사용자 정보 가져오기
 */
function getNaverUserInfo($access_token) {
    $url = "https://openapi.naver.com/v1/nid/me";
    
    $headers = [
        "Authorization: Bearer " . $access_token
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || $data['resultcode'] !== '00') {
        return null;
    }
    
    $profile = $data['response'];
    
    return [
        'id' => $profile['id'],
        'email' => $profile['email'] ?? '',
        'name' => $profile['name'] ?? $profile['nickname'] ?? '',
        'phone' => $profile['mobile'] ?? null,
        'picture' => $profile['profile_image'] ?? null,
        'raw_data' => $data
    ];
}

/**
 * 구글 사용자 정보 가져오기
 */
function getGoogleUserInfo($access_token) {
    $url = "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $access_token;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || isset($data['error'])) {
        return null;
    }
    
    return [
        'id' => $data['id'],
        'email' => $data['email'] ?? '',
        'name' => $data['name'] ?? '',
        'picture' => $data['picture'] ?? null,
        'raw_data' => $data
    ];
}

/**
 * 신규 가입 쿠폰 발급
 */
function issueWelcomeCoupon($user_id) {
    try {
        $db = getDB();
        
        // 신규 가입 쿠폰 조회
        $stmt = $db->prepare("
            SELECT * FROM coupons 
            WHERE coupon_code = 'WELCOME20' AND is_active = 1 
            AND (valid_until IS NULL OR valid_until >= NOW())
        ");
        $stmt->execute();
        $coupon = $stmt->fetch();
        
        if ($coupon) {
            // 사용자에게 쿠폰 발급
            $stmt = $db->prepare("
                INSERT INTO customer_coupons (customer_id, coupon_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user_id, $coupon['id']]);
        }
    } catch (Exception $e) {
        // 쿠폰 발급 실패는 무시 (로그만 기록)
        error_log("Welcome coupon issue failed: " . $e->getMessage());
    }
}
?> 