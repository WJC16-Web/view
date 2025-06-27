<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

startSession();
$db = getDB();

// 검색 파라미터
$search = $_GET['search'] ?? '';
$categories = $_GET['categories'] ?? [];
$region_1 = $_GET['region_1'] ?? '';
$region_2 = $_GET['region_2'] ?? '';
$region_3 = $_GET['region_3'] ?? '';
$user_lat = floatval($_GET['lat'] ?? 0);
$user_lng = floatval($_GET['lng'] ?? 0);
$radius = intval($_GET['radius'] ?? 10);

if (is_string($categories)) {
    $categories = explode(',', $categories);
}

// 업체 조회 쿼리
$where_conditions = ["b.is_active = 1", "b.is_approved = 1"];
$params = [];

// 검색어 필터
if ($search) {
    $where_conditions[] = "(b.name LIKE ? OR b.address LIKE ? OR b.description LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

// 카테고리 필터
if (!empty($categories)) {
    $category_placeholders = str_repeat('?,', count($categories) - 1) . '?';
    $where_conditions[] = "b.category IN ($category_placeholders)";
    $params = array_merge($params, $categories);
}

// 지역 필터
if ($region_3) {
    $where_conditions[] = "b.region_id = ?";
    $params[] = $region_3;
} elseif ($region_2) {
    $where_conditions[] = "b.region_id IN (SELECT id FROM regions WHERE parent_id = ?)";
    $params[] = $region_2;
} elseif ($region_1) {
    $where_conditions[] = "b.region_id IN (SELECT id FROM regions WHERE parent_id IN (SELECT id FROM regions WHERE parent_id = ?))";
    $params[] = $region_1;
}

// GPS 기반 필터링
$distance_select = "";
$distance_where = "";
if ($user_lat && $user_lng) {
    $distance_select = ", (6371 * acos(cos(radians(?)) * cos(radians(b.latitude)) * cos(radians(b.longitude) - radians(?)) + sin(radians(?)) * sin(radians(b.latitude)))) AS distance";
    $distance_where = " HAVING distance <= ?";
    $params = array_merge([$user_lat, $user_lng, $user_lat], $params, [$radius]);
}

// 선생님이 있는 업체만 조회
$where_conditions[] = "EXISTS (SELECT 1 FROM teachers t WHERE t.business_id = b.id AND t.is_active = 1 AND t.is_approved = 1)";

$where_clause = implode(' AND ', $where_conditions);

$query = "
    SELECT b.*, 
           AVG(r.overall_rating) as avg_rating,
           COUNT(DISTINCT r.id) as review_count,
           bp.photo_url as main_photo
           $distance_select
    FROM businesses b
    LEFT JOIN reviews r ON b.id = r.business_id
    LEFT JOIN business_photos bp ON b.id = bp.business_id AND bp.photo_type = 'main'
    WHERE $where_clause
    GROUP BY b.id
    $distance_where
    ORDER BY " . ($user_lat && $user_lng ? "distance ASC" : "b.created_at DESC");

$stmt = $db->prepare($query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<style>
.map-container {
    display: flex;
    height: calc(100vh - 120px);
    gap: 0;
}

.map-sidebar {
    width: 400px;
    background: white;
    overflow-y: auto;
    border-right: 1px solid #ddd;
}

.map-content {
    flex: 1;
    position: relative;
}

#map {
    width: 100%;
    height: 100%;
}

.map-controls {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 1000;
    display: flex;
    gap: 10px;
}

.control-btn {
    background: white;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 6px;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.control-btn:hover {
    background: #f8f9fa;
}

.control-btn.active {
    background: #ff4757;
    color: white;
}

.business-list-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background 0.3s;
}

.business-list-item:hover {
    background: #f8f9fa;
}

.business-list-item.selected {
    background: #fff3cd;
    border-left: 4px solid #ff4757;
}

.business-info {
    display: flex;
    gap: 12px;
}

.business-image {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    background: #f0f0f0;
    background-size: cover;
    background-position: center;
    flex-shrink: 0;
}

.business-details {
    flex: 1;
}

.business-name {
    font-weight: bold;
    color: #333;
    margin-bottom: 4px;
}

.business-category {
    color: #ff4757;
    font-size: 12px;
    margin-bottom: 4px;
}

.business-address {
    color: #666;
    font-size: 13px;
    margin-bottom: 6px;
}

.business-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
}

