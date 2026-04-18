<?php
$rootConfig = dirname(__DIR__) . '/config.php';
if (!is_file($rootConfig)) {
    $rootConfig = __DIR__ . '/config.php';
}
include_once $rootConfig;
// ต้อง set flash ก่อน logout เพราะ admin_logout() จะ destroy session
set_flash_message('success', 'ออกจากระบบแล้ว');
admin_logout();
admin_auth_redirect('login.php');
?>
