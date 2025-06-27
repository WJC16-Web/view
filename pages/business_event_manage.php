<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// 업체 관리자 권한 확인
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// 업체 정보 조회
$stmt = $db->prepare("SELECT id FROM businesses WHERE owner_id = ?");
$stmt->execute([$user_id]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: business_register.php');
    exit;
}

$business_id = $business['id'];

// 이벤트 등록/수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $category = $_POST['category'];
        $discount_rate = (int)$_POST['discount_rate'];
        $original_price = (float)$_POST['original_price'];
        $discounted_price = (float)$_POST['discounted_price'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $max_participants = (int)$_POST['max_participants'];
        
        $errors = [];
        
        if (empty($title)) $errors[] = "이벤트 제목을 입력해주세요.";
        if (empty($description)) $errors[] = "이벤트 설명을 입력해주세요.";
        if (empty($start_date)) $errors[] = "시작일을 선택해주세요.";
        if (empty($end_date)) $errors[] = "종료일을 선택해주세요.";
        if ($start_date > $end_date) $errors[] = "종료일은 시작일보다 늦어야 합니다.";
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $db->prepare("INSERT INTO events (business_id, title, description, category, discount_rate, original_price, discounted_price, start_date, end_date, max_participants) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$business_id, $title, $description, $category, $discount_rate, $original_price, $discounted_price, $start_date, $end_date, $max_participants]);
                    $success = "이벤트가 등록되었습니다.";
                } else {
                    $event_id = (int)$_POST['event_id'];
                    $stmt = $db->prepare("UPDATE events SET title = ?, description = ?, category = ?, discount_rate = ?, original_price = ?, discounted_price = ?, start_date = ?, end_date = ?, max_participants = ? WHERE id = ? AND business_id = ?");
                    $stmt->execute([$title, $description, $category, $discount_rate, $original_price, $discounted_price, $start_date, $end_date, $max_participants, $event_id, $business_id]);
                    $success = "이벤트가 수정되었습니다.";
                }
            } catch (Exception $e) {
                $errors[] = "이벤트 처리 중 오류가 발생했습니다.";
            }
        }
    } elseif ($action === 'delete') {
        $event_id = (int)$_POST['event_id'];
        try {
            $stmt = $db->prepare("UPDATE events SET is_active = 0 WHERE id = ? AND business_id = ?");
            $stmt->execute([$event_id, $business_id]);
            $success = "이벤트가 삭제되었습니다.";
        } catch (Exception $e) {
            $errors[] = "이벤트 삭제 중 오류가 발생했습니다.";
        }
    }
}

// 이벤트 목록 조회
$stmt = $db->prepare("SELECT e.*, 
                             (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id) as participant_count
                      FROM events e 
                      WHERE e.business_id = ? AND e.is_active = 1 
                      ORDER BY e.created_at DESC");
$stmt->execute([$business_id]);
$events = $stmt->fetchAll();

// 수정할 이벤트 정보 (수정 모드인 경우)
$edit_event = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND business_id = ?");
    $stmt->execute([$edit_id, $business_id]);
    $edit_event = $stmt->fetch();
}
?>

<style>
.business-header {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.event-form {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.event-list {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.event-item {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.event-item:last-child {
    border-bottom: none;
}

.event-info h6 {
    margin-bottom: 0.5rem;
    color: #2c3e50;
}

.event-meta {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.event-stats {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.stat-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
}

.stat-active { background: #d4edda; color: #155724; }
.stat-participants { background: #e3f2fd; color: #1976d2; }
.stat-price { background: #fff3e0; color: #f57c00; }

.btn-group {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    flex: 1;
}
</style>

<div class="business-header">
    <div class="container">
        <h1>🎉 이벤트 관리</h1>
        <p>업체 이벤트를 등록하고 관리하세요</p>
    </div>
</div>

<div class="container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- 이벤트 등록/수정 폼 -->
    <div class="event-form">
        <h4><?php echo $edit_event ? '이벤트 수정' : '새 이벤트 등록'; ?></h4>
        <form method="POST">
            <input type="hidden" name="action" value="<?php echo $edit_event ? 'edit' : 'add'; ?>">
            <?php if ($edit_event): ?>
                <input type="hidden" name="event_id" value="<?php echo $edit_event['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group mb-3">
                <label class="form-label">이벤트 제목 *</label>
                <input type="text" name="title" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_event['title'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group mb-3">
                <label class="form-label">이벤트 설명 *</label>
                <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($edit_event['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">카테고리</label>
                    <select name="category" class="form-select">
                        <option value="discount" <?php echo ($edit_event['category'] ?? '') === 'discount' ? 'selected' : ''; ?>>할인 이벤트</option>
                        <option value="package" <?php echo ($edit_event['category'] ?? '') === 'package' ? 'selected' : ''; ?>>패키지 이벤트</option>
                        <option value="hair" <?php echo ($edit_event['category'] ?? '') === 'hair' ? 'selected' : ''; ?>>헤어 이벤트</option>
                        <option value="membership" <?php echo ($edit_event['category'] ?? '') === 'membership' ? 'selected' : ''; ?>>멤버십 이벤트</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">할인율 (%)</label>
                    <input type="number" name="discount_rate" class="form-control" min="0" max="100"
                           value="<?php echo $edit_event['discount_rate'] ?? 0; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">원가</label>
                    <input type="number" name="original_price" class="form-control" min="0"
                           value="<?php echo $edit_event['original_price'] ?? 0; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">할인가</label>
                    <input type="number" name="discounted_price" class="form-control" min="0"
                           value="<?php echo $edit_event['discounted_price'] ?? 0; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">시작일 *</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo $edit_event['start_date'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">종료일 *</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo $edit_event['end_date'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">최대 참여자 (0=무제한)</label>
                    <input type="number" name="max_participants" class="form-control" min="0"
                           value="<?php echo $edit_event['max_participants'] ?? 0; ?>">
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_event ? '이벤트 수정' : '이벤트 등록'; ?>
                </button>
                <?php if ($edit_event): ?>
                    <a href="business_event_manage.php" class="btn btn-secondary">취소</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- 이벤트 목록 -->
    <div class="event-list">
        <div class="p-3 bg-light">
            <h5 class="mb-0">등록된 이벤트 (<?php echo count($events); ?>개)</h5>
        </div>
        
        <?php if (empty($events)): ?>
            <div class="text-center p-5">
                <i class="fas fa-calendar-plus" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h5>등록된 이벤트가 없습니다</h5>
                <p>첫 번째 이벤트를 등록해보세요!</p>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <div class="event-item">
                    <div class="event-info">
                        <h6><?php echo htmlspecialchars($event['title']); ?></h6>
                        <div class="event-meta">
                            📅 <?php echo date('Y.m.d', strtotime($event['start_date'])); ?> ~ <?php echo date('Y.m.d', strtotime($event['end_date'])); ?>
                        </div>
                        <div class="event-meta">
                            📝 <?php echo htmlspecialchars(substr($event['description'], 0, 50)); ?>...
                        </div>
                        <div class="event-stats">
                            <span class="stat-badge stat-active">활성</span>
                            <span class="stat-badge stat-participants">
                                👥 <?php echo $event['participant_count']; ?>명 참여
                            </span>
                            <?php if ($event['original_price'] > 0): ?>
                                <span class="stat-badge stat-price">
                                    💰 ₩<?php echo number_format($event['discounted_price'] ?: $event['original_price']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="btn-group">
                        <a href="?edit=<?php echo $event['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit"></i> 수정
                        </a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('이벤트를 삭제하시겠습니까?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-trash"></i> 삭제
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 