.rating-stars {
    color: #ffc107;
}

.business-distance {
    color: #007bff;
    font-size: 12px;
    font-weight: 500;
}

.map-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
}

.map-search {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.map-search input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.map-search button {
    padding: 8px 15px;
    background: #ff4757;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.map-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-tag {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    color: #495057;
}

.filter-tag.active {
    background: #ff4757;
    color: white;
}

.no-results {
    padding: 40px 20px;
    text-align: center;
    color: #666;
}

@media (max-width: 768px) {
    .map-container {
        flex-direction: column;
        height: auto;
    }
    
    .map-sidebar {
        width: 100%;
        height: 300px;
        order: 2;
    }
    
    .map-content {
        height: 400px;
        order: 1;
    }
    
    .map-controls {
        top: 10px;
        left: 10px;
    }
}
</style>

<div class="map-container">
    <!-- 지도 영역 -->
    <div class="map-content">
        <div id="map"></div>
        
        <!-- 지도 컨트롤 -->
        <div class="map-controls">
            <button class="control-btn" onclick="toggleSidebar()" title="업체 목록">
                <i class="fas fa-list"></i>
            </button>
            <button class="control-btn" onclick="centerUserLocation()" title="내 위치">
                <i class="fas fa-crosshairs"></i>
            </button>
            <button class="control-btn" onclick="toggleMapType()" title="지도 타입">
                <i class="fas fa-layer-group"></i>
            </button>
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?<?php echo http_build_query($_GET); ?>" 
               class="control-btn" title="리스트 보기">
                <i class="fas fa-th-list"></i>
            </a>
        </div>
    </div>
    
    <!-- 업체 목록 사이드바 -->
    <div class="map-sidebar" id="mapSidebar">
        <!-- 검색 헤더 -->
        <div class="map-header">
            <div class="map-search">
                <input type="text" id="searchInput" placeholder="업체명, 지역 검색..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button onclick="searchBusinesses()">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            
            <div class="map-filters">
                <?php if ($search): ?>
                    <span class="filter-tag active"><?php echo htmlspecialchars($search); ?></span>
                <?php endif; ?>
                
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <span class="filter-tag active"><?php echo htmlspecialchars($cat); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($user_lat && $user_lng): ?>
                    <span class="filter-tag active">내 위치 <?php echo $radius; ?>km</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 업체 목록 -->
        <div class="business-list">
            <?php if (empty($businesses)): ?>
                <div class="no-results">
                    <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <h5>검색 결과가 없습니다</h5>
                    <p>다른 조건으로 검색해보세요.</p>
                </div>
            <?php else: ?>
                <?php foreach ($businesses as $business): 
                    $status = getBusinessStatus($business['id']);
                ?>
                    <div class="business-list-item" 
                         data-lat="<?php echo $business['latitude']; ?>"
                         data-lng="<?php echo $business['longitude']; ?>"
                         data-id="<?php echo $business['id']; ?>"
                         onclick="selectBusiness(this, <?php echo $business['id']; ?>)">
                        
                        <div class="business-info">
                            <div class="business-image" 
                                 style="background-image: url('<?php echo $business['main_photo'] ? BASE_URL . '/' . $business['main_photo'] : BASE_URL . '/assets/images/no-image.jpg'; ?>')">
                            </div>
                            
                            <div class="business-details">
                                <div class="business-category">
                                    <?php echo htmlspecialchars($business['category']); ?>
                                </div>
                                
                                <div class="business-name">
                                    <?php echo htmlspecialchars($business['name']); ?>
                                </div>
                                
                                <div class="business-address">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($business['address']); ?>
                                </div>
                                
                                <div class="business-rating">
                                    <span class="rating-stars">
                                        <?php echo displayRating($business['avg_rating'] ?: 0, false); ?>
                                    </span>
                                    <span><?php echo number_format($business['avg_rating'] ?: 0, 1); ?></span>
                                    <span class="text-muted">(<?php echo number_format($business['review_count']); ?>)</span>
                                    
                                    <?php if (isset($business['distance'])): ?>
                                        <span class="business-distance ml-auto">
                                            <?php echo number_format($business['distance'], 1); ?>km
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-top: 4px;">
                                    <span class="badge badge-sm status-<?php echo $status['status']; ?>">
                                        <?php echo $status['message']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=YOUR_KAKAO_MAP_KEY&libraries=services"></script>
<script>
let map;
let markers = [];
let infoWindow;
let currentMarker;
let userMarker;

// 지도 초기화
function initMap() {
    const container = document.getElementById('map');
    const options = {
        center: new kakao.maps.LatLng(37.5665, 126.9780), // 서울 중심
        level: 8
    };
    
    map = new kakao.maps.Map(container, options);
    
    // 사용자 위치 표시
    <?php if ($user_lat && $user_lng): ?>
        showUserLocation(<?php echo $user_lat; ?>, <?php echo $user_lng; ?>);
        map.setCenter(new kakao.maps.LatLng(<?php echo $user_lat; ?>, <?php echo $user_lng; ?>));
        map.setLevel(6);
    <?php endif; ?>
    
    // 업체 마커 표시
    showBusinessMarkers();
    
    // 지도 클릭 시 인포윈도우 닫기
    kakao.maps.event.addListener(map, 'click', function() {
        if (infoWindow) {
            infoWindow.close();
        }
        clearBusinessSelection();
    });
}

// 사용자 위치 마커 표시
function showUserLocation(lat, lng) {
    const position = new kakao.maps.LatLng(lat, lng);
    
    userMarker = new kakao.maps.Marker({
        position: position,
        map: map,
        image: new kakao.maps.MarkerImage(
            'https://t1.daumcdn.net/localimg/localimages/07/mapapidoc/markerStar.png',
            new kakao.maps.Size(24, 35)
        )
    });
    
    // 반경 표시
    const circle = new kakao.maps.Circle({
        center: position,
        radius: <?php echo $radius * 1000; ?>, // 미터 단위
        strokeWeight: 2,
        strokeColor: '#ff4757',
        strokeOpacity: 0.8,
        fillColor: '#ff4757',
        fillOpacity: 0.1
    });
    
    circle.setMap(map);
}

// 업체 마커들 표시
function showBusinessMarkers() {
    const businesses = <?php echo json_encode($businesses); ?>;
    
    businesses.forEach((business, index) => {
        if (!business.latitude || !business.longitude) return;
        
        const position = new kakao.maps.LatLng(business.latitude, business.longitude);
        
        const marker = new kakao.maps.Marker({
            position: position,
            map: map,
            title: business.name
        });
        
        markers.push(marker);
        
        // 마커 클릭 이벤트
        kakao.maps.event.addListener(marker, 'click', function() {
            showBusinessInfo(business, marker);
            selectBusinessInList(business.id);
        });
    });
    
    // 모든 마커가 보이도록 지도 범위 조정
    if (markers.length > 0 && !<?php echo $user_lat && $user_lng ? 'true' : 'false'; ?>) {
        const bounds = new kakao.maps.LatLngBounds();
        markers.forEach(marker => bounds.extend(marker.getPosition()));
        map.setBounds(bounds);
    }
}

// 업체 정보 인포윈도우 표시
function showBusinessInfo(business, marker) {
    if (infoWindow) {
        infoWindow.close();
    }
    
    const content = `
        <div style="padding: 15px; min-width: 200px;">
            <h6 style="margin: 0 0 8px 0; font-weight: bold;">${business.name}</h6>
            <p style="margin: 0 0 5px 0; color: #666; font-size: 13px;">${business.category}</p>
            <p style="margin: 0 0 8px 0; color: #666; font-size: 12px;">${business.address}</p>
            <div style="margin-bottom: 10px;">
                <span style="color: #ffc107;">★</span>
                <span style="font-size: 13px;">${(business.avg_rating || 0).toFixed(1)} (${business.review_count || 0})</span>
            </div>
            <a href="${window.location.origin}/view/pages/business_detail.php?id=${business.id}" 
               style="display: inline-block; background: #ff4757; color: white; padding: 6px 12px; 
                      text-decoration: none; border-radius: 4px; font-size: 12px;">
                상세보기
            </a>
        </div>
    `;
    
    infoWindow = new kakao.maps.InfoWindow({
        content: content
    });
    
    infoWindow.open(map, marker);
}

// 업체 선택 (리스트에서)
function selectBusiness(element, businessId) {
    clearBusinessSelection();
    element.classList.add('selected');
    
    const lat = parseFloat(element.dataset.lat);
    const lng = parseFloat(element.dataset.lng);
    
    if (lat && lng) {
        const position = new kakao.maps.LatLng(lat, lng);
        map.setCenter(position);
        map.setLevel(4);
        
        // 해당 마커 클릭
        const business = <?php echo json_encode($businesses); ?>.find(b => b.id == businessId);
        const markerIndex = <?php echo json_encode($businesses); ?>.findIndex(b => b.id == businessId);
        
        if (business && markers[markerIndex]) {
            showBusinessInfo(business, markers[markerIndex]);
        }
    }
}

// 리스트에서 업체 선택
function selectBusinessInList(businessId) {
    clearBusinessSelection();
    const element = document.querySelector(`[data-id="${businessId}"]`);
    if (element) {
        element.classList.add('selected');
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// 업체 선택 해제
function clearBusinessSelection() {
    document.querySelectorAll('.business-list-item.selected').forEach(el => {
        el.classList.remove('selected');
    });
}

// 사이드바 토글
function toggleSidebar() {
    const sidebar = document.getElementById('mapSidebar');
    sidebar.style.display = sidebar.style.display === 'none' ? 'block' : 'none';
}

// 내 위치로 이동
function centerUserLocation() {
    <?php if ($user_lat && $user_lng): ?>
        map.setCenter(new kakao.maps.LatLng(<?php echo $user_lat; ?>, <?php echo $user_lng; ?>));
        map.setLevel(6);
    <?php else: ?>
        getCurrentLocation();
    <?php endif; ?>
}

// 현재 위치 가져오기
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            // URL에 위치 정보 추가하여 새로고침
            const url = new URL(window.location);
            url.searchParams.set('lat', lat);
            url.searchParams.set('lng', lng);
            url.searchParams.set('radius', 5);
            window.location = url;
        }, function(error) {
            alert('위치 정보를 가져올 수 없습니다.');
        });
    } else {
        alert('이 브라우저는 위치 서비스를 지원하지 않습니다.');
    }
}

// 지도 타입 토글
let isRoadview = false;
function toggleMapType() {
    if (!isRoadview) {
        map.setMapTypeId(kakao.maps.MapTypeId.HYBRID);
        isRoadview = true;
    } else {
        map.setMapTypeId(kakao.maps.MapTypeId.ROADMAP);
        isRoadview = false;
    }
}

// 검색 실행
function searchBusinesses() {
    const searchTerm = document.getElementById('searchInput').value;
    const url = new URL(window.location);
    url.searchParams.set('search', searchTerm);
    window.location = url;
}

// 엔터키 검색
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchBusinesses();
    }
});

// 페이지 로드 시 지도 초기화
document.addEventListener('DOMContentLoaded', initMap);
</script>

<?php require_once '../includes/footer.php'; ?> 