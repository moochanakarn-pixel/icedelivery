<?php
$rootConfig = dirname(__DIR__) . '/config.php';
if (!is_file($rootConfig)) {
    $rootConfig = __DIR__ . '/config.php';
}
include_once $rootConfig;

if (admin_is_logged_in()) {
    admin_auth_redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
    } elseif (admin_login(isset($_POST['username']) ? $_POST['username'] : '', isset($_POST['password']) ? $_POST['password'] : '', $error)) {
        set_flash_message('success', 'เข้าสู่ระบบหลังบ้านเรียบร้อย');
        admin_auth_redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>เข้าสู่ระบบหลังบ้าน</title>
<?php $adminCss = is_file(dirname(__DIR__) . '/assets/admin.css') ? '../assets/admin.css' : 'assets/admin.css'; ?>
<link rel="stylesheet" href="<?php echo h($adminCss); ?>">
</head>
<body>
<div class="login-shell">
    <form method="post" class="login-card">
        <h1>🔒 หลังบ้าน ICE LUCKY</h1>
        <p>หน้านี้ไม่ได้แสดงใน LINE ใช้สำหรับผู้จัดการหรือแอดมินที่ต้องการสิทธิ์มากกว่าเมนูใช้งานประจำวัน</p>
        <?php if ($error !== '') { ?><div class="notice error"><?php echo h($error); ?></div><?php } ?>
        <?php echo csrf_input(); ?>
        <div class="field">
            <label>ชื่อผู้ใช้</label>
            <input type="text" name="username" class="input" autocomplete="username">
        </div>
        <div class="field" style="margin-top:12px">
            <label>รหัสผ่าน</label>
            <input type="password" name="password" class="input" autocomplete="current-password">
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
        </div>
        <div class="footer-note">แนะนำให้เปลี่ยนรหัสผ่านทันทีหลังเข้าใช้งานครั้งแรก</div>
    </form>
</div>
</body>
</html>
