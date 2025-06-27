<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

startSession();
checkAuth();

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// 문의 작성 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    
    if (!$category || !$subject || !$content) {
        $error = '모든 필수 항목을 입력해주세요.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO inquiries (
                    user_id, category, subject, content, is_private, 
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([$user_id, $category, $subject, $content, $is_private]);
            $success = '문의가 등록되었습니다. 빠른 시일 내에 답변드리겠습니다.';
            
            // 관리자에게 알림
            addNotification(
                1, // 관리자 ID (실제로는 관리자 계정들을 조회해야 함)
                'new_inquiry',
                '새 문의 등록',
                "새로운 문의가 등록되었습니다: {$subject}"
            );
            
        } catch (Exception $e) {
            $error = '문의 등록 중 오류가 발생했습니다.';
        }
    }
}

// 문의 목록 조회
$page = intval($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where_conditions = ["user_id = ?"];
$params = [$user_id];

$status_filter = $_GET['status'] ?? '';
if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$category_filter = $_GET['category'] ?? '';
if ($category_filter) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// 전체 개수 조회
$stmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE $where_clause");
$stmt->execute($params);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// 문의 목록 조회
$stmt = $db->prepare("
    SELECT i.*, u.name as admin_name
    FROM inquiries i
    LEFT JOIN users u ON i.answered_by = u.id
    WHERE $where_clause
    ORDER BY i.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<style>
.inquiry-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.inquiry-header {
    background: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.inquiry-tabs {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    border-bottom: 1px solid #eee;
}

.tab-btn {
    padding: 12px 24px;
    border: none;
    background: none;
    color: #666;
    font-weight: 500;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s;
}

.tab-btn.active {
    color: #ff4757;
    border-bottom-color: #ff4757;
}

.tab-btn:hover {
    color: #ff4757;
}

.inquiry-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.inquiry-form {
    padding: 30px;
    display: none;
}

.inquiry-form.active {
    display: block;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group select:focus,
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #ff4757;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-group .checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
}

.form-group .checkbox-wrapper input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.submit-btn {
    background: #ff4757;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s;
}

.submit-btn:hover {
    background: #ff3838;
}

.inquiry-list {
    padding: 30px;
    display: none;
}

.inquiry-list.active {
    display: block;
}

.inquiry-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: white;
}

.inquiry-item {
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s;
    cursor: pointer;
}

.inquiry-item:hover {
    border-color: #ff4757;
    box-shadow: 0 2px 8px rgba(255, 71, 87, 0.1);
}

.inquiry-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.inquiry-category {
    background: #e9ecef;
    color: #495057;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.inquiry-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-answered {
    background: #d4edda;
    color: #155724;
}

.status-closed {
    background: #f8d7da;
    color: #721c24;
}

.inquiry-subject {
    font-weight: bold;
    color: #333;
    margin-bottom: 8px;
}

.inquiry-preview {
    color: #666;
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 10px;
}

.inquiry-date {
    color: #999;
    font-size: 13px;
}

.inquiry-detail {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}

.inquiry-detail.active {
    display: flex;
}

.detail-content {
    background: white;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.detail-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.detail-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.detail-footer {
    background: #f8f9fa;
    padding: 15px 20px;
    border-top: 1px solid #eee;
    text-align: right;
}

.close-btn {
    background: #6c757d;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
}

.answer-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-top: 15px;
}

.answer-header {
    font-weight: bold;
    color: #495057;
    margin-bottom: 8px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
}

.pagination a,
.pagination span {
    padding: 8px 12px;
    border: 1px solid #ddd;
    text-decoration: none;
    color: #333;
    border-radius: 4px;
}

.pagination a:hover {
    background: #f8f9fa;
}

.pagination .current {
    background: #ff4757;
    color: white;
    border-color: #ff4757;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .inquiry-container {
        padding: 15px;
    }
    
    .inquiry-header,
    .inquiry-form,
    .inquiry-list {
        padding: 20px;
    }
    
    .inquiry-tabs {
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .inquiry-filters {
        flex-direction: column;
    }
    
    .inquiry-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .detail-content {
        width: 95%;
        margin: 20px;
    }
}
</style>

<div class="inquiry-container">
    <!-- 헤더 -->
    <div class="inquiry-header">
        <h2><i class="fas fa-headset"></i> 고객 지원</h2>
        <p>궁금한 점이나 문제가 있으시면 언제든지 문의해주세요. 빠르게 도움을 드리겠습니다.</p>
    </div>
    
    <!-- 알림 메시지 -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- 탭 메뉴 -->
    <div class="inquiry-tabs">
        <button class="tab-btn active" onclick="switchTab('list')">
            <i class="fas fa-list"></i> 내 문의 내역
        </button>
        <button class="tab-btn" onclick="switchTab('create')">
            <i class="fas fa-plus"></i> 새 문의 작성
        </button>
    </div>
    
    <div class="inquiry-content">
        <!-- 문의 목록 -->
        <div class="inquiry-list active" id="listTab">
            <!-- 필터 -->
            <div class="inquiry-filters">
                <select class="filter-select" onchange="applyFilter('status', this.value)">
                    <option value="">전체 상태</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                        답변 대기
                    </option>
                    <option value="answered" <?php echo $status_filter === 'answered' ? 'selected' : ''; ?>>
                        답변 완료
                    </option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>
                        종료
                    </option>
                </select>
                
                <select class="filter-select" onchange="applyFilter('category', this.value)">
                    <option value="">전체 분류</option>
                    <option value="reservation" <?php echo $category_filter === 'reservation' ? 'selected' : ''; ?>>
                        예약 관련
                    </option>
                    <option value="payment" <?php echo $category_filter === 'payment' ? 'selected' : ''; ?>>
                        결제/환불
                    </option>
                    <option value="business" <?php echo $category_filter === 'business' ? 'selected' : ''; ?>>
                        업체 신고
                    </option>
                    <option value="technical" <?php echo $category_filter === 'technical' ? 'selected' : ''; ?>>
                        기술 지원
                    </option>
                    <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>
                        기타 문의
                    </option>
                </select>
            </div>
            
            <!-- 문의 목록 -->
            <?php if (empty($inquiries)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-comments" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>등록된 문의가 없습니다.</p>
                    <button class="submit-btn" onclick="switchTab('create')" style="margin-top: 15px;">
                        첫 문의 작성하기
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($inquiries as $inquiry): ?>
                    <div class="inquiry-item" onclick="showInquiryDetail(<?php echo $inquiry['id']; ?>)">
                        <div class="inquiry-meta">
                            <div>
                                <span class="inquiry-category">
                                    <?php 
                                    $categories = [
                                        'reservation' => '예약 관련',
                                        'payment' => '결제/환불',
                                        'business' => '업체 신고',
                                        'technical' => '기술 지원',
                                        'other' => '기타 문의'
                                    ];
                                    echo $categories[$inquiry['category']] ?? $inquiry['category'];
                                    ?>
                                </span>
                                <?php if ($inquiry['is_private']): ?>
                                    <span class="inquiry-category" style="background: #ff4757; color: white;">
                                        <i class="fas fa-lock"></i> 비공개
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <span class="inquiry-status status-<?php echo $inquiry['status']; ?>">
                                <?php 
                                $statuses = [
                                    'pending' => '답변 대기',
                                    'answered' => '답변 완료',
                                    'closed' => '종료'
                                ];
                                echo $statuses[$inquiry['status']] ?? $inquiry['status'];
                                ?>
                            </span>
                        </div>
                        
                        <div class="inquiry-subject">
                            <?php echo htmlspecialchars($inquiry['subject']); ?>
                        </div>
                        
                        <div class="inquiry-preview">
                            <?php echo htmlspecialchars(mb_substr($inquiry['content'], 0, 100)); ?>
                            <?php if (mb_strlen($inquiry['content']) > 100): ?>...<?php endif; ?>
                        </div>
                        
                        <div class="inquiry-date">
                            <i class="fas fa-clock"></i>
                            <?php echo date('Y-m-d H:i', strtotime($inquiry['created_at'])); ?>
                            
                            <?php if ($inquiry['answered_at']): ?>
                                | 답변: <?php echo date('Y-m-d H:i', strtotime($inquiry['answered_at'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- 페이지네이션 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- 문의 작성 폼 -->
        <div class="inquiry-form" id="createTab">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="category">문의 분류 *</label>
                    <select name="category" id="category" required>
                        <option value="">분류를 선택해주세요</option>
                        <option value="reservation">예약 관련</option>
                        <option value="payment">결제/환불</option>
                        <option value="business">업체 신고</option>
                        <option value="technical">기술 지원</option>
                        <option value="other">기타 문의</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject">제목 *</label>
                    <input type="text" name="subject" id="subject" required 
                           placeholder="문의 제목을 입력해주세요">
                </div>
                
                <div class="form-group">
                    <label for="content">문의 내용 *</label>
                    <textarea name="content" id="content" required 
                              placeholder="문의 내용을 자세히 작성해주세요. 문제 상황, 발생 시간, 에러 메시지 등을 포함하면 더 빠른 해결이 가능합니다."></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" name="is_private" id="is_private">
                        <label for="is_private">비공개 문의 (개인정보가 포함된 경우 체크)</label>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    문의 등록하기
                </button>
            </form>
        </div>
    </div>
</div>

<!-- 문의 상세 모달 -->
<div class="inquiry-detail" id="inquiryDetailModal">
    <div class="detail-content">
        <div class="detail-header">
            <h4 id="detailSubject"></h4>
            <button class="close-btn" onclick="closeInquiryDetail()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="detail-body">
            <div id="detailContent"></div>
            <div id="detailAnswer"></div>
        </div>
        
        <div class="detail-footer">
            <div id="detailMeta"></div>
        </div>
    </div>
</div>

<script>
// 탭 전환
function switchTab(tab) {
    // 탭 버튼 스타일 변경
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // 탭 내용 전환
    document.querySelectorAll('.inquiry-list, .inquiry-form').forEach(content => {
        content.classList.remove('active');
    });
    
    if (tab === 'list') {
        document.getElementById('listTab').classList.add('active');
    } else {
        document.getElementById('createTab').classList.add('active');
    }
}

// 필터 적용
function applyFilter(type, value) {
    const url = new URL(window.location);
    url.searchParams.set(type, value);
    url.searchParams.set('page', '1'); // 첫 페이지로 리셋
    window.location = url;
}

// 문의 상세 보기
function showInquiryDetail(inquiryId) {
    fetch(`../api/get_inquiry.php?id=${inquiryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const inquiry = data.inquiry;
                
                document.getElementById('detailSubject').textContent = inquiry.subject;
                document.getElementById('detailContent').innerHTML = `
                    <div style="margin-bottom: 15px;">
                        <strong>분류:</strong> ${getCategoryName(inquiry.category)}
                        ${inquiry.is_private ? '<span style="color: #ff4757; margin-left: 10px;"><i class="fas fa-lock"></i> 비공개</span>' : ''}
                    </div>
                    <div style="white-space: pre-wrap; line-height: 1.6;">${inquiry.content}</div>
                `;
                
                let answerHtml = '';
                if (inquiry.answer_content) {
                    answerHtml = `
                        <div class="answer-section">
                            <div class="answer-header">
                                <i class="fas fa-reply"></i> 답변 (${inquiry.admin_name || '관리자'})
                            </div>
                            <div style="white-space: pre-wrap; line-height: 1.6;">${inquiry.answer_content}</div>
                        </div>
                    `;
                }
                document.getElementById('detailAnswer').innerHTML = answerHtml;
                
                document.getElementById('detailMeta').innerHTML = `
                    <small class="text-muted">
                        작성일: ${formatDate(inquiry.created_at)}
                        ${inquiry.answered_at ? ' | 답변일: ' + formatDate(inquiry.answered_at) : ''}
                    </small>
                `;
                
                document.getElementById('inquiryDetailModal').classList.add('active');
            } else {
                alert('문의 내용을 불러올 수 없습니다.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('오류가 발생했습니다.');
        });
}

// 문의 상세 닫기
function closeInquiryDetail() {
    document.getElementById('inquiryDetailModal').classList.remove('active');
}

// 분류명 변환
function getCategoryName(category) {
    const categories = {
        'reservation': '예약 관련',
        'payment': '결제/환불',
        'business': '업체 신고',
        'technical': '기술 지원',
        'other': '기타 문의'
    };
    return categories[category] || category;
}

// 날짜 포맷팅
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ko-KR') + ' ' + date.toLocaleTimeString('ko-KR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// 모달 외부 클릭 시 닫기
document.getElementById('inquiryDetailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeInquiryDetail();
    }
});

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeInquiryDetail();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>