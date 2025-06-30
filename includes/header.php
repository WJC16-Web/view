<?php
// 출력 버퍼링 시작 (헤더 오류 방지)
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

startSession();
$current_user = getCurrentUser();
$page_title = $page_title ?? '뷰티북 - 예약의 새로운 경험';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- PWA 메타 태그 -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#ff4757">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.json">
    
    <!-- 폰트 -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        /* 리셋 CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
            padding-bottom: 80px; /* 하단 네비 공간 */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* 모바일 앱 헤더 */
        .mobile-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #fff;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .back-btn:active {
            background: #f0f0f0;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .header-icon:active {
            background: #f0f0f0;
        }
        
        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: #ff4757;
            border-radius: 50%;
        }
        
        /* 메인 콘텐츠 */
        .main-content {
            margin-top: 60px;
            min-height: calc(100vh - 140px);
            padding: 0;
        }
        
        /* 하단 네비게이션 */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-around;
            box-shadow: 0 -2px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            border-top: 1px solid #f0f0f0;
            padding: 8px 0;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #999;
            transition: all 0.3s;
            padding: 8px 12px;
            border-radius: 12px;
            min-width: 60px;
        }
        
        .nav-item.active {
            color: #ff4757;
            background: rgba(255, 71, 87, 0.1);
        }
        
        .nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        
        .nav-item span {
            font-size: 11px;
            font-weight: 500;
        }
        
        /* 검색바 스타일 */
        .search-section {
            padding: 20px;
            background: #fff;
            margin-bottom: 10px;
        }
        
        .search-container {
            position: relative;
            background: #f8f9fa;
            border-radius: 25px;
            overflow: hidden;
        }
        
        .search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: none;
            background: transparent;
            font-size: 16px;
            outline: none;
        }
        
        .search-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            background: #ff4757;
            border: none;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        /* 카드 스타일 */
        .card {
            background: #fff;
            border-radius: 16px;
            margin: 10px 20px;
            overflow: hidden;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        /* 버튼 스타일 */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 52px;
            gap: 8px;
        }
        
        .btn-primary {
            background: #ff4757;
            color: white;
        }
        
        .btn-primary:active {
            background: #ff3742;
            transform: scale(0.98);
        }
        
        .btn-outline {
            background: transparent;
            color: #ff4757;
            border: 2px solid #ff4757;
        }
        
        .btn-outline:active {
            background: #ff4757;
            color: white;
            transform: scale(0.98);
        }
        
        .btn-full {
            width: 100%;
        }
        
        .btn-large {
            padding: 20px 24px;
            font-size: 18px;
            min-height: 60px;
        }
        
        /* 리스트 스타일 */
        .list-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: inherit;
            transition: background 0.3s;
        }
        
        .list-item:active {
            background: #f8f9fa;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-icon {
            width: 24px;
            height: 24px;
            margin-right: 15px;
            color: #666;
        }
        
        .list-content {
            flex: 1;
        }
        
        .list-title {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
        }
        
        .list-subtitle {
            font-size: 14px;
            color: #666;
        }
        
        .list-arrow {
            color: #ccc;
            font-size: 14px;
        }
        
        /* 뱃지 */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-primary {
            background: rgba(255, 71, 87, 0.1);
            color: #ff4757;
        }
        
        .badge-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .badge-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        /* 알림 메시지 */
        .alert {
            margin: 10px 20px;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-color: rgba(40, 167, 69, 0.2);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-color: rgba(220, 53, 69, 0.2);
        }
        
        /* 폼 스타일 */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 16px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            background: #fff;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #ff4757;
        }
        
        /* 로딩 애니메이션 */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #ff4757;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 모달 오버레이 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            display: none;
        }
        
        .modal {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 20px 20px 0 0;
            max-height: 90vh;
            z-index: 2001;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .modal.show {
            transform: translateY(0);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        /* 풀스크린 컨테이너 */
        .container {
            max-width: 100%;
            padding: 0;
            margin: 0;
        }
        
        /* 홈 화면용 특별 스타일 */
        .home-header {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
            padding: 20px;
            margin-bottom: 0;
        }
        
        .home-header .header-title {
            color: white;
            font-size: 20px;
        }
        
        .home-header .header-icon {
            color: white;
        }
        
        /* 사파리 하단 홈 인디케이터 대응 */
        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(8px + env(safe-area-inset-bottom));
                height: calc(80px + env(safe-area-inset-bottom));
            }
            
            body {
                padding-bottom: calc(80px + env(safe-area-inset-bottom));
            }
        }
        
        /* 다크모드 지원 */
        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
                color: #fff;
            }
            
            .mobile-header,
            .bottom-nav,
            .card {
                background: #2d2d2d;
                border-color: #404040;
            }
            
            .search-container,
            .form-input {
                background: #404040;
                border-color: #555;
                color: #fff;
            }
            
            .header-title,
            .card-title {
                color: #fff;
            }
            
            .list-item:active {
                background: #404040;
            }
        }
    </style>
