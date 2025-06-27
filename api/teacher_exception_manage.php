<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
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
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // 예외 일정 추가
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST 방식만 허용됩니다.');
            }
            
            $exception_date = $_POST['exception_date'] ?? '';
            $exception_type = $_POST['exception_type'] ?? '';
            $start_time = $_POST['start_time'] ?? null;
            $end_time = $_POST['end_time'] ?? null;
            $reason = $_POST['reason'] ?? '';
            
            // 유효성 검사
            if (!$exception_date || !$exception_type) {
                throw new Exception('필수 정보가 누락되었습니다.');
            }
            
            if (!in_array($exception_type, ['off', 'special_hours', 'blocked'])) {
                throw new Exception('유효하지 않은 예외 타입입니다.');
            }
            
            // 날짜 형식 검사
            if (!DateTime::createFromFormat('Y-m-d', $exception_date)) {
                throw new Exception('잘못된 날짜 형식입니다.');
            }
            
            // 과거 날짜 체크
            if (strtotime($exception_date) < strtotime(date('Y-m-d'))) {
                throw new Exception('과거 날짜에는 예외 일정을 등록할 수 없습니다.');
            }
            
            // 특별 근무시간인 경우 시간 필수
            if ($exception_type === 'special_hours') {
                if (!$start_time || !$end_time) {
                    throw new Exception('특별 근무시간의 경우 시작시간과 종료시간을 입력해야 합니다.');
                }
                
                if (strtotime($start_time) >= strtotime($end_time)) {
                    throw new Exception('종료시간은 시작시간보다 늦어야 합니다.');
                }
            }
            
            // 중복 체크
            $stmt = $db->prepare("
                SELECT id FROM teacher_exceptions 
                WHERE teacher_id = ? AND exception_date = ?
            ");
            $stmt->execute([$teacher_id, $exception_date]);
            
            if ($stmt->fetch()) {
                throw new Exception('해당 날짜에 이미 예외 일정이 등록되어 있습니다.');
            }
            
            // 해당 날짜에 기존 예약이 있는지 확인
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM reservations 
                WHERE teacher_id = ? AND reservation_date = ? 
                AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$teacher_id, $exception_date]);
            $existing_reservations = $stmt->fetch()['count'];
            
            if ($existing_reservations > 0 && $exception_type === 'off') {
                throw new Exception('해당 날짜에 예약이 있어 휴무 등록이 불가능합니다. 기존 예약을 먼저 처리해주세요.');
            }
            
            // 예외 일정 등록
            $stmt = $db->prepare("
                INSERT INTO teacher_exceptions 
                (teacher_id, exception_date, exception_type, start_time, end_time, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $teacher_id, $exception_date, $exception_type, 
                $start_time, $end_time, $reason
            ]);
            
            $exception_id = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => '예외 일정이 등록되었습니다.',
                'data' => [
                    'exception_id' => $exception_id,
                    'exception_date' => $exception_date,
                    'exception_type' => $exception_type,
                    'reason' => $reason
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'update':
            // 예외 일정 수정
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST 방식만 허용됩니다.');
            }
            
            $exception_id = intval($_POST['exception_id'] ?? 0);
            $exception_date = $_POST['exception_date'] ?? '';
            $exception_type = $_POST['exception_type'] ?? '';
            $start_time = $_POST['start_time'] ?? null;
            $end_time = $_POST['end_time'] ?? null;
            $reason = $_POST['reason'] ?? '';
            
            if (!$exception_id) {
                throw new Exception('예외 일정 ID가 필요합니다.');
            }
            
            // 기존 예외 일정 확인
            $stmt = $db->prepare("
                SELECT * FROM teacher_exceptions 
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([$exception_id, $teacher_id]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                throw new Exception('해당 예외 일정을 찾을 수 없습니다.');
            }
            
            // 유효성 검사 (add와 동일)
            if (!$exception_date || !$exception_type) {
                throw new Exception('필수 정보가 누락되었습니다.');
            }
            
            if (!in_array($exception_type, ['off', 'special_hours', 'blocked'])) {
                throw new Exception('유효하지 않은 예외 타입입니다.');
            }
            
            if ($exception_type === 'special_hours' && (!$start_time || !$end_time)) {
                throw new Exception('특별 근무시간의 경우 시작시간과 종료시간을 입력해야 합니다.');
            }
            
            // 예외 일정 수정
            $stmt = $db->prepare("
                UPDATE teacher_exceptions 
                SET exception_date = ?, exception_type = ?, start_time = ?, end_time = ?, reason = ?
                WHERE id = ? AND teacher_id = ?
            ");
            
            $stmt->execute([
                $exception_date, $exception_type, $start_time, $end_time, $reason,
                $exception_id, $teacher_id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => '예외 일정이 수정되었습니다.'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'delete':
            // 예외 일정 삭제
            $exception_id = intval($_POST['exception_id'] ?? $_GET['id'] ?? 0);
            
            if (!$exception_id) {
                throw new Exception('예외 일정 ID가 필요합니다.');
            }
            
            // 기존 예외 일정 확인
            $stmt = $db->prepare("
                SELECT * FROM teacher_exceptions 
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([$exception_id, $teacher_id]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                throw new Exception('해당 예외 일정을 찾을 수 없습니다.');
            }
            
            // 예외 일정 삭제
            $stmt = $db->prepare("
                DELETE FROM teacher_exceptions 
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([$exception_id, $teacher_id]);
            
            echo json_encode([
                'success' => true,
                'message' => '예외 일정이 삭제되었습니다.'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'list':
            // 예외 일정 목록 조회
            $start_date = $_GET['start_date'] ?? date('Y-m-d');
            $end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
            
            $stmt = $db->prepare("
                SELECT * FROM teacher_exceptions 
                WHERE teacher_id = ? 
                AND exception_date BETWEEN ? AND ?
                ORDER BY exception_date ASC
            ");
            $stmt->execute([$teacher_id, $start_date, $end_date]);
            $exceptions = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $exceptions
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('유효하지 않은 액션입니다.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 