<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

// FAQ 데이터 (실제로는 DB에서 가져와야 함)
$faq_categories = [
    'all' => '전체',
    'reservation' => '예약/취소',
    'payment' => '결제/환불',
    'account' => '회원가입/로그인',
    'service' => '서비스 이용',
    'business' => '업체 관리',
    'etc' => '기타'
];

$faqs = [
    [
        'id' => 1,
        'category' => 'reservation',
        'question' => '예약은 어떻게 하나요?',
        'answer' => '1. 원하는 업체를 검색하거나 리스트에서 선택합니다.<br>
                    2. 업체 상세페이지에서 "예약하기" 버튼을 클릭합니다.<br>
                    3. 서비스, 선생님, 날짜/시간을 선택합니다.<br>
                    4. 고객 정보와 요청사항을 입력합니다.<br>
                    5. 결제 방법을 선택하고 예약을 완료합니다.<br><br>
                    예약 완료 후 업체에서 승인하면 확정됩니다.',
        'tags' => '예약, 방법, 절차',
        'helpful' => 245,
        'created_at' => '2024-01-15'
    ],
    [
        'id' => 2,
        'category' => 'reservation',
        'question' => '예약을 취소하고 싶어요',
        'answer' => '예약 취소는 다음과 같이 진행하세요:<br><br>
                    <strong>신청중 상태:</strong> 언제든지 취소 가능합니다.<br>
                    <strong>확정 상태:</strong> 고객이 직접 취소할 수 없으며, 업체에 문의하셔야 합니다.<br><br>
                    취소 방법:<br>
                    1. 마이페이지 > 예약관리에서 해당 예약을 찾습니다.<br>
                    2. "취소" 버튼을 클릭합니다.<br>
                    3. 취소 사유를 입력하고 확인합니다.<br><br>
                    <em>※ 시술 시작 시간 1시간 전까지만 취소 가능합니다.</em>',
        'tags' => '예약취소, 취소방법, 환불',
        'helpful' => 189,
        'created_at' => '2024-01-15'
    ],
    [
        'id' => 3,
        'category' => 'payment',
        'question' => '결제 방법은 어떤 것들이 있나요?',
        'answer' => '다음과 같은 결제 방법을 지원합니다:<br><br>
                    <strong>현장결제</strong><br>
                    - 방문 시 현금 또는 카드로 결제<br>
                    - 추가 할인 혜택 없음<br><br>
                    <strong>전액 선결제</strong><br>
                    - 온라인에서 신용카드, 카카오페이, 네이버페이 등으로 결제<br>
                    - 5-10% 할인 혜택 제공<br><br>
                    <strong>예약금 결제</strong><br>
                    - 서비스 금액의 10-30% 예약금만 선결제<br>
                    - 나머지 금액은 방문 시 결제<br>
                    - 업체별로 예약금 정책이 다를 수 있습니다.',
        'tags' => '결제, 결제방법, 할인',
        'helpful' => 156,
        'created_at' => '2024-01-16'
    ],
    [
        'id' => 4,
        'category' => 'payment',
        'question' => '환불은 언제 받을 수 있나요?',
        'answer' => '환불 정책은 다음과 같습니다:<br><br>
                    <strong>취소 시점에 따른 환불율</strong><br>
                    - 24시간 전: 100% 환불<br>
                    - 12시간 전: 90% 환불<br>
                    - 6시간 전: 70% 환불<br>
                    - 1시간 전: 50% 환불<br>
                    - 1시간 미만: 환불 불가<br><br>
                    <strong>환불 처리 시간</strong><br>
                    - 신용카드: 3-5영업일<br>
                    - 계좌이체: 1-2영업일<br>
                    - 간편결제: 즉시 (카카오페이, 네이버페이)<br><br>
                    <em>※ 업체 사정으로 인한 취소는 100% 환불됩니다.</em>',
        'tags' => '환불, 환불정책, 취소수수료',
        'helpful' => 134,
        'created_at' => '2024-01-16'
    ],
    [
        'id' => 5,
        'category' => 'account',
        'question' => '회원가입은 어떻게 하나요?',
        'answer' => '회원가입은 매우 간단합니다:<br><br>
                    <strong>일반고객 가입</strong><br>
                    1. "회원가입" 버튼을 클릭합니다.<br>
                    2. 이름, 이메일, 전화번호, 비밀번호를 입력합니다.<br>
                    3. 휴대폰 인증을 완료합니다.<br>
                    4. 이용약관에 동의하고 가입을 완료합니다.<br><br>
                    <strong>업체관리자 가입</strong><br>
                    1. 사업자등록번호, 업체명, 대표자명을 추가로 입력합니다.<br>
                    2. 사업자등록증 사본을 업로드합니다.<br>
                    3. 관리자 승인 후 이용 가능합니다.<br><br>
                    <strong>소셜 로그인</strong><br>
                    카카오, 네이버, 구글 계정으로도 간편 가입 가능합니다.',
        'tags' => '회원가입, 가입방법, 소셜로그인',
        'helpful' => 98,
        'created_at' => '2024-01-17'
    ],
    [
        'id' => 6,
        'category' => 'account',
        'question' => '비밀번호를 잊어버렸어요',
        'answer' => '비밀번호 찾기 방법:<br><br>
                    1. 로그인 페이지에서 "비밀번호 찾기"를 클릭합니다.<br>
                    2. 가입 시 등록한 이메일 주소를 입력합니다.<br>
                    3. 이메일로 발송된 임시 비밀번호를 확인합니다.<br>
                    4. 임시 비밀번호로 로그인 후 새 비밀번호로 변경합니다.<br><br>
                    <strong>이메일을 받지 못한 경우:</strong><br>
                    - 스팸함을 확인해 주세요<br>
                    - 이메일 주소가 정확한지 확인해 주세요<br>
                    - 고객센터(1588-1234)로 문의해 주세요',
        'tags' => '비밀번호, 비밀번호찾기, 로그인',
        'helpful' => 87,
        'created_at' => '2024-01-17'
    ],
    [
        'id' => 7,
        'category' => 'service',
        'question' => '쿠폰은 어떻게 사용하나요?',
        'answer' => '쿠폰 사용 방법:<br><br>
                    1. 예약 과정에서 결제 단계에 도달합니다.<br>
                    2. "쿠폰 사용하기" 버튼을 클릭합니다.<br>
                    3. 보유한 쿠폰 목록에서 사용할 쿠폰을 선택합니다.<br>
                    4. 할인이 적용된 금액을 확인하고 결제를 완료합니다.<br><br>
                    <strong>쿠폰 종류:</strong><br>
                    - 신규 가입 쿠폰: 첫 예약 시 20% 할인<br>
                    - 생일 쿠폰: 생일 월에 특별 할인<br>
                    - 재방문 쿠폰: 3회 이상 이용 시 발급<br>
                    - 업체별 쿠폰: 각 업체에서 발행하는 할인 쿠폰<br><br>
                    <em>※ 쿠폰은 유효기간이 있으니 확인 후 사용하세요.</em>',
        'tags' => '쿠폰, 할인, 쿠폰사용',
        'helpful' => 76,
        'created_at' => '2024-01-18'
    ],
    [
        'id' => 8,
        'category' => 'service',
        'question' => '적립금은 어떻게 적립되고 사용하나요?',
        'answer' => '<strong>적립금 적립 방법:</strong><br>
                    - 예약 완료 시: 결제 금액의 1-3% 자동 적립<br>
                    - 후기 작성 시: 1,000원 추가 적립<br>
                    - 친구 추천 시: 추천인과 피추천인 모두 5,000원 적립<br>
                    - 이벤트 참여 시: 이벤트에 따라 다양한 적립<br><br>
                    <strong>적립금 사용 방법:</strong><br>
                    1. 예약 결제 시 "적립금 사용" 옵션을 선택합니다.<br>
                    2. 사용할 적립금 금액을 입력합니다.<br>
                    3. 할인된 금액으로 결제를 완료합니다.<br><br>
                    <strong>적립금 정책:</strong><br>
                    - 최소 사용 금액: 1,000원 이상<br>
                    - 유효기간: 적립일로부터 2년<br>
                    - 현금 전환 불가',
        'tags' => '적립금, 포인트, 할인',
        'helpful' => 65,
        'created_at' => '2024-01-18'
    ],
    [
        'id' => 9,
        'category' => 'business',
        'question' => '업체는 어떻게 등록하나요?',
        'answer' => '<strong>업체 등록 절차:</strong><br><br>
                    1. <strong>회원가입</strong><br>
                    - 업체관리자 유형으로 회원가입<br>
                    - 사업자등록번호, 업체명, 대표자명 입력<br><br>
                    2. <strong>서류 제출</strong><br>
                    - 사업자등록증 사본 업로드<br>
                    - 영업신고증 또는 허가증 업로드<br><br>
                    3. <strong>업체 정보 입력</strong><br>
                    - 업체 주소, 연락처, 운영시간<br>
                    - 업체 소개, 대표 사진<br>
                    - 서비스 메뉴 및 가격표<br><br>
                    4. <strong>관리자 승인</strong><br>
                    - 제출된 서류 검토 (1-3영업일)<br>
                    - 승인 완료 시 서비스 이용 가능<br><br>
                    <em>※ 승인 과정에서 추가 서류 요청이 있을 수 있습니다.</em>',
        'tags' => '업체등록, 사업자, 입점',
        'helpful' => 54,
        'created_at' => '2024-01-19'
    ],
    [
        'id' => 10,
        'category' => 'business',
        'question' => '수수료는 얼마인가요?',
        'answer' => '<strong>플랫폼 이용 수수료:</strong><br><br>
                    <strong>거래 수수료</strong><br>
                    - 예약 완료 시: 거래액의 5%<br>
                    - 신규 업체 (첫 3개월): 3% 할인 적용<br>
                    - VIP 업체 (월 매출 500만원 이상): 4% 적용<br><br>
                    <strong>무료 서비스</strong><br>
                    - 업체 등록 및 기본 관리<br>
                    - 예약 관리 시스템 이용<br>
                    - 고객 관리 및 통계 제공<br>
                    - 마케팅 도구 (쿠폰, 이벤트)<br><br>
                    <strong>유료 서비스 (선택)</strong><br>
                    - 프리미엄 노출: 월 50,000원<br>
                    - 추가 사진 슬롯: 월 10,000원<br>
                    - 맞춤 배너 광고: 월 100,000원<br><br>
                    <em>※ 모든 금액은 부가세 별도입니다.</em>',
        'tags' => '수수료, 요금, 비용',
        'helpful' => 43,
        'created_at' => '2024-01-19'
    ]
];

