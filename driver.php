<?php
include 'config.php';

$message = '';
$message_type = 'success';
$is_ajax_request = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

function driver_json_response($ok, $message, $extra = array()) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(array('ok' => (bool)$ok, 'message' => (string)$message), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function driver_status_label($status) {
    if ($status === 'paid') {
        return 'เก็บเงินแล้ว';
    }
    if ($status === 'delivered') {
        return 'ส่งแล้ว';
    }
    return 'รอส่ง';
}

function driver_next_action_label($status) {
    if ($status === 'delivered') {
        return 'รับเงินแล้ว';
    }
    if ($status === 'paid') {
        return 'เสร็จแล้ว';
    }
    return 'ส่งแล้ว';
}

function driver_next_action_status($status) {
    if ($status === 'delivered') {
        return 'paid';
    }
    if ($status === 'paid') {
        return null;
    }
    return 'delivered';
}

function driver_format_money($amount) {
    return number_format((float)$amount) . ' บาท';
}

function driver_fetch_orders_by_date_period($conn, $date, $period = '') {
    $dateEsc = mysqli_real_escape_string($conn, $date);
    $orderBy = customers_order_by_sql('customers') . ', orders.id ASC';
    $sql = "SELECT orders.id, orders.customer_id, orders.order_date, orders.order_period, orders.status, orders.delivery_note,
                   customers.name, customers.phone, customers.route, customers.route_order,
                   customers.map_url, customers.delivery_point_url,
                   COALESCE(SUM(order_items.qty * order_items.price), 0) AS total_amount
            FROM orders
            LEFT JOIN customers ON customers.id = orders.customer_id
            LEFT JOIN order_items ON order_items.order_id = orders.id
            WHERE orders.order_date = '{$dateEsc}'
            GROUP BY orders.id
            ORDER BY {$orderBy}";
    return fetch_all_rows(@mysqli_query($conn, $sql));
}

function driver_fetch_outstanding_groups($conn, $period = '') {
    $todayEsc = mysqli_real_escape_string($conn, date('Y-m-d'));
    $orderBy = customers_order_by_sql('customers') . ', orders.order_date ASC, orders.id ASC';
    $sql = "SELECT orders.id, orders.customer_id, orders.order_date, orders.order_period, orders.status, orders.delivery_note,
                   customers.name, customers.phone, customers.route, customers.route_order,
                   customers.map_url, customers.delivery_point_url,
                   COALESCE(SUM(order_items.qty * order_items.price), 0) AS total_amount
            FROM orders
            LEFT JOIN customers ON customers.id = orders.customer_id
            LEFT JOIN order_items ON order_items.order_id = orders.id
            WHERE orders.paid = 0 AND orders.order_date < '{$todayEsc}'
            GROUP BY orders.id
            ORDER BY {$orderBy}";
    $rows = fetch_all_rows(@mysqli_query($conn, $sql));
    $groups = array();
    foreach ($rows as $row) {
        $customerId = isset($row['customer_id']) ? (int)$row['customer_id'] : 0;
        if ($customerId <= 0) {
            $customerId = -(int)$row['id'];
        }
        if (!isset($groups[$customerId])) {
            $groups[$customerId] = array(
                'customer_id' => $customerId,
                'name' => isset($row['name']) ? $row['name'] : '',
                'phone' => isset($row['phone']) ? $row['phone'] : '',
                'route' => isset($row['route']) ? $row['route'] : 0,
                'route_order' => isset($row['route_order']) ? $row['route_order'] : 0,
                'total_amount' => 0,
                'count' => 0,
                'orders' => array(),
                'first_date' => isset($row['order_date']) ? $row['order_date'] : '',
                'last_date' => isset($row['order_date']) ? $row['order_date'] : '',
                'notes' => array(),
                'has_pending' => false,
                'has_delivered' => false,
            );
        }
        $groups[$customerId]['total_amount'] += (float)$row['total_amount'];
        $groups[$customerId]['count']++;
        $groups[$customerId]['orders'][] = $row;
        $status = isset($row['status']) ? (string)$row['status'] : '';
        if ($status === 'delivered') {
            $groups[$customerId]['has_delivered'] = true;
        }
        if ($status === '' || $status === 'pending') {
            $groups[$customerId]['has_pending'] = true;
        }
        $date = isset($row['order_date']) ? (string)$row['order_date'] : '';
        if ($groups[$customerId]['first_date'] === '' || ($date !== '' && strcmp($date, $groups[$customerId]['first_date']) < 0)) {
            $groups[$customerId]['first_date'] = $date;
        }
        if ($groups[$customerId]['last_date'] === '' || ($date !== '' && strcmp($date, $groups[$customerId]['last_date']) > 0)) {
            $groups[$customerId]['last_date'] = $date;
        }
        $note = trim(isset($row['delivery_note']) ? (string)$row['delivery_note'] : '');
        if ($note !== '' && !in_array($note, $groups[$customerId]['notes'], true)) {
            $groups[$customerId]['notes'][] = $note;
        }
    }
    return array_values($groups);
}

function driver_count_by_status($orders, $status) {
    $count = 0;
    foreach ($orders as $order) {
        if ((string)(isset($order['status']) ? $order['status'] : 'pending') === $status) {
            $count++;
        }
    }
    return $count;
}

if (isset($_SESSION['ice_saved_notice']) && is_array($_SESSION['ice_saved_notice'])) {
    $savedInfo = $_SESSION['ice_saved_notice'];
    unset($_SESSION['ice_saved_notice']);
    $savedCount = isset($savedInfo['count']) ? (int)$savedInfo['count'] : 0;
    $savedPeriod = isset($savedInfo['period']) ? (string)$savedInfo['period'] : '';
    $savedDate = isset($savedInfo['date']) ? (string)$savedInfo['date'] : '';
    $message = 'บันทึกออเดอร์เรียบร้อย';
    if ($savedCount > 0) { $message .= ' ' . number_format($savedCount) . ' ร้าน'; }
    if ($savedPeriod !== '') { $message .= ' • ' . get_period_label($savedPeriod); }
    if ($savedDate !== '') { $message .= ' • ' . $savedDate; }
}

if (isset($_GET['action']) && $_GET['action'] === 'check_order_count') {
    $todayEsc = mysqli_real_escape_string($conn, date('Y-m-d'));
    $cntRes = @mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE order_date='{$todayEsc}'");
    $row = $cntRes ? mysqli_fetch_assoc($cntRes) : null;
    driver_json_response(true, '', array('count' => $row ? (int)$row['cnt'] : 0));
}

if (isset($_GET['action']) && $_GET['action'] === 'driver_history') {
    $sql = "SELECT orders.id, orders.order_date, orders.order_period, orders.status,
                   orders.delivered_qty, orders.delivery_photo, orders.delivered_at,
                   customers.name,
                   COALESCE(SUM(order_items.qty * order_items.price), 0) AS total_amount
            FROM orders
            LEFT JOIN customers ON customers.id = orders.customer_id
            LEFT JOIN order_items ON order_items.order_id = orders.id
            WHERE orders.status IN ('delivered','paid') AND orders.delivered_at IS NOT NULL
            GROUP BY orders.id
            ORDER BY orders.delivered_at DESC
            LIMIT 50";
    $rows = fetch_all_rows(@mysqli_query($conn, $sql));
    driver_json_response(true, '', array('history' => $rows));
}

if (isset($_GET['action']) && $_GET['action'] === 'nearby_stores') {
    $lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float)$_GET['lat'] : null;
    $lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float)$_GET['lng'] : null;
    if ($lat === null || $lng === null) {
        driver_json_response(false, 'ไม่มีพิกัด GPS');
    }
    $todayEsc = mysqli_real_escape_string($conn, date('Y-m-d'));
    $latF = round($lat, 7);
    $lngF = round($lng, 7);
    $sql = "SELECT customers.id, customers.name, customers.latitude, customers.longitude,
                   COALESCE(orders.status, 'no_order') AS order_status,
                   orders.id AS order_id,
                   ROUND(6371000 * ACOS(LEAST(1, COS(RADIANS({$latF})) * COS(RADIANS(customers.latitude)) *
                     COS(RADIANS(customers.longitude) - RADIANS({$lngF})) +
                     SIN(RADIANS({$latF})) * SIN(RADIANS(customers.latitude))))) AS distance_m
            FROM customers
            LEFT JOIN orders ON orders.customer_id = customers.id
                AND orders.order_date = '{$todayEsc}'
            WHERE customers.latitude IS NOT NULL AND customers.longitude IS NOT NULL
            HAVING distance_m <= 300
            ORDER BY distance_m ASC";
    $stores = fetch_all_rows(@mysqli_query($conn, $sql));
    driver_json_response(true, count($stores) . ' ร้านในรัศมี 300 เมตร', array('stores' => $stores));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deliver_confirm') {
    if (!csrf_validate()) {
        driver_json_response(false, 'เซสชันหมดอายุ กรุณาลองใหม่');
    }
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    $qty = isset($_POST['qty']) && $_POST['qty'] !== '' ? max(0, (int)$_POST['qty']) : null;
    $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
    $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;

    if ($id <= 0) {
        driver_json_response(false, 'ข้อมูลไม่ถูกต้อง');
    }

    $photoSql = 'NULL';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && $_FILES['photo']['size'] <= 10485760) {
        $uploadDir = __DIR__ . '/uploads/delivery/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $tmpName = $_FILES['photo']['tmp_name'];
        $ext = 'jpg';
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? @finfo_file($finfo, $tmpName) : '';
        $mimeMap = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');
        if (isset($mimeMap[$mime])) {
            $ext = $mimeMap[$mime];
        }
        $filename = $id . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
        if (@move_uploaded_file($tmpName, $uploadDir . $filename)) {
            $photoSql = "'" . mysqli_real_escape_string($conn, 'uploads/delivery/' . $filename) . "'";
        }
    }

    $sets = array("status='delivered'", "delivered=1", "delivered_at=NOW()", "delivery_photo={$photoSql}");
    $sets[] = $qty !== null ? "delivered_qty={$qty}" : "delivered_qty=NULL";
    if ($lat !== null && $lng !== null && abs($lat) > 0.001 && abs($lng) > 0.001) {
        $sets[] = "delivery_lat=" . round($lat, 7);
        $sets[] = "delivery_lng=" . round($lng, 7);
    }
    @mysqli_query($conn, "UPDATE orders SET " . implode(', ', $sets) . " WHERE id={$id} AND status='pending' LIMIT 1");
    if (mysqli_errno($conn)) {
        driver_json_response(false, 'บันทึกไม่สำเร็จ: ' . mysqli_error($conn));
    }
    admin_log_action('deliver_confirm', 'ส่งของออเดอร์ ID ' . $id . ($qty !== null ? ' จำนวน ' . $qty . ' ก้อน' : ''));
    driver_json_response(true, 'บันทึกการส่งเรียบร้อย', array('status' => 'delivered', 'id' => $id));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    if (!csrf_validate()) {
        $message = 'เซสชันหมดอายุ กรุณาลองใหม่';
        $message_type = 'error';
        if ($is_ajax_request) {
            driver_json_response(false, $message, array('message_type' => $message_type));
        }
    } else {
        $id = (int)$_POST['id'];
        $status = trim((string)$_POST['status']);
        if ($id > 0 && in_array($status, array('pending', 'delivered', 'paid'), true)) {
            $delivered = ($status === 'delivered' || $status === 'paid') ? 1 : 0;
            $paid = $status === 'paid' ? 1 : 0;
            @mysqli_query($conn, "UPDATE orders SET status='" . mysqli_real_escape_string($conn, $status) . "', delivered={$delivered}, paid={$paid} WHERE id={$id} LIMIT 1");
            if (mysqli_errno($conn)) {
                $message = 'อัปเดตสถานะไม่สำเร็จ: ' . mysqli_error($conn);
                $message_type = 'error';
            } else {
                $message = 'อัปเดตสถานะเรียบร้อย';
                $message_type = 'success';
            }
        } else {
            $message = 'ข้อมูลสถานะไม่ถูกต้อง';
            $message_type = 'error';
        }
        if ($is_ajax_request) {
            driver_json_response($message_type === 'success', $message, array('message_type' => $message_type, 'id' => $id, 'status' => $status));
        }
    }
}

