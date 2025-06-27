<?php
// 공통 유틸리티 함수들

// 세션 시작
function startSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// 로그인 확인
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// 사용자 정보 가져오기
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, phone, user_type, is_active, created_at, last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        // last_login 컬럼이 없는 경우 기본 컬럼만 선택
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, phone, user_type, is_active, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
}

// 사용자 권한 확인
function hasPermission($required_type) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    return $user['user_type'] === $required_type || $user['user_type'] === 'admin';
}

// 페이지 권한 체크
function requireLogin($redirect = '/view/pages/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit();
    }
}

function requireUserType($type, $redirect = '/view/') {
    if (!hasPermission($type)) {
        header("Location: $redirect");
        exit();
    }
}

// 비밀번호 해시화
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 비밀번호 확인
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// XSS 방지
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// 이메일 유효성 검사
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// 휴대폰 번호 유효성 검사
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^01[0-9]{8,9}$/', $phone);
}

// 파일 업로드
function uploadFile($file, $directory = 'uploads/', $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => '파일이 선택되지 않았습니다.'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => '허용되지 않는 파일 형식입니다.'];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => '파일 크기가 너무 큽니다.'];
    }
    
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    $filename = uniqid() . '.' . $file_extension;
    $filepath = $directory . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    } else {
        return ['success' => false, 'message' => '파일 업로드에 실패했습니다.'];
    }
}

// 날짜 형식 변환
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'Y-m-d H:i') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

// 시간 형식 변환
function formatTime($time, $format = 'H:i') {
    if (empty($time)) return '';
    return date($format, strtotime($time));
}

// 금액 형식 변환
function formatPrice($price) {
    return number_format($price) . '원';
}

// 거리 계산 (Haversine 공식)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // km
    
    $dlat = deg2rad($lat2 - $lat1);
    $dlon = deg2rad($lon2 - $lon1);
    
    $a = sin($dlat/2) * sin($dlat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    return round($distance, 2);
}

// 평점 표시
function displayRating($rating, $show_number = true) {
    $stars = '';
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5;
    
    for ($i = 0; $i < $full_stars; $i++) {
        $stars .= '★';
    }
    
    if ($half_star) {
        $stars .= '☆';
    }
    
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
    for ($i = 0; $i < $empty_stars; $i++) {
        $stars .= '☆';
    }
    
    if ($show_number) {
        $stars .= ' (' . number_format($rating, 1) . ')';
    }
    
    return $stars;
}

// 사용자 알림 추가
function addNotification($user_id, $type, $title, $message, $related_id = null, $related_type = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, notification_type, title, message, related_id, related_type) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $type, $title, $message, $related_id, $related_type]);
}

// SMS 발송 (가상)
function sendSMSNotification($user_id, $phone, $message, $sms_type = 'general') {
    // 실제 환경에서는 SMS API 호출
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO sms_logs (user_id, phone_number, message, sms_type, status, sent_at)
        VALUES (?, ?, ?, ?, 'sent', NOW())
    ");
    return $stmt->execute([$user_id, $phone, $message, $sms_type]);
}

// 푸시 알림 발송 (가상)
function sendPushNotification($user_id, $title, $message, $push_type = 'general') {
    // 실제 환경에서는 FCM API 호출
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO push_logs (user_id, title, message, push_type, status, sent_at)
        VALUES (?, ?, ?, ?, 'sent', NOW())
    ");
    return $stmt->execute([$user_id, $title, $message, $push_type]);
}

// 사용자 위치 가져오기
function getUserLocation() {
    startSession();
    if (isset($_SESSION['user_latitude']) && isset($_SESSION['user_longitude'])) {
        return [
            'latitude' => $_SESSION['user_latitude'],
            'longitude' => $_SESSION['user_longitude'],
            'set_time' => $_SESSION['location_set_time'] ?? null
        ];
    }
    return null;
}

