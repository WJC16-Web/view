<?php
$page_title = '뷰티북 - 예약의 새로운 경험';
require_once 'includes/header.php';

// 현재 사용자 정보
$current_user = getCurrentUser();

// 메인 페이지 데이터 가져오기
try {
    $db = getDB();
    
    // 인기 업체 (평점 높은 순)
    $stmt = $db->prepare("
        SELECT b.*, AVG(r.overall_rating) as avg_rating, COUNT(r.id) as review_count,
               bp.photo_url as main_photo
        FROM businesses b
        LEFT JOIN reviews r ON b.id = r.business_id
        LEFT JOIN business_photos bp ON b.id = bp.business_id AND bp.photo_type = 'main'
        WHERE b.is_active = 1 AND b.is_approved = 1
        GROUP BY b.id
        HAVING avg_rating >= 4.0
        ORDER BY avg_rating DESC, review_count DESC
        LIMIT 8
    ");
    $stmt->execute();
    $popular_businesses = $stmt->fetchAll();
    
    // 최근 등록된 업체
    $stmt = $db->prepare("
        SELECT b.*, bp.photo_url as main_photo
        FROM businesses b
        LEFT JOIN business_photos bp ON b.id = bp.business_id AND bp.photo_type = 'main'
        WHERE b.is_active = 1 AND b.is_approved = 1
        ORDER BY b.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $new_businesses = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $popular_businesses = [];
    $new_businesses = [];
}
?>

<style>
/* 홈 전용 스타일 */
.hero-section {
    background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
    color: white;
    padding: 40px 20px;
    text-align: center;
    margin-top: -60px;
    padding-top: 100px;
}

.hero-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}

.hero-subtitle {
    font-size: 16px;
    opacity: 0.9;
    margin-bottom: 30px;
}

.search-container {
    background: white;
    border-radius: 25px;
    padding: 8px;
    margin: 20px 0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.search-input {
    border: none;
    padding: 12px 20px;
    font-size: 16px;
    width: calc(100% - 60px);
    background: transparent;
}

.search-btn {
    width: 44px;
    height: 44px;
    background: #ff4757;
    border: none;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    float: right;
}

.category-section {
    padding: 30px 20px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.category-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: inherit;
    transition: transform 0.3s;
}

.category-item:active {
    transform: scale(0.95);
}

.category-icon {
    width: 60px;
    height: 60px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 8px;
    border: 2px solid #e9ecef;
}

.category-name {
    font-size: 12px;
    text-align: center;
    color: #666;
}

.business-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.business-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    text-decoration: none;
    color: inherit;
    transition: transform 0.3s;
}

.business-card:active {
    transform: scale(0.98);
}

.business-image {
    width: 100%;
    height: 120px;
    background: #f8f9fa;
    background-size: cover;
    background-position: center;
    position: relative;
}

.business-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 71, 87, 0.9);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
}

.business-info {
    padding: 15px;
}

.business-name {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.business-category {
    font-size: 12px;
    color: #999;
    margin-bottom: 8px;
}

.business-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
}

.rating-stars {
    color: #ffc107;
}

.rating-text {
    color: #666;
}

.quick-actions {
    padding: 20px;
    background: white;
    margin: 10px 20px;
    border-radius: 16px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s;
}

.action-item:active {
    transform: scale(0.98);
    background: #e9ecef;
}

.action-icon {
    width: 48px;
    height: 48px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 8px;
}

.action-title {
    font-size: 14px;
    font-weight: 600;
    text-align: center;
}

.stats-section {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin: 10px 20px;
    border-radius: 16px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    text-align: center;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
}

/* 스크롤 영역 */
.horizontal-scroll {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding: 0 20px;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
}

.horizontal-scroll::-webkit-scrollbar {
    display: none;
}

.scroll-item {
    flex: 0 0 160px;
    scroll-snap-align: start;
}

/* 로그인된 사용자용 개인화 */
.user-welcome {
    background: white;
    padding: 20px;
    margin: 10px 20px;
    border-radius: 16px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
}

.welcome-text {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.welcome-subtitle {
    font-size: 14px;
    color: #666;
}

/* 빈 상태 */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}
</style>

<!-- 히어로 섹션 -->
<div class="hero-section">
    <h1 class="hero-title">🌸 뷰티북</h1>
    <p class="hero-subtitle">나만의 뷰티 예약, 간편하고 빠르게</p>
    
    <!-- 검색 -->
    <form action="pages/business_list.php" method="GET" class="search-container">
        <input type="text" name="search" class="search-input" 
               placeholder="업체명, 지역, 서비스 검색...">
        <button type="submit" class="search-btn">
            <i class="fas fa-search"></i>
        </button>
    </form>
</div>

<!-- 사용자 환영 메시지 -->
<?php if ($current_user): ?>
<div class="user-welcome">
    <div class="welcome-text">안녕하세요, <?php echo htmlspecialchars($current_user['name']); ?>님! 👋</div>
    <div class="welcome-subtitle">오늘도 아름다운 하루 되세요</div>
</div>
<?php endif; ?>