$selected_view = isset($_GET['view']) ? trim((string)$_GET['view']) : 'today';
if (!in_array($selected_view, array('today', 'outstanding', 'tomorrow', 'history'), true)) {
    $selected_view = 'today';
}
$view_titles = array(
    'today' => 'งานส่งวันนี้',
    'outstanding' => 'ค้างเก็บเงิน',
    'tomorrow' => 'รายการพรุ่งนี้',
    'history' => 'ประวัติการส่ง',
);
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
// โหลดเฉพาะข้อมูลที่ tab ปัจจุบันต้องการ
$_todayEsc = mysqli_real_escape_string($conn, $today);
$todayOrders = ($selected_view === 'today') ? driver_fetch_orders_by_date_period($conn, $today) : array();
$tomorrowOrders = ($selected_view === 'tomorrow') ? driver_fetch_orders_by_date_period($conn, $tomorrow) : array();
$outstandingGroups = ($selected_view === 'outstanding') ? driver_fetch_outstanding_groups($conn) : array();

if ($selected_view === 'today') {
    $todayPending   = driver_count_by_status($todayOrders, 'pending');
    $todayDelivered = driver_count_by_status($todayOrders, 'delivered');
    $todayPaid      = driver_count_by_status($todayOrders, 'paid');
    $todayTotal     = count($todayOrders);
    $todayCollected = 0;
    foreach ($todayOrders as $order) {
        if ((string)(isset($order['status']) ? $order['status'] : 'pending') === 'paid') {
            $todayCollected += (float)$order['total_amount'];
        }
    }
} else {
    // ดึง KPI strip ด้วย single JOIN query แทนการโหลด rows ทั้งหมด
    $_todaySumRes = @mysqli_query($conn, "SELECT
        SUM(CASE WHEN COALESCE(o.status,'pending')='pending' THEN 1 ELSE 0 END) AS cnt_pending,
        SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) AS cnt_delivered,
        SUM(CASE WHEN o.status='paid' THEN 1 ELSE 0 END) AS cnt_paid,
        COUNT(DISTINCT o.id) AS cnt_total,
        COALESCE(SUM(CASE WHEN o.status='paid' THEN oi.qty*oi.price ELSE 0 END),0) AS collected
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id=o.id
        WHERE o.order_date='{$_todayEsc}'");
    if ($_todaySumRes) {
        $_sr = mysqli_fetch_assoc($_todaySumRes);
        $todayPending   = (int)$_sr['cnt_pending'];
        $todayDelivered = (int)$_sr['cnt_delivered'];
        $todayPaid      = (int)$_sr['cnt_paid'];
        $todayTotal     = (int)$_sr['cnt_total'];
        $todayCollected = (float)$_sr['collected'];
    } else {
        $todayPending = $todayDelivered = $todayPaid = $todayTotal = 0;
        $todayCollected = 0;
    }
}

