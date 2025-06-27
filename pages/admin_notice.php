<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

// 공지사항 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $is_important = isset($_POST['is_important']) ? 1 : 0;
        
        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $db->prepare("INSERT INTO notices (title, content, is_important, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$title, $content, $is_important, $_SESSION['user_id']]);
                $success = "공지사항이 등록되었습니다.";
            } catch (Exception $e) {
                $error = "공지사항 등록 중 오류가 발생했습니다.";
            }
        }
    }
}

// 공지사항 목록 (임시 데이터)
$notices = [
    [
        'id' => 1,
        'title' => '시스템 점검 안내',
        'content' => '2024년 1월 20일 새벽 2시~4시 시스템 점검이 있을 예정입니다.',
        'is_important' => 1,
        'created_at' => '2024-01-15 10:00:00'
    ],
    [
        'id' => 2,
        'title' => '새로운 기능 업데이트',
        'content' => '예약 시스템에 새로운 기능이 추가되었습니다.',
        'is_important' => 0,
        'created_at' => '2024-01-14 14:30:00'
    ]
];
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}
.notice-form {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}
.notice-list {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}
.notice-item {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}
.notice-important {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}
</style>

<div class="admin-header">
    <div class="container">
        <h1>📢 공지사항 관리</h1>
        <p>시스템 공지사항을 작성하고 관리합니다</p>
    </div>
</div>

<div class="container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- 공지사항 작성 폼 -->
    <div class="notice-form">
        <h4>새 공지사항 작성</h4>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
                <label class="form-label">제목 *</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">내용 *</label>
                <textarea name="content" class="form-control" rows="5" required></textarea>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_important" class="form-check-input" id="important">
                    <label class="form-check-label" for="important">중요 공지사항</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> 공지사항 등록
            </button>
        </form>
    </div>
    
    <!-- 공지사항 목록 -->
    <div class="notice-list">
        <div class="p-3 bg-light">
            <h5 class="mb-0">공지사항 목록</h5>
        </div>
        
        <?php foreach ($notices as $notice): ?>
            <div class="notice-item <?php echo $notice['is_important'] ? 'notice-important' : ''; ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">
                            <?php if ($notice['is_important']): ?>
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($notice['title']); ?>
                        </h6>
                        <p class="mb-2 text-muted"><?php echo htmlspecialchars($notice['content']); ?></p>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i>
                            <?php echo date('Y.m.d H:i', strtotime($notice['created_at'])); ?>
                        </small>
                    </div>
                    <div class="ms-3">
                        <button class="btn btn-sm btn-outline-primary">수정</button>
                        <button class="btn btn-sm btn-outline-danger">삭제</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 