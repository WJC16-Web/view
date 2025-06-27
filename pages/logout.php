<?php
require_once '../includes/functions.php';

startSession();

// 세션 파괴
$_SESSION = array();

// 세션 쿠키 삭제
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Remember me 쿠키 삭제
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

session_destroy();

// 메인 페이지로 리다이렉트
redirect('/view/', '로그아웃되었습니다.');
?> 