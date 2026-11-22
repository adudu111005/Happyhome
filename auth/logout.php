<?php
session_start();

// Xóa toàn bộ dữ liệu phiên đăng nhập
$_SESSION = [];

// Xóa cookie session
if (ini_get("session.use_cookies")) {
    $cookieParams = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $cookieParams["path"],
        $cookieParams["domain"],
        $cookieParams["secure"],
        $cookieParams["httponly"]
    );
}

session_destroy();

// Không lưu trang trong bộ nhớ trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: ../welcome.php");
exit();