<!-- 카테고리 섹션 -->
<div class="category-section">
    <h2 class="section-title">
        <i class="fas fa-spa"></i>
        서비스 카테고리
    </h2>
    
    <div class="category-grid">
        <a href="pages/business_list.php?category=nail" class="category-item">
            <div class="category-icon">💅</div>
            <span class="category-name">네일</span>
        </a>
        <a href="pages/business_list.php?category=hair" class="category-item">
            <div class="category-icon">💇‍♀️</div>
            <span class="category-name">헤어</span>
        </a>
        <a href="pages/business_list.php?category=skincare" class="category-item">
            <div class="category-icon">🧴</div>
            <span class="category-name">피부관리</span>
        </a>
        <a href="pages/business_list.php?category=massage" class="category-item">
            <div class="category-icon">💆‍♀️</div>
            <span class="category-name">마사지</span>
        </a>
        <a href="pages/business_list.php?category=waxing" class="category-item">
            <div class="category-icon">🪒</div>
            <span class="category-name">왁싱</span>
        </a>
        <a href="pages/business_list.php?category=makeup" class="category-item">
            <div class="category-icon">💄</div>
            <span class="category-name">메이크업</span>
        </a>
        <a href="pages/business_list.php?category=tanning" class="category-item">
            <div class="category-icon">🌞</div>
            <span class="category-name">태닝</span>
        </a>
        <a href="pages/business_list.php" class="category-item">
            <div class="category-icon">➕</div>
            <span class="category-name">전체보기</span>
        </a>
    </div>
</div>

<!-- 빠른 액션 -->
<div class="quick-actions">
    <h2 class="section-title">
        <i class="fas fa-bolt"></i>
        빠른 서비스
    </h2>
    
    <div class="action-grid">
        <?php if ($current_user && $current_user['user_type'] === 'customer'): ?>
            <a href="pages/customer_mypage.php?tab=reservations" class="action-item">
                <div class="action-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="action-title">내 예약</div>
            </a>
            <a href="pages/customer_mypage.php?tab=favorites" class="action-item">
                <div class="action-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="action-title">즐겨찾기</div>
            </a>
        <?php else: ?>
            <a href="pages/register.php?type=customer" class="action-item">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">회원가입</div>
            </a>
            <a href="pages/business_register.php" class="action-item">
                <div class="action-icon">
                    <i class="fas fa-store"></i>
                </div>
                <div class="action-title">업체등록</div>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 인기 업체 -->
<?php if (!empty($popular_businesses)): ?>
<div class="category-section">
    <h2 class="section-title">
        <i class="fas fa-star"></i>
        인기 업체
    </h2>
    
    <div class="business-grid">
        <?php foreach (array_slice($popular_businesses, 0, 4) as $business): ?>
            <a href="pages/business_detail.php?id=<?php echo $business['id']; ?>" class="business-card">
                <div class="business-image" style="background-image: url('<?php echo $business['main_photo'] ? htmlspecialchars($business['main_photo']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjEwMCIgeT0iNjAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzZjNzU3ZCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPm5vIGltYWdlPC90ZXh0Pjwvc3ZnPg=='; ?>')">
                    <?php if ($business['avg_rating'] >= 4.5): ?>
                        <div class="business-badge">HOT</div>
                    <?php endif; ?>
                </div>
                <div class="business-info">
                    <div class="business-name"><?php echo htmlspecialchars($business['name']); ?></div>
                    <div class="business-category"><?php echo htmlspecialchars($business['category']); ?></div>
                    <div class="business-rating">
                        <span class="rating-stars">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($business['avg_rating']) ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <span class="rating-text">
                            <?php echo number_format($business['avg_rating'], 1); ?> (<?php echo $business['review_count']; ?>)
                        </span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="pages/business_list.php?sort=rating" class="btn btn-outline">
            더 많은 인기 업체 보기
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 새로운 업체 -->
<?php if (!empty($new_businesses)): ?>
<div class="category-section">
    <h2 class="section-title">
        <i class="fas fa-plus-circle"></i>
        새로운 업체
    </h2>
    
    <div class="horizontal-scroll">
        <?php foreach ($new_businesses as $business): ?>
            <div class="scroll-item">
                <a href="pages/business_detail.php?id=<?php echo $business['id']; ?>" class="business-card">
                    <div class="business-image" style="background-image: url('<?php echo $business['main_photo'] ? htmlspecialchars($business['main_photo']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjEwMCIgeT0iNjAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzZjNzU3ZCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPm5vIGltYWdlPC90ZXh0Pjwvc3ZnPg=='; ?>')">
                        <div class="business-badge">NEW</div>
                    </div>
                    <div class="business-info">
                        <div class="business-name"><?php echo htmlspecialchars($business['name']); ?></div>
                        <div class="business-category"><?php echo htmlspecialchars($business['category']); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 통계 섹션 -->
<div class="stats-section">
    <h2 class="section-title" style="color: white; margin-bottom: 20px;">
        <i class="fas fa-chart-bar"></i>
        뷰티북 현황
    </h2>
    
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-number">1,200+</div>
            <div class="stat-label">등록 업체</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">15,000+</div>
            <div class="stat-label">이용 고객</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">4.8★</div>
            <div class="stat-label">평균 만족도</div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 검색 폼 처리
    $('.search-container form').submit(function(e) {
        const searchValue = $('.search-input').val().trim();
        if (!searchValue) {
            e.preventDefault();
            $('.search-input').focus();
            return false;
        }
    });
    
    // 카테고리 터치 피드백
    $('.category-item, .action-item, .business-card').on('touchstart', function() {
        $(this).css('opacity', '0.7');
    }).on('touchend touchcancel', function() {
        $(this).css('opacity', '');
    });
    
    // 무한 스크롤 (추후 구현)
    let isLoading = false;
    $(window).scroll(function() {
        if ($(window).scrollTop() + $(window).height() >= $(document).height() - 1000) {
            if (!isLoading) {
                // loadMoreContent();
            }
        }
    });
    
    // 위치 기반 추천 (추후 구현)
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            // 위치 기반 업체 추천 로직
            console.log('현재 위치:', position.coords.latitude, position.coords.longitude);
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 