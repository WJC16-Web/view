<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST 방식만 허용됩니다.');
    }
    
    // 로그인 확인
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('로그인이 필요합니다.');
    }
    
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    $change_type = $_POST['change_type'] ?? ''; // datetime, service, teacher
    $change_reason = $_POST['change_reason'] ?? '';
    
    // 유효성 검사
    if (!$reservation_id || !$change_type) {
        throw new Exception('필수 정보가 누락되었습니다.');
    }
    
    $allowed_change_types = ['datetime', 'service', 'teacher'];
    if (!in_array($change_type, $allowed_change_types)) {
        throw new Exception('유효하지 않은 변경 타입입니다.');
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    // 예약 정보 확인 및 권한 체크
    $stmt = $db->prepare("
        SELECT r.*, b.name as business_name, b.owner_id,
               c.name as customer_name, c.phone as customer_phone,
               t.user_id as teacher_user_id
        FROM reservations r
        JOIN businesses b ON r.business_id = b.id
        JOIN users c ON r.customer_id = c.id
        JOIN teachers t ON r.teacher_id = t.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        throw new Exception('존재하지 않는 예약입니다.');
    }
    
    // 권한 확인
    $can_request = false;
    if ($user_type === 'customer' && $reservation['customer_id'] == $user_id) {
        $can_request = true;
    } elseif ($user_type === 'business_owner' && $reservation['owner_id'] == $user_id) {
        $can_request = true;
    } elseif ($user_type === 'teacher' && $reservation['teacher_user_id'] == $user_id) {
        $can_request = true;
    }
    
    if (!$can_request) {
        throw new Exception('예약 변경 권한이 없습니다.');
    }
    
    // 예약 상태 확인
    if (!in_array($reservation['status'], ['confirmed', 'pending'])) {
        throw new Exception('변경 가능한 예약 상태가 아닙니다.');
    }
    
    // 예약 시간 확인 (1시간 전까지만 변경 가능)
    $reservation_datetime = $reservation['reservation_date'] . ' ' . $reservation['start_time'];
    $time_diff = strtotime($reservation_datetime) - time();
    if ($time_diff < 3600) { // 1시간
        throw new Exception('예약 1시간 전까지만 변경 요청이 가능합니다.');
    }
    
    // 기존 값 저장
    $original_value = [];
    $new_value = [];
    
    switch ($change_type) {
        case 'datetime':
            $new_date = $_POST['new_date'] ?? '';
            $new_time = $_POST['new_time'] ?? '';
            
            if (empty($new_date) || empty($new_time)) {
                throw new Exception('새로운 날짜와 시간을 입력해주세요.');
            }
            
            // 날짜/시간 형식 검사
            if (!DateTime::createFromFormat('Y-m-d', $new_date)) {
                throw new Exception('잘못된 날짜 형식입니다.');
            }
            
            if (!DateTime::createFromFormat('H:i', $new_time)) {
                throw new Exception('잘못된 시간 형식입니다.');
            }
            
            // 미래 시간인지 확인
            $new_datetime = $new_date . ' ' . $new_time;
            if (strtotime($new_datetime) <= time()) {
                throw new Exception('과거 시간으로는 변경할 수 없습니다.');
            }
            
            $original_value = [
                'date' => $reservation['reservation_date'],
                'time' => $reservation['start_time']
            ];
            $new_value = [
                'date' => $new_date,
                'time' => $new_time
            ];
            break;
            
        case 'service':
            $new_service_id = intval($_POST['new_service_id'] ?? 0);
            
            if (!$new_service_id) {
                throw new Exception('새로운 서비스를 선택해주세요.');
            }
            
            // 서비스 존재 확인
            $stmt = $db->prepare("
                SELECT * FROM business_services 
                WHERE id = ? AND business_id = ? AND is_active = 1
            ");
            $stmt->execute([$new_service_id, $reservation['business_id']]);
            $new_service = $stmt->fetch();
            
            if (!$new_service) {
                throw new Exception('유효하지 않은 서비스입니다.');
            }
            
            $original_value = ['service_id' => $reservation['service_id']];
            $new_value = ['service_id' => $new_service_id];
            break;
            
        case 'teacher':
            $new_teacher_id = intval($_POST['new_teacher_id'] ?? 0);
            
            if (!$new_teacher_id) {
                throw new Exception('새로운 선생님을 선택해주세요.');
            }
            
            // 선생님 존재 확인
            $stmt = $db->prepare("
                SELECT * FROM teachers 
                WHERE id = ? AND business_id = ? AND is_active = 1 AND is_approved = 1
            ");
            $stmt->execute([$new_teacher_id, $reservation['business_id']]);
            $new_teacher = $stmt->fetch();
            
            if (!$new_teacher) {
                throw new Exception('유효하지 않은 선생님입니다.');
            }
            
            $original_value = ['teacher_id' => $reservation['teacher_id']];
            $new_value = ['teacher_id' => $new_teacher_id];
            break;
    }
    
    // 이미 동일한 변경 요청이 있는지 확인
    $stmt = $db->prepare("
        SELECT id FROM reservation_changes 
        WHERE reservation_id = ? AND change_type = ? AND status = 'pending'
    ");
    $stmt->execute([$reservation_id, $change_type]);
    
    if ($stmt->fetch()) {
        throw new Exception('이미 동일한 변경 요청이 진행 중입니다.');
    }
    
    // 변경 요청 저장
    $stmt = $db->prepare("
        INSERT INTO reservation_changes 
        (reservation_id, change_type, original_value, new_value, change_reason, requested_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $reservation_id,
        $change_type,
        json_encode($original_value, JSON_UNESCAPED_UNICODE),
        json_encode($new_value, JSON_UNESCAPED_UNICODE),
        $change_reason,
        $user_id
    ]);
    
    $change_id = $db->lastInsertId();
    
    // 관련자들에게 알림 발송
    $change_type_names = [
        'datetime' => '날짜/시간',
        'service' => '서비스',
        'teacher' => '담당자'
    ];
    
    $change_type_name = $change_type_names[$change_type];
    
    if ($user_type === 'customer') {
        // 고객이 요청한 경우 → 업체에 알림
        addNotification(
            $reservation['owner_id'],
            'change_request',
            '예약 변경 요청',
            "{$reservation['customer_name']} 고객이 {$change_type_name} 변경을 요청했습니다."
        );
    } else {
        // 업체/선생님이 요청한 경우 → 고객에게 알림
        addNotification(
            $reservation['customer_id'],
            'change_request',
            '예약 변경 요청',
            "{$reservation['business_name']}에서 {$change_type_name} 변경을 요청했습니다."
        );
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '변경 요청이 성공적으로 접수되었습니다.',
        'data' => [
            'change_id' => $change_id,
            'change_type' => $change_type,
            'change_type_name' => $change_type_name,
            'original_value' => $original_value,
            'new_value' => $new_value,
            'status' => 'pending'
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 