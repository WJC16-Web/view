<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => '로그?�이 ?�요?�니??']);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$reservation_id = $input['reservation_id'] ?? 0;

if (!$reservation_id) {
    echo json_encode(['success' => false, 'message' => '?�약 ID가 ?�요?�니??']);
    exit;
}

try {
    $db->beginTransaction();
    
    // ?�약 ?�보 ?�인
    $stmt = $db->prepare("
        SELECT r.*, b.name as business_name 
        FROM reservations r
        JOIN businesses b ON r.business_id = b.id
        WHERE r.id = ? AND r.customer_id = ?
    ");
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        throw new Exception('?�약??찾을 ???�습?�다.');
    }
    
    // 취소 가???�태 ?�인
    if ($reservation['status'] !== 'pending') {
        throw new Exception('?�정???�약?� 취소?????�습?�다. ?�체??직접 문의?�주?�요.');
    }
    
    // ?�간 ?�인 (1?�간 ?�까지�?취소 가??
    $reservation_datetime = $reservation['reservation_date'] . ' ' . $reservation['start_time'];
    $reservation_timestamp = strtotime($reservation_datetime);
    $current_timestamp = time();
    $time_diff = $reservation_timestamp - $current_timestamp;
    
    if ($time_diff < 3600) { // 1?�간 = 3600�?
        throw new Exception('?�약 ?�작 1?�간 ?�까지�?취소?????�습?�다.');
    }
    
    // ?�약 ?�태 ?�데?�트
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'cancelled', 
            cancelled_at = NOW(),
            cancellation_reason = '고객 ?�청'
        WHERE id = ?
    ");
    $stmt->execute([$reservation_id]);
    
    // ?�약 ?�태 로그 추�?
    $stmt = $db->prepare("
        INSERT INTO reservation_status_logs 
        (reservation_id, old_status, new_status, changed_by, reason, changed_at)
        VALUES (?, ?, 'cancelled', ?, '고객 ?�청?�로 취소', NOW())
    ");
    $stmt->execute([$reservation_id, $reservation['status'], $user_id]);
    
    // ?�결?�한 경우 ?�불 처리 (가??
    if ($reservation['payment_status'] === 'paid') {
        // ?�불 ?�책???�른 ?�불??계산
        $refund_rate = 1.0; // 24?�간 ?�이므�?100% ?�불
        if ($time_diff < 86400) { // 24?�간
            if ($time_diff < 43200) { // 12?�간
                if ($time_diff < 21600) { // 6?�간
                    $refund_rate = 0.5; // 50%
                } else {
                    $refund_rate = 0.7; // 70%
                }
            } else {
                $refund_rate = 0.9; // 90%
            }
        }
        
        $refund_amount = $reservation['total_amount'] * $refund_rate;
        
        // ?�불 기록 추�?
        $stmt = $db->prepare("
            INSERT INTO refunds 
            (reservation_id, original_amount, refund_amount, refund_rate, status, requested_at)
            VALUES (?, ?, ?, ?, 'processing', NOW())
        ");
        $stmt->execute([
            $reservation_id, 
            $reservation['total_amount'], 
            $refund_amount, 
            $refund_rate
        ]);
    }
    
    // ?�림 발송 (?�체?�게)
    $stmt = $db->prepare("
        INSERT INTO notifications 
        (user_id, notification_type, title, message, created_at)
        SELECT owner_id, 'reservation_cancelled', 
               '?�약??취소?�었?�니??,
               CONCAT('고객???�약??취소?�습?�다. ?�약번호: ', ?),
               NOW()
        FROM businesses 
        WHERE id = ?
    ");
    $stmt->execute([$reservation_id, $reservation['business_id']]);
    
    $db->commit();
    
    $response_message = '?�약???�공?�으�?취소?�었?�니??';
    if (isset($refund_amount)) {
        $response_message .= ' ?�불 처리가 진행?�니?? (?�불 ?�정 금액: ' . number_format($refund_amount) . '??';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $response_message
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 
