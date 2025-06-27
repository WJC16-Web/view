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
$action = $input['action'] ?? '';
$phone = $input['phone'] ?? '';
$verification_code = $input['verification_code'] ?? '';

// 휴대폰 번호 형식 검증
if ($phone && !preg_match('/^01[0-9]{8,9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => '올바른 휴대폰 번호를 입력해주세요.']);
    exit;
}

try {
    $db = getDB();
    
    if ($action === 'send') {
        // 인증번호 발송
        if (!$phone) {
            echo json_encode(['success' => false, 'message' => '휴대폰 번호를 입력해주세요.']);
            exit;
        }
        
        // 1분 내 중복 발송 방지
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM phone_verifications 
            WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$phone]);
        $recent_count = $stmt->fetch()['count'];
        
        if ($recent_count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => '인증번호 발송은 1분에 한 번만 가능합니다.'
            ]);
            exit;
        }
        
        // 하루 최대 5회 제한
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM phone_verifications 
            WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute([$phone]);
        $daily_count = $stmt->fetch()['count'];
        
        if ($daily_count >= 5) {
            echo json_encode([
                'success' => false, 
                'message' => '하루 최대 5회까지만 인증번호 발송이 가능합니다.'
            ]);
            exit;
        }
        
        // 6자리 인증번호 생성
        $code = sprintf('%06d', mt_rand(100000, 999999));
        
        // 데이터베이스에 저장
        $stmt = $db->prepare("
            INSERT INTO phone_verifications (phone, verification_code, expires_at, created_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE), NOW())
        ");
        $stmt->execute([$phone, $code]);
        
        // SMS 발송 (가상 구현)
        $sms_result = sendVerificationSMS($phone, $code);
        
        if ($sms_result) {
            echo json_encode([
                'success' => true,
                'message' => '인증번호가 발송되었습니다. (3분 유효)',
                'phone' => $phone
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'SMS 발송에 실패했습니다. 잠시 후 다시 시도해주세요.'
            ]);
        }
        
    } elseif ($action === 'verify') {
        // 인증번호 확인
        if (!$phone || !$verification_code) {
            echo json_encode(['success' => false, 'message' => '휴대폰 번호와 인증번호를 입력해주세요.']);
            exit;
        }
        
        // 인증번호 확인
        $stmt = $db->prepare("
            SELECT * FROM phone_verifications 
            WHERE phone = ? AND verification_code = ? 
            AND expires_at > NOW() AND is_verified = 0
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$phone, $verification_code]);
        $verification = $stmt->fetch();
        
        if (!$verification) {
            echo json_encode([
                'success' => false,
                'message' => '인증번호가 일치하지 않거나 만료되었습니다.'
            ]);
            exit;
        }
        
        // 인증 완료 처리
        $stmt = $db->prepare("
            UPDATE phone_verifications 
            SET is_verified = 1, verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$verification['id']]);
        
        // 세션에 인증 정보 저장
        $_SESSION['phone_verified'] = $phone;
        $_SESSION['phone_verified_at'] = time();
        
        echo json_encode([
            'success' => true,
            'message' => '휴대폰 인증이 완료되었습니다.',
            'phone' => $phone,
            'verified' => true
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    }
    
} catch (Exception $e) {
    error_log("Phone verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '인증 처리 중 오류가 발생했습니다.']);
}

/**
 * SMS 인증번호 발송 (가상 구현)
 */
function sendVerificationSMS($phone, $code) {
    try {
        $db = getDB();
        
        // SMS 메시지 내용
        $message = "[뷰티예약] 인증번호는 [{$code}]입니다. 3분 내에 입력해주세요.";
        
        // 실제 SMS API 연동 부분 (예시)
        /*
        // 네이버 클라우드 플랫폼 SENS 예시
        $sens_result = sendSENS([
            'to' => $phone,
            'content' => $message,
            'type' => 'SMS'
        ]);
        
        // 알리고 API 예시
        $aligo_result = sendAligo([
            'receiver' => $phone,
            'msg' => $message
        ]);
        
        // 쿨SMS API 예시
        $coolsms_result = sendCoolSMS([
            'to' => $phone,
            'text' => $message
        ]);
        */
        
        // 가상 발송 성공 (실제로는 위의 API 결과 확인)
        $success = true;
        
        // SMS 발송 로그 저장
        $stmt = $db->prepare("
            INSERT INTO sms_logs (
                phone, message, sms_type, status, 
                sent_at, created_at
            ) VALUES (?, ?, 'VERIFICATION', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $phone,
            $message,
            $success ? 'SUCCESS' : 'FAILED'
        ]);
        
        return $success;
        
    } catch (Exception $e) {
        error_log("SMS send error: " . $e->getMessage());
        return false;
    }
}

/**
 * 네이버 클라우드 플랫폼 SENS SMS 발송
 */
function sendSENS($data) {
    // SENS 설정
    $serviceId = 'YOUR_SERVICE_ID';
    $accessKey = 'YOUR_ACCESS_KEY';
    $secretKey = 'YOUR_SECRET_KEY';
    $from = 'YOUR_FROM_NUMBER';
    
    $url = "https://sens.apigw.ntruss.com/sms/v2/services/{$serviceId}/messages";
    $timestamp = time() * 1000;
    $method = 'POST';
    
    // 서명 생성
    $message = $method . ' ' . "/sms/v2/services/{$serviceId}/messages" . "\n" . $timestamp . "\n" . $accessKey;
    $signature = base64_encode(hash_hmac('sha256', $message, $secretKey, true));
    
    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'x-ncp-apigw-timestamp: ' . $timestamp,
        'x-ncp-iam-access-key: ' . $accessKey,
        'x-ncp-apigw-signature-v2: ' . $signature
    ];
    
    $body = json_encode([
        'type' => 'SMS',
        'from' => $from,
        'content' => $data['content'],
        'messages' => [
            ['to' => $data['to']]
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 202;
}

/**
 * 알리고 SMS 발송
 */
function sendAligo($data) {
    $user_id = 'YOUR_USER_ID';
    $api_key = 'YOUR_API_KEY';
    $sender = 'YOUR_SENDER_NUMBER';
    
    $url = 'https://apis.aligo.in/send/';
    
    $post_data = [
        'key' => $api_key,
        'user_id' => $user_id,
        'sender' => $sender,
        'receiver' => $data['receiver'],
        'msg' => $data['msg'],
        'msg_type' => 'SMS'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result && $result['result_code'] == 1;
}

/**
 * 쿨SMS 발송
 */
function sendCoolSMS($data) {
    $api_key = 'YOUR_API_KEY';
    $api_secret = 'YOUR_API_SECRET';
    $from = 'YOUR_FROM_NUMBER';
    
    $url = 'https://api.coolsms.co.kr/messages/v4/send';
    $timestamp = time();
    $salt = uniqid();
    
    // 서명 생성
    $signature = hash_hmac('sha256', $timestamp . $salt, $api_secret);
    
    $headers = [
        'Authorization: HMAC-SHA256 apikey=' . $api_key . ', date=' . $timestamp . ', salt=' . $salt . ', signature=' . $signature,
        'Content-Type: application/json'
    ];
    
    $body = json_encode([
        'message' => [
            'to' => $data['to'],
            'from' => $from,
            'text' => $data['text']
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}
?> 