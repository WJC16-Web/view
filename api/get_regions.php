<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();
    
    $type = $_GET['type'] ?? 'sido'; // sido, sigungu, dong
    $parent_id = $_GET['parent_id'] ?? null;
    
    // 유효성 검사
    $allowed_types = ['sido', 'sigungu', 'dong'];
    if (!in_array($type, $allowed_types)) {
        throw new Exception('잘못된 지역 타입입니다.');
    }
    
    // 쿼리 구성
    $sql = "SELECT id, region_name, region_code FROM regions WHERE region_type = ? AND is_active = 1";
    $params = [$type];
    
    if ($parent_id !== null) {
        $sql .= " AND parent_id = ?";
        $params[] = $parent_id;
    } else if ($type !== 'sido') {
        // 시/도가 아닌데 parent_id가 없으면 빈 결과 반환
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => '상위 지역을 선택해주세요.'
        ]);
        exit;
    }
    
    $sql .= " ORDER BY region_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $regions = $stmt->fetchAll();
    
    // 결과 가공
    $result = [];
    foreach ($regions as $region) {
        $result[] = [
            'id' => (int)$region['id'],
            'name' => $region['region_name'],
            'code' => $region['region_code']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'count' => count($result),
        'type' => $type,
        'parent_id' => $parent_id
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}
?> 