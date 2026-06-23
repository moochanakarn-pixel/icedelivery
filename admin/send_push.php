<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    set_flash_message('error', 'คำขอไม่ถูกต้อง');
    admin_auth_redirect('index.php');
}
$title = trim(isset($_POST['title']) ? (string)$_POST['title'] : '');
$body  = trim(isset($_POST['body'])  ? (string)$_POST['body']  : '');
if ($title === '' || $body === '') {
    set_flash_message('error', 'กรอกหัวข้อและข้อความก่อน');
    admin_auth_redirect('index.php');
}

$fcmKey = defined('FCM_SERVER_KEY') ? FCM_SERVER_KEY : '';
if ($fcmKey === '') {
    set_flash_message('error', 'ยังไม่ได้ตั้งค่า FCM_SERVER_KEY ใน config.local.php');
    admin_auth_redirect('index.php');
}

$res = @mysqli_query($conn, "SELECT token FROM fcm_tokens");
$tokens = array();
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $tokens[] = $r['token']; } }
if (!$tokens) {
    set_flash_message('error', 'ไม่มีอุปกรณ์ที่ลงทะเบียน');
    admin_auth_redirect('index.php');
}

$sent = 0;
foreach (array_chunk($tokens, 500) as $chunk) {
    $payload = json_encode(array(
        'registration_ids' => $chunk,
        'notification' => array('title' => $title, 'body' => $body, 'sound' => 'default'),
        'data' => array('title' => $title, 'body' => $body),
    ));
    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array('Authorization: key=' . $fcmKey, 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => $payload,
    ));
    $result = curl_exec($ch);
    curl_close($ch);
    $sent += count($chunk);
}
admin_log_action('send_push', "ส่ง push notification: {$title} ({$sent} อุปกรณ์)");
set_flash_message('success', "ส่ง push notification เรียบร้อย {$sent} อุปกรณ์");
admin_auth_redirect('index.php');
