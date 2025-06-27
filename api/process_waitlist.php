<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/functions.php';

// CRON 작업이나 관리자만 접근 가능
$allowed_ips = ['127.0.0.1', '::1']; // 로컬호스트만 허용
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '접근이 거부되었습니다.']);
    exit;
}

try {
    $db = getDB();
    $processed = 0;
    $notifications_sent = 0;
    
    // 1. 만료된 대기열 정리
    $stmt = $db->prepare("
        DELETE FROM reservation_waitlist 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND status = 'waiting'
    ");
    $stmt->execute();
    $expired_count = $stmt->rowCount();
    
    // 2. 만료된 우선 예약권 정리
    $stmt = $db->prepare("
        UPDATE reservation_waitlist 
        SET status = 'expired', updated_at = NOW()
        WHERE status = 'notified' 
        AND notified_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt->execute();
    $expired_priority_count = $stmt->rowCount();
    
    // 3. 새로 생긴 예약 슬롯에 대해 대기열 처리
    $available_slots = findAvailableSlots();
    
    foreach ($available_slots as $slot) {
        // 해당 시간대 대기 중인 고객 조회 (VIP 우선순위)
        $waitlist = getWaitlistForSlot($slot);
        
        if (!empty($waitlist)) {
            $customer = $waitlist[0]; // 첫 번째 우선순위 고객
            
            // 대기열 상태 업데이트
            $stmt = $db->prepare("
                UPDATE reservation_waitlist 
                SET status = 'notified', notified_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$customer['id']]);
            
            // 고객에게 알림 발송
            $notification_sent = sendWaitlistNotification($customer, $slot);
            
            if ($notification_sent) {
                $notifications_sent++;
            }
            
            $processed++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => '대기열 처리가 완료되었습니다.',
        'stats' => [
            'expired_waitlist' => $expired_count,
            'expired_priority' => $expired_priority_count,
            'processed_slots' => $processed,
            'notifications_sent' => $notifications_sent
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Waitlist processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '대기열 처리 중 오류가 발생했습니다.']);
}

/**
 * 예약 가능한 슬롯 찾기
 */
function findAvailableSlots() {
    $db = getDB();
    $slots = [];
    
    // 최근 1시간 내에 취소된 예약 조회
    $stmt = $db->prepare("
        SELECT DISTINCT 
            r.business_id,
            r.teacher_id,
            r.service_id,
            r.reservation_date,
            r.start_time,
            r.end_time
        FROM reservations r
        WHERE r.status = 'CANCELLED'
        AND r.updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND r.reservation_date >= CURDATE()
        AND CONCAT(r.reservation_date, ' ', r.start_time) > NOW()
    ");
    $stmt->execute();
    $cancelled_reservations = $stmt->fetchAll();
    
    foreach ($cancelled_reservations as $reservation) {
        // 해당 시간대에 다른 예약이 있는지 확인
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM reservations 
            WHERE business_id = ? AND teacher_id = ?
            AND reservation_date = ? AND start_time = ?
            AND status IN ('PENDING', 'CONFIRMED')
        ");
        $stmt->execute([
            $reservation['business_id'],
            $reservation['teacher_id'],
            $reservation['reservation_date'],
            $reservation['start_time']
        ]);
        
        $conflict_count = $stmt->fetch()['count'];
        
        if ($conflict_count == 0) {
            $slots[] = $reservation;
        }
    }
    
    return $slots;
}

/**
 * 특정 슬롯에 대한 대기열 조회 (VIP 우선순위)
 */
function getWaitlistForSlot($slot) {
    $db = getDB();
    
    // VIP 여부 확인 서브쿼리 (이용 횟수 10회 이상)
    $vip_subquery = "
        (SELECT COUNT(*) FROM reservations r2 
         WHERE r2.customer_id = rw.customer_id 
         AND r2.status = 'COMPLETED') >= 10 as is_vip
    ";
    
    $stmt = $db->prepare("
        SELECT rw.*, 
               u.name as customer_name,
               u.phone as customer_phone,
               u.email as customer_email,
               $vip_subquery
        FROM reservation_waitlist rw
        JOIN users u ON rw.customer_id = u.id
        WHERE rw.status = 'waiting'
        AND rw.business_id = ?
        AND (rw.teacher_id IS NULL OR rw.teacher_id = ?)
        AND (rw.service_id IS NULL OR rw.service_id = ?)
        AND rw.preferred_date = ?
        AND rw.preferred_time = ?
        ORDER BY is_vip DESC, rw.created_at ASC
        LIMIT 1
    ");
    
    $stmt->execute([
        $slot['business_id'],
        $slot['teacher_id'],
        $slot['service_id'],
        $slot['reservation_date'],
        $slot['start_time']
    ]);
    
    return $stmt->fetchAll();
}

/**
 * 대기열 알림 발송
 */
function sendWaitlistNotification($customer, $slot) {
    try {
        $db = getDB();
        
        // 업체 정보 조회
        $stmt = $db->prepare("
            SELECT b.name as business_name, b.phone as business_phone,
                   t.name as teacher_name,
                   bs.service_name, bs.price
            FROM businesses b
            LEFT JOIN teachers t ON b.id = t.business_id AND t.id = ?
            LEFT JOIN business_services bs ON b.id = bs.business_id AND bs.id = ?
            WHERE b.id = ?
        ");
        $stmt->execute([
            $slot['teacher_id'],
            $slot['service_id'],
            $slot['business_id']
        ]);
        $business_info = $stmt->fetch();
        
        // 예약 가능 시간 포맷팅
        $date_formatted = date('Y년 m월 d일', strtotime($slot['reservation_date']));
        $time_formatted = date('H:i', strtotime($slot['start_time']));
        
        // SMS 메시지 작성
        $message = "[뷰티예약] 🎉 원하시던 시간에 자리가 났습니다!\n\n";
        $message .= "📅 {$date_formatted} {$time_formatted}\n";
        $message .= "🏪 {$business_info['business_name']}\n";
        
        if ($business_info['teacher_name']) {
            $message .= "👩‍💼 {$business_info['teacher_name']} 선생님\n";
        }
        
        if ($business_info['service_name']) {
            $message .= "💅 {$business_info['service_name']}\n";
        }
        
        $message .= "\n⏰ 10분 내에 예약하지 않으면 다음 분에게 넘어갑니다.\n";
        $message .= "지금 바로 예약하세요!";
        
        // SMS 발송
        $sms_sent = sendSMSNotification($customer['customer_phone'], $message, 'WAITLIST_NOTIFICATION');
        
        // 푸시 알림 발송
        $push_data = [
            'title' => '🎉 예약 가능 알림',
            'body' => "{$date_formatted} {$time_formatted}에 자리가 났습니다!",
            'data' => [
                'type' => 'waitlist_notification',
                'business_id' => $slot['business_id'],
                'teacher_id' => $slot['teacher_id'],
                'service_id' => $slot['service_id'],
                'date' => $slot['reservation_date'],
                'time' => $slot['start_time']
            ]
        ];
        
        $push_sent = sendPushNotification($customer['customer_id'], $push_data);
        
        // 알림 테이블에 저장
        addNotification(
            $customer['customer_id'],
            'waitlist_available',
            '예약 가능 알림',
            $message
        );
        
        return $sms_sent || $push_sent;
        
    } catch (Exception $e) {
        error_log("Waitlist notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * 대기열 통계 조회
 */
function getWaitlistStats() {
    $db = getDB();
    
    $stats = [];
    
    // 전체 대기 중인 고객 수
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservation_waitlist WHERE status = 'waiting'");
    $stmt->execute();
    $stats['total_waiting'] = $stmt->fetchColumn();
    
    // VIP 대기 고객 수
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM reservation_waitlist rw
        WHERE rw.status = 'waiting'
        AND (SELECT COUNT(*) FROM reservations r 
             WHERE r.customer_id = rw.customer_id AND r.status = 'COMPLETED') >= 10
    ");
    $stmt->execute();
    $stats['vip_waiting'] = $stmt->fetchColumn();
    
    // 오늘 알림 발송된 건수
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM reservation_waitlist 
        WHERE status = 'notified' 
        AND DATE(notified_at) = CURDATE()
    ");
    $stmt->execute();
    $stats['today_notified'] = $stmt->fetchColumn();
    
    // 업체별 대기열 현황
    $stmt = $db->prepare("
        SELECT b.name as business_name, COUNT(*) as waiting_count
        FROM reservation_waitlist rw
        JOIN businesses b ON rw.business_id = b.id
        WHERE rw.status = 'waiting'
        GROUP BY rw.business_id, b.name
        ORDER BY waiting_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['business_stats'] = $stmt->fetchAll();
    
    return $stats;
}

/**
 * 대기열 우선순위 재계산
 */
function recalculateWaitlistPriority() {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // 모든 대기 중인 고객의 VIP 상태 업데이트
        $stmt = $db->prepare("
            UPDATE reservation_waitlist rw
            SET vip_priority = (
                SELECT CASE 
                    WHEN COUNT(r.id) >= 10 THEN 1 
                    ELSE 0 
                END
                FROM reservations r 
                WHERE r.customer_id = rw.customer_id 
                AND r.status = 'COMPLETED'
            )
            WHERE rw.status = 'waiting'
        ");
        $stmt->execute();
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Priority recalculation error: " . $e->getMessage());
        return false;
    }
}

/**
 * 만료된 대기열 정리
 */
function cleanupExpiredWaitlist() {
    $db = getDB();
    
    // 7일 이상 된 대기열 삭제
    $stmt = $db->prepare("
        DELETE FROM reservation_waitlist 
        WHERE status = 'waiting' 
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();
    
    // 만료된 우선 예약권 상태 변경
    $stmt = $db->prepare("
        UPDATE reservation_waitlist 
        SET status = 'expired', updated_at = NOW()
        WHERE status = 'notified' 
        AND notified_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt->execute();
    $expired_count = $stmt->rowCount();
    
    return [
        'deleted' => $deleted_count,
        'expired' => $expired_count
    ];
}
?> 