// summary สำหรับ bottom bar — index paid+order_date รองรับแล้ว
$_outRes = @mysqli_query($conn, "SELECT COUNT(*) AS cnt, COALESCE(SUM(oi.qty*oi.price),0) AS amt FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE o.paid=0 AND o.order_date < '{$_todayEsc}'");
$_outRow = $_outRes ? mysqli_fetch_assoc($_outRes) : null;
$outstandingCount  = $_outRow ? (int)$_outRow['cnt'] : 0;
$outstandingAmount = $_outRow ? (float)$_outRow['amt'] : 0;

$_gpsRes = @mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM customers WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
$gpsCustomerCount = $_gpsRes ? (int)(mysqli_fetch_assoc($_gpsRes)['cnt'] ?? 0) : 0;

$historyOrders = array();
if ($selected_view === 'history') {
    $historySql = "SELECT orders.id, orders.order_date, orders.order_period, orders.status,
                          orders.delivered_qty, orders.delivery_photo, orders.delivered_at,
                          customers.name,
                          COALESCE(SUM(order_items.qty * order_items.price), 0) AS total_amount
                   FROM orders
                   LEFT JOIN customers ON customers.id = orders.customer_id
                   LEFT JOIN order_items ON order_items.order_id = orders.id
                   WHERE orders.status IN ('delivered','paid') AND orders.delivered_at IS NOT NULL
                   GROUP BY orders.id
                   ORDER BY orders.delivered_at DESC
                   LIMIT 50";
    $historyOrders = fetch_all_rows(@mysqli_query($conn, $historySql));
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>หน้าคนส่งน้ำแข็ง</title>
<link rel="stylesheet" href="assets/mobile.css">
<link rel="stylesheet" href="assets/app.css?v=20260405d">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b7dda">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body class="page-driver page-driver-v2">
<div class="wrapper">
    <div class="driver-screen-title">
        <div class="driver-screen-kicker">หน้าคนส่ง</div>
        <h1><?php echo h(isset($view_titles[$selected_view]) ? $view_titles[$selected_view] : 'หน้าคนส่งน้ำแข็ง'); ?></h1>
        <div class="driver-screen-meta">กดทีละร้านได้เลย</div>
    </div>
    <div class="driver-topbar">
        <div class="driver-view-tabs">
            <a class="driver-tab<?php echo $selected_view === 'today' ? ' active' : ''; ?>" href="?view=today">วันนี้</a>
            <a class="driver-tab<?php echo $selected_view === 'outstanding' ? ' active' : ''; ?>" href="?view=outstanding">ค้างเก็บเงิน</a>
            <a class="driver-tab<?php echo $selected_view === 'tomorrow' ? ' active' : ''; ?>" href="?view=tomorrow">พรุ่งนี้</a>
            <a class="driver-tab<?php echo $selected_view === 'history' ? ' active' : ''; ?>" href="?view=history">ประวัติ</a>
        </div>
    </div>

    <div class="driver-new-order-banner" id="newOrderBanner" onclick="location.reload()">มีออเดอร์ใหม่ กดเพื่อรีเฟรช</div>

    <?php if ($message !== '') { ?><div class="notice <?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php } ?>

    <?php if ($selected_view === 'today') { ?>
    <div class="driver-nearby-wrap">
        <?php if ($gpsCustomerCount === 0) { ?>
            <div class="driver-gps-note">ยังไม่มีร้านที่ตั้งค่า GPS (ตั้งค่าในหน้าแอดมิน)</div>
        <?php } else { ?>
            <button id="nearbyBtn" class="btn btn-light btn-block">📍 เช็คร้านใกล้ฉัน (GPS)</button>
            <div class="driver-nearby-subtitle">มีร้านที่ตั้งค่า GPS แล้ว <?php echo $gpsCustomerCount; ?> ร้าน</div>
        <?php } ?>
        <div id="nearbyResult" style="display:none" class="driver-nearby-result"></div>
    </div>
    <?php } ?>

    <div class="driver-kpi-strip driver-kpi-strip-compact">
        <div class="driver-kpi-card">
            <div class="driver-kpi-label">รอส่ง</div>
            <div class="driver-kpi-value"><?php echo number_format($todayPending); ?></div>
        </div>
        <div class="driver-kpi-card">
            <div class="driver-kpi-label">ส่งแล้ว</div>
            <div class="driver-kpi-value"><?php echo number_format($todayDelivered); ?></div>
        </div>
        <div class="driver-kpi-card">
            <div class="driver-kpi-label">เก็บเงินแล้ว</div>
            <div class="driver-kpi-value"><?php echo number_format($todayPaid); ?></div>
        </div>
        <div class="driver-kpi-card">
            <div class="driver-kpi-label">ยอดรับวันนี้</div>
            <div class="driver-kpi-value" style="color:var(--m-success,#22c55e)"><?php echo driver_format_money($todayCollected); ?></div>
        </div>
    </div>
    <div class="driver-progress-wrap">
        <div class="driver-progress-bar" style="width: <?php echo floor($todayPaid / max(1, $todayTotal) * 100); ?>%"></div>
    </div>

    <?php if ($selected_view === 'today') { ?>
    <div class="section section-driver-main">
        <div class="driver-section-head">
            <div>
                <h2>งานส่งวันนี้</h2>
                <div class="driver-subhead"><?php echo h($today); ?> • ต่อ 1 ร้าน = 1 ปุ่มหลัก</div>
            </div>
            <label class="driver-filter-toggle"><input type="checkbox" id="toggleHideDone" checked> <span>ซ่อนที่เสร็จแล้ว</span></label>
        </div>
        <?php if (!$todayOrders) { ?>
            <div class="empty">ไม่มีรายการในรอบนี้</div>
        <?php } else { ?>
            <div class="driver-card-list" id="todayCardList">
                <?php foreach ($todayOrders as $order) {
                    $status = $order['status'] ? $order['status'] : 'pending';
                    $nextStatus = driver_next_action_status($status);
                    $nextLabel = driver_next_action_label($status);
                    $mapUrl = trim(isset($order['delivery_point_url']) && $order['delivery_point_url'] !== '' ? $order['delivery_point_url'] : (isset($order['map_url']) ? $order['map_url'] : ''));
                ?>
                <div class="driver-job-card<?php echo $status === 'paid' ? ' is-done' : ''; ?>" data-order-id="<?php echo (int)$order['id']; ?>" data-status="<?php echo h($status); ?>">
                    <div class="driver-job-main">
                        <div class="driver-job-title-row">
                            <div>
                                <div class="driver-job-title"><?php echo h($order['name']); ?></div>
                                <div class="driver-job-meta">
                                    <span><?php echo h(customer_sort_summary($order['route'], $order['route_order'])); ?></span>
                                    
                                </div>
                            </div>
                            <div class="status-badge status-<?php echo h($status); ?> js-status-badge"><?php echo h(driver_status_label($status)); ?></div>
                        </div>
                        <div class="driver-job-amount-wrap"><div class="driver-job-amount-label">ยอดรับจากร้านนี้</div><div class="driver-job-amount"><?php echo driver_format_money($order['total_amount']); ?></div></div>
                        <?php if (!empty($order['delivery_note'])) { ?><div class="delivery-note">หมายเหตุส่งของ: <?php echo h($order['delivery_note']); ?></div><?php } ?>
                        <div class="driver-quick-actions">
                            <?php if (!empty($order['phone'])) { ?><a class="mini-action" href="tel:<?php echo h($order['phone']); ?>">โทร</a><?php } ?>
                            <?php if ($mapUrl !== '') { ?><a class="mini-action" href="<?php echo h($mapUrl); ?>" target="_blank" rel="noopener">แผนที่</a><?php } ?>
                        </div>
                    </div>
                    <div class="driver-job-actions js-actions-wrap">
                        <?php if ($status === 'paid') { ?>
                            <span class="btn-disabled btn-block">เสร็จแล้ว</span>
                        <?php } elseif ($status === 'pending') { ?>
                            <button class="btn btn-send btn-block js-open-deliver-modal" data-id="<?php echo (int)$order['id']; ?>" data-name="<?php echo h($order['name']); ?>">ส่งแล้ว</button>
                        <?php } else { ?>
                            <form method="post" class="status-form driver-single-action"><?php echo csrf_input(); ?><input type="hidden" name="id" value="<?php echo (int)$order['id']; ?>"><input type="hidden" name="status" value="<?php echo h($nextStatus); ?>"><button class="btn btn-pay js-status-btn btn-block" data-id="<?php echo (int)$order['id']; ?>" data-status="<?php echo h($nextStatus); ?>"><?php echo h($nextLabel); ?></button></form>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php } ?>
    <?php if ($selected_view === 'tomorrow') { ?>
    <div class="section">
        <div class="driver-section-head">
            <div>
                <h2>รายการพรุ่งนี้</h2>
                <div class="driver-subhead"><?php echo h($tomorrow); ?></div>
            </div>
        </div>
        <?php if (!$tomorrowOrders) { ?>
            <div class="empty">ไม่มีรายการในรอบนี้</div>
        <?php } else { ?>
            <div class="driver-card-list driver-card-list-compact">
                <?php foreach ($tomorrowOrders as $order) { ?>
                <div class="driver-job-card driver-job-card-tomorrow">
                    <div class="driver-job-main">
                        <div class="driver-job-title-row">
                            <div>
                                <div class="driver-job-title"><?php echo h($order['name']); ?></div>
                                <div class="driver-job-meta">
                                    <span><?php echo h(customer_sort_summary($order['route'], $order['route_order'])); ?></span>
                                    
                                </div>
                            </div>
                            <div class="status-badge status-<?php echo h($order['status'] ? $order['status'] : 'pending'); ?>"><?php echo h(driver_status_label($order['status'] ? $order['status'] : 'pending')); ?></div>
                        </div>
                        <div class="driver-job-amount-wrap"><div class="driver-job-amount-label">ยอดรับจากร้านนี้</div><div class="driver-job-amount"><?php echo driver_format_money($order['total_amount']); ?></div></div>
                        <?php if (!empty($order['delivery_note'])) { ?><div class="delivery-note">หมายเหตุส่งของ: <?php echo h($order['delivery_note']); ?></div><?php } ?>
                    </div>
                    <div class="driver-job-actions"><span class="btn-disabled btn-block">พรุ่งนี้</span></div>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php } ?>
    <?php if ($selected_view === 'outstanding') { ?>
    <div class="section">
        <div class="driver-section-head">
            <div>
                <h2>ค้างเก็บเงิน</h2>
                <div class="driver-subhead">รวมตามลูกค้าเพื่อให้ดูง่ายขึ้น</div>
            </div>
        </div>
        <?php if (!$outstandingGroups) { ?>
            <div class="empty">ไม่มีรายการค้างเก็บเงิน</div>
        <?php } else { ?>
            <div class="driver-outstanding-list">
                <?php foreach ($outstandingGroups as $group) { ?>
                <div class="driver-outstanding-card">
                    <div class="driver-outstanding-top">
                        <div>
                            <div class="driver-job-title"><?php echo h($group['name']); ?></div>
                            <div class="driver-job-meta">
                                <span><?php echo h(customer_sort_summary($group['route'], $group['route_order'])); ?></span>
                                <span>ค้าง <?php echo number_format($group['count']); ?> บิล</span>
                                <span><?php echo h($group['first_date']); ?><?php if ($group['first_date'] !== $group['last_date']) { ?> - <?php echo h($group['last_date']); ?><?php } ?></span>
                            </div>
                            <?php if (!empty($group['notes'])) { ?><div class="delivery-note">หมายเหตุส่งของ: <?php echo h(implode(' | ', $group['notes'])); ?></div><?php } ?>
                        </div>
                        <div class="driver-outstanding-sum"><?php echo driver_format_money($group['total_amount']); ?></div>
                    </div>
                    <details class="driver-outstanding-details">
                        <summary>ดูบิลย่อย <?php echo number_format($group['count']); ?> รายการ</summary>
                        <div class="driver-suborder-list">
                            <?php foreach ($group['orders'] as $sub_order) {
                                $status = $sub_order['status'] ? $sub_order['status'] : 'pending';
                                $nextStatus = driver_next_action_status($status);
                                $nextLabel = driver_next_action_label($status);
                            ?>
                            <div class="driver-suborder-row" data-order-id="<?php echo (int)$sub_order['id']; ?>" data-status="<?php echo h($status); ?>">
                                <div class="driver-suborder-info">
                                    <div class="driver-suborder-title">วันที่ <?php echo h($sub_order['order_date']); ?> • <?php echo h(get_period_label($sub_order['order_period'])); ?></div><div class="driver-suborder-statusline"><?php echo h(driver_status_label($status)); ?></div>
                                    <div class="driver-suborder-money"><?php echo driver_format_money($sub_order['total_amount']); ?></div>
                                    <?php if (!empty($sub_order['delivery_note'])) { ?><div class="driver-suborder-note">หมายเหตุ: <?php echo h($sub_order['delivery_note']); ?></div><?php } ?>
                                </div>
                                <div class="status-badge status-<?php echo h($status); ?> js-status-badge"><?php echo h(driver_status_label($status)); ?></div>
                                <div class="driver-suborder-actions js-actions-wrap">
                                    <?php if ($status === 'paid') { ?>
                                        <span class="btn-disabled btn-block">เสร็จแล้ว</span>
                                    <?php } else { ?>
                                        <form method="post" class="status-form driver-single-action"><?php echo csrf_input(); ?><input type="hidden" name="id" value="<?php echo (int)$sub_order['id']; ?>"><input type="hidden" name="status" value="<?php echo h($nextStatus); ?>"><button class="btn <?php echo $nextStatus === 'paid' ? 'btn-pay' : 'btn-send'; ?> js-status-btn btn-block" data-id="<?php echo (int)$sub_order['id']; ?>" data-status="<?php echo h($nextStatus); ?>"><?php echo h($nextLabel); ?></button></form>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </details>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php } ?>
    <?php if ($selected_view === 'history') { ?>
    <div class="section">
        <div class="driver-section-head">
            <div>
                <h2>ประวัติการส่ง</h2>
                <div class="driver-subhead">50 รายการล่าสุด</div>
            </div>
        </div>
        <?php if (!$historyOrders) { ?>
            <div class="empty">ยังไม่มีประวัติการส่ง</div>
        <?php } else { ?>
            <div>
                <?php foreach ($historyOrders as $hOrder) {
                    $hDeliveredAt = isset($hOrder['delivered_at']) ? $hOrder['delivered_at'] : '';
                    $hDateDisplay = '';
                    if ($hDeliveredAt !== '') {
                        $ts = strtotime($hDeliveredAt);
                        if ($ts) {
                            $thMonth = array('','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.');
                            $d = (int)date('j', $ts);
                            $m = (int)date('n', $ts);
                            $y = (int)date('Y', $ts) + 543;
                            $t = date('H:i', $ts);
                            $hDateDisplay = $d . ' ' . $thMonth[$m] . ' ' . $y . ' ' . $t . ' น.';
                        }
                    }
                    $hasPhoto = !empty($hOrder['delivery_photo']);
                ?>
                <div class="driver-history-item">
                    <div class="driver-history-top">
                        <div>
                            <div class="driver-history-name"><?php echo h($hOrder['name']); ?></div>
                            <div class="driver-history-meta"><?php echo $hDateDisplay !== '' ? h($hDateDisplay) : '—'; ?> • ส่ง <?php echo (int)(isset($hOrder['delivered_qty']) ? $hOrder['delivered_qty'] : 0); ?> ชิ้น</div>
                            <span class="driver-history-photo-badge"><?php echo $hasPhoto ? '📷 มีรูป' : 'ไม่มีรูป'; ?></span>
                        </div>
                        <div class="driver-history-amount"><?php echo driver_format_money($hOrder['total_amount']); ?></div>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php } ?>
</div>
<div class="driver-bottom-bar">
    <div class="driver-bottom-item"><strong id="bottomPending"><?php echo number_format($todayPending); ?></strong><span>รอส่ง</span></div>
    <div class="driver-bottom-item"><strong id="bottomDelivered"><?php echo number_format($todayDelivered); ?></strong><span>รอเก็บเงิน</span></div>
    <div class="driver-bottom-item"><strong id="bottomOutstanding"><?php echo number_format($outstandingAmount); ?></strong><span>ยอดค้าง</span></div>
</div>
<div class="toast" id="driverToast"></div>

<div id="deliverModal" class="deliver-modal-overlay" aria-modal="true" role="dialog">
  <div class="deliver-modal-box">
    <div class="deliver-modal-drag"></div>
    <div class="deliver-modal-title">ยืนยันส่งของ — <span id="deliverModalName"></span></div>
    <div class="deliver-modal-body">
      <label class="deliver-label">จำนวนที่ส่ง (ถัง / ก้อน)</label>
      <input type="number" id="deliverQty" class="deliver-input" min="0" inputmode="numeric" placeholder="เช่น 10">
      <label class="deliver-label" style="margin-top:12px">ถ่ายรูปหลักฐาน <span class="deliver-optional">(ไม่บังคับ)</span></label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <label class="deliver-camera-btn" for="deliverPhotoCamera">📷 ถ่ายรูป</label>
        <label class="deliver-camera-btn" for="deliverPhotoGallery" style="background:#f3f4f6;color:#333">🖼️ เลือกจากคลัง</label>
      </div>
      <input type="file" id="deliverPhotoCamera" accept="image/*" capture="environment" style="display:none">
      <input type="file" id="deliverPhotoGallery" accept="image/*" style="display:none">
      <div id="deliverPhotoPreview" style="display:none;margin-top:8px">
        <img id="deliverPhotoImg" style="max-width:100%;border-radius:8px;border:2px solid #e0e0e0">
        <button id="deliverPhotoClear" class="deliver-photo-clear">✕ ลบรูป</button>
      </div>
      <div id="deliverGpsStatus" class="deliver-gps-status"></div>
    </div>
    <div class="deliver-modal-actions">
      <button id="deliverCancelBtn" class="btn btn-light">ยกเลิก</button>
      <button id="deliverConfirmBtn" class="btn btn-send">✔ ยืนยันส่งแล้ว</button>
    </div>
  </div>
</div>

<script>
(function(){
  'use strict';
  var FETCH_URL = 'driver.php?view=<?php echo h($selected_view); ?>';
  var csrfToken = <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE); ?>;
  var selectedView = <?php echo json_encode($selected_view, JSON_UNESCAPED_UNICODE); ?>;

  // ---- Fetch with timeout ----
  function fetchWithTimeout(url, opts, ms) {
    var ctrl = new AbortController();
    var timer = setTimeout(function(){ ctrl.abort(); }, ms || 15000);
    opts.signal = ctrl.signal;
    return fetch(url, opts).finally(function(){ clearTimeout(timer); });
  }

  // ---- Compress image ----
  async function compressImage(file, maxPx, quality) {
    return new Promise(function(resolve) {
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function() {
        var w = img.width, h = img.height;
        if (w > maxPx || h > maxPx) {
          var ratio = Math.min(maxPx/w, maxPx/h);
          w = Math.round(w*ratio); h = Math.round(h*ratio);
        }
        var canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        URL.revokeObjectURL(url);
        canvas.toBlob(function(blob) { resolve(blob || file); }, 'image/jpeg', quality);
      };
      img.onerror = function() { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  // ---- ป้องกันกดซ้ำ global lock ----
  var _inFlight = {};
  function lockId(id){ _inFlight[id] = true; }
  function unlockId(id){ delete _inFlight[id]; }
  function isLocked(id){ return !!_inFlight[id]; }

  // ---- Toast ----
  var toast = document.getElementById('driverToast');
  var _toastTimer = null;
  function showToast(msg, isError){
    if(!toast) return;
    toast.textContent = msg || '';
    toast.className = 'toast show' + (isError ? ' toast-error' : '');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(function(){ toast.className = 'toast'; }, 2800);
  }

  // ---- Helpers ----
  function statusLabel(s){
    return s==='paid'?'เก็บเงินแล้ว': s==='delivered'?'ส่งแล้ว':'รอส่ง';
  }
  function nextActionForStatus(s){
    return s==='delivered'?'paid': s==='paid'?null:'delivered';
  }
  function nextLabelForStatus(s){
    return s==='delivered'?'รับเงินแล้ว':'ส่งแล้ว';
  }

  function buildActionHtml(id, name, status){
    if(status==='paid') return '<span class="btn-done btn-block">✓ เสร็จแล้ว</span>';
    if(status==='pending'){
      var n = name ? name.replace(/"/g,'&quot;') : '';
      return '<button class="btn btn-send btn-block js-open-deliver-modal" data-id="'+id+'" data-name="'+n+'">ส่งแล้ว</button>';
    }
    // delivered → รับเงิน
    return '<button class="btn btn-pay btn-block js-status-btn" data-id="'+id+'" data-status="paid">รับเงินแล้ว</button>';
  }

  function setCardLoading(card, loading){
    if(!card) return;
    card.classList.toggle('is-updating', loading);
    card.querySelectorAll('button').forEach(function(b){ b.disabled = loading; });
  }

  function updateCard(card, newStatus){
    if(!card) return;
    var id = card.getAttribute('data-order-id') || '';
    var name = card.querySelector('.driver-job-title') ? card.querySelector('.driver-job-title').textContent : '';
    card.setAttribute('data-status', newStatus);
    card.classList.toggle('is-done', newStatus==='paid');
    var badge = card.querySelector('.js-status-badge');
    if(badge){ badge.className='status-badge status-'+newStatus+' js-status-badge'; badge.textContent=statusLabel(newStatus); }
    var wrap = card.querySelector('.js-actions-wrap');
    if(wrap) wrap.innerHTML = buildActionHtml(id, name, newStatus);
    setCardLoading(card, false);
    applyHideDone();
    updateBottomCounts();
  }

  function moveToNextCard(card){
    if(selectedView!=='today'||!card) return;
    var next = card.nextElementSibling;
    if(next && next.scrollIntoView) setTimeout(function(){ next.scrollIntoView({behavior:'smooth',block:'center'}); }, 150);
  }

  function updateBottomCounts(){
    var pending = document.querySelectorAll('.driver-job-card[data-status="pending"]').length;
    var delivered = document.querySelectorAll('.driver-job-card[data-status="delivered"]').length;
    var outstanding = 0;
    document.querySelectorAll('.driver-outstanding-sum').forEach(function(el){
      var n = parseFloat(String(el.textContent||'').replace(/[^0-9.]/g,''));
      if(!isNaN(n)) outstanding += n;
    });
    var bp=document.getElementById('bottomPending'), bd=document.getElementById('bottomDelivered'), bo=document.getElementById('bottomOutstanding');
    if(bp) bp.textContent=pending.toLocaleString('th-TH');
    if(bd) bd.textContent=delivered.toLocaleString('th-TH');
    if(bo) bo.textContent=Math.round(outstanding).toLocaleString('th-TH');
  }

  var hideDoneToggle = document.getElementById('toggleHideDone');
  function applyHideDone(){
    if(!hideDoneToggle) return;
    document.querySelectorAll('.driver-job-card.is-done').forEach(function(c){ c.style.display=hideDoneToggle.checked?'none':''; });
  }
  if(hideDoneToggle){ hideDoneToggle.addEventListener('change', applyHideDone); applyHideDone(); }

  // ---- ปุ่ม รับเงินแล้ว (delivered → paid) ----
  document.addEventListener('click', async function(e){
    var btn = e.target.closest('.js-status-btn');
    if(!btn) return;
    e.preventDefault();
    var id = btn.getAttribute('data-id');
    var newStatus = btn.getAttribute('data-status');
    if(!id || !newStatus) return;
    if(isLocked(id)) return;
    var card = btn.closest('[data-order-id]');
    lockId(id);
    setCardLoading(card, true);
    var fd = new FormData();
    fd.append('id', id);
    fd.append('status', newStatus);
    fd.append('csrf_token', csrfToken);
    try {
      var res = await fetchWithTimeout(FETCH_URL, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'}, 15000);
      var data = await res.json();
      if(!res.ok||!data||!data.ok) throw new Error(data&&data.message?data.message:'อัปเดตไม่สำเร็จ');
      updateCard(card, newStatus);
      moveToNextCard(card);
      showToast('บันทึกเรียบร้อย ✓');
    } catch(err){
      setCardLoading(card, false);
      var msg = (err && err.name === 'AbortError') ? 'หมดเวลา กรุณาลองใหม่' : (err&&err.message?err.message:'อัปเดตไม่สำเร็จ');
      showToast(msg, true);
    } finally {
      unlockId(id);
    }
  });

  // ---- Deliver Modal ----
  var deliverModal = document.getElementById('deliverModal');
  var deliverModalName = document.getElementById('deliverModalName');
  var deliverQty = document.getElementById('deliverQty');
  var deliverPhoto = document.getElementById('deliverPhotoCamera');
  var deliverPhotoGallery = document.getElementById('deliverPhotoGallery');
  var deliverPhotoImg = document.getElementById('deliverPhotoImg');
  var deliverPhotoPreview = document.getElementById('deliverPhotoPreview');
  var deliverPhotoClear = document.getElementById('deliverPhotoClear');
  var deliverGpsStatus = document.getElementById('deliverGpsStatus');
  var deliverCancelBtn = document.getElementById('deliverCancelBtn');
  var deliverConfirmBtn = document.getElementById('deliverConfirmBtn');
  var _modalSubmitting = false;
  var currentDeliverId = null;
  var currentDeliverCard = null;
  var deliverGpsLat = null;
  var deliverGpsLng = null;

  function openDeliverModal(id, name, card){
    if(isLocked(id)) return;
    currentDeliverId = id;
    currentDeliverCard = card;
    deliverModalName.textContent = name;
    deliverQty.value = '';
    deliverPhoto.value = '';
    deliverPhotoGallery.value = '';
    deliverPhotoImg.src = '';
    deliverPhotoPreview.style.display = 'none';
    deliverGpsLat = null; deliverGpsLng = null;
    deliverGpsStatus.textContent = 'กำลังดึง GPS...';
    deliverGpsStatus.className = 'deliver-gps-status';
    _modalSubmitting = false;
    deliverConfirmBtn.disabled = false;
    deliverConfirmBtn.textContent = '✔ ยืนยันส่งแล้ว';
    deliverModal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    setTimeout(function(){ deliverQty.focus(); }, 200);
    if(navigator.geolocation){
      navigator.geolocation.getCurrentPosition(function(pos){
        deliverGpsLat = pos.coords.latitude;
        deliverGpsLng = pos.coords.longitude;
        deliverGpsStatus.textContent = '📍 '+deliverGpsLat.toFixed(5)+', '+deliverGpsLng.toFixed(5);
        deliverGpsStatus.className = 'deliver-gps-status gps-ok';
      }, function(){
        deliverGpsStatus.textContent = 'ไม่สามารถดึง GPS ได้';
        deliverGpsStatus.className = 'deliver-gps-status gps-fail';
      }, {timeout:8000, maximumAge:30000});
    } else {
      deliverGpsStatus.textContent = 'อุปกรณ์ไม่รองรับ GPS';
    }
  }

  function closeDeliverModal(){
    if(_modalSubmitting) return;
    deliverModal.classList.remove('is-open');
    document.body.style.overflow = '';
    currentDeliverId = null;
    currentDeliverCard = null;
  }

  function onPhotoSelected(input) {
    var file = input.files[0];
    if(!file) return;
    if(input !== deliverPhoto) { deliverPhoto.value = ''; }
    if(input !== deliverPhotoGallery) { deliverPhotoGallery.value = ''; }
    var reader = new FileReader();
    reader.onload = function(ev){ deliverPhotoImg.src=ev.target.result; deliverPhotoPreview.style.display='block'; };
    reader.readAsDataURL(file);
  }
  deliverPhoto.addEventListener('change', function(){ onPhotoSelected(deliverPhoto); });
  deliverPhotoGallery.addEventListener('change', function(){ onPhotoSelected(deliverPhotoGallery); });
  deliverPhotoClear.addEventListener('click', function(){
    deliverPhoto.value=''; deliverPhotoGallery.value=''; deliverPhotoImg.src=''; deliverPhotoPreview.style.display='none';
  });
  deliverCancelBtn.addEventListener('click', closeDeliverModal);
  deliverModal.addEventListener('click', function(e){ if(e.target===deliverModal) closeDeliverModal(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeDeliverModal(); });

  deliverConfirmBtn.addEventListener('click', async function(){
    if(!currentDeliverId || _modalSubmitting) return;
    _modalSubmitting = true;
    deliverConfirmBtn.disabled = true;
    deliverConfirmBtn.textContent = 'กำลังบันทึก...';
    lockId(currentDeliverId);
    var savedId = currentDeliverId;
    var savedCard = currentDeliverCard;
    var fd = new FormData();
    fd.append('action','deliver_confirm');
    fd.append('csrf_token', csrfToken);
    fd.append('id', savedId);
    if(deliverQty.value!=='') fd.append('qty', deliverQty.value);
    if(deliverGpsLat!==null) fd.append('lat', deliverGpsLat);
    if(deliverGpsLng!==null) fd.append('lng', deliverGpsLng);
    var photoFile = (deliverPhoto.files[0]) || (deliverPhotoGallery.files[0]);
    if(photoFile) {
      var compressed = await compressImage(photoFile, 1280, 0.82);
      fd.append('photo', compressed, 'delivery.jpg');
    }
    try {
      var res = await fetchWithTimeout(FETCH_URL, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'}, 15000);
      var data = await res.json();
      if(!res.ok||!data||!data.ok) throw new Error(data&&data.message?data.message:'บันทึกไม่สำเร็จ');
      deliverModal.classList.remove('is-open');
      document.body.style.overflow='';
      currentDeliverId=null; currentDeliverCard=null;
      updateCard(savedCard, 'delivered');
      moveToNextCard(savedCard);
      showToast('ส่งแล้วเรียบร้อย ✓');
    } catch(err){
      var msg = (err && err.name === 'AbortError') ? 'หมดเวลา กรุณาลองใหม่' : (err&&err.message?err.message:'บันทึกไม่สำเร็จ');
      showToast(msg, true);
    } finally {
      _modalSubmitting = false;
      deliverConfirmBtn.disabled = false;
      deliverConfirmBtn.textContent = '✔ ยืนยันส่งแล้ว';
      unlockId(savedId);
    }
  });

  document.addEventListener('click', function(e){
    var btn = e.target.closest('.js-open-deliver-modal');
    if(!btn) return;
    e.preventDefault();
    var card = btn.closest('[data-order-id]');
    openDeliverModal(btn.getAttribute('data-id'), btn.getAttribute('data-name'), card);
  });

  // ---- GPS Nearby ----
  var nearbyBtn = document.getElementById('nearbyBtn');
  var nearbyResult = document.getElementById('nearbyResult');
  if(nearbyBtn){
    var _nearbyBusy = false;
    nearbyBtn.addEventListener('click', function(){
      if(_nearbyBusy) return;
      if(!navigator.geolocation){ showToast('อุปกรณ์ไม่รองรับ GPS', true); return; }
      _nearbyBusy = true;
      nearbyBtn.disabled = true;
      nearbyBtn.textContent = 'กำลังดึง GPS...';
      navigator.geolocation.getCurrentPosition(async function(pos){
        nearbyBtn.textContent = 'กำลังโหลด...';
        var lat = pos.coords.latitude, lng = pos.coords.longitude;
        try {
          var res = await fetchWithTimeout('driver.php?action=nearby_stores&lat='+lat+'&lng='+lng, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}}, 15000);
          var data = await res.json();
          if(!data||!data.ok) throw new Error(data.message||'เกิดข้อผิดพลาด');
          var stores = data.stores||[];
          var sl = {pending:'รอส่ง',delivered:'ส่งแล้ว',paid:'เก็บเงินแล้ว',no_order:'ไม่มีออเดอร์'};
          var html = '<div class="driver-nearby-title">'+data.message+'</div>';
          if(stores.length===0){
            html += '<div class="driver-nearby-empty">ไม่มีร้านในรัศมี 300 เมตร</div>';
          } else {
            stores.forEach(function(s){
              html += '<div class="driver-nearby-item"><span class="driver-nearby-name">'+s.name+'</span><span class="driver-nearby-dist">'+s.distance_m+' ม.</span><span class="driver-nearby-status status-tag-'+s.order_status+'">'+(sl[s.order_status]||s.order_status)+'</span></div>';
            });
          }
          nearbyResult.innerHTML = html;
          nearbyResult.style.display = 'block';
        } catch(err){
          var msg = (err && err.name === 'AbortError') ? 'หมดเวลา กรุณาลองใหม่' : (err.message||'เกิดข้อผิดพลาด');
          showToast(msg, true);
        } finally {
          _nearbyBusy = false;
          nearbyBtn.disabled = false;
          nearbyBtn.textContent = '📍 เช็คร้านใกล้ฉัน (GPS)';
        }
      }, function(){
        showToast('ไม่สามารถดึง GPS ได้', true);
        _nearbyBusy = false;
        nearbyBtn.disabled = false;
        nearbyBtn.textContent = '📍 เช็คร้านใกล้ฉัน (GPS)';
      }, {timeout:10000, maximumAge:60000});
    });
  }

  // ---- แจ้งเตือนออเดอร์ใหม่ (SSE) ----
  var newOrderBanner = document.getElementById('newOrderBanner');
  (function startOrderSSE() {
    if (!window.EventSource) return;
    var es = new EventSource('driver_sse.php');
    es.addEventListener('new_order', function(e) {
      if (newOrderBanner) newOrderBanner.classList.add('show');
    });
    es.onerror = function() {
      es.close();
      setTimeout(startOrderSSE, 30000);
    };
  })();

})();
</script>
</body>
</html>
