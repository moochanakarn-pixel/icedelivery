<?php
$rootConfig = dirname(__DIR__) . '/config.php';
if (!is_file($rootConfig)) {
    $rootConfig = __DIR__ . '/config.php';
}
include_once $rootConfig;
admin_logout();
set_flash_message('success', 'ออกจากระบบแล้ว');
admin_auth_redirect('login.php');
?>
