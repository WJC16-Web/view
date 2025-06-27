<?php
$page_title = '업체 상세 - 뷰티북';
require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();

// 업체 ID 확인
$business_id = $_GET['id'] ?? 0;
if (!$business_id) {
    header('Location: business_list.php');
    exit;
}

// 업체 정보 가져오기
$business_stmt = $db->prepare("
    SELECT b.*, COUNT(DISTINCT t.id) as teacher_count,
           AVG(r.overall_rating) as avg_rating,
           COUNT(DISTINCT r.id) as review_count
    FROM businesses b
    LEFT JOIN teachers t ON b.id = t.business_id AND t.is_active = 1 AND t.is_approved = 1
    LEFT JOIN reviews r ON b.id = r.business_id
    WHERE b.id = ? AND b.is_active = 1 AND b.is_approved = 1
    GROUP BY b.id
");
$business_stmt->execute([$business_id]);
$business = $business_stmt->fetch();

if (!$business) {
    header('Location: business_list.php');
    exit;
}

// 선생님 목록
$teachers_stmt = $db->prepare("
    SELECT t.*, u.name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.business_id = ? AND t.is_active = 1 AND t.is_approved = 1
");
$teachers_stmt->execute([$business_id]);
$teachers = $teachers_stmt->fetchAll();

// 리뷰 목록
$reviews_stmt = $db->prepare("
    SELECT r.*, u.name as customer_name 
    FROM reviews r 
    JOIN users u ON r.customer_id = u.id 
    WHERE r.business_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$reviews_stmt->execute([$business_id]);
$reviews = $reviews_stmt->fetchAll();
?>

<style>
.business-detail-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.business-header {
    background: white;
    border-radius: 15px;
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.business-name {
    font-size: 32px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
}

.business-category {
    background: #ff4757;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    display: inline-block;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 20px;
}

.business-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.meta-icon {
    font-size: 20px;
}

.rating-display {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stars {
    color: #ffc107;
    font-size: 18px;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.main-content, .sidebar {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 20px;
}

.teacher-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.teacher-card {
    border: 2px solid #e1e5e9;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
}

.teacher-card:hover {
    border-color: #ff4757;
    transform: translateY(-5px);
}

.teacher-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 10px;
}

.teacher-specialty {
    color: #666;
    margin-bottom: 15px;
}

.book-btn {
    background: #ff4757;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
}

.review-item {
    border-bottom: 1px solid #eee;
    padding: 20px 0;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.reviewer-name {
    font-weight: bold;
}

.review-date {
    color: #666;
    font-size: 14px;
}

.review-rating {
    color: #ffc107;
    margin-bottom: 10px;
}

.quick-info {
    margin-bottom: 30px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.back-btn {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    margin-bottom: 20px;
    display: inline-block;
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .business-meta {
        grid-template-columns: 1fr;
    }
    
    .teacher-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="business-detail-container">
    <a href="business_list.php" class="back-btn">← 업체 목록으로</a>
    
    <div class="business-header">
        <div class="business-name"><?= htmlspecialchars($business['name']) ?></div>
        <div class="business-category"><?= htmlspecialchars($business['category']) ?></div>
        
        <div class="business-meta">
            <div class="meta-item">
                <span class="meta-icon">📍</span>
                <span><?= htmlspecialchars($business['address']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-icon">📞</span>
                <span><?= htmlspecialchars($business['phone']) ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-icon">👩‍💼</span>
                <span><?= $business['teacher_count'] ?>명의 선생님</span>
            </div>
            <div class="meta-item">
                <div class="rating-display">
                    <span class="stars">
                        <?php
                        $rating = round($business['avg_rating'] ?? 0, 1);
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $rating ? '★' : '☆';
                        }
                        ?>
                    </span>
                    <span><?= $rating ?> (<?= $business['review_count'] ?>개 리뷰)</span>
                </div>
            </div>
        </div>
        
        <?php if ($business['description']): ?>
            <div style="margin-top: 20px; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($business['description'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-grid">
        <div class="main-content">
            <div class="section-title">선생님 소개</div>
            
            <?php if (empty($teachers)): ?>
                <p>등록된 선생님이 없습니다.</p>
            <?php else: ?>
                <div class="teacher-grid">
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="teacher-card">
                            <div class="teacher-name"><?= htmlspecialchars($teacher['name']) ?></div>
                            <?php if ($teacher['specialty']): ?>
                                <div class="teacher-specialty"><?= htmlspecialchars($teacher['specialty']) ?></div>
                            <?php endif; ?>
                            <?php if ($teacher['career']): ?>
                                <div style="color: #666; font-size: 14px; margin-bottom: 15px;">
                                    <?= htmlspecialchars($teacher['career']) ?>
                                </div>
                            <?php endif; ?>
                            <a href="reservation_form.php?business_id=<?= $business_id ?>&teacher_id=<?= $teacher['id'] ?>" class="book-btn">
                                예약하기
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="section-title" style="margin-top: 40px;">고객 리뷰</div>
            
            <?php if (empty($reviews)): ?>
                <p>작성된 리뷰가 없습니다.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <span class="reviewer-name"><?= htmlspecialchars($review['customer_name']) ?></span>
                            <span class="review-date"><?= date('Y.m.d', strtotime($review['created_at'])) ?></span>
                        </div>
                        <div class="review-rating">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $review['overall_rating'] ? '★' : '☆';
                            }
                            ?>
                        </div>
                        <?php if ($review['review_text']): ?>
                            <div><?= nl2br(htmlspecialchars($review['review_text'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="sidebar">
            <div class="section-title">업체 정보</div>
            
            <div class="quick-info">
                <div class="info-item">
                    <span>영업상태</span>
                    <span style="color: #28a745; font-weight: bold;">영업중</span>
                </div>
                <div class="info-item">
                    <span>예약 가능</span>
                    <span style="color: #ff4757; font-weight: bold;">즉시 가능</span>
                </div>
                <div class="info-item">
                    <span>평균 서비스 시간</span>
                    <span>60분</span>
                </div>
                <div class="info-item">
                    <span>주차</span>
                    <span>가능</span>
                </div>
            </div>
            
            <a href="reservation_form.php?business_id=<?= $business_id ?>" 
               style="background: linear-gradient(135deg, #ff4757, #ff6b7a); color: white; padding: 15px; border-radius: 8px; text-decoration: none; display: block; text-align: center; font-weight: bold; font-size: 18px;">
                바로 예약하기
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 