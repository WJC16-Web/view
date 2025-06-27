<?php
require_once '../includes/header.php';
require_once '../config/database.php';

$db = getDB();

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// 이벤트 카테고리
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 이벤트 참여 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join') {
    if (!isset($_SESSION['user_id'])) {
        $error = "로그인이 필요합니다.";
    } else {
        $event_id = (int)$_POST['event_id'];
        $user_id = $_SESSION['user_id'];
        
        try {
            // 이미 참여했는지 확인
            $stmt = $db->prepare("SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $user_id]);
            
            if ($stmt->fetch()) {
                $error = "이미 참여한 이벤트입니다.";
            } else {
                // 이벤트 참여 등록
                $stmt = $db->prepare("INSERT INTO event_participants (event_id, user_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$event_id, $user_id]);
                
                // 참여자 수 업데이트
                $stmt = $db->prepare("UPDATE events SET current_participants = current_participants + 1 WHERE id = ?");
                $stmt->execute([$event_id]);
                
                $success = "이벤트 참여가 완료되었습니다!";
            }
        } catch (Exception $e) {
            $error = "이벤트 참여 중 오류가 발생했습니다.";
        }
    }
}

// 검색 조건 구성
$where_conditions = ["e.is_active = 1", "e.end_date >= CURDATE()"];
$params = [];

if ($category) {
    $where_conditions[] = "e.category = ?";
    $params[] = $category;
}

if ($search) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR b.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// 전체 이벤트 수
$count_sql = "SELECT COUNT(*) FROM events e 
              JOIN businesses b ON e.business_id = b.id 
              WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_events = $stmt->fetchColumn();

// 이벤트 목록 조회
$sql = "SELECT e.*, b.name as business_name, b.address as business_address, b.phone as business_phone,
               (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id) as participant_count
        FROM events e 
        JOIN businesses b ON e.business_id = b.id 
        WHERE $where_clause 
        ORDER BY e.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$total_pages = ceil($total_events / $per_page);

// 사용자 참여 이벤트 확인 (로그인한 경우)
$user_events = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT event_id FROM event_participants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_events = array_column($stmt->fetchAll(), 'event_id');
}
?>

<style>
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4rem 0;
    text-align: center;
}

.hero-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.hero-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}

.category-filters {
    background: white;
    padding: 2rem 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 3rem;
}

.filter-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.75rem 1.5rem;
    border: 2px solid #e9ecef;
    background: white;
    color: #495057;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.filter-btn:hover,
.filter-btn.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
    text-decoration: none;
}

.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.event-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.event-image {
    width: 100%;
    height: 200px;
    background-size: cover;
    background-position: center;
    position: relative;
}

.event-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: rgba(255,255,255,0.9);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #667eea;
}

.event-content {
    padding: 1.5rem;
}

.event-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    color: #2c3e50;
}

.event-description {
    color: #666;
    margin-bottom: 1rem;
    line-height: 1.6;
}

.event-period {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #888;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.event-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-event {
    flex: 1;
    padding: 0.75rem;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary-event {
    background: #667eea;
    color: white;
}

.btn-primary-event:hover {
    background: #5a6fd8;
    color: white;
    text-decoration: none;
}

.btn-outline-event {
    border: 2px solid #667eea;
    color: #667eea;
    background: white;
}

.btn-outline-event:hover {
    background: #667eea;
    color: white;
    text-decoration: none;
}

.btn-joined {
    background: #28a745;
    color: white;
    cursor: default;
}

.btn-full {
    background: #dc3545;
    color: white;
    cursor: default;
}

.search-form {
    background: rgba(255,255,255,0.1);
    padding: 1.5rem;
    border-radius: 10px;
    backdrop-filter: blur(10px);
}

.business-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    color: #666;
    font-size: 0.9rem;
}

.business-info i {
    color: #667eea;
}

.price-info {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.original-price {
    text-decoration: line-through;
    color: #999;
    font-size: 0.9rem;
}

.discounted-price {
    color: #dc3545;
    font-weight: 700;
    font-size: 1.1rem;
}

.current-price {
    color: #667eea;
    font-weight: 700;
    font-size: 1.1rem;
}

.discount-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: #dc3545;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.participants-info {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.participants-info i {
    color: #667eea;
    margin-right: 0.5rem;
}

.progress {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    margin-top: 0.5rem;
}

.progress-bar {
    height: 100%;
    background: #667eea;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.no-events {
    text-align: center;
    padding: 4rem 2rem;
    color: #666;
}

.no-events i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 1.5rem;
}

.pagination {
    justify-content: center;
}

.page-link {
    color: #667eea;
    border-color: #dee2e6;
}

.page-item.active .page-link {
    background-color: #667eea;
    border-color: #667eea;
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .events-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .filter-btn {
        width: 200px;
        text-align: center;
    }
}
</style>

<!-- 히어로 섹션 -->
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">🎉 특별 이벤트</h1>
        <p class="hero-subtitle">다양한 할인 혜택과 특별한 이벤트를 만나보세요</p>
    </div>
