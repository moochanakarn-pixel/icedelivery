<?php
$rootDir = dirname(__DIR__);
$lineBootstrap = $rootDir . '/line_bootstrap.php';
if (!is_file($lineBootstrap)) {
    $lineBootstrap = __DIR__ . '/line_bootstrap.php';
}
include_once $lineBootstrap;

function admin_nav_items() {
    return array(
        'index.php' => 'ภาพรวม',
        '../index.php' => 'คีย์ออเดอร์',
        '../customers.php' => 'ลูกค้า',
        '../report.php' => 'รายงาน',
        'admin_users.php' => 'ผู้ดูแล',
        'line_richmenu.php' => 'LINE',
        'settings.php' => 'ตั้งค่า',
    );
}

function admin_current_page_name() {
    return basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
}

function admin_page_title($title) {
    return h($title) . ' • Admin';
}

function admin_render_header($title, $subtitle) {
    $user = admin_current_user();
    $nav = admin_nav_items();
    $current = basename((string)(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''));
    $flash = consume_flash_message();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="manifest" href="../manifest.json">
<meta name="theme-color" content="#0b7dda">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title><?php echo admin_page_title($title); ?></title>
<?php
$assetBase = is_file(dirname(__DIR__) . '/assets/mobile.css') ? '../assets' : 'assets';
?>
<link rel="stylesheet" href="<?php echo h($assetBase); ?>/mobile.css">
<link rel="stylesheet" href="<?php echo h($assetBase); ?>/app.css?v=20260405c">
<link rel="stylesheet" href="<?php echo h($assetBase); ?>/admin.css">
</head>
<body>
<div class="admin-shell">
    <div class="admin-topbar">
        <h1><?php echo h($title); ?></h1>
        <p><?php echo h($subtitle); ?></p>
        <div class="admin-meta">
            <span class="chip">👤 <?php echo h($user && $user['full_name'] !== '' ? $user['full_name'] : ($user ? $user['username'] : '')); ?></span>
            <span class="chip">🔐 <?php echo h($user ? $user['role'] : ''); ?></span>
            <a class="chip" href="logout.php">ออกจากระบบ</a>
        </div>
    </div>

    <div class="admin-nav">
        <?php foreach ($nav as $file => $label) {
            // เปรียบเทียบแค่ชื่อไฟล์ปลายทาง ไม่ใช้ path เต็ม
            $navBasename = basename((string)$file);
            $isActive = $navBasename === $current;
        ?>
            <a href="<?php echo h($file); ?>" class="<?php echo $isActive ? 'active' : ''; ?>"><?php echo h($label); ?></a>
        <?php } ?>
    </div>

    <?php if ($flash) { ?><div class="notice <?php echo h(isset($flash['type']) ? $flash['type'] : 'info'); ?>"><?php echo h(isset($flash['message']) ? $flash['message'] : ''); ?></div><?php } ?>
<?php
}

function admin_render_footer($note) {
?>
    <div class="footer-note"><?php echo h($note); ?></div>
</div>
</body>
</html>
<?php
}
?>
