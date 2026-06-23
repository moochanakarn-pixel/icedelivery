<?php
define('SKIP_SCHEMA_UPDATES', true);
include 'config.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$lastCount = isset($_GET['last_count']) ? (int)$_GET['last_count'] : -1;
$today = date('Y-m-d');
$todayEsc = mysqli_real_escape_string($conn, $today);

$maxTime = 55;
$start = time();

while (true) {
    if (time() - $start >= $maxTime) {
        echo "event: ping\ndata: keepalive\n\n";
        flush();
        break;
    }

    $res = @mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE order_date='{$todayEsc}'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $count = $row ? (int)$row['cnt'] : 0;

    if ($lastCount === -1) {
        $lastCount = $count;
        echo "event: init\ndata: " . json_encode(array('count' => $count)) . "\n\n";
        flush();
    } elseif ($count !== $lastCount) {
        $lastCount = $count;
        echo "event: new_order\ndata: " . json_encode(array('count' => $count)) . "\n\n";
        flush();
    } else {
        echo "event: ping\ndata: " . $count . "\n\n";
        flush();
    }

    sleep(10);
    if (connection_aborted()) break;
}
