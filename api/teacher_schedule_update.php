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
    $db = getDB();
    
    // 선생님 정보 확인
    $stmt = $db->prepare("SELECT id FROM teachers WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        throw new Exception('선생님 정보를 찾을 수 없습니다.');
    }
    
    $teacher_id = $teacher['id'];
    $schedules = $_POST['schedules'] ?? [];
    
    if (empty($schedules) || !is_array($schedules)) {
        throw new Exception('스케줄 정보가 누락되었습니다.');
    }
    
    $db->beginTransaction();
    
    // 기존 스케줄 삭제
    $stmt = $db->prepare("DELETE FROM teacher_schedules WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    
    // 새 스케줄 등록
    $insert_stmt = $db->prepare("
        INSERT INTO teacher_schedules 
        (teacher_id, day_of_week, start_time, end_time, break_start, break_end, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    
    foreach ($schedules as $schedule) {
        $day_of_week = intval($schedule['day_of_week'] ?? -1);
        $start_time = $schedule['start_time'] ?? '';
        $end_time = $schedule['end_time'] ?? '';
        $break_start = $schedule['break_start'] ?? null;
        $break_end = $schedule['break_end'] ?? null;
        
        // 유효성 검사
        if ($day_of_week < 0 || $day_of_week > 6) {
            throw new Exception('유효하지 않은 요일입니다.');
        }
        
        if (empty($start_time) || empty($end_time)) {
            throw new Exception('시작시간과 종료시간을 입력해주세요.');
        }
        
        // 시간 형식 검사
        if (!DateTime::createFromFormat('H:i', $start_time) || !DateTime::createFromFormat('H:i', $end_time)) {
            throw new Exception('잘못된 시간 형식입니다. (HH:MM 형식)');
        }
        
        // 시간 순서 검사
        if (strtotime($start_time) >= strtotime($end_time)) {
            throw new Exception('종료시간은 시작시간보다 늦어야 합니다.');
        }
        
        // 휴게시간 검사
        if ($break_start && $break_end) {
            if (!DateTime::createFromFormat('H:i', $break_start) || !DateTime::createFromFormat('H:i', $break_end)) {
                throw new Exception('잘못된 휴게시간 형식입니다.');
            }
            
            if (strtotime($break_start) >= strtotime($break_end)) {
                throw new Exception('휴게 종료시간은 휴게 시작시간보다 늦어야 합니다.');
            }
            
            if (strtotime($break_start) < strtotime($start_time) || strtotime($break_end) > strtotime($end_time)) {
                throw new Exception('휴게시간은 근무시간 내에 있어야 합니다.');
            }
        }
        
        // NULL 처리
        $break_start = $break_start ?: null;
        $break_end = $break_end ?: null;
        
        $insert_stmt->execute([
            $teacher_id, $day_of_week, $start_time, $end_time, $break_start, $break_end
        ]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '정기 스케줄이 성공적으로 업데이트되었습니다.',
        'data' => [
            'teacher_id' => $teacher_id,
            'updated_schedules' => count($schedules)
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