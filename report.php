<?php
include 'config.php';

function get_sum_value($conn, $sql)
{
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : array('total' => 0);
    return isset($row['total']) && $row['total'] !== null ? $row['total'] : 0;
}

$today = date('Y-m-d');
$today_display = date('d/m/Y', strtotime($today));
$current_month = date('Y-m');
$current_year = date('Y');
$report_view = isset($_GET['view']) ? trim((string)$_GET['view']) : '';
$is_today_view = ($report_view === 'today');

$today_sales = get_sum_value($conn, "SELECT SUM(order_items.qty * order_items.price) AS total FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE orders.order_date = '{$today}'");
$today_paid_sales = get_sum_value($conn, "SELECT SUM(order_items.qty * order_items.price) AS total FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE orders.order_date = '{$today}' AND (orders.status = 'paid' OR orders.paid = 1)");
$month_sales = get_sum_value($conn, "SELECT SUM(order_items.qty * order_items.price) AS total FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE DATE_FORMAT(orders.order_date, '%Y-%m') = '{$current_month}'");
$month_paid_sales = get_sum_value($conn, "SELECT SUM(order_items.qty * order_items.price) AS total FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE DATE_FORMAT(orders.order_date, '%Y-%m') = '{$current_month}' AND (orders.status = 'paid' OR orders.paid = 1)");
$year_sales = get_sum_value($conn, "SELECT SUM(order_items.qty * order_items.price) AS total FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE YEAR(orders.order_date) = '{$current_year}'");
$year_paid_sales = get_sum_value($conn, "SELECT SUM(order_items.qty * order_items.price) AS total FROM order_items JOIN orders ON orders.id = order_items.order_id WHERE YEAR(orders.order_date) = '{$current_year}' AND (orders.status = 'paid' OR orders.paid = 1)");
$today_orders = get_sum_value($conn, "SELECT COUNT(*) AS total FROM orders WHERE order_date = '{$today}'");
$today_customers = get_sum_value($conn, "SELECT COUNT(DISTINCT customer_id) AS total FROM orders WHERE order_date = '{$today}'");
$paid_orders = get_sum_value($conn, "SELECT COUNT(*) AS total FROM orders WHERE order_date = '{$today}' AND status = 'paid'");


