<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$teacher_id = $_GET['teacher_id'] ?? null;

if (!$teacher_id || $teacher_id != getUserId()) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid teacher ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $events = [];
    
    // 수동 등록된 스케줄 가져오기
    $manual_query = "SELECT id, title, description, start_datetime, end_datetime, 'manual' as type, 'manual' as source
                     FROM teacher_schedules 
                     WHERE teacher_id = :teacher_id AND status = 'active'";
    $manual_stmt = $db->prepare($manual_query);
    $manual_stmt->bindParam(':teacher_id', $teacher_id);
    $manual_stmt->execute();
    
    while ($row = $manual_stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id' => 'manual_' . $row['id'],
            'title' => $row['title'],
            'start' => $row['start_datetime'],
            'end' => $row['end_datetime'],
            'backgroundColor' => '#6c757d',
            'borderColor' => '#6c757d',
            'extendedProps' => [
                'type' => 'manual',
                'description' => $row['description'],
                'source' => 'manual'
            ]
        ];
    }
    
    // 예약된 스케줄 가져오기
    $reservation_query = "SELECT r.id, ts.service_name, r.reservation_date, r.start_time, r.end_time, 
                                 u.name as customer_name, r.status, ts.duration_minutes
                          FROM reservations r 
                          JOIN teacher_services ts ON r.service_id = ts.id 
                          JOIN users u ON r.customer_id = u.id
                          WHERE r.teacher_id = :teacher_id AND r.status IN ('confirmed', 'pending')";
    $reservation_stmt = $db->prepare($reservation_query);
    $reservation_stmt->bindParam(':teacher_id', $teacher_id);
    $reservation_stmt->execute();
    
    while ($row = $reservation_stmt->fetch(PDO::FETCH_ASSOC)) {
        $start_datetime = $row['reservation_date'] . ' ' . $row['start_time'];
        $end_datetime = $row['end_time'] ? 
                       ($row['reservation_date'] . ' ' . $row['end_time']) : 
                       date('Y-m-d H:i:s', strtotime($start_datetime . ' +' . $row['duration_minutes'] . ' minutes'));
        
        $color = $row['status'] === 'confirmed' ? '#28a745' : '#ffc107';
        
        $events[] = [
            'id' => 'reservation_' . $row['id'],
            'title' => $row['service_name'] . ' - ' . $row['customer_name'],
            'start' => $start_datetime,
            'end' => $end_datetime,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'type' => 'reservation',
                'customer_name' => $row['customer_name'],
                'service_name' => $row['service_name'],
                'status' => $row['status']
            ]
        ];
    }
    
    echo json_encode($events);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>