</head>
<body>
    <!-- 모바일 헤더 -->
    <div class="mobile-header <?php echo (strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/view/' || $_SERVER['REQUEST_URI'] === '/view') ? 'home-header' : ''; ?>">
        <div class="header-left">
            <?php if (!in_array(basename($_SERVER['PHP_SELF']), ['index.php'])): ?>
                <button class="back-btn" onclick="history.back()">
                    <i class="fas fa-chevron-left"></i>
                </button>
            <?php endif; ?>
            <h1 class="header-title">
                <?php 
                $page_name = basename($_SERVER['PHP_SELF'], '.php');
                $titles = [
                    'index' => '뷰티북',
                    'login' => '로그인',
                    'register' => '회원가입',
                    'business_list' => '업체 찾기',
                    'customer_mypage' => '마이페이지',
                    'business_dashboard' => '업체 관리',
                    'teacher_mypage' => '선생님',
                    'admin_dashboard' => '관리자',
                    'reservation_form' => '예약하기',
                    'business_detail' => '업체 정보'
                ];
                echo $titles[$page_name] ?? '뷰티북';
                ?>
            </h1>
        </div>
        
        <div class="header-right">
            <?php if ($current_user): ?>
                <button class="header-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <div class="notification-badge"></div>
                </button>
                <button class="header-icon" onclick="toggleProfile()">
                    <i class="fas fa-user"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 메인 콘텐츠 시작 -->
    <main class="main-content">
        
        <!-- 메시지 표시 -->
        <?php 
        $message = showMessage();
        if ($message): 
        ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

<script>
// 모바일 웹앱 기본 스크립트
document.addEventListener('DOMContentLoaded', function() {
    // 뒤로가기 버튼 이벤트
    window.addEventListener('popstate', function(e) {
        // 브라우저 뒤로가기 처리
    });
    
    // 터치 이벤트 최적화
    let touchStartY = 0;
    let touchEndY = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartY = e.changedTouches[0].screenY;
    });
    
    document.addEventListener('touchend', function(e) {
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartY - touchEndY;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // 위로 스와이프
            } else {
                // 아래로 스와이프 (새로고침)
                if (window.scrollY === 0) {
                    // location.reload();
                }
            }
        }
    }
    
    // 네이티브 앱 느낌을 위한 추가 설정
    document.addEventListener('touchmove', function(e) {
        // 전체 페이지 스크롤 방지 (필요시)
        // e.preventDefault();
    }, { passive: false });
});

// 알림 토글
function toggleNotifications() {
    // 알림 패널 구현
    alert('알림 기능 준비 중입니다.');
}

// 프로필 토글
function toggleProfile() {
    // 프로필 메뉴 구현
    const menu = document.createElement('div');
    menu.className = 'modal-overlay';
    menu.innerHTML = `
        <div class="modal show">
            <div class="modal-header">
                <div class="modal-title">프로필</div>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="list-item" onclick="location.href='<?php echo BASE_URL; ?>/pages/profile.php'">
                    <i class="list-icon fas fa-user-cog"></i>
                    <div class="list-content">
                        <div class="list-title">내 정보 수정</div>
                    </div>
                    <i class="list-arrow fas fa-chevron-right"></i>
                </div>
                <div class="list-item" onclick="location.href='<?php echo BASE_URL; ?>/pages/logout.php'">
                    <i class="list-icon fas fa-sign-out-alt"></i>
                    <div class="list-content">
                        <div class="list-title">로그아웃</div>
                    </div>
                    <i class="list-arrow fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(menu);
    
    menu.addEventListener('click', function(e) {
        if (e.target === menu) {
            menu.remove();
        }
    });
}
</script> 