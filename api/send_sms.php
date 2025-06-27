<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../includes/functions.php';

// SMS 발송 함수 (가상 구현)
function sendSMS($user_id, $phone_number, $message, $sms_type = 'general') {
    try {
        $db = getDB();
        
        // SMS 로그 저장
        $stmt = $db->prepare("
            INSERT INTO sms_logs (user_id, phone_number, message, sms_type, status, sent_at)
            VALUES (?, ?, ?, ?, 'sent', NOW())
        ");
        $stmt->execute([$user_id, $phone_number, $message, $sms_type]);
        
        // 실제 SMS API 연동 부분 (가상)
        // 예: SENS, 알리고, 카카오 알림톡 등
        /*
        $api_response = callSMSAPI([
            'phone' => $phone_number,
            'message' => $message,
            'sender' => '02-1234-5678' // 발신번호
        ]);
        
        if (!$api_response['success']) {
            throw new Exception('SMS 발송 실패: ' . $api_response['error']);
        }
        */
        
        return [
            'success' => true,
            'message' => 'SMS가 성공적으로 발송되었습니다.',
            'sms_id' => $db->lastInsertId()
        ];
        
    } catch (Exception $e) {
        // 실패 로그 업데이트
        if (isset($db) && isset($stmt)) {
            $stmt = $db->prepare("
                UPDATE sms_logs 
                SET status = 'failed', error_message = ? 
                WHERE id = LAST_INSERT_ID()
            ");
            $stmt->execute([$e->getMessage()]);
        }
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// 예약 관련 SMS 템플릿
function getReservationSMSTemplate($type, $data) {
    $templates = [
        'reservation_confirmed' => "[뷰티북] {$data['business_name']}에서 {$data['date']} {$data['time']} 예약이 확정되었습니다. 문의: {$data['phone']}",
        
        'reservation_rejected' => "[뷰티북] {$data['business_name']}에서 {$data['date']} {$data['time']} 예약이 거절되었습니다. 사유: {$data['reason']}",
        
        'reservation_cancelled' => "[뷰티북] {$data['business_name']}에서 {$data['date']} {$data['time']} 예약이 취소되었습니다. 사유: {$data['reason']}",
        
        'reservation_reminder' => "[뷰티북] 내일 {$data['time']} {$data['business_name']} 예약을 잊지 마세요! 주소: {$data['address']}",
        
        'reservation_completed' => "[뷰티북] {$data['business_name']} 이용이 완료되었습니다. 후기를 남겨주시면 적립금을 드려요!",
        
        'waitlist_available' => "[뷰티북] {$data['business_name']} {$data['date']} {$data['time']} 자리가 났습니다! 10분 내 예약하세요: {$data['link']}",
        
        'verification_code' => "[뷰티북] 인증번호: {$data['code']} (3분 내 입력)",
        
        'welcome' => "[뷰티북] 가입을 환영합니다! 첫 예약 20% 할인 쿠폰을 받으세요."
    ];
    
    return $templates[$type] ?? '';
}

// 실제 API 호출부분
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST 방식만 허용됩니다.');
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $phone_number = $_POST['phone_number'] ?? '';
    $sms_type = $_POST['sms_type'] ?? 'general';
    $template_data = json_decode($_POST['template_data'] ?? '{}', true);
    
    // 유효성 검사
    if (!$user_id || !$phone_number) {
        throw new Exception('필수 정보가 누락되었습니다.');
    }
    
    // 휴대폰 번호 형식 검사
    $phone_cleaned = preg_replace('/[^0-9]/', '', $phone_number);
    if (!preg_match('/^01[0-9]{8,9}$/', $phone_cleaned)) {
        throw new Exception('올바른 휴대폰 번호 형식이 아닙니다.');
    }
    
    // 메시지 생성
    if (isset($_POST['message'])) {
        $message = $_POST['message'];
    } else {
        $message = getReservationSMSTemplate($sms_type, $template_data);
        if (empty($message)) {
            throw new Exception('유효하지 않은 SMS 타입입니다.');
        }
    }
    
    // SMS 발송
    $result = sendSMS($user_id, $phone_cleaned, $message, $sms_type);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// 실제 SMS API 호출 함수 (가상)
function callSMSAPI($data) {
    // 여기에 실제 SMS 서비스 API 호출 코드 구현
    // 예: SENS, 알리고, 쿨SMS 등
    
    /*
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.coolsms.co.kr/sms/2/send',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'api_key' => 'YOUR_API_KEY',
            'api_secret' => 'YOUR_API_SECRET',
            'to' => $data['phone'],
            'from' => $data['sender'],
            'text' => $data['message']
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code === 200) {
        return ['success' => true, 'response' => json_decode($response, true)];
    } else {
        return ['success' => false, 'error' => 'API 호출 실패'];
    }
    */
    
    // 가상 응답 (테스트용)
    return [
        'success' => true,
        'message_id' => 'SMS_' . uniqid(),
        'cost' => 20 // 20원
    ];
}
?> 