// 위치 기반 업체 필터링
function filterBusinessesByDistance($businesses, $max_distance = 10) {
    $user_location = getUserLocation();
    
    if (!$user_location) {
        return $businesses; // 위치 정보가 없으면 모든 업체 반환
    }
    
    $filtered = [];
    foreach ($businesses as $business) {
        if ($business['latitude'] && $business['longitude']) {
            $distance = calculateDistance(
                $user_location['latitude'],
                $user_location['longitude'],
                $business['latitude'],
                $business['longitude']
            );
            
            if ($distance <= $max_distance) {
                $business['distance'] = $distance;
                $filtered[] = $business;
            }
        }
    }
    
    // 거리순 정렬
    usort($filtered, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
    
    return $filtered;
}

// 업체 실시간 상태 확인 (개선된 버전)
function getBusinessStatusAdvanced($business_id, $check_datetime = null) {
    if (!$check_datetime) {
        $check_datetime = date('Y-m-d H:i:s');
    }
    
    $check_date = date('Y-m-d', strtotime($check_datetime));
    $check_time = date('H:i', strtotime($check_datetime));
    $day_of_week = date('w', strtotime($check_date)); // 0=일요일
    
    $db = getDB();
    
    // 해당 업체의 활성 선생님들 조회
    $stmt = $db->prepare("
        SELECT t.id, t.user_id, u.name,
               ts.start_time, ts.end_time, ts.break_start, ts.break_end,
               te.exception_type, te.start_time as exception_start, te.end_time as exception_end
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN teacher_schedules ts ON t.id = ts.teacher_id AND ts.day_of_week = ? AND ts.is_active = 1
        LEFT JOIN teacher_exceptions te ON t.id = te.teacher_id AND te.exception_date = ?
        WHERE t.business_id = ? AND t.is_active = 1 AND t.is_approved = 1
    ");
    $stmt->execute([$day_of_week, $check_date, $business_id]);
    $teachers = $stmt->fetchAll();
    
    if (empty($teachers)) {
        return [
            'status' => 'no_teachers',
            'message' => '등록된 선생님이 없습니다',
            'available_count' => 0,
            'total_count' => 0
        ];
    }
    
    $available_teachers = 0;
    $working_teachers = 0;
    $break_teachers = 0;
    $busy_teachers = 0;
    
    foreach ($teachers as $teacher) {
        $teacher_status = getTeacherStatus($teacher, $check_date, $check_time);
        
        switch ($teacher_status['status']) {
            case 'available':
                $available_teachers++;
                $working_teachers++;
                break;
            case 'busy':
                $busy_teachers++;
                $working_teachers++;
                break;
            case 'break':
                $break_teachers++;
                $working_teachers++;
                break;
            case 'off':
                // 카운트하지 않음
                break;
        }
    }
    
    $total_teachers = count($teachers);
    
    // 상태 결정 로직
    if ($available_teachers > 0) {
        return [
            'status' => 'available',
            'message' => "예약가능 {$available_teachers}명",
            'available_count' => $available_teachers,
            'total_count' => $total_teachers
        ];
    } elseif ($busy_teachers > 0) {
        return [
            'status' => 'busy',
            'message' => '모든 선생님 예약중',
            'available_count' => 0,
            'total_count' => $total_teachers
        ];
    } elseif ($break_teachers > 0) {
        // 가장 빠른 재개 시간 찾기
        $next_available = null;
        foreach ($teachers as $teacher) {
            if ($teacher['break_end']) {
                if (!$next_available || $teacher['break_end'] < $next_available) {
                    $next_available = $teacher['break_end'];
                }
            }
        }
        
        return [
            'status' => 'break',
            'message' => '휴게시간' . ($next_available ? " (재개: {$next_available})" : ''),
            'available_count' => 0,
            'total_count' => $total_teachers,
            'next_available' => $next_available
        ];
    } else {
        return [
            'status' => 'closed',
            'message' => '영업종료',
            'available_count' => 0,
            'total_count' => $total_teachers
        ];
    }
}

// 개별 선생님 상태 확인
function getTeacherStatus($teacher, $check_date, $check_time) {
    // 예외 일정 확인
    if ($teacher['exception_type']) {
        switch ($teacher['exception_type']) {
            case 'off':
                return ['status' => 'off', 'message' => '휴무'];
            case 'special_hours':
                if ($teacher['exception_start'] && $teacher['exception_end']) {
                    if ($check_time >= $teacher['exception_start'] && $check_time < $teacher['exception_end']) {
                        return checkTeacherAvailability($teacher['id'], $check_date, $check_time);
                    }
                }
                return ['status' => 'off', 'message' => '영업시간 외'];
            case 'blocked':
                return ['status' => 'off', 'message' => '예약불가'];
        }
    }
    
    // 정기 스케줄 확인
    if (!$teacher['start_time'] || !$teacher['end_time']) {
        return ['status' => 'off', 'message' => '스케줄 없음'];
    }
    
    // 근무시간 체크
    if ($check_time < $teacher['start_time'] || $check_time >= $teacher['end_time']) {
        return ['status' => 'off', 'message' => '영업시간 외'];
    }
    
    // 휴게시간 체크
    if ($teacher['break_start'] && $teacher['break_end']) {
        if ($check_time >= $teacher['break_start'] && $check_time < $teacher['break_end']) {
            return ['status' => 'break', 'message' => '휴게시간'];
        }
    }
    
    // 예약 상황 확인
    return checkTeacherAvailability($teacher['id'], $check_date, $check_time);
}

// 선생님 예약 가능 여부 확인
function checkTeacherAvailability($teacher_id, $check_date, $check_time) {
    $db = getDB();
    
    // 해당 시간에 예약이 있는지 확인
    $stmt = $db->prepare("
        SELECT COUNT(*) as reservation_count
        FROM reservations
        WHERE teacher_id = ? AND reservation_date = ? 
        AND start_time <= ? AND end_time > ?
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$teacher_id, $check_date, $check_time, $check_time]);
    $result = $stmt->fetch();
    
    if ($result['reservation_count'] > 0) {
        return ['status' => 'busy', 'message' => '예약중'];
    } else {
        return ['status' => 'available', 'message' => '예약가능'];
    }
}

// 대기열 알림 처리
function processWaitlistNotifications() {
    $db = getDB();
    
    // 자리가 난 대기열 찾기
    $stmt = $db->prepare("
        SELECT w.*, b.name as business_name, bs.service_name,
               u.name as customer_name, u.phone as customer_phone
        FROM reservation_waitlist w
        JOIN businesses b ON w.business_id = b.id
        JOIN business_services bs ON w.service_id = bs.id
        JOIN users u ON w.customer_id = u.id
        WHERE w.status = 'waiting' AND w.expires_at > NOW()
        ORDER BY w.priority DESC, w.created_at ASC
    ");
    $stmt->execute();
    $waitlist_items = $stmt->fetchAll();
    
    foreach ($waitlist_items as $item) {
        // 해당 시간대가 예약 가능한지 확인
        $is_available = isTimeSlotAvailable(
            $item['teacher_id'], 
            $item['desired_date'], 
            $item['desired_time']
        );
        
        if ($is_available) {
            // 우선순위 10분간 예약 기회 제공
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // 상태 업데이트
            $stmt = $db->prepare("
                UPDATE reservation_waitlist 
                SET status = 'notified', notified_at = NOW(), expires_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$expires_at, $item['id']]);
            
            // 알림 발송
            $link = BASE_URL . "/pages/reservation_form.php?business_id={$item['business_id']}&teacher_id={$item['teacher_id']}&service_id={$item['service_id']}&date={$item['desired_date']}&time={$item['desired_time']}&waitlist_id={$item['id']}";
            
            addNotification(
                $item['customer_id'],
                'waitlist_available',
                '대기열 알림',
                "{$item['business_name']} {$item['desired_date']} {$item['desired_time']} 자리가 났습니다!"
            );
            
            // SMS 발송
            $sms_message = "[뷰티북] {$item['business_name']} {$item['desired_date']} {$item['desired_time']} 자리가 났습니다! 10분 내 예약하세요.";
            sendSMSNotification($item['customer_id'], $item['customer_phone'], $sms_message, 'waitlist_available');
        }
    }
}

// 3단계 지역 정보 가져오기
function getRegionHierarchy($region_id) {
    $db = getDB();
    
    $stmt = $db->prepare("
        WITH RECURSIVE region_path AS (
            SELECT id, region_name, region_type, parent_id, 0 as level
            FROM regions WHERE id = ?
            
            UNION ALL
            
            SELECT r.id, r.region_name, r.region_type, r.parent_id, rp.level + 1
            FROM regions r
            JOIN region_path rp ON r.id = rp.parent_id
        )
        SELECT * FROM region_path ORDER BY level DESC
    ");
    $stmt->execute([$region_id]);
    
    return $stmt->fetchAll();
}

// 지역명으로 검색
function searchRegionsByName($search_term, $region_type = null) {
    $db = getDB();
    
    $sql = "SELECT id, region_name, region_type, parent_id FROM regions WHERE region_name LIKE ? AND is_active = 1";
    $params = ["%{$search_term}%"];
    
    if ($region_type) {
        $sql .= " AND region_type = ?";
        $params[] = $region_type;
    }
    
    $sql .= " ORDER BY region_name ASC LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// 예약 상태 변경 로그 (복원)
function logReservationStatus($reservation_id, $old_status, $new_status, $changed_by, $reason = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO reservation_status_logs (reservation_id, old_status, new_status, changed_by, reason) 
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$reservation_id, $old_status, $new_status, $changed_by, $reason]);
}

// 페이징 처리
function getPagination($current_page, $total_items, $items_per_page = ITEMS_PER_PAGE) {
    $total_pages = ceil($total_items / $items_per_page);
    $start_item = ($current_page - 1) * $items_per_page;
    
    return [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_items' => $total_items,
        'items_per_page' => $items_per_page,
        'start_item' => $start_item,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

// JSON 응답
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// 성공 응답
function successResponse($message = '성공', $data = null) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

// 에러 응답
function errorResponse($message = '오류가 발생했습니다', $status_code = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status_code);
}

// 리다이렉트
function redirect($url, $message = null) {
    if ($message) {
        startSession();
        $_SESSION['message'] = $message;
    }
    
    // 헤더가 이미 전송된 경우 자바스크립트 리다이렉트 사용
    if (headers_sent()) {
        echo "<script type='text/javascript'>";
        echo "window.location.href = '$url';";
        echo "</script>";
        echo "<noscript>";
        echo "<meta http-equiv='refresh' content='0;url=$url' />";
        echo "</noscript>";
    } else {
        header("Location: $url");
    }
    exit();
}

// 플래시 메시지 표시
function showMessage() {
    startSession();
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}

// 요일 변환
function getDayOfWeekKorean($day_number) {
    $days = ['일', '월', '화', '수', '목', '금', '토'];
    return $days[$day_number] ?? '';
}

// 시간 슬롯 생성 (30분 단위)
function generateTimeSlots($start_time = '09:00', $end_time = '21:00', $interval = 30) {
    $slots = [];
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    
    while ($start < $end) {
        $slots[] = date('H:i', $start);
        $start += $interval * 60; // 분을 초로 변환
    }
    
    return $slots;
}

// 예약 가능 여부 확인 (복원)
function isTimeSlotAvailable($teacher_id, $date, $time, $duration = 60) {
    $db = getDB();
    
    $start_time = $time;
    $end_time = date('H:i', strtotime($time) + ($duration * 60));
    
    // 기존 예약과 겹치는지 확인
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM reservations 
        WHERE teacher_id = ? 
        AND reservation_date = ? 
        AND status IN ('pending', 'confirmed')
        AND (
            (start_time <= ? AND end_time > ?) OR
            (start_time < ? AND end_time >= ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    
    $stmt->execute([
        $teacher_id, $date, 
        $start_time, $start_time,
        $end_time, $end_time,
        $start_time, $end_time
    ]);
    
    return $stmt->fetchColumn() == 0;
}

// 기존 업체 상태 확인 함수 (호환성 유지)
function getBusinessStatus($business_id) {
    $status = getBusinessStatusAdvanced($business_id);
    return $status;
}

?> 