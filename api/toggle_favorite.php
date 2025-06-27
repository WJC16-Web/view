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
$business_id = $input['business_id'] ?? 0;
$action = $input['action'] ?? 'toggle'; // toggle, add, remove

if (!$business_id) {
    echo json_encode(['success' => false, 'message' => '?�체 ID가 ?�요?�니??']);
    exit;
}

try {
    // ?�체 존재 ?�인
    $stmt = $db->prepare("SELECT id, name FROM businesses WHERE id = ? AND approval_status = 'approved'");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();
    
    if (!$business) {
        throw new Exception('?�체�?찾을 ???�습?�다.');
    }
    
    // ?�재 즐겨찾기 ?�태 ?�인
    $stmt = $db->prepare("SELECT id FROM customer_favorites WHERE customer_id = ? AND business_id = ?");
    $stmt->execute([$user_id, $business_id]);
    $existing_favorite = $stmt->fetch();
    
    $is_favorited = (bool)$existing_favorite;
    
    if ($action === 'toggle') {
        $action = $is_favorited ? 'remove' : 'add';
    }
    
    if ($action === 'add' && !$is_favorited) {
        // 즐겨찾기 추�?
        $stmt = $db->prepare("
            INSERT INTO customer_favorites (customer_id, business_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $business_id]);
        
        $message = '즐겨찾기??추�??�었?�니??';
        $is_favorited = true;
        
    } elseif ($action === 'remove' && $is_favorited) {
        // 즐겨찾기 ?�거
        $stmt = $db->prepare("
            DELETE FROM customer_favorites 
            WHERE customer_id = ? AND business_id = ?
        ");
        $stmt->execute([$user_id, $business_id]);
        
        $message = '즐겨찾기?�서 ?�거?�었?�니??';
        $is_favorited = false;
        
    } else {
        throw new Exception('?��? ' . ($is_favorited ? '즐겨찾기??추�??? : '즐겨찾기?�서 ?�거??) . ' ?�체?�니??');
    }
    
    // ?�체??�?즐겨찾기 ??조회
    $stmt = $db->prepare("SELECT COUNT(*) as total_favorites FROM customer_favorites WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $total_favorites = $stmt->fetch()['total_favorites'];
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'is_favorited' => $is_favorited,
        'total_favorites' => $total_favorites
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
