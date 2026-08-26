<?php
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('Asia/Bangkok');
if (ob_get_level() === 0) {
    if (!headers_sent() && extension_loaded('zlib')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
if (!headers_sent() && session_id() === '') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
    }
    @ini_set('session.use_strict_mode', '1');
}
if (session_id() === '') {
    @session_start();
}

require_once __DIR__ . '/config.local.php';
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$conn) {
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . mysqli_connect_error());
}