$period_summary = fetch_all_rows(mysqli_query($conn, "
    SELECT orders.order_period, COUNT(*) AS total_orders, COALESCE(SUM(order_items.qty * order_items.price), 0) AS total_amount
    FROM orders
    LEFT JOIN order_items ON order_items.order_id = orders.id
    WHERE orders.order_date = '{$today}'
    GROUP BY orders.order_period
    ORDER BY " . orders_period_order_sql('orders.order_period') . "
"));

$customer_ranking = fetch_all_rows(mysqli_query($conn, "
    SELECT customers.name, COALESCE(SUM(order_items.qty * order_items.price), 0) AS total
    FROM orders
    JOIN customers ON customers.id = orders.customer_id
    LEFT JOIN order_items ON order_items.order_id = orders.id
    WHERE DATE_FORMAT(orders.order_date, '%Y-%m') = '{$current_month}'
    GROUP BY customers.id
    ORDER BY total DESC, customers.name ASC
    LIMIT 10
"));


$today_customer_summary = fetch_all_rows(mysqli_query($conn, "
    SELECT customers.name,
           COALESCE(SUM(order_items.qty * order_items.price), 0) AS total_amount,
           COALESCE(SUM(CASE WHEN orders.status = 'paid' OR orders.paid = 1 THEN order_items.qty * order_items.price ELSE 0 END), 0) AS paid_amount
    FROM orders
    JOIN customers ON customers.id = orders.customer_id
    LEFT JOIN order_items ON order_items.order_id = orders.id
    WHERE orders.order_date = '{$today}'
    GROUP BY customers.id, customers.name, customers.route_order
    HAVING total_amount > 0 OR paid_amount > 0
    ORDER BY customers.route_order ASC, customers.name ASC
"));

$line_summary_lines = array();
$line_summary_lines[] = 'สรุปยอดส่งน้ำแข็ง';
$line_summary_lines[] = 'วันที่ ' . $today_display;
$line_summary_lines[] = '';
if ($today_customer_summary) {
    foreach ($today_customer_summary as $row) {
        $line_summary_lines[] = trim((string)$row['name']) . ' สั่ง ' . number_format((float)$row['total_amount'], 0) . ' บาท | เก็บแล้ว ' . number_format((float)$row['paid_amount'], 0) . ' บาท';
    }
} else {
    $line_summary_lines[] = 'ยังไม่มีข้อมูล';
}
$line_summary_lines[] = '';
$line_summary_lines[] = 'รวมทั้งวัน สั่ง ' . number_format((float)$today_sales, 0) . ' บาท | เก็บแล้ว ' . number_format((float)$today_paid_sales, 0) . ' บาท';
$line_summary_text = implode("
", $line_summary_lines);
$line_summary_liff_id = defined('LINE_REPORT_SHARE_LIFF_ID') ? trim((string)LINE_REPORT_SHARE_LIFF_ID) : '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>รายงานยอดขายน้ำแข็ง</title>
<link rel="stylesheet" href="assets/mobile.css">
<link rel="stylesheet" href="assets/app.css?v=20260405c">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b7dda">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body class="page-report">
<div class="wrapper">
    <div class="hero">
        <h1>📊 รายงานร้านน้ำแข็ง</h1>
        <p><?php echo $is_today_view ? 'ดูสรุปยอดของวันนี้แบบวันเดียว' : 'ดูยอดสั่งและยอดเก็บเงินแล้วของวันนี้ เดือนนี้ ปีนี้ พร้อมสรุปยอดใช้งานประจำวัน'; ?><?php if (!$is_today_view && rounds_enabled()) { ?> และแยกรอบส่ง<?php } ?></p>
        <div class="hero-actions">
            <button type="button" class="btn btn-share" id="lineShareBtn">ส่งข้อความไลน์</button>
            <button type="button" class="btn btn-copy" id="copySummaryBtn">คัดลอกข้อความ</button>
        </div>
</div>

    <div class="stats">
        <div class="card"><div class="label">ยอดสั่งวันนี้</div><div class="value"><?php echo number_format($today_sales); ?></div><div class="muted">บาท</div></div>
        <div class="card"><div class="label">ยอดเก็บแล้ววันนี้</div><div class="value"><?php echo number_format($today_paid_sales); ?></div><div class="muted">บาท</div></div>
        <div class="card"><div class="label">ออเดอร์วันนี้</div><div class="value"><?php echo number_format($today_orders); ?></div><div class="muted">รายการ</div></div>
        <div class="card"><div class="label">ลูกค้าวันนี้</div><div class="value"><?php echo number_format($today_customers); ?></div><div class="muted">ร้าน</div></div>
        <div class="card"><div class="label">รับเงินแล้ววันนี้</div><div class="value"><?php echo number_format($paid_orders); ?></div><div class="muted">รายการ</div></div>
        <?php if (!$is_today_view) { ?>
        <div class="card"><div class="label">ยอดสั่งเดือนนี้</div><div class="value"><?php echo number_format($month_sales); ?></div><div class="muted">บาท</div></div>
        <div class="card"><div class="label">ยอดเก็บแล้วเดือนนี้</div><div class="value"><?php echo number_format($month_paid_sales); ?></div><div class="muted">บาท</div></div>
        <div class="card"><div class="label">ยอดสั่งปีนี้</div><div class="value"><?php echo number_format($year_sales); ?></div><div class="muted">บาท</div></div>
        <div class="card"><div class="label">ยอดเก็บแล้วปีนี้</div><div class="value"><?php echo number_format($year_paid_sales); ?></div><div class="muted">บาท</div></div>
        <?php } ?>
    </div>

    <div class="card section summary-share">
        <h2>📩 ข้อความสำหรับส่ง LINE</h2>
        <div class="today-summary-list">
            <?php if (!$today_customer_summary) { ?>
                <div class="empty">ยังไม่มีข้อมูลสำหรับส่ง</div>
            <?php } else { ?>
                <?php foreach ($today_customer_summary as $row) { ?>
                    <div class="list-row">
                        <div><?php echo h($row['name']); ?></div>
                        <div style="text-align:right">
                            <div><span class="muted">สั่ง</span> <strong><?php echo number_format((float)$row['total_amount'], 0); ?></strong> บาท</div>
                            <div><span class="muted">เก็บแล้ว</span> <strong><?php echo number_format((float)$row['paid_amount'], 0); ?></strong> บาท</div>
                        </div>
                    </div>
                <?php } ?>
                <div class="list-row">
                    <div><strong>รวมทั้งวัน</strong></div>
                    <div style="text-align:right">
                        <div><span class="muted">สั่ง</span> <strong><?php echo number_format((float)$today_sales, 0); ?></strong> บาท</div>
                        <div><span class="muted">เก็บแล้ว</span> <strong><?php echo number_format((float)$today_paid_sales, 0); ?></strong> บาท</div>
                    </div>
                </div>
            <?php } ?>
        </div>
        <div class="summary-share-box" id="lineSummaryPreview"><?php echo nl2br(h($line_summary_text)); ?></div>
    </div>

    <?php if (rounds_enabled()) { ?>
    <div class="card section" style="margin-top:16px">
        <h2>🕒 สรุปรอบส่งวันนี้</h2>
        <?php if (!$period_summary) { ?>
            <div class="empty">ยังไม่มีข้อมูล</div>
        <?php } else { ?>
            <?php foreach ($period_summary as $row) { ?>
                <div class="list-row">
                    <div><?php echo h(get_period_label($row['order_period'])); ?></div>
                    <div><strong><?php echo number_format($row['total_orders']); ?></strong> รายการ · <strong><?php echo number_format($row['total_amount']); ?></strong> บาท</div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if (!$is_today_view) { ?>
    <div class="card section" style="margin-top:16px">
        <h2>🏆 ลูกค้ายอดสูงเดือนนี้</h2>
        <?php if (!$customer_ranking) { ?>
            <div class="empty">ยังไม่มีข้อมูล</div>
        <?php } else { ?>
            <?php foreach ($customer_ranking as $index => $row) { ?>
                <div class="list-row">
                    <div><?php echo ($index + 1) . '. ' . h($row['name']); ?></div>
                    <div><strong><?php echo number_format($row['total']); ?></strong> บาท</div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <?php } ?>
</div>

<div class="toast" id="shareToast"></div>
<script>
(function(){
    var shareText = <?php echo json_encode($line_summary_text, JSON_UNESCAPED_UNICODE); ?>;
    var liffId = <?php echo json_encode($line_summary_liff_id, JSON_UNESCAPED_UNICODE); ?>;
    var shareBtn = document.getElementById('lineShareBtn');
    var copyBtn = document.getElementById('copySummaryBtn');
    var toast = document.getElementById('shareToast');

    function showToast(message) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        window.clearTimeout(showToast._timer);
        showToast._timer = window.setTimeout(function(){
            toast.classList.remove('show');
        }, 2200);
    }

    function fallbackLineShare() {
        var shareUrl = 'https://line.me/R/msg/text/?' + encodeURIComponent(shareText);
        window.location.href = shareUrl;
    }

    async function copySummary() {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(shareText);
            } else {
                var temp = document.createElement('textarea');
                temp.value = shareText;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }
            showToast('คัดลอกข้อความแล้ว');
        } catch (err) {
            showToast('คัดลอกไม่สำเร็จ');
        }
    }

    async function loadLiff() {
        if (window._iceReportLiffLoaded) return true;
        return new Promise(function(resolve){
            var s = document.createElement('script');
            s.src = 'https://static.line-scdn.net/liff/edge/2/sdk.js';
            s.onload = function(){ window._iceReportLiffLoaded = true; resolve(true); };
            s.onerror = function(){ resolve(false); };
            document.head.appendChild(s);
        });
    }

    async function shareViaLine() {
        if (liffId) { await loadLiff(); }
        if (typeof liff !== 'undefined' && liffId) {
            try {
                if (!liff.isInClient()) {
                    await liff.init({ liffId: liffId, withLoginOnExternalBrowser: true });
                } else {
                    await liff.init({ liffId: liffId });
                }
                if (liff.isApiAvailable && liff.isApiAvailable('shareTargetPicker')) {
                    var result = await liff.shareTargetPicker([{ type: 'text', text: shareText }]);
                    if (result) {
                        showToast('ส่งข้อความไปที่ LINE แล้ว');
                        return;
                    }
                }
            } catch (err) {
            }
        }
        fallbackLineShare();
    }

    if (shareBtn) {
        shareBtn.addEventListener('click', function(){
            shareViaLine();
        });
    }
    if (copyBtn) {
        copyBtn.addEventListener('click', function(){
            copySummary();
        });
    }
})();
</script>
</body>
</html>
