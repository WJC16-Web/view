<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';

if (!isLoggedIn() || getUserRole() !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$schedule_id = $input['schedule_id'] ?? null;

if (!$schedule_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Schedule ID is required']);
    exit;
}

// schedule_id에서 manual_ 접두사 제거
if (strpos($schedule_id, 'manual_') === 0) {
    $schedule_id = substr($schedule_id, 7);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid schedule ID format']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $teacher_id = getUserId();
    
    // 해당 스케줄이 현재 선생님의 것인지 확인하고 삭제
    $delete_query = "DELETE FROM teacher_schedules 
                     WHERE id = :schedule_id AND teacher_id = :teacher_id AND type = 'manual'";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':schedule_id', $schedule_id);
    $delete_stmt->bindParam(':teacher_id', $teacher_id);
    $delete_stmt->execute();
    
    if ($delete_stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Schedule not found or not authorized']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>