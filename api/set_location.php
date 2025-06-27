<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST 방식만 허용됩니다.');
    }
    
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    
    // 위도, 경도 유효성 검사
    if ($latitude < -90 || $latitude > 90) {
        throw new Exception('잘못된 위도 값입니다.');
    }
    
    if ($longitude < -180 || $longitude > 180) {
        throw new Exception('잘못된 경도 값입니다.');
    }
    
    // 세션에 위치 정보 저장
    $_SESSION['user_latitude'] = $latitude;
    $_SESSION['user_longitude'] = $longitude;
    $_SESSION['location_set_time'] = time();
    
    // 한국 내 위치인지 대략적으로 확인
    $is_korea = (
        $latitude >= 33.0 && $latitude <= 38.6 &&
        $longitude >= 125.0 && $longitude <= 131.9
    );
    
    $response = [
        'success' => true,
        'message' => '위치가 설정되었습니다.',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'is_korea' => $is_korea
    ];
    
    if (!$is_korea) {
        $response['warning'] = '현재 위치가 한국이 아닌 것 같습니다. 서비스 이용에 제한이 있을 수 있습니다.';
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 