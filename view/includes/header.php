<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? '예약 시스템'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales/ko.global.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/view/index.php">예약 시스템</a>
            <div class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                    <?php if (getUserRole() == 'teacher'): ?>
                        <a class="nav-link" href="/view/pages/teacher_mypage.php">마이페이지</a>
                        <a class="nav-link" href="/view/pages/teacher_service_manage.php">서비스 관리</a>
                    <?php elseif (getUserRole() == 'business'): ?>
                        <a class="nav-link" href="/view/pages/business_dashboard.php">대시보드</a>
                    <?php endif; ?>
                    <a class="nav-link" href="/view/pages/logout.php">로그아웃</a>
                <?php else: ?>
                    <a class="nav-link" href="/view/pages/login.php">로그인</a>
                    <a class="nav-link" href="/view/pages/register.php">회원가입</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container mt-4">