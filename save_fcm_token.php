<?php
define('SKIP_SCHEMA_UPDATES', true);
include 'config.php';
header('Content-Type: application/json');
$token = trim(isset($_POST['token']) ? (string)$_POST['token'] : '');
if ($token === '') { echo json_encode(array('ok' => false)); exit; }
$tokenEsc = mysqli_real_escape_string($conn, $token);
$ua = mysqli_real_escape_string($conn, isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '');
@mysqli_query($conn, "INSERT INTO fcm_tokens(token, user_agent, created_at) VALUES('{$tokenEsc}', '{$ua}', NOW()) ON DUPLICATE KEY UPDATE user_agent='{$ua}', updated_at=NOW()");
echo json_encode(array('ok' => true));
