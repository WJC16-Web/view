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
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
        throw new Exception('고객 로그인이 필요합니다.');
    }
    
    $customer_id = $_SESSION['user_id'];
    $business_id = intval($_POST['business_id'] ?? 0);
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $service_id = intval($_POST['service_id'] ?? 0);
    $desired_date = $_POST['desired_date'] ?? '';
    $desired_time = $_POST['desired_time'] ?? '';
    
    // 유효성 검사
    if (!$business_id || !$service_id || !$desired_date || !$desired_time) {
        throw new Exception('필수 정보가 누락되었습니다.');
    }
    
    // 날짜 형식 검사
    if (!DateTime::createFromFormat('Y-m-d', $desired_date)) {
        throw new Exception('잘못된 날짜 형식입니다.');
    }
    
    if (!DateTime::createFromFormat('H:i', $desired_time)) {
        throw new Exception('잘못된 시간 형식입니다.');
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    // 업체 및 서비스 존재 확인
    $stmt = $db->prepare("
        SELECT bs.*, b.name as business_name
        FROM business_services bs
        JOIN businesses b ON bs.business_id = b.id
        WHERE bs.id = ? AND bs.business_id = ? AND bs.is_active = 1 AND b.is_active = 1 AND b.is_approved = 1
    ");
    $stmt->execute([$service_id, $business_id]);
    $service = $stmt->fetch();
    
    if (!$service) {
        throw new Exception('존재하지 않는 업체 또는 서비스입니다.');
    }
    
    // 선생님 확인 (선택사항)
    if ($teacher_id > 0) {
        $stmt = $db->prepare("
            SELECT t.*, u.name
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ? AND t.business_id = ? AND t.is_active = 1 AND t.is_approved = 1
        ");
        $stmt->execute([$teacher_id, $business_id]);
        $teacher = $stmt->fetch();
        
        if (!$teacher) {
            throw new Exception('존재하지 않는 선생님입니다.');
        }
    }
    
    // 이미 대기열에 등록되어 있는지 확인
    $stmt = $db->prepare("
        SELECT id FROM reservation_waitlist 
        WHERE customer_id = ? AND business_id = ? AND desired_date = ? AND desired_time = ? AND status = 'waiting'
    ");
    $stmt->execute([$customer_id, $business_id, $desired_date, $desired_time]);
    
    if ($stmt->fetch()) {
        throw new Exception('이미 해당 시간대 대기열에 등록되어 있습니다.');
    }
    
    // 현재 시간이 원하는 시간보다 이후인지 확인
    $desired_datetime = $desired_date . ' ' . $desired_time;
    if (strtotime($desired_datetime) <= time()) {
        throw new Exception('과거 시간에는 대기열 등록이 불가능합니다.');
    }
    
    // VIP 고객 우선순위 설정 (예: 총 이용 횟수 10회 이상)
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed_count 
        FROM reservations 
        WHERE customer_id = ? AND status = 'completed'
    ");
    $stmt->execute([$customer_id]);
    $completed_count = $stmt->fetch()['completed_count'];
    
    $priority = 0;
    if ($completed_count >= 10) {
        $priority = 1; // VIP 고객
    }
    
    // 대기열 등록
    $stmt = $db->prepare("
        INSERT INTO reservation_waitlist 
        (customer_id, business_id, teacher_id, service_id, desired_date, desired_time, priority, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    
    $teacher_id_value = $teacher_id > 0 ? $teacher_id : null;
    $stmt->execute([
        $customer_id, $business_id, $teacher_id_value, $service_id, 
        $desired_date, $desired_time, $priority
    ]);
    
    $waitlist_id = $db->lastInsertId();
    
    // 현재 대기 순서 확인
    $stmt = $db->prepare("
        SELECT COUNT(*) + 1 as wait_position 
        FROM reservation_waitlist 
        WHERE business_id = ? AND desired_date = ? AND desired_time = ? 
        AND status = 'waiting' AND priority >= ? AND id < ?
    ");
    $stmt->execute([$business_id, $desired_date, $desired_time, $priority, $waitlist_id]);
    $wait_position = $stmt->fetch()['wait_position'];
    
    // 알림 추가
    addNotification(
        $customer_id,
        'waitlist_joined',
        '대기열 등록 완료',
        "{$service['business_name']}의 {$desired_date} {$desired_time} 시간대 대기열에 등록되었습니다. (대기순서: {$wait_position}번째)"
    );
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '대기열에 성공적으로 등록되었습니다.',
        'data' => [
            'waitlist_id' => $waitlist_id,
            'wait_position' => $wait_position,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'business_name' => $service['business_name'],
            'service_name' => $service['service_name'],
            'desired_datetime' => $desired_datetime,
            'is_vip' => $priority > 0
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