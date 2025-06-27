<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['teacher', 'business_owner'])) {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);

$reservation_id = $input['reservation_id'] ?? 0;
$new_status = $input['status'] ?? '';
$reason = $input['reason'] ?? '';

if (!$reservation_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => '필수 입력이 빠졌습니다.']);
    exit;
}

$allowed_statuses = ['confirmed', 'rejected', 'completed', 'cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => '효력이 없는 상태입니다.']);
    exit;
}

try {
    $db->beginTransaction();
    
    // 예약 조회 권한 인
    $stmt = $db->prepare("
        SELECT r.*, b.owner_id, t.user_id as teacher_user_id, 
               u.name as customer_name, u.phone as customer_phone,
               bs.service_name
        FROM reservations r
        JOIN businesses b ON r.business_id = b.id
        JOIN teachers t ON r.teacher_id = t.id
        JOIN users u ON r.customer_id = u.id
        JOIN business_services bs ON r.service_id = bs.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        throw new Exception('예약을 찾을 수 없습니다.');
    }
    
    // 권한 인
    $has_permission = false;
    if ($user_type === 'business_owner' && $reservation['owner_id'] == $user_id) {
        $has_permission = true;
    } elseif ($user_type === 'teacher' && $reservation['teacher_user_id'] == $user_id) {
        $has_permission = true;
    }
    
    if (!$has_permission) {
        throw new Exception('예약정보수정권한이 없습니다.');
    }
    
    // 상태 변경가 가능한지 인
    $current_status = $reservation['status'];
    $valid_transitions = [
        'pending' => ['confirmed', 'rejected'],
        'confirmed' => ['completed', 'cancelled'],
        'completed' => [], // 료 예약? 상태 변경불가
        'cancelled' => [],
        'rejected' => []
    ];
    
    if (!in_array($new_status, $valid_transitions[$current_status])) {
        throw new Exception("'{$current_status}' 상태에서 '{$new_status}' 상태로 변경할 수 없습니다.");
    }
    
    // 거절/취소 유 수
    if (in_array($new_status, ['rejected', 'cancelled']) && empty($reason)) {
        throw new Exception('거절 는 취소 유력야 해야 합니다.');
    }
    
    // 예약 상태 데트
    $update_fields = ['status = ?'];
    $update_params = [$new_status];
    
    if ($new_status === 'confirmed') {
        $update_fields[] = 'confirmed_at = NOW()';
    } elseif ($new_status === 'completed') {
        $update_fields[] = 'completed_at = NOW()';
    } elseif ($new_status === 'cancelled') {
        $update_fields[] = 'cancelled_at = NOW()';
        $update_fields[] = 'cancellation_reason = ?';
        $update_params[] = $reason;
    } elseif ($new_status === 'rejected') {
        $update_fields[] = 'rejected_at = NOW()';
        $update_fields[] = 'rejection_reason = ?';
        $update_params[] = $reason;
    }
    
    $update_params[] = $reservation_id;
    
    $sql = "UPDATE reservations SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($update_params);
    
    // 예약 상태 로그 추
    $stmt = $db->prepare("
        INSERT INTO reservation_status_logs 
        (reservation_id, old_status, new_status, changed_by, reason, changed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$reservation_id, $current_status, $new_status, $user_id, $reason]);
    
    // 고객게 림 발송
    $notification_messages = [
        'confirmed' => '예약정었습니다.',
        'rejected' => '예약거절었습니다. 유: ' . $reason,
        'completed' => '비용 료었습니다. 기부주세요',
        'cancelled' => '예약취소었습니다. 유: ' . $reason
    ];
    
    $stmt = $db->prepare("
        INSERT INTO notifications 
        (user_id, notification_type, title, message, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $reservation['customer_id'],
        'reservation_' . $new_status,
        '예약 상태 변',
        $notification_messages[$new_status]
    ]);
    
    // 료 립지
    if ($new_status === 'completed') {
        $point_rate = 0.02; // 2% 립
        $point_amount = floor($reservation['total_amount'] * $point_rate);
        
        if ($point_amount > 0) {
            $stmt = $db->prepare("
                INSERT INTO points 
                (customer_id, point_type, amount, description, reservation_id, created_at)
                VALUES (?, 'earned', ?, '비용 립용, ?, NOW())
            ");
            $stmt->execute([$reservation['customer_id'], $point_amount, $reservation_id]);
        }
    }
    
    // 거절/취소 불 처리
    if (in_array($new_status, ['rejected', 'cancelled']) && $reservation['payment_status'] === 'paid') {
        $refund_rate = 1.0; // 체 정로 한 취소100% 불
        $refund_amount = $reservation['total_amount'] * $refund_rate;
        
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
    
    $db->commit();
    
    $success_messages = [
        'confirmed' => '예약정었습니다.',
        'rejected' => '예약거절었습니다.',
        'completed' => '비용 료 처리었습니다.',
        'cancelled' => '예약취소었습니다.'
    ];
    
    echo json_encode([
        'success' => true,
        'message' => $success_messages[$new_status],
        'new_status' => $new_status
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