// 검색 필터링
if ($search) {
    $faqs = array_filter($faqs, function($faq) use ($search) {
        return stripos($faq['question'], $search) !== false || 
               stripos($faq['answer'], $search) !== false ||
               stripos($faq['tags'], $search) !== false;
    });
}

// 카테고리 필터링
if ($category !== 'all') {
    $faqs = array_filter($faqs, function($faq) use ($category) {
        return $faq['category'] === $category;
    });
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <!-- 페이지 헤더 -->
    <div class="row">
        <div class="col-12">
            <div class="text-center mb-5">
                <h1><i class="fas fa-question-circle text-primary"></i> 자주 묻는 질문</h1>
                <p class="text-muted">궁금한 점을 빠르게 해결해드립니다</p>
            </div>
        </div>
    </div>
    
    <!-- 검색 및 카테고리 -->
    <div class="row mb-4">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-4 mb-3">
                            <select name="category" class="form-control">
                                <?php foreach ($faq_categories as $key => $name): ?>
                                    <option value="<?= $key ?>" <?= $category === $key ? 'selected' : '' ?>>
                                        <?= $name ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="질문을 검색하세요..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 카테고리 탭 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="nav nav-pills nav-fill justify-content-center" role="tablist">
                <?php foreach ($faq_categories as $key => $name): ?>
                    <a class="nav-link <?= $category === $key ? 'active' : '' ?>" 
                       href="?category=<?= $key ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                        <?= $name ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- FAQ 목록 -->
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <?php if (empty($faqs)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">검색 결과가 없습니다</h5>
                        <p class="text-muted">다른 키워드로 검색하거나 카테고리를 변경해보세요.</p>
                        <a href="?" class="btn btn-outline-primary">전체 FAQ 보기</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="accordion" id="faqAccordion">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="card mb-2">
                            <div class="card-header" id="heading<?= $faq['id'] ?>">
                                <h2 class="mb-0">
                                    <button class="btn btn-link btn-block text-left collapsed" 
                                            type="button" data-toggle="collapse" 
                                            data-target="#collapse<?= $faq['id'] ?>" 
                                            aria-expanded="false" 
                                            aria-controls="collapse<?= $faq['id'] ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge badge-<?= getCategoryBadgeClass($faq['category']) ?> mr-2">
                                                    <?= $faq_categories[$faq['category']] ?>
                                                </span>
                                                <strong><?= htmlspecialchars($faq['question']) ?></strong>
                                            </div>
                                            <div class="text-right">
                                                <small class="text-muted mr-2">
                                                    <i class="fas fa-thumbs-up"></i> <?= $faq['helpful'] ?>
                                                </small>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                            </div>
                            <div id="collapse<?= $faq['id'] ?>" 
                                 class="collapse" 
                                 aria-labelledby="heading<?= $faq['id'] ?>" 
                                 data-parent="#faqAccordion">
                                <div class="card-body">
                                    <div class="answer-content">
                                        <?= $faq['answer'] ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-tags"></i> 
                                                <?= htmlspecialchars($faq['tags']) ?>
                                            </small>
                                        </div>
                                        <div>
                                            <small class="text-muted mr-3">
                                                작성일: <?= date('Y.m.d', strtotime($faq['created_at'])) ?>
                                            </small>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="markHelpful(<?= $faq['id'] ?>)">
                                                <i class="fas fa-thumbs-up"></i> 도움됨
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 추가 도움말 섹션 -->
    <div class="row mt-5">
        <div class="col-lg-10 mx-auto">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5>원하는 답변을 찾지 못하셨나요?</h5>
                    <p class="text-muted">1:1 문의를 통해 더 자세한 도움을 받으실 수 있습니다.</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-comments fa-2x text-primary mb-2"></i>
                                    <h6>1:1 문의</h6>
                                    <p class="text-muted small">개인적인 문의사항을 남겨주세요</p>
                                    <a href="inquiry.php" class="btn btn-outline-primary btn-sm">문의하기</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-phone fa-2x text-success mb-2"></i>
                                    <h6>전화 상담</h6>
                                    <p class="text-muted small">평일 09:00 - 18:00</p>
                                    <strong class="text-success">1588-1234</strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <i class="fas fa-comment-dots fa-2x text-warning mb-2"></i>
                                    <h6>카카오톡 상담</h6>
                                    <p class="text-muted small">실시간 채팅 상담</p>
                                    <a href="#" class="btn btn-outline-warning btn-sm">채팅하기</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 인기 검색어 -->
    <div class="row mt-4">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-fire text-danger"></i> 인기 검색어</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap">
                        <?php 
                        $popular_keywords = ['예약', '취소', '환불', '결제', '쿠폰', '적립금', '업체등록', '수수료'];
                        foreach ($popular_keywords as $keyword): 
                        ?>
                            <a href="?search=<?= urlencode($keyword) ?>" 
                               class="badge badge-light border mr-2 mb-2 p-2">
                                <?= $keyword ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.accordion .card-header {
    border-bottom: none;
    background: #f8f9fa;
}

.accordion .btn-link {
    text-decoration: none;
    color: #333;
}

.accordion .btn-link:hover {
    text-decoration: none;
    color: #007bff;
}

.answer-content {
    line-height: 1.6;
}

.answer-content strong {
    color: #333;
}

.nav-pills .nav-link.active {
    background-color: #007bff;
}

.badge {
    font-size: 0.75em;
}
</style>

<script>
function markHelpful(faqId) {
    // AJAX로 도움됨 표시 처리
    fetch('../api/mark_helpful.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            faq_id: faqId,
            type: 'faq'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('피드백이 등록되었습니다. 감사합니다!');
            // 카운트 업데이트
            location.reload();
        } else {
            alert('오류가 발생했습니다. 다시 시도해주세요.');
        }
    });
}

// URL 해시가 있으면 해당 FAQ 열기
$(document).ready(function() {
    if (window.location.hash) {
        const targetId = window.location.hash.replace('#', '');
        const targetCollapse = $('#collapse' + targetId);
        if (targetCollapse.length) {
            targetCollapse.collapse('show');
        }
    }
});
</script>

<?php
function getCategoryBadgeClass($category) {
    switch ($category) {
        case 'reservation': return 'primary';
        case 'payment': return 'success';
        case 'account': return 'info';
        case 'service': return 'warning';
        case 'business': return 'danger';
        default: return 'secondary';
    }
}

include '../includes/footer.php';
?> 