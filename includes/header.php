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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
    
    <!-- jQuery & jQuery UI -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- 카카오맵 API -->
    <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=YOUR_KAKAO_MAP_KEY&libraries=services"></script>
    
    <style>
        /* 임시 기본 스타일 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Malgun Gothic', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #fafafa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* 헤더 스타일 */
        .header {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #ff4757;
            text-decoration: none;
        }
        
        .search-bar {
            flex: 1;
            max-width: 500px;
            margin: 0 30px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .search-input:focus {
            border-color: #ff4757;
        }
        
        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #ff4757;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 50%;
            cursor: pointer;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #ff4757;
            color: white;
        }
        
        .btn-primary:hover {
            background: #ff3742;
        }
        
        .btn-outline {
            background: white;
            color: #ff4757;
            border: 2px solid #ff4757;
        }
        
        .btn-outline:hover {
            background: #ff4757;
            color: white;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 5px;
            z-index: 1001;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #eee;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        /* 네비게이션 */
        .nav {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-content {
            display: flex;
            justify-content: center;
            gap: 40px;
            padding: 15px 0;
        }
        
        .nav-item {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 0;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .nav-item:hover, .nav-item.active {
            color: #ff4757;
            border-bottom-color: #ff4757;
        }
        
        /* 알림 */
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
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
        
        /* 반응형 */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .search-bar {
                margin: 0;
                max-width: 100%;
            }
            
            .nav-content {
                gap: 20px;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <!-- 로고 -->
                <a href="<?php echo BASE_URL; ?>/" class="logo">
                    <i class="fas fa-spa"></i> 뷰티북
                </a>
                
                <!-- 검색바 -->
                <div class="search-bar">
                    <form action="<?php echo BASE_URL; ?>/pages/business_list.php" method="GET">
                        <input type="text" name="search" class="search-input" 
                               placeholder="업체명, 지역, 서비스로 검색하세요..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- 사용자 메뉴 -->
                <div class="user-menu">
                    <?php if ($current_user): ?>
                        <!-- 로그인된 상태 -->
                        <div class="dropdown">
                            <a href="#" class="btn btn-outline">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['name']); ?>님
                            </a>
                            <div class="dropdown-content">
                                <?php if ($current_user['user_type'] === 'customer'): ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/customer_mypage.php" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt"></i> 마이페이지
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/customer_mypage.php?tab=reservations" class="dropdown-item">
                                        <i class="fas fa-calendar"></i> 예약 관리
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/customer_mypage.php?tab=favorites" class="dropdown-item">
                                        <i class="fas fa-heart"></i> 즐겨찾기
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/customer_mypage.php?tab=coupons" class="dropdown-item">
                                        <i class="fas fa-ticket-alt"></i> 쿠폰함
                                    </a>
                                <?php elseif ($current_user['user_type'] === 'business_owner'): ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/business_register.php" class="dropdown-item">
                                        <i class="fas fa-building"></i> 업체 등록
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/business_dashboard.php" class="dropdown-item">
                                        <i class="fas fa-store"></i> 업체 대시보드
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/reservation_manage.php" class="dropdown-item">
                                        <i class="fas fa-calendar-check"></i> 예약 관리
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/business_event_manage.php" class="dropdown-item">
                                        <i class="fas fa-star"></i> 이벤트 관리
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/teacher_register.php" class="dropdown-item">
                                        <i class="fas fa-user-plus"></i> 선생님 등록
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/business_service_manage.php" class="dropdown-item">
                                        <i class="fas fa-cut"></i> 서비스 관리
                                    </a>
                                <?php elseif ($current_user['user_type'] === 'teacher'): ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/teacher_mypage.php" class="dropdown-item">
                                        <i class="fas fa-user-md"></i> 내 대시보드
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/teacher_mypage.php?tab=schedule" class="dropdown-item">
                                        <i class="fas fa-calendar-alt"></i> 스케줄 관리
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/teacher_mypage.php?tab=reservations" class="dropdown-item">
                                        <i class="fas fa-clipboard-list"></i> 예약 현황
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/teacher_mypage.php?tab=customers" class="dropdown-item">
                                        <i class="fas fa-users"></i> 고객 관리
                                    </a>
                                <?php elseif ($current_user['user_type'] === 'admin'): ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/admin_dashboard.php" class="dropdown-item">
                                        <i class="fas fa-cogs"></i> 관리자 페이지
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/admin_user_manage.php" class="dropdown-item">
                                        <i class="fas fa-users"></i> 회원 관리
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/admin_business_manage.php" class="dropdown-item">
                                        <i class="fas fa-store"></i> 업체 관리
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/admin_business_approve.php" class="dropdown-item">
                                        <i class="fas fa-building"></i> 업체 승인
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/pages/admin_statistics.php" class="dropdown-item">
                                        <i class="fas fa-chart-bar"></i> 통계
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="dropdown-item">
                                    <i class="fas fa-cog"></i> 설정
                                </a>
                                <a href="<?php echo BASE_URL; ?>/pages/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> 로그아웃
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- 비로그인 상태 -->
                        <a href="<?php echo BASE_URL; ?>/pages/login.php" class="btn btn-outline">
                            <i class="fas fa-sign-in-alt"></i> 로그인
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> 회원가입
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <!-- 네비게이션 -->
    <nav class="nav">
        <div class="container">
            <div class="nav-content">
                <a href="<?php echo BASE_URL; ?>/" class="nav-item">
                    <i class="fas fa-home"></i> 홈
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_list.php" class="nav-item">
                    <i class="fas fa-list"></i> 업체 찾기
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=nail" class="nav-item">
                    💅 네일
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=hair" class="nav-item">
                    💇‍♀️ 헤어
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=waxing" class="nav-item">
                    🪒 왁싱
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=skincare" class="nav-item">
                    🧴 피부관리
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/business_list.php?category=massage" class="nav-item">
                    💆‍♀️ 마사지
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/faq.php" class="nav-item">
                    <i class="fas fa-question-circle"></i> FAQ
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/event.php" class="nav-item">
                    <i class="fas fa-gift"></i> 이벤트
                </a>
            </div>
        </div>
    </nav>
    
    <!-- 메시지 표시 -->
    <?php 
    $message = showMessage();
    if ($message): 
    ?>
        <div class="container">
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- 메인 컨텐츠 시작 -->
    <main class="main-content"> 