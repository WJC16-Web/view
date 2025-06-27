<?php
$page_title = '뷰티북 - 예약의 새로운 경험';
require_once 'includes/header.php';

// 인기 업체 가져오기
$db = getDB();
$popular_businesses_stmt = $db->prepare("
    SELECT b.*, 
           AVG(r.overall_rating) as avg_rating,
           COUNT(DISTINCT r.id) as review_count,
           bp.photo_url as main_photo,
           (SELECT COUNT(*) FROM teachers t WHERE t.business_id = b.id AND t.is_active = 1 AND t.is_approved = 1) as teacher_count
    FROM businesses b
    LEFT JOIN reviews r ON b.id = r.business_id
    LEFT JOIN business_photos bp ON b.id = bp.business_id AND bp.photo_type = 'main'
    WHERE b.is_active = 1 AND b.is_approved = 1
    GROUP BY b.id
    HAVING teacher_count > 0
    ORDER BY avg_rating DESC, review_count DESC
    LIMIT 8
");
$popular_businesses_stmt->execute();
$popular_businesses = $popular_businesses_stmt->fetchAll();

// 신규 업체 가져오기
$new_businesses_stmt = $db->prepare("
    SELECT b.*, 
           bp.photo_url as main_photo,
           (SELECT COUNT(*) FROM teachers t WHERE t.business_id = b.id AND t.is_active = 1 AND t.is_approved = 1) as teacher_count
    FROM businesses b
    LEFT JOIN business_photos bp ON b.id = bp.business_id AND bp.photo_type = 'main'
    WHERE b.is_active = 1 AND b.is_approved = 1
    AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY b.id
    HAVING teacher_count > 0
    ORDER BY b.created_at DESC
    LIMIT 6
");
$new_businesses_stmt->execute();
$new_businesses = $new_businesses_stmt->fetchAll();
?>

<style>
/* 메인 페이지 스타일 */
.hero-section {
    background: linear-gradient(135deg, #ff4757, #ff6b7a);
    color: white;
    padding: 100px 0;
    text-align: center;
}

.hero-content h1 {
    font-size: 48px;
    margin-bottom: 20px;
    font-weight: bold;
}

.hero-content p {
    font-size: 20px;
    margin-bottom: 40px;
    opacity: 0.9;
}

.hero-search {
    max-width: 600px;
    margin: 0 auto;
    position: relative;
}

.hero-search-input {
    width: 100%;
    padding: 20px 60px 20px 25px;
    border: none;
    border-radius: 50px;
    font-size: 18px;
    outline: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.hero-search-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: #2c3e50;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 50px;
    cursor: pointer;
    font-size: 16px;
}

.categories-section {
    padding: 80px 0;
    background: white;
}

.section-title {
    text-align: center;
    font-size: 36px;
    margin-bottom: 50px;
    color: #2c3e50;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
}

.category-card {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s;
    text-decoration: none;
    color: #333;
}

.category-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
    color: #ff4757;
}

.category-icon {
    font-size: 48px;
    margin-bottom: 20px;
    display: block;
}

.category-name {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 10px;
}

.category-desc {
    color: #666;
    font-size: 14px;
}

.businesses-section {
    padding: 80px 0;
    background: #f8f9fa;
}

.business-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-top: 40px;
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
    background: #ddd;
    background-size: cover;
    background-position: center;
    position: relative;
}

.business-status {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.status-open { background: #27ae60; }
.status-busy { background: #f39c12; }
.status-break { background: #3498db; }
.status-closed { background: #95a5a6; }

.business-info {
    padding: 20px;
}

.business-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #2c3e50;
}

.business-category {
    color: #ff4757;
    font-size: 14px;
    margin-bottom: 10px;
}

.business-address {
    color: #666;
    font-size: 14px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.business-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.rating-stars {
    color: #ffc107;
}

.rating-text {
    color: #666;
    font-size: 14px;
}

.business-price {
    color: #2c3e50;
    font-weight: bold;
}

.features-section {
    padding: 80px 0;
    background: white;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 40px;
    margin-top: 40px;
}

.feature-card {
    text-align: center;
    padding: 40px 20px;
}

.feature-icon {
    width: 80px;
    height: 80px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin: 0 auto 30px;
}

.feature-title {
    font-size: 24px;
    margin-bottom: 20px;
    color: #2c3e50;
}

.feature-desc {
    color: #666;
    line-height: 1.6;
}

.cta-section {
    padding: 80px 0;
    background: linear-gradient(135deg, #2c3e50, #34495e);
    color: white;
    text-align: center;
}

.cta-content h2 {
    font-size: 36px;
    margin-bottom: 20px;
}

.cta-content p {
    font-size: 18px;
    margin-bottom: 40px;
    opacity: 0.9;
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.btn-large {
    padding: 15px 40px;
    font-size: 18px;
    border-radius: 50px;
}

/* 반응형 */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 32px;
    }
    
    .hero-content p {
        font-size: 16px;
    }
    
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .business-grid {
        grid-template-columns: 1fr;
    }
    
    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<!-- 히어로 섹션 -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1>🌟 뷰티의 모든 것, 뷰티북 🌟</h1>
            <p>내가 원하는 시간에, 원하는 곳에서<br>간편하게 예약하고 아름다워지세요</p>
            
            <div class="hero-search">
                <form action="<?php echo BASE_URL; ?>/pages/business_list.php" method="GET">
                    <input type="text" name="search" class="hero-search-input" 
                           placeholder="지역, 업체명, 서비스를 검색해보세요...">
                    <button type="submit" class="hero-search-btn">
                        <i class="fas fa-search"></i> 검색
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- 카테고리 섹션 -->
<section class="categories-section">
    <div class="container">
        <h2 class="section-title">어떤 서비스를 찾고 계세요?</h2>
        
        <div class="categories-grid">
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=nail" class="category-card">
                <span class="category-icon">💅</span>
                <div class="category-name">네일</div>
                <div class="category-desc">젤네일, 네일아트, 케어</div>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=hair" class="category-card">
                <span class="category-icon">💇‍♀️</span>
                <div class="category-name">헤어</div>
                <div class="category-desc">컷, 펌, 염색, 케어</div>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=waxing" class="category-card">
                <span class="category-icon">🪒</span>
                <div class="category-name">왁싱</div>
                <div class="category-desc">브라질리언, 비키니, 다리</div>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=skincare" class="category-card">
                <span class="category-icon">🧴</span>
                <div class="category-name">피부관리</div>
                <div class="category-desc">관리, 마사지, 트리트먼트</div>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=massage" class="category-card">
                <span class="category-icon">💆‍♀️</span>
                <div class="category-name">마사지</div>
                <div class="category-desc">타이, 스웨디시, 딥티슈</div>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=makeup" class="category-card">
                <span class="category-icon">💄</span>
                <div class="category-name">메이크업</div>
                <div class="category-desc">웨딩, 파티, 데일리</div>
            </a>
        </div>
    </div>
</section>

<!-- 인기 업체 섹션 -->
<?php if (!empty($popular_businesses)): ?>
<section class="businesses-section">
    <div class="container">
        <h2 class="section-title">⭐ 인기 업체</h2>
        <p style="text-align: center; color: #666; margin-bottom: 40px;">평점이 높고 예약이 많은 업체들을 만나보세요</p>
        
        <div class="business-grid">
            <?php foreach ($popular_businesses as $business): 
                $status = getBusinessStatus($business['id']);
            ?>
                <a href="<?php echo BASE_URL; ?>/pages/business_detail.php?id=<?php echo $business['id']; ?>" class="business-card">
                    <div class="business-image" style="background-image: url('<?php echo $business['main_photo'] ? BASE_URL . '/' . $business['main_photo'] : BASE_URL . '/assets/images/no-image.jpg'; ?>')">
                        <span class="business-status status-<?php echo $status['status']; ?>">
                            <?php echo $status['message']; ?>
                        </span>
                    </div>
                    <div class="business-info">
                        <div class="business-category"><?php echo htmlspecialchars($business['category']); ?></div>
                        <div class="business-name"><?php echo htmlspecialchars($business['name']); ?></div>
                        <div class="business-address">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($business['address']); ?>
                        </div>
                        <div class="business-rating">
                            <span class="rating-stars">
                                <?php echo displayRating($business['avg_rating'] ?: 0, false); ?>
                            </span>
                            <span class="rating-text">
                                <?php echo number_format($business['avg_rating'] ?: 0, 1); ?> (<?php echo $business['review_count']; ?>)
                            </span>
                        </div>
                        <div class="business-price">
                            서비스 2만원부터
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="<?php echo BASE_URL; ?>/pages/business_list.php?sort=rating" class="btn btn-outline">
                더 많은 인기 업체 보기 <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 신규 업체 섹션 -->
<?php if (!empty($new_businesses)): ?>
<section class="businesses-section" style="background: white;">
    <div class="container">
        <h2 class="section-title">🆕 신규 업체</h2>
        <p style="text-align: center; color: #666; margin-bottom: 40px;">최근 30일 내에 새로 오픈한 업체들을 확인해보세요</p>
        
        <div class="business-grid">
            <?php foreach ($new_businesses as $business): 
                $status = getBusinessStatus($business['id']);
            ?>
                <a href="<?php echo BASE_URL; ?>/pages/business_detail.php?id=<?php echo $business['id']; ?>" class="business-card">
                    <div class="business-image" style="background-image: url('<?php echo $business['main_photo'] ? BASE_URL . '/' . $business['main_photo'] : BASE_URL . '/assets/images/no-image.jpg'; ?>')">
                        <span class="business-status status-<?php echo $status['status']; ?>">
                            <?php echo $status['message']; ?>
                        </span>
                    </div>
                    <div class="business-info">
                        <div class="business-category"><?php echo htmlspecialchars($business['category']); ?></div>
                        <div class="business-name"><?php echo htmlspecialchars($business['name']); ?></div>
                        <div class="business-address">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($business['address']); ?>
                        </div>
                        <div class="business-price">
                            신규 오픈 혜택 중!
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 특징 섹션 -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title">왜 뷰티북을 선택해야 할까요?</h2>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="feature-title">실시간 예약</h3>
                <p class="feature-desc">
                    선생님별 실시간 스케줄을 확인하고<br>
                    즉시 예약할 수 있어요
                </p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="feature-title">검증된 업체</h3>
                <p class="feature-desc">
                    리뷰와 평점을 통해<br>
                    믿을 수 있는 업체만 만나보세요
                </p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3 class="feature-title">간편한 관리</h3>
                <p class="feature-desc">
                    예약부터 취소까지<br>
                    모든 과정이 간편해요
                </p>
            </div>
        </div>
    </div>
</section>

<!-- CTA 섹션 -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2>지금 시작해보세요!</h2>
            <p>뷰티북과 함께 더 아름다운 당신을 만나보세요</p>
            
            <div class="cta-buttons">
                <?php if (!$current_user): ?>
                    <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus"></i> 회원가입하기
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/business_register.php" class="btn btn-outline btn-large">
                        <i class="fas fa-store"></i> 업체 등록하기
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/pages/business_list.php" class="btn btn-primary btn-large">
                        <i class="fas fa-search"></i> 업체 찾아보기
                    </a>
                    <?php if ($current_user['user_type'] === 'customer'): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/reservation_manage.php" class="btn btn-outline btn-large">
                            <i class="fas fa-calendar"></i> 내 예약 관리
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?> 