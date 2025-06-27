<?php
$page_title = '업체 찾기 - 뷰티북';
require_once '../includes/header.php';

// 필터 파라미터 받기
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$region_1 = sanitize($_GET['region_1'] ?? ''); // 시/도
$region_2 = sanitize($_GET['region_2'] ?? ''); // 구/군
$region_3 = sanitize($_GET['region_3'] ?? ''); // 동
$date = sanitize($_GET['date'] ?? '');
$time = sanitize($_GET['time'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'recommended');
$radius = floatval($_GET['radius'] ?? 0);
$user_lat = floatval($_GET['lat'] ?? 0);
$user_lng = floatval($_GET['lng'] ?? 0);

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = BUSINESSES_PER_PAGE;
$offset = ($page - 1) * $per_page;

// 다중 업종 선택 처리
$categories = [];
if (!empty($_GET['categories']) && is_array($_GET['categories'])) {
    $categories = array_map('sanitize', $_GET['categories']);
} elseif (!empty($category)) {
    $categories = [$category];
}

$db = getDB();

// 지역 데이터 가져오기
$regions_1 = $db->query("SELECT * FROM regions WHERE level = 1 ORDER BY name")->fetchAll();
$regions_2 = [];
$regions_3 = [];

if ($region_1) {
    $stmt = $db->prepare("SELECT * FROM regions WHERE level = 2 AND parent_id = ? ORDER BY name");
    $stmt->execute([$region_1]);
    $regions_2 = $stmt->fetchAll();
}

if ($region_2) {
    $stmt = $db->prepare("SELECT * FROM regions WHERE level = 3 AND parent_id = ? ORDER BY name");
    $stmt->execute([$region_2]);
    $regions_3 = $stmt->fetchAll();
}

// 업체 검색 쿼리 구성
$where_conditions = ["b.is_active = 1", "b.is_approved = 1"];
$params = [];

// 선생님이 1명 이상 있는 업체만
$where_conditions[] = "(SELECT COUNT(*) FROM teachers t WHERE t.business_id = b.id AND t.is_active = 1 AND t.is_approved = 1) > 0";

// 검색어 필터
if ($search) {
    $where_conditions[] = "(b.name LIKE ? OR b.address LIKE ? OR b.category LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

// 업종 필터 (다중 선택)
if (!empty($categories)) {
    $category_placeholders = str_repeat('?,', count($categories) - 1) . '?';
    // JSON_OVERLAPS 대신 LIKE 연산자 사용 (MariaDB 10.3.8 호환)
    $subcategory_conditions = [];
    foreach ($categories as $cat) {
        $subcategory_conditions[] = "b.subcategories LIKE ?";
        $params[] = "%\"$cat\"%";
    }
    $subcategory_clause = implode(' OR ', $subcategory_conditions);
    $where_conditions[] = "(b.category IN ($category_placeholders) OR ($subcategory_clause))";
    $params = array_merge($params, $categories);
}

// 지역 필터
if ($region_3) {
    // 동 단위까지 선택한 경우
    $stmt = $db->prepare("SELECT name FROM regions WHERE id = ?");
    $stmt->execute([$region_3]);
    $region_name = $stmt->fetchColumn();
    if ($region_name) {
        $where_conditions[] = "b.address LIKE ?";
        $params[] = "%$region_name%";
    }
} elseif ($region_2) {
    // 구/군 단위까지 선택한 경우
    $stmt = $db->prepare("SELECT name FROM regions WHERE id = ?");
    $stmt->execute([$region_2]);
    $region_name = $stmt->fetchColumn();
    if ($region_name) {
        $where_conditions[] = "b.address LIKE ?";
        $params[] = "%$region_name%";
    }
} elseif ($region_1) {
    // 시/도 단위까지 선택한 경우
    $stmt = $db->prepare("SELECT name FROM regions WHERE id = ?");
    $stmt->execute([$region_1]);
    $region_name = $stmt->fetchColumn();
    if ($region_name) {
        $where_conditions[] = "b.address LIKE ?";
        $params[] = "%$region_name%";
    }
}

// GPS 기반 거리 필터
$distance_select = "";
$distance_order = "";
if ($user_lat && $user_lng && $radius > 0) {
    $distance_select = ", (6371 * acos(cos(radians(?)) * cos(radians(b.latitude)) * cos(radians(b.longitude) - radians(?)) + sin(radians(?)) * sin(radians(b.latitude)))) AS distance";
    $where_conditions[] = "(6371 * acos(cos(radians(?)) * cos(radians(b.latitude)) * cos(radians(b.longitude) - radians(?)) + sin(radians(?)) * sin(radians(b.latitude)))) <= ?";
    $params = array_merge([$user_lat, $user_lng, $user_lat], $params, [$user_lat, $user_lng, $user_lat, $radius]);
    $distance_order = "distance ASC, ";
}

// 날짜/시간 필터
$available_filter = "";
if ($date && $time) {
    $day_of_week = date('w', strtotime($date));
    $available_filter = "
        AND EXISTS (
            SELECT 1 FROM teachers t 
            JOIN teacher_schedules ts ON t.id = ts.teacher_id 
            WHERE t.business_id = b.id 
            AND t.is_active = 1 AND t.is_approved = 1
            AND ts.day_of_week = $day_of_week
            AND ts.is_active = 1
            AND ? BETWEEN ts.start_time AND ts.end_time
            AND NOT EXISTS (
                SELECT 1 FROM reservations r 
                WHERE r.teacher_id = t.id 
                AND r.reservation_date = ?
                AND r.status IN ('pending', 'confirmed')
                AND ? BETWEEN r.start_time AND r.end_time
            )
            AND NOT EXISTS (
                SELECT 1 FROM teacher_exceptions te
                WHERE te.teacher_id = t.id 
                AND te.exception_date = ?
                AND te.exception_type = 'off'
            )
        )
    ";
    $params = array_merge($params, [$time, $date, $time, $date]);
}

// 정렬 조건
$order_by = "";
switch ($sort) {
    case 'rating':
        $order_by = "avg_rating DESC, review_count DESC";
        break;
    case 'review':
        $order_by = "review_count DESC, avg_rating DESC";
        break;
    case 'price':
        $order_by = "min_price ASC";
        break;
    case 'distance':
        $order_by = $distance_order . "b.created_at DESC";
        break;
    case 'newest':
        $order_by = "b.created_at DESC";
        break;
    default: // recommended
        $order_by = $distance_order . "CASE WHEN b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END DESC, avg_rating DESC, review_count DESC";
}

// 메인 쿼리 실행
$where_clause = implode(' AND ', $where_conditions);

$count_query = "
    SELECT COUNT(DISTINCT b.id) 
    FROM businesses b 
    WHERE $where_clause $available_filter
";

$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_count = $stmt->fetchColumn();

$main_query = "
    SELECT b.*,
           AVG(r.overall_rating) as avg_rating,
           COUNT(DISTINCT r.id) as review_count,
           bp.photo_url as main_photo,
           MIN(bs.price) as min_price
           $distance_select
    FROM businesses b
    LEFT JOIN reviews r ON b.id = r.business_id
    LEFT JOIN business_photos bp ON b.id = bp.business_id AND bp.photo_type = 'main'
    LEFT JOIN business_services bs ON b.id = bs.business_id AND bs.is_active = 1
    WHERE $where_clause $available_filter
    GROUP BY b.id
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($main_query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

// 페이징 정보
$pagination = getPagination($page, $total_count, $per_page);

// 업종 목록
$service_categories = [
    'nail' => ['name' => '네일', 'icon' => '💅'],
    'hair' => ['name' => '헤어', 'icon' => '💇‍♀️'],
    'waxing' => ['name' => '왁싱', 'icon' => '🪒'],
    'skincare' => ['name' => '피부관리', 'icon' => '🧴'],
    'massage' => ['name' => '마사지', 'icon' => '💆‍♀️'],
    'makeup' => ['name' => '메이크업', 'icon' => '💄'],
    'tanning' => ['name' => '태닝', 'icon' => '🌞'],
    'pedicure' => ['name' => '페디큐어', 'icon' => '🦶']
];
?>

<style>
.business-list-container {
    display: flex;
    gap: 30px;
    min-height: calc(100vh - 200px);
}

.filter-sidebar {
    width: 300px;
    background: white;
    padding: 30px 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    height: fit-content;
    position: sticky;
    top: 20px;
}

.filter-section {
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #eee;
}

.filter-section:last-child {
    border-bottom: none;
}

.filter-title {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group {
    margin-bottom: 15px;
}

.filter-label {
    display: block;
    font-weight: 500;
    color: #34495e;
    margin-bottom: 8px;
}

.filter-select {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    background: white;
}

.filter-select:focus {
    outline: none;
    border-color: #ff4757;
}

.category-checkboxes {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.category-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 6px;
    transition: background 0.3s;
}

.category-checkbox:hover {
    background: #f8f9fa;
}

.category-checkbox input[type="checkbox"] {
    width: 16px;
    height: 16px;
}

.category-checkbox label {
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.datetime-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.filter-input {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}

.filter-input:focus {
    outline: none;
    border-color: #ff4757;
}

.location-buttons {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.location-btn {
    flex: 1;
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.location-btn:hover {
    background: #ff4757;
    color: white;
    border-color: #ff4757;
}

.radius-select {
    margin-top: 10px;
}

.filter-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.filter-btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.apply-btn {
    background: #ff4757;
    color: white;
}

.apply-btn:hover {
    background: #ff3742;
}

.reset-btn {
    background: #6c757d;
    color: white;
}

.reset-btn:hover {
    background: #5a6268;
}

.main-content {
    flex: 1;
}

.search-header {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.search-bar-large {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.search-input-large {
    flex: 1;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 10px;
    font-size: 16px;
}

.search-input-large:focus {
    outline: none;
    border-color: #ff4757;
}

.search-btn-large {
    padding: 15px 30px;
    background: #ff4757;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.search-btn-large:hover {
    background: #ff3742;
}

.search-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #666;
    font-size: 14px;
}

.sort-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.sort-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.view-toggle {
    display: flex;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
}

.view-btn {
    padding: 8px 12px;
    background: white;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.view-btn.active {
    background: #ff4757;
    color: white;
}

.businesses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.business-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s;
    text-decoration: none;
    color: inherit;
}

.business-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
}

.business-image {
    width: 100%;
    height: 200px;
    background: #f8f9fa;
    background-size: cover;
    background-position: center;
    position: relative;
}

.business-status {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.status-open { background: #27ae60; }
.status-busy { background: #f39c12; }
.status-break { background: #3498db; }
.status-closed { background: #95a5a6; }
.status-no_teachers { background: #e74c3c; }

.business-distance {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 11px;
}

.business-info {
    padding: 20px;
}

.business-category {
    color: #ff4757;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
}

.business-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #2c3e50;
}

.business-address {
    color: #666;
    font-size: 14px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.business-rating {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.rating-stars {
    color: #ffc107;
    font-size: 14px;
}

.rating-text {
    color: #666;
    font-size: 13px;
}

.business-services {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}

.service-tag {
    background: #f8f9fa;
    color: #666;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
}

.business-price {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.price-text {
    color: #2c3e50;
    font-weight: bold;
    font-size: 16px;
}

.book-btn {
    background: #ff4757;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
}

.book-btn:hover {
    background: #ff3742;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-results i {
    font-size: 64px;
    color: #ddd;
    margin-bottom: 20px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 40px;
}

.page-btn {
    padding: 10px 15px;
    border: 1px solid #ddd;
    background: white;
    color: #666;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
}

.page-btn:hover {
    background: #f8f9fa;
}

.page-btn.active {
    background: #ff4757;
    color: white;
    border-color: #ff4757;
}

.page-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* 반응형 */
@media (max-width: 1024px) {
    .business-list-container {
        flex-direction: column;
    }
    
    .filter-sidebar {
        width: 100%;
        position: static;
    }
    
    .businesses-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .search-bar-large {
        flex-direction: column;
    }
    
    .search-stats {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .businesses-grid {
        grid-template-columns: 1fr;
    }
    
    .datetime-inputs {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="business-list-container">
        <!-- 필터 사이드바 -->
        <div class="filter-sidebar">
            <form id="filterForm" method="GET">
                <!-- 지역 필터 -->
                <div class="filter-section">
                    <h3 class="filter-title">
                        <i class="fas fa-map-marker-alt"></i> 지역
                    </h3>
                    
                    <div class="filter-group">
                        <label class="filter-label">시/도</label>
                        <select name="region_1" id="region1" class="filter-select">
                            <option value="">전체</option>
                            <?php foreach ($regions_1 as $region): ?>
                                <option value="<?php echo $region['id']; ?>" 
                                        <?php echo $region_1 == $region['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">구/군</label>
                        <select name="region_2" id="region2" class="filter-select">
                            <option value="">전체</option>
                            <?php foreach ($regions_2 as $region): ?>
                                <option value="<?php echo $region['id']; ?>" 
                                        <?php echo $region_2 == $region['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">동</label>
                        <select name="region_3" id="region3" class="filter-select">
                            <option value="">전체</option>
                            <?php foreach ($regions_3 as $region): ?>
                                <option value="<?php echo $region['id']; ?>" 
                                        <?php echo $region_3 == $region['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="location-buttons">
                        <button type="button" class="location-btn" onclick="getCurrentLocation()">
                            <i class="fas fa-crosshairs"></i> 내 위치
                        </button>
                        <button type="button" class="location-btn" onclick="clearLocation()">
                            <i class="fas fa-times"></i> 위치 해제
                        </button>
                    </div>
                    
                    <div class="radius-select" id="radiusSelect" style="display: none;">
                        <label class="filter-label">검색 반경</label>
                        <select name="radius" class="filter-select">
                            <option value="3" <?php echo $radius == 3 ? 'selected' : ''; ?>>3km 이내</option>
                            <option value="5" <?php echo $radius == 5 ? 'selected' : ''; ?>>5km 이내</option>
                            <option value="10" <?php echo $radius == 10 ? 'selected' : ''; ?>>10km 이내</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="lat" id="userLat" value="<?php echo $user_lat; ?>">
                    <input type="hidden" name="lng" id="userLng" value="<?php echo $user_lng; ?>">
                </div>
                
                <!-- 업종 필터 -->
                <div class="filter-section">
                    <h3 class="filter-title">
                        <i class="fas fa-tags"></i> 업종
                    </h3>
                    
                    <div class="category-checkboxes">
                        <?php foreach ($service_categories as $cat_key => $cat_info): ?>
                            <div class="category-checkbox">
                                <input type="checkbox" 
                                       name="categories[]" 
                                       value="<?php echo $cat_key; ?>" 
                                       id="cat_<?php echo $cat_key; ?>"
                                       <?php echo in_array($cat_key, $categories) ? 'checked' : ''; ?>>
                                <label for="cat_<?php echo $cat_key; ?>">
                                    <span><?php echo $cat_info['icon']; ?></span>
                                    <?php echo $cat_info['name']; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- 날짜/시간 필터 -->
                <div class="filter-section">
                    <h3 class="filter-title">
                        <i class="fas fa-calendar-alt"></i> 날짜/시간
                    </h3>
                    
                    <div class="datetime-inputs">
                        <div>
                            <label class="filter-label">날짜</label>
                            <input type="date" name="date" class="filter-input" 
                                   value="<?php echo $date; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                        <div>
                            <label class="filter-label">시간</label>
                            <select name="time" class="filter-select">
                                <option value="">시간 선택</option>
                                <?php 
                                $time_slots = generateTimeSlots('09:00', '21:00', 30);
                                foreach ($time_slots as $slot): 
                                ?>
                                    <option value="<?php echo $slot; ?>" 
                                            <?php echo $time === $slot ? 'selected' : ''; ?>>
                                        <?php echo $slot; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- 기존 검색어 유지 -->
                <?php if ($search): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                
                <div class="filter-actions">
                    <button type="submit" class="filter-btn apply-btn">
                        <i class="fas fa-search"></i> 검색
                    </button>
                    <button type="button" class="filter-btn reset-btn" onclick="resetFilters()">
                        <i class="fas fa-undo"></i> 초기화
                    </button>
                </div>
            </form>
        </div>
        
        <!-- 메인 컨텐츠 -->
        <div class="main-content">
            <!-- 검색 헤더 -->
            <div class="search-header">
                <form class="search-bar-large" method="GET">
                    <input type="text" name="search" class="search-input-large" 
                           placeholder="업체명, 지역, 서비스로 검색하세요..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn-large">
                        <i class="fas fa-search"></i> 검색
                    </button>
                    
                    <!-- 필터 값들 유지 -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if ($key !== 'search' && $key !== 'page'): ?>
                            <?php if (is_array($value)): ?>
                                <?php foreach ($value as $v): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>[]" 
                                           value="<?php echo htmlspecialchars($v); ?>">
                                <?php endforeach; ?>
                            <?php else: ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" 
                                       value="<?php echo htmlspecialchars($value); ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </form>
                
                <div class="search-stats">
                    <span>
                        총 <strong><?php echo number_format($total_count); ?></strong>개 업체
                        <?php if ($search): ?>
                            | '<strong><?php echo htmlspecialchars($search); ?></strong>' 검색 결과
                        <?php endif; ?>
                    </span>
                    
                    <div class="sort-controls">
                        <select name="sort" class="sort-select" onchange="changeSort(this.value)">
                            <option value="recommended" <?php echo $sort === 'recommended' ? 'selected' : ''; ?>>추천순</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>평점 높은순</option>
                            <option value="review" <?php echo $sort === 'review' ? 'selected' : ''; ?>>리뷰 많은순</option>
                            <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>가격 낮은순</option>
                            <?php if ($user_lat && $user_lng): ?>
                                <option value="distance" <?php echo $sort === 'distance' ? 'selected' : ''; ?>>거리 가까운순</option>
                            <?php endif; ?>
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>신규업체순</option>
                        </select>
                        
                        <div class="view-toggle">
                            <button type="button" class="view-btn active" data-view="grid">
                                <i class="fas fa-th"></i>
                            </button>
                            <button type="button" class="view-btn" data-view="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 업체 목록 -->
            <?php if (empty($businesses)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>검색 결과가 없습니다</h3>
                    <p>다른 조건으로 검색해보세요.</p>
                </div>
            <?php else: ?>
                <div class="businesses-grid" id="businessGrid">
                    <?php foreach ($businesses as $business): 
                        $status = getBusinessStatus($business['id']);
                    ?>
                        <a href="<?php echo BASE_URL; ?>/pages/business_detail.php?id=<?php echo $business['id']; ?>" 
                           class="business-card">
                            <div class="business-image" 
                                 style="background-image: url('<?php echo $business['main_photo'] ? BASE_URL . '/' . $business['main_photo'] : BASE_URL . '/assets/images/no-image.jpg'; ?>')">
                                <span class="business-status status-<?php echo $status['status']; ?>">
                                    <?php echo $status['message']; ?>
                                </span>
                                
                                <?php if (isset($business['distance'])): ?>
                                    <span class="business-distance">
                                        <?php echo number_format($business['distance'], 1); ?>km
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="business-info">
                                <div class="business-category">
                                    <?php echo htmlspecialchars($business['category']); ?>
                                </div>
                                
                                <h3 class="business-name">
                                    <?php echo htmlspecialchars($business['name']); ?>
                                </h3>
                                
                                <div class="business-address">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($business['address']); ?>
                                </div>
                                
                                <div class="business-rating">
                                    <span class="rating-stars">
                                        <?php echo displayRating($business['avg_rating'] ?: 0, false); ?>
                                    </span>
                                    <span class="rating-text">
                                        <?php echo number_format($business['avg_rating'] ?: 0, 1); ?> 
                                        (<?php echo number_format($business['review_count']); ?>)
                                    </span>
                                </div>
                                
                                <div class="business-price">
                                    <span class="price-text">
                                        <?php echo $business['min_price'] ? formatPrice($business['min_price']) . '부터' : '가격 문의'; ?>
                                    </span>
                                    <button class="book-btn" onclick="event.preventDefault(); location.href='<?php echo BASE_URL; ?>/pages/business_detail.php?id=<?php echo $business['id']; ?>'">
                                        예약하기
                                    </button>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- 페이징 -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($pagination['has_previous']): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="page-btn">
                                <i class="fas fa-chevron-left"></i> 이전
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($pagination['total_pages'], $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['has_next']): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="page-btn">
                                다음 <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 3단계 지역 선택 연동
    loadSido(); // 페이지 로드시 시/도 목록 로드
    
    $('#region1').change(function() {
        var sidoId = $(this).val();
        $('#region2').html('<option value="">구/군 선택</option>');
        $('#region3').html('<option value="">동 선택</option>');
        
        if (sidoId) {
            loadSigungu(sidoId);
        }
    });
    
    $('#region2').change(function() {
        var sigunguId = $(this).val();
        $('#region3').html('<option value="">동 선택</option>');
        
        if (sigunguId) {
            loadDong(sigunguId);
        }
    });
    
    // 뷰 토글
    $('.view-btn').click(function() {
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        
        var view = $(this).data('view');
        if (view === 'list') {
            $('#businessGrid').addClass('list-view');
        } else {
            $('#businessGrid').removeClass('list-view');
        }
    });
    
    // 위치 표시
    <?php if ($user_lat && $user_lng): ?>
        $('#radiusSelect').show();
    <?php endif; ?>
});

// 현재 위치 가져오기
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            $('#userLat').val(position.coords.latitude);
            $('#userLng').val(position.coords.longitude);
            $('#radiusSelect').show();
            $('select[name="radius"]').val(5); // 기본 5km
            
            alert('현재 위치가 설정되었습니다. 검색을 실행해주세요.');
        }, function(error) {
            alert('위치 정보를 가져올 수 없습니다.');
        });
    } else {
        alert('이 브라우저는 위치 서비스를 지원하지 않습니다.');
    }
}

// 위치 정보 초기화
function clearLocation() {
    $('#userLat').val('');
    $('#userLng').val('');
    $('#radiusSelect').hide();
    $('select[name="radius"]').val(5);
}

// 정렬 변경
function changeSort(sortValue) {
    var url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page'); // 페이지 초기화
    window.location = url;
}

// 필터 초기화
function resetFilters() {
    window.location = '<?php echo BASE_URL; ?>/pages/business_list.php';
}

// 시/도 목록 로드
function loadSido() {
    $.get('<?php echo BASE_URL; ?>/api/get_regions.php', {
        type: 'sido'
    }, function(data) {
        if (data.success) {
            var select = $('#region1');
            select.empty().append('<option value="">시/도 선택</option>');
            
            data.data.forEach(function(region) {
                select.append('<option value="' + region.id + '">' + region.region_name + '</option>');
            });
        }
    });
}

// 구/군 목록 로드
function loadSigungu(sidoId) {
    $.get('<?php echo BASE_URL; ?>/api/get_regions.php', {
        type: 'sigungu',
        parent_id: sidoId
    }, function(data) {
        if (data.success) {
            var select = $('#region2');
            select.empty().append('<option value="">구/군 선택</option>');
            
            data.data.forEach(function(region) {
                select.append('<option value="' + region.id + '">' + region.region_name + '</option>');
            });
        }
    });
}

// 동 목록 로드
function loadDong(sigunguId) {
    $.get('<?php echo BASE_URL; ?>/api/get_regions.php', {
        type: 'dong',
        parent_id: sigunguId
    }, function(data) {
        if (data.success) {
            var select = $('#region3');
            select.empty().append('<option value="">동 선택</option>');
            
            data.data.forEach(function(region) {
                select.append('<option value="' + region.id + '">' + region.region_name + '</option>');
            });
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?> 