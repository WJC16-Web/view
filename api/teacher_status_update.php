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
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
        throw new Exception('선생님 로그인이 필요합니다.');
    }
    
    $user_id = $_SESSION['user_id'];
    $status = $_POST['status'] ?? '';
    
    if (empty($status)) {
        throw new Exception('상태 정보가 누락되었습니다.');
    }
    
    $allowed_statuses = ['available', 'busy', 'break', 'offline'];
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('유효하지 않은 상태입니다.');
    }
    
    $db = getDB();
    
    // 선생님 정보 확인
    $stmt = $db->prepare("
        SELECT t.*, u.name 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.user_id = ? AND t.is_active = 1
    ");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        throw new Exception('선생님 정보를 찾을 수 없습니다.');
    }
    
    // 현재 시간이 근무시간인지 확인
    $current_time = date('H:i');
    $current_day = date('w'); // 0=일요일
    
    $stmt = $db->prepare("
        SELECT * FROM teacher_schedules 
        WHERE teacher_id = ? AND day_of_week = ? AND is_active = 1
    ");
    $stmt->execute([$teacher['id'], $current_day]);
    $schedule = $stmt->fetch();
    
    $is_working_hours = false;
    if ($schedule) {
        if ($current_time >= $schedule['start_time'] && $current_time <= $schedule['end_time']) {
            $is_working_hours = true;
        }
    }
    
    // 예외 일정 확인
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT * FROM teacher_exceptions 
        WHERE teacher_id = ? AND exception_date = ?
    ");
    $stmt->execute([$teacher['id'], $today]);
    $exception = $stmt->fetch();
    
    if ($exception) {
        if ($exception['exception_type'] === 'off') {
            throw new Exception('오늘은 휴무일입니다. 상태를 변경할 수 없습니다.');
        } elseif ($exception['exception_type'] === 'special_hours') {
            if ($exception['start_time'] && $exception['end_time']) {
                $is_working_hours = ($current_time >= $exception['start_time'] && $current_time <= $exception['end_time']);
            }
        }
    }
    
    // 근무시간 외에는 offline만 가능
    if (!$is_working_hours && $status !== 'offline') {
        throw new Exception('근무시간이 아닙니다. offline 상태만 설정 가능합니다.');
    }
    
    // 상태 업데이트 (실제로는 메모리나 캐시에 저장하는 것이 좋지만, 여기서는 간단히 처리)
    // 시스템에서 실시간 상태를 추적할 수 있는 별도 테이블이나 캐시 시스템을 사용하는 것을 권장
    
    // 로그 기록을 위한 임시 테이블 생성 (실제 운영에서는 Redis 등 사용 권장)
    $stmt = $db->prepare("
        INSERT INTO teacher_status_logs (teacher_id, status, changed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE status = VALUES(status), changed_at = VALUES(changed_at)
    ");
    
    // 테이블이 없을 경우를 대비한 테이블 생성
    $db->exec("
        CREATE TABLE IF NOT EXISTS teacher_status_logs (
            teacher_id INT PRIMARY KEY,
            status ENUM('available', 'busy', 'break', 'offline') NOT NULL,
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
        )
    ");
    
    $stmt = $db->prepare("
        INSERT INTO teacher_status_logs (teacher_id, status, changed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE status = VALUES(status), changed_at = VALUES(changed_at)
    ");
    $stmt->execute([$teacher['id'], $status]);
    
    // 상태 메시지 생성
    $status_messages = [
        'available' => '예약 가능',
        'busy' => '예약 중',
        'break' => '휴게시간',
        'offline' => '오프라인'
    ];
    
    $status_message = $status_messages[$status] ?? $status;
    
    // 상태 변경 알림 (필요한 경우)
    if ($status === 'available') {
        // 대기열에 있는 고객들에게 알림 가능
        // processWaitlistNotifications() 함수 호출 가능
    }
    
    echo json_encode([
        'success' => true,
        'message' => "상태가 '{$status_message}'로 변경되었습니다.",
        'data' => [
            'teacher_id' => $teacher['id'],
            'teacher_name' => $teacher['name'],
            'status' => $status,
            'status_message' => $status_message,
            'is_working_hours' => $is_working_hours,
            'changed_at' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 