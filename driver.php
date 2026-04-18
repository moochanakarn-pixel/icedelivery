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
        return 'paid';
    }
    return 'delivered';
}

function driver_format_money($amount) {
    return number_format((float)$amount) . ' บาท';
}

function driver_fetch_orders_by_date_period($conn, $date, $period) {
    $dateEsc = mysqli_real_escape_string($conn, $date);
    $periodEsc = mysqli_real_escape_string($conn, $period);
    $orderBy = customers_order_by_sql('customers') . ', orders.id ASC';
    $sql = "SELECT orders.id, orders.customer_id, orders.order_date, orders.order_period, orders.status, orders.delivery_note,
                   customers.name, customers.phone, customers.route, customers.route_order, customers.preferred_round,
                   customers.map_url, customers.delivery_point_url,
                   COALESCE(SUM(order_items.qty * order_items.price), 0) AS total_amount
            FROM orders
            LEFT JOIN customers ON customers.id = orders.customer_id
            LEFT JOIN order_items ON order_items.order_id = orders.id
            WHERE orders.order_date = '{$dateEsc}' AND orders.order_period = '{$periodEsc}'
            GROUP BY orders.id
            ORDER BY {$orderBy}";
    return fetch_all_rows(@mysqli_query($conn, $sql));
}

function driver_fetch_outstanding_groups($conn, $period) {
    $todayEsc = mysqli_real_escape_string($conn, date('Y-m-d'));
    $orderBy = customers_order_by_sql('customers') . ', orders.order_date ASC, orders.id ASC';
    $sql = "SELECT orders.id, orders.customer_id, orders.order_date, orders.order_period, orders.status, orders.delivery_note,
                   customers.name, customers.phone, customers.route, customers.route_order, customers.preferred_round,
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

$periods = order_periods();
$selected_period = normalize_period(isset($_GET['order_period']) ? $_GET['order_period'] : default_period_code());
$selected_view = isset($_GET['view']) ? trim((string)$_GET['view']) : 'today';
if (!in_array($selected_view, array('today', 'outstanding', 'tomorrow'), true)) {
    $selected_view = 'today';
}
$view_titles = array(
    'today' => 'งานส่งวันนี้',
    'outstanding' => 'ค้างเก็บเงิน',
    'tomorrow' => 'รายการพรุ่งนี้',
);
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$todayOrders = driver_fetch_orders_by_date_period($conn, $today, $selected_period);
$tomorrowOrders = driver_fetch_orders_by_date_period($conn, $tomorrow, $selected_period);
$outstandingGroups = driver_fetch_outstanding_groups($conn, $selected_period);
$todayPending = driver_count_by_status($todayOrders, 'pending');
$todayDelivered = driver_count_by_status($todayOrders, 'delivered');
$todayPaid = driver_count_by_status($todayOrders, 'paid');
$outstandingCount = 0;
$outstandingAmount = 0;
foreach ($outstandingGroups as $group) {
    $outstandingCount += (int)$group['count'];
    $outstandingAmount += (float)$group['total_amount'];
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
        <div class="driver-screen-meta"><?php echo h(get_period_label($selected_period)); ?> • กดทีละร้านได้เลย</div>
    </div>
    <div class="driver-topbar">
        <div class="round-quick-links">
            <?php foreach ($periods as $code => $label) { ?>
                <a class="round-link<?php echo $selected_period === $code ? ' active' : ''; ?>" href="?view=<?php echo h($selected_view); ?>&order_period=<?php echo h($code); ?>"><?php echo h(str_replace('รอบ ', '', $label)); ?></a>
            <?php } ?>
        </div>
        <div class="driver-view-tabs">
            <a class="driver-tab<?php echo $selected_view === 'today' ? ' active' : ''; ?>" href="?view=today&order_period=<?php echo h($selected_period); ?>">วันนี้</a>
            <a class="driver-tab<?php echo $selected_view === 'outstanding' ? ' active' : ''; ?>" href="?view=outstanding&order_period=<?php echo h($selected_period); ?>">ค้างเก็บเงิน</a>
            <a class="driver-tab<?php echo $selected_view === 'tomorrow' ? ' active' : ''; ?>" href="?view=tomorrow&order_period=<?php echo h($selected_period); ?>">พรุ่งนี้</a>
        </div>
    </div>

    <?php if ($message !== '') { ?><div class="notice <?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php } ?>

    <div class="driver-kpi-strip driver-kpi-strip-compact">
        <div class="driver-kpi-card">
            <div class="driver-kpi-label">รอส่ง</div>
            <div class="driver-kpi-value"><?php echo number_format($todayPending); ?></div>
        </div>
        <div class="driver-kpi-card">
            <div class="driver-kpi-label">รอเก็บเงิน</div>
            <div class="driver-kpi-value"><?php echo number_format($todayDelivered); ?></div>
        </div>
        <div class="driver-kpi-card">
            <div class="driver-kpi-label">ยอดค้าง</div>
            <div class="driver-kpi-value"><?php echo number_format($outstandingAmount); ?></div>
        </div>
    </div>

    <?php if ($selected_view === 'today') { ?>
    <div class="section section-driver-main">
        <div class="driver-section-head">
            <div>
                <h2>งานส่งวันนี้</h2>
                <div class="driver-subhead"><?php echo h($today); ?> • <?php echo h(get_period_label($selected_period)); ?> • ต่อ 1 ร้าน = 1 ปุ่มหลัก</div>
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
                        <?php } else { ?>
                            <form method="post" class="status-form driver-single-action"><?php echo csrf_input(); ?><input type="hidden" name="id" value="<?php echo (int)$order['id']; ?>"><input type="hidden" name="status" value="<?php echo h($nextStatus); ?>"><button class="btn <?php echo $nextStatus === 'paid' ? 'btn-pay' : 'btn-send'; ?> js-status-btn btn-block" data-id="<?php echo (int)$order['id']; ?>" data-status="<?php echo h($nextStatus); ?>"><?php echo h($nextLabel); ?></button></form>
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
                <div class="driver-subhead"><?php echo h($tomorrow); ?> • <?php echo h(get_period_label($selected_period)); ?></div>
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
                <div class="driver-subhead"><?php echo h(get_period_label($selected_period)); ?> • รวมตามลูกค้าเพื่อให้ดูง่ายขึ้น</div>
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
</div>
<div class="driver-bottom-bar">
    <div class="driver-bottom-item"><strong id="bottomPending"><?php echo number_format($todayPending); ?></strong><span>รอส่ง</span></div>
    <div class="driver-bottom-item"><strong id="bottomDelivered"><?php echo number_format($todayDelivered); ?></strong><span>รอเก็บเงิน</span></div>
    <div class="driver-bottom-item"><strong id="bottomOutstanding"><?php echo number_format($outstandingAmount); ?></strong><span>ยอดค้าง</span></div>
</div>
<div class="toast" id="driverToast"></div>
<script>
(function(){
  var csrfToken = <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE); ?>;
  var selectedView = <?php echo json_encode($selected_view, JSON_UNESCAPED_UNICODE); ?>;
  var toast = document.getElementById('driverToast');
  var hideDoneToggle = document.getElementById('toggleHideDone');
  function showToast(message){
    if(!toast){return;}
    toast.textContent = message || '';
    toast.classList.add('show');
    window.clearTimeout(showToast._timer);
    showToast._timer = window.setTimeout(function(){ toast.classList.remove('show'); }, 1800);
  }
  function nextActionForStatus(status){
    return status === 'delivered' ? 'paid' : (status === 'paid' ? 'paid' : 'delivered');
  }
  function nextLabelForStatus(status){
    return status === 'delivered' ? 'รับเงินแล้ว' : (status === 'paid' ? 'เสร็จแล้ว' : 'ส่งแล้ว');
  }
  function statusLabel(status){
    return status === 'paid' ? 'เก็บเงินแล้ว' : (status === 'delivered' ? 'ส่งแล้ว' : 'รอส่ง');
  }
  function updateBottomCounts(){
    var pending = document.querySelectorAll('.driver-job-card[data-status="pending"]').length;
    var delivered = document.querySelectorAll('.driver-job-card[data-status="delivered"]').length;
    var outstanding = 0;
    document.querySelectorAll('.driver-outstanding-sum').forEach(function(el){
      var num = parseFloat(String(el.textContent || '').replace(/[^0-9.]/g, ''));
      if(!isNaN(num)){ outstanding += num; }
    });
    var bp = document.getElementById('bottomPending');
    var bd = document.getElementById('bottomDelivered');
    var bo = document.getElementById('bottomOutstanding');
    if(bp){ bp.textContent = pending.toLocaleString('th-TH'); }
    if(bd){ bd.textContent = delivered.toLocaleString('th-TH'); }
    if(bo){ bo.textContent = Math.round(outstanding).toLocaleString('th-TH'); }
  }
  function applyHideDone(){
    if(!hideDoneToggle){ return; }
    document.querySelectorAll('.driver-job-card.is-done').forEach(function(card){
      card.style.display = hideDoneToggle.checked ? 'none' : '';
    });
  }
  function buildSingleActionHtml(id, status){
    if(status === 'paid'){
      return '<span class="btn-disabled btn-block">เสร็จแล้ว</span>';
    }
    var nextStatus = nextActionForStatus(status);
    var nextLabel = nextLabelForStatus(status);
    var btnClass = nextStatus === 'paid' ? 'btn-pay' : 'btn-send';
    return '<form method="post" class="status-form driver-single-action"><input type="hidden" name="csrf_token" value="'+ csrfToken +'"><input type="hidden" name="id" value="'+ id +'"><input type="hidden" name="status" value="'+ nextStatus +'"><button class="btn '+ btnClass +' js-status-btn btn-block" data-id="'+ id +'" data-status="'+ nextStatus +'">'+ nextLabel +'</button></form>';
  }
  function updateCard(card, status){
    if(!card){ return; }
    card.classList.remove('is-updating');
    card.setAttribute('data-status', status);
    if(status === 'paid'){
      card.classList.add('is-done');
    } else {
      card.classList.remove('is-done');
    }
    var badge = card.querySelector('.js-status-badge');
    if(badge){
      badge.className = 'status-badge status-' + status + ' js-status-badge';
      badge.textContent = statusLabel(status);
    }
    var actionsWrap = card.querySelector('.js-actions-wrap');
    if(actionsWrap){
      actionsWrap.innerHTML = buildSingleActionHtml(card.getAttribute('data-order-id') || '', status);
    }
    applyHideDone();
    updateBottomCounts();
  }
  function moveToNextCard(card){
    if(selectedView !== 'today' || !card){ return; }
    var next = card.nextElementSibling;
    if(next && typeof next.scrollIntoView === 'function'){
      setTimeout(function(){ next.scrollIntoView({behavior:'smooth', block:'center'}); }, 120);
    }
  }
  if(hideDoneToggle){
    hideDoneToggle.addEventListener('change', applyHideDone);
    applyHideDone();
  }
  document.addEventListener('click', async function(e){
    var btn = e.target.closest('.js-status-btn');
    if(!btn){ return; }
    e.preventDefault();
    var card = btn.closest('[data-order-id]');
    var id = btn.getAttribute('data-id');
    var status = btn.getAttribute('data-status');
    if(!id || !status || !card){ return; }
    if(card.classList.contains('is-updating')){ return; }
    card.classList.add('is-updating');
    btn.disabled = true;
    var fd = new FormData();
    fd.append('id', id);
    fd.append('status', status);
    fd.append('csrf_token', csrfToken);
    try {
      var res = await fetch('driver.php?view=<?php echo h($selected_view); ?>&order_period=<?php echo h($selected_period); ?>', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
      var data = await res.json();
      if(!res.ok || !data || !data.ok){ throw new Error(data && data.message ? data.message : 'อัปเดตไม่สำเร็จ'); }
      updateCard(card, status);
      moveToNextCard(card);
      showToast(data.message || 'อัปเดตแล้ว');
    } catch(err) {
      card.classList.remove('is-updating');
      btn.disabled = false;
      showToast(err && err.message ? err.message : 'อัปเดตไม่สำเร็จ');
    }
  });
})();
</script>
</body>
</html>