</div>

<!-- 검색 및 필터 -->
<div class="category-filters">
    <div class="container">
        <form method="GET" class="search-form mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" 
                           placeholder="이벤트명, 업체명으로 검색..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">전체 카테고리</option>
                        <option value="discount" <?php echo $category === 'discount' ? 'selected' : ''; ?>>할인 이벤트</option>
                        <option value="package" <?php echo $category === 'package' ? 'selected' : ''; ?>>패키지 이벤트</option>
                        <option value="hair" <?php echo $category === 'hair' ? 'selected' : ''; ?>>헤어 이벤트</option>
                        <option value="membership" <?php echo $category === 'membership' ? 'selected' : ''; ?>>멤버십 이벤트</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> 검색
                    </button>
                </div>
            </div>
        </form>
        
        <div class="filter-buttons">
            <a href="event.php" class="filter-btn <?php echo !$category ? 'active' : ''; ?>">
                🎯 전체 이벤트
            </a>
            <a href="event.php?category=discount" class="filter-btn <?php echo $category === 'discount' ? 'active' : ''; ?>">
                💰 할인 이벤트
            </a>
            <a href="event.php?category=package" class="filter-btn <?php echo $category === 'package' ? 'active' : ''; ?>">
                📦 패키지 이벤트
            </a>
            <a href="event.php?category=hair" class="filter-btn <?php echo $category === 'hair' ? 'active' : ''; ?>">
                ✂️ 헤어 이벤트
            </a>
            <a href="event.php?category=membership" class="filter-btn <?php echo $category === 'membership' ? 'active' : ''; ?>">
                🌟 멤버십 이벤트
            </a>
        </div>
    </div>
</div>

<!-- 이벤트 목록 -->
<div class="container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($events)): ?>
        <div class="no-events">
            <i class="fas fa-calendar-times"></i>
            <h3>진행 중인 이벤트가 없습니다</h3>
            <p>새로운 이벤트가 준비되면 알려드릴게요!</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <div class="event-image" style="background-image: url('<?php echo $event['image_url'] ?: 'https://via.placeholder.com/400x250/667eea/ffffff?text=' . urlencode($event['title']); ?>')">
                        <div class="event-badge">
                            <?php
                            $category_names = [
                                'discount' => '할인',
                                'package' => '패키지',
                                'hair' => '헤어',
                                'membership' => '멤버십',
                                'massage' => '마사지',
                                'referral' => '추천'
                            ];
                            echo $category_names[$event['category']] ?? '이벤트';
                            ?>
                        </div>
                        <?php if ($event['discount_rate'] > 0): ?>
                            <div class="discount-badge">
                                <?php echo $event['discount_rate']; ?>% 할인
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="event-content">
                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                        <div class="business-info">
                            <i class="fas fa-store"></i>
                            <strong><?php echo htmlspecialchars($event['business_name']); ?></strong>
                        </div>
                        <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                        
                        <?php if ($event['original_price'] > 0): ?>
                            <div class="price-info">
                                <?php if ($event['discounted_price'] > 0): ?>
                                    <span class="original-price">₩<?php echo number_format($event['original_price']); ?></span>
                                    <span class="discounted-price">₩<?php echo number_format($event['discounted_price']); ?></span>
                                <?php else: ?>
                                    <span class="current-price">₩<?php echo number_format($event['original_price']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-period">
                            <i class="fas fa-calendar-alt"></i>
                            <span>
                                <?php echo date('Y.m.d', strtotime($event['start_date'])); ?> ~ 
                                <?php echo date('Y.m.d', strtotime($event['end_date'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($event['max_participants'] > 0): ?>
                            <div class="participants-info">
                                <i class="fas fa-users"></i>
                                <span><?php echo $event['current_participants']; ?> / <?php echo $event['max_participants']; ?>명 참여</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo ($event['current_participants'] / $event['max_participants']) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-actions">
                            <?php if (in_array($event['id'], $user_events)): ?>
                                <button class="btn-event btn-joined" disabled>
                                    <i class="fas fa-check"></i> 참여 완료
                                </button>
                            <?php elseif ($event['max_participants'] > 0 && $event['current_participants'] >= $event['max_participants']): ?>
                                <button class="btn-event btn-full" disabled>
                                    <i class="fas fa-times"></i> 참여 마감
                                </button>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="join">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" class="btn-event btn-primary-event">
                                        <i class="fas fa-heart"></i> 이벤트 참여
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="business_detail.php?id=<?php echo $event['business_id']; ?>" class="btn-event btn-outline-event">
                                <i class="fas fa-store"></i> 업체 보기
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 페이징 -->
        <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($category); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($category); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function shareEvent(eventId) {
    if (navigator.share) {
        navigator.share({
            title: '뷰티북 특별 이벤트',
            text: '뷰티북에서 진행 중인 특별한 이벤트를 확인해보세요!',
            url: window.location.href
        });
    } else {
        // 브라우저가 Web Share API를 지원하지 않는 경우
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            alert('링크가 클립보드에 복사되었습니다!');
        });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?> 