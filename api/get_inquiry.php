<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../includes/functions.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않는 메소드입니다.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$inquiry_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if (!$inquiry_id) {
    echo json_encode(['success' => false, 'message' => '문의 ID가 필요합니다.']);
    exit;
}

try {
    $db = getDB();
    
    // 문의 조회 (권한 확인 포함)
    $where_condition = "i.id = ?";
    $params = [$inquiry_id];
    
    // 관리자가 아니면 본인 문의만 조회 가능
    if ($user_type !== 'admin') {
        $where_condition .= " AND i.user_id = ?";
        $params[] = $user_id;
    }
    
    $stmt = $db->prepare("
        SELECT i.*,
               u.name as user_name,
               u.email as user_email,
               admin.name as admin_name
        FROM inquiries i
        JOIN users u ON i.user_id = u.id
        LEFT JOIN users admin ON i.answered_by = admin.id
        WHERE $where_condition
    ");
    
    $stmt->execute($params);
    $inquiry = $stmt->fetch();
    
    if (!$inquiry) {
        echo json_encode(['success' => false, 'message' => '문의를 찾을 수 없습니다.']);
        exit;
    }
    
    // 비공개 문의 권한 확인
    if ($inquiry['is_private'] && $user_type !== 'admin' && $inquiry['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => '접근 권한이 없습니다.']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'inquiry' => $inquiry
    ]);
    
} catch (Exception $e) {
    error_log("Inquiry fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '문의 조회 중 오류가 발생했습니다.']);
}
?> 