<?php
if (!defined('SKIP_SCHEMA_UPDATES')) {
    define('SKIP_SCHEMA_UPDATES', true);
}
include_once __DIR__ . '/line_bootstrap.php';

$body = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : '';

if (!line_verify_signature($body, $signature)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'invalid signature';
    exit;
}

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'ok';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$data = json_decode($body, true);
if (!is_array($data) || !isset($data['events']) || !is_array($data['events'])) {
    exit;
}

foreach ($data['events'] as $event) {
    $eventType = isset($event['type']) ? (string)$event['type'] : '';
    $webhookEventId = isset($event['webhookEventId']) ? (string)$event['webhookEventId'] : '';
    $userId = isset($event['source']['userId']) ? (string)$event['source']['userId'] : '';

    if ($userId === '') {
        continue;
    }

    if ($webhookEventId !== '') {
        $isNew = line_store_processed_event($webhookEventId, $eventType, $userId);
        if (!$isNew) {
            continue;
        }
    }

    $role = line_find_role($userId);
    $displayName = '';
    $needProfile = ($eventType === 'follow');

    $safeUserId = mysqli_real_escape_string($conn, $userId);
    $resUser = @mysqli_query($conn, "SELECT display_name FROM line_users WHERE line_user_id = '{$safeUserId}' LIMIT 1");
    if ($resUser && mysqli_num_rows($resUser) > 0) {
        $rowUser = mysqli_fetch_assoc($resUser);
        $displayName = isset($rowUser['display_name']) ? trim((string)$rowUser['display_name']) : '';
        if ($displayName === '') {
            $needProfile = true;
        }
    } else {
        $needProfile = true;
    }

    if ($needProfile) {
        $profile = line_profile($userId);
        if (is_array($profile) && !empty($profile['displayName'])) {
            $displayName = trim((string)$profile['displayName']);
        }
    }

    line_upsert_user($userId, $displayName, $role, $eventType);

    if ($eventType === 'follow' || ($eventType === 'message' && isset($event['message']['type']) && $event['message']['type'] === 'text' && trim((string)$event['message']['text']) === 'เมนู')) {
        line_sync_user_menu($userId);
    }
}
exit;
