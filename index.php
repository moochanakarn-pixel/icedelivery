<?php
include 'config.php';

$message = '';
$message_type = 'success';
$periods = order_periods();
$has_last_prices = table_exists($conn, 'last_prices');
$has_delivery_note_column = column_exists($conn, 'orders', 'delivery_note');

$selected_date = isset($_REQUEST['order_date']) ? $_REQUEST['order_date'] : date('Y-m-d');
$selected_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) ? $selected_date : date('Y-m-d');
$selected_period = normalize_period(isset($_REQUEST['order_period']) ? $_REQUEST['order_period'] : default_period_code());


$is_ajax_save = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_orders'])
);

$is_ajax_list = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['ajax_list'])
);

if (isset($_GET['saved']) && (string)$_GET['saved'] === '1') {
    $savedCount = (int)(isset($_GET['saved_count']) ? $_GET['saved_count'] : 0);
    $savedPeriod = (string)(isset($_GET['saved_period']) ? $_GET['saved_period'] : $selected_period);
    $savedDate = (string)(isset($_GET['saved_date']) ? $_GET['saved_date'] : $selected_date);
    $message = 'บันทึกออเดอร์เรียบร้อย';
    if ($savedCount > 0) {
        $message .= ' ' . number_format($savedCount) . ' ร้าน';
    }
    if ($savedPeriod !== '') {
        $message .= ' • ' . get_period_label($savedPeriod);
    }
    if ($savedDate !== '') {
        $message .= ' • ' . $savedDate;
    }
    $message_type = 'success';
}

function safe_hash_equals_local($known, $user) {
    $known = (string)$known;
    $user = (string)$user;
    if (function_exists('hash_equals')) {
        return hash_equals($known, $user);
    }
    if (strlen($known) !== strlen($user)) {
        return false;
    }
    $result = 0;
    $len = strlen($known);
    for ($i = 0; $i < $len; $i++) {
        $result |= ord($known[$i]) ^ ord($user[$i]);
    }
    return $result === 0;
}

function build_order_save_token() {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(16));
    }
    return md5(uniqid((string)mt_rand(), true));
}

function refresh_order_save_token() {
    $_SESSION['ice_order_save_token'] = build_order_save_token();
    return $_SESSION['ice_order_save_token'];
}

function ajax_json_response($ok, $message, $extra = array()) {
    header('Content-Type: application/json; charset=utf-8');
    $payload = array_merge(array(
        'ok' => (bool)$ok,
        'message' => (string)$message,
        'message_type' => $ok ? 'success' : 'error',
    ), is_array($extra) ? $extra : array());
    echo json_encode($payload);
    exit;
}

function render_index_order_rows($customers, $last_prices, $existing_order_notes) {
    ob_start();
    if (!$customers) {
        echo '<div class="empty">' . h(rounds_enabled() ? 'ไม่พบลูกค้าในรอบนี้' : 'ไม่พบลูกค้า') . '</div>';
    } else {
        echo '<div class="list-shell">';
        echo '<div class="list-head"><div>ลูกค้า</div><div>ราคา</div><div>หมายเหตุส่งของ</div></div>';
        foreach ($customers as $customer) {
            $id = (int)$customer['id'];
            $allowed_types = customer_allowed_ice_types($customer);
            $primary_code = isset($allowed_types[0]) ? $allowed_types[0] : 'big';
            $default_price = isset($last_prices[$id][$primary_code]) ? (int)$last_prices[$id][$primary_code] : 0;
            echo '<div class="order-row" data-customer-name="' . h($customer['name']) . '">';
            echo '<div class="customer-name">' . h($customer['name']) . '</div>';
            echo '<div>';
            echo '<input type="text" class="price" name="price[' . $id . '][' . h($primary_code) . ']" value="' . ($default_price > 0 ? h($default_price) : '') . '" inputmode="numeric" pattern="[0-9]*" autocomplete="off" autocorrect="off" spellcheck="false">';
            echo '</div>';
            echo '<div>';
            echo '<input type="text" class="customer-note-input" name="delivery_note[' . $id . ']" value="' . h(isset($existing_order_notes[$id]) ? $existing_order_notes[$id] : '') . '" placeholder="หมายเหตุ">';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    return ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_orders'])) {
    $posted_token = isset($_POST['save_token']) ? (string)$_POST['save_token'] : '';
    $session_token = isset($_SESSION['ice_order_save_token']) ? (string)$_SESSION['ice_order_save_token'] : '';

    if ($posted_token === '' || $session_token === '' || !safe_hash_equals_local($session_token, $posted_token)) {
        $message = 'คำขอบันทึกหมดอายุแล้ว กรุณาเปิดสรุปก่อนส่งอีกครั้ง';
        $message_type = 'error';
        if ($is_ajax_save) {
            ajax_json_response(false, $message, array('message_type' => $message_type, 'save_token' => refresh_order_save_token()));
        }
    } else {
        unset($_SESSION['ice_order_save_token']);

        if (!isset($_POST['price']) || !is_array($_POST['price'])) {
            $message = 'ข้อมูลที่ส่งมาไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
            $message_type = 'error';
            if ($is_ajax_save) {
                ajax_json_response(false, $message, array('message_type' => $message_type, 'save_token' => refresh_order_save_token()));
            }
        } else {

        $posted_prices = $_POST['price'];
        $posted_notes = isset($_POST['delivery_note']) && is_array($_POST['delivery_note']) ? $_POST['delivery_note'] : array();
        $save_period = normalize_period(isset($_POST['order_period']) ? $_POST['order_period'] : $selected_period);
        $safe_date = mysqli_real_escape_string($conn, $selected_date);
        $safe_period = mysqli_real_escape_string($conn, $save_period);
        $saved_count = 0;
        $has_db_error = false;
        $db_error_message = '';

        if (!mysqli_query($conn, 'START TRANSACTION')) {
            $has_db_error = true;
            $db_error_message = mysqli_error($conn);
        }

        if (!$has_db_error) {
            $posted_customer_ids = array();
            foreach ($posted_prices as $posted_customer_id => $items) {
                $posted_customer_id = (int)$posted_customer_id;
                if ($posted_customer_id > 0 && is_array($items)) {
                    $posted_customer_ids[] = $posted_customer_id;
                }
            }
            $posted_customer_ids = array_values(array_unique($posted_customer_ids));

            $existing_orders = array();
            if ($posted_customer_ids) {
                $existing_res = mysqli_query($conn, "SELECT id, customer_id FROM orders WHERE order_date='{$safe_date}' AND order_period='{$safe_period}' AND customer_id IN (" . implode(',', $posted_customer_ids) . ")");
                if ($existing_res === false) {
                    $has_db_error = true;
                    $db_error_message = mysqli_error($conn);
                } else {
                    while ($existing_row = mysqli_fetch_assoc($existing_res)) {
                        $existing_orders[(int)$existing_row['customer_id']] = (int)$existing_row['id'];
                    }
                }
            }

            foreach ($posted_prices as $customer_id => $items) {
                $customer_id = (int)$customer_id;
                if ($customer_id <= 0 || !is_array($items)) {
                    continue;
                }

                $delivery_note = isset($posted_notes[$customer_id]) ? trim((string)$posted_notes[$customer_id]) : '';
                if (function_exists('mb_substr')) {
                    $delivery_note = mb_substr($delivery_note, 0, 255, 'UTF-8');
                } else {
                    $delivery_note = substr($delivery_note, 0, 255);
                }
                $delivery_note_sql = mysqli_real_escape_string($conn, $delivery_note);

                $clean_items = array();
                foreach ($items as $ice_code => $price_value) {
                    $price = to_int_val($price_value);
                    if ($price > 0) {
                        $clean_items[(string)$ice_code] = array('qty' => 1, 'price' => $price);
                    }
                }
                if (!$clean_items) {
                    continue;
                }

                $existing_order_id = isset($existing_orders[$customer_id]) ? (int)$existing_orders[$customer_id] : 0;

                if ($existing_order_id > 0) {
                    $order_id = $existing_order_id;
                    $update_sql = $has_delivery_note_column
                        ? "UPDATE orders SET status='pending', delivered=0, paid=0, delivery_note='{$delivery_note_sql}' WHERE id={$order_id}"
                        : "UPDATE orders SET status='pending', delivered=0, paid=0 WHERE id={$order_id}";
                    if (!mysqli_query($conn, $update_sql)) {
                        $has_db_error = true;
                        $db_error_message = mysqli_error($conn);
                        break;
                    }
                    if (!mysqli_query($conn, "DELETE FROM order_items WHERE order_id={$order_id}")) {
                        $has_db_error = true;
                        $db_error_message = mysqli_error($conn);
                        break;
                    }
                } else {
                    $insert_sql = $has_delivery_note_column
                        ? "INSERT INTO orders(customer_id, status, order_date, order_period, delivered, paid, delivery_note) VALUES({$customer_id}, 'pending', '{$safe_date}', '{$safe_period}', 0, 0, '{$delivery_note_sql}')"
                        : "INSERT INTO orders(customer_id, status, order_date, order_period, delivered, paid) VALUES({$customer_id}, 'pending', '{$safe_date}', '{$safe_period}', 0, 0)";
                    if (!mysqli_query($conn, $insert_sql)) {
                        $has_db_error = true;
                        $db_error_message = mysqli_error($conn);
                        break;
                    }
                    $order_id = mysqli_insert_id($conn);
                }

                foreach ($clean_items as $ice_code => $row) {
                    $ice_code_sql = mysqli_real_escape_string($conn, $ice_code);
                    $qty_sql = (int)$row['qty'];
                    $price_sql = (int)$row['price'];
                    if (!mysqli_query($conn, "INSERT INTO order_items(order_id, ice_type, qty, price) VALUES({$order_id}, '{$ice_code_sql}', {$qty_sql}, {$price_sql})")) {
                        $has_db_error = true;
                        $db_error_message = mysqli_error($conn);
                        break 2;
                    }
                    if ($has_last_prices) {
                        if (!mysqli_query($conn, "REPLACE INTO last_prices(customer_id, ice_type, price, updated_at) VALUES({$customer_id}, '{$ice_code_sql}', {$price_sql}, NOW())")) {
                            $has_db_error = true;
                            $db_error_message = mysqli_error($conn);
                            break 2;
                        }
                    }
                }
                $saved_count++;
            }
        }

        if ($has_db_error) {
            mysqli_query($conn, 'ROLLBACK');
            $message = 'บันทึกไม่สำเร็จ' . ($db_error_message !== '' ? ': ' . $db_error_message : '');
            $message_type = 'error';
            if ($is_ajax_save) {
                ajax_json_response(false, $message, array('message_type' => $message_type, 'save_token' => refresh_order_save_token()));
            }
        } else {
            mysqli_query($conn, 'COMMIT');
            if ($saved_count > 0) {
                $message = 'บันทึกออเดอร์เรียบร้อย';
                $message .= ' ' . number_format($saved_count) . ' ร้าน';
                if ($save_period !== '') {
                    $message .= ' • ' . get_period_label($save_period);
                }
                if ($selected_date !== '') {
                    $message .= ' • ' . $selected_date;
                }
                $message_type = 'success';

                if ($is_ajax_save) {
                    ajax_json_response(true, $message, array(
                        'message_type' => $message_type,
                        'saved_count' => $saved_count,
                        'save_token' => refresh_order_save_token(),
                    ));
                }

                $redirectQuery = array(
                    'order_date' => $selected_date,
                    'order_period' => $save_period,
                    'saved' => '1',
                    'saved_count' => (string)$saved_count,
                    'saved_period' => $save_period,
                    'saved_date' => $selected_date,
                );
                header('Location: index.php?' . http_build_query($redirectQuery));
                exit;
            }
            $message = 'ยังไม่มีรายการที่ต้องบันทึก';
            $message_type = 'success';
            if ($is_ajax_save) {
                ajax_json_response(false, $message, array('message_type' => $message_type, 'save_token' => refresh_order_save_token()));
            }
        }
        }
    }
}

$save_token = refresh_order_save_token();
$line_summary_liff_id = defined('LINE_REPORT_SHARE_LIFF_ID') ? trim((string)LINE_REPORT_SHARE_LIFF_ID) : '';

$where = array('1=1');
$customer_sql = "SELECT * FROM customers WHERE " . implode(' AND ', $where) . " ORDER BY " . customers_order_by_sql();
$all_customers = fetch_all_rows(mysqli_query($conn, $customer_sql));
$customers = array();
foreach ($all_customers as $customer) {
    $preferred = isset($customer['preferred_round']) ? $customer['preferred_round'] : (rounds_enabled() ? 'morning' : 'all_day');
    if (customer_matches_period($preferred, $selected_period)) {
        $customers[] = $customer;
    }
}

$last_prices = array();
if ($has_last_prices && $customers) {
    $ids = array();
    foreach ($customers as $customer) {
        $ids[] = (int)$customer['id'];
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if ($ids) {
        $price_result = mysqli_query($conn, "SELECT customer_id, ice_type, price FROM last_prices WHERE customer_id IN (" . implode(',', $ids) . ")");
        if ($price_result) {
            while ($row = mysqli_fetch_assoc($price_result)) {
                $last_prices[(int)$row['customer_id']][(string)$row['ice_type']] = (int)$row['price'];
            }
        }
    }
}

$existing_order_notes = array();
if ($customers) {
    $ids = array();
    foreach ($customers as $customer) {
        $ids[] = (int)$customer['id'];
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if ($ids && column_exists($conn, 'orders', 'delivery_note')) {
        $note_result = mysqli_query($conn, "SELECT customer_id, delivery_note FROM orders WHERE order_date='" . mysqli_real_escape_string($conn, $selected_date) . "' AND order_period='" . mysqli_real_escape_string($conn, $selected_period) . "' AND customer_id IN (" . implode(',', $ids) . ")");
        if ($note_result) {
            while ($row = mysqli_fetch_assoc($note_result)) {
                $existing_order_notes[(int)$row['customer_id']] = (string)$row['delivery_note'];
            }
        }
    }
}
$order_list_html = render_index_order_rows($customers, $last_prices, $existing_order_notes);

if ($is_ajax_list) {
    ajax_json_response(true, 'โหลดรายการสำเร็จ', array(
        'html' => $order_list_html,
        'save_token' => refresh_order_save_token(),
        'period_label' => get_period_label($selected_period),
        'selected_period' => $selected_period,
        'selected_date' => $selected_date,
    ));
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>คีย์ออเดอร์น้ำแข็ง</title>
<link rel="stylesheet" href="assets/mobile.css">
<style>
*{box-sizing:border-box}
body{margin:0;background:#eef6ff;color:#15314a;font-family:Tahoma,Arial,sans-serif;font-size:18px;line-height:1.45}
a{text-decoration:none}
.wrapper{max-width:1080px;margin:0 auto;padding:14px 12px 104px}
.notice{padding:14px 16px;border-radius:18px;margin-bottom:14px;font-weight:bold}
.notice.success{background:#e9fff1;color:#10763d}.notice.error{background:#fff0f0;color:#b42318}
.list-shell{background:#fff;border-radius:20px;border:1px solid #dfeaf6;box-shadow:0 8px 22px rgba(0,0,0,.06);overflow:hidden}
.list-head,.order-row{display:grid;grid-template-columns:minmax(0,1fr) 108px 210px;gap:10px;align-items:center}
.list-head{padding:12px 14px;background:#f7fbff;border-bottom:1px solid #e2ebf3;font-weight:bold;color:#4f6982}
.order-row{padding:12px 14px;border-bottom:1px solid #edf2f7}
.order-row:last-child{border-bottom:0}
.customer-name{font-weight:bold;color:#17324d;line-height:1.25;word-break:break-word}.customer-note-input{width:100%;height:40px;border-radius:10px;border:1px solid #c7d8ea;background:#fff;padding:0 10px;font-size:14px;line-height:40px}.summary-note{display:block;margin-top:4px;font-size:13px;color:#8a5a00;font-weight:bold}
.price{width:100%;height:40px;border-radius:10px;border:1px solid #c7d8ea;text-align:center;font-size:18px;background:#fff;padding:0 8px}
.panel{background:#fff;border-radius:20px;padding:14px;border:1px solid #dfeaf6;box-shadow:0 8px 22px rgba(0,0,0,.06);margin-top:14px}
.panel-title{font-size:22px;font-weight:bold;color:#0b7dda;margin:0 0 10px}
.control-grid{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end}
.field label{display:block;font-size:14px;font-weight:bold;margin-bottom:6px;color:#55708a}
.date-input{width:100%;height:48px;border-radius:12px;border:1px solid #c7d8ea;background:#fff;padding:0 12px;font-size:17px}
.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:14px;padding:12px 14px;font-size:17px;font-weight:bold;cursor:pointer}
.btn-primary{background:#0b7dda;color:#fff}.btn-green{background:#1d9e57;color:#fff}.btn-light{background:#eef7ff;color:#0b7dda}.btn-block{width:100%}
.period-tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.period-tabs label{display:block;cursor:pointer}.period-tabs input{display:none}
.period-tabs span{display:block;border-radius:14px;border:2px solid #d5e5f6;background:#f7fbff;padding:10px 8px;text-align:center;font-weight:bold;color:#36556f}
.period-tabs input:checked + span{background:#0b7dda;border-color:#0b7dda;color:#fff}
.period-note{margin-top:10px;background:#fff9e9;border:1px solid #f2df9d;color:#705400;border-radius:12px;padding:10px 12px;font-size:15px;font-weight:bold}
.summary-overlay{position:fixed;inset:0;background:rgba(9,28,45,.52);display:none;align-items:flex-end;justify-content:center;padding:14px;z-index:50}
.summary-overlay.show{display:flex}
.summary-dialog{background:#fff;width:min(920px,100%);max-height:88vh;border-radius:24px;box-shadow:0 16px 44px rgba(0,0,0,.22);display:flex;flex-direction:column;overflow:hidden}
.summary-head{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:16px 16px 12px;border-bottom:1px solid #edf2f7}
.summary-head h2{margin:0;color:#0b7dda;font-size:24px}
.summary-content{padding:14px 16px;overflow:auto}
.summary-table{width:100%;border-collapse:collapse;font-size:16px}
.summary-table th,.summary-table td{padding:10px 8px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top}
.summary-table th:last-child,.summary-table td:last-child{text-align:right}
.summary-actions{display:flex;gap:10px;flex-wrap:wrap;padding:14px 16px;border-top:1px solid #edf2f7;background:#fbfdff}.btn-share{background:#eff8f1;color:#108a43;border:1px solid #cdebd6}.toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);background:#17324d;color:#fff;padding:12px 16px;border-radius:999px;box-shadow:0 10px 24px rgba(0,0,0,.18);opacity:0;pointer-events:none;transition:all .2s ease;font-size:16px;z-index:9999}.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.empty{background:#fff;border:1px dashed #cddaea;color:#6f8296;border-radius:18px;padding:20px;text-align:center;font-size:18px}
.sticky{position:fixed;left:0;right:0;bottom:0;background:rgba(255,255,255,.96);backdrop-filter:blur(6px);border-top:1px solid #dfeaf6;padding:10px 12px calc(10px + env(safe-area-inset-bottom));box-shadow:0 -8px 24px rgba(0,0,0,.08);z-index:40}
.sticky-inner{max-width:1080px;margin:0 auto;display:flex;gap:10px;justify-content:space-between;align-items:center}
.sticky-text{min-width:0}.sticky-main{font-size:17px;font-weight:bold;color:#15314a}.sticky-sub{font-size:13px;color:#6c8398;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.sticky-actions{display:flex;gap:10px;align-items:center;flex-shrink:0}
.page-loading{opacity:.72;pointer-events:none}.list-loading{position:relative}.list-loading:after{content:'กำลังโหลดรายการ...';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.82);font-weight:bold;color:#0b7dda}
@media (max-width:760px){
 .wrapper{padding:12px 8px 98px}
 .list-head{display:none}
 .order-row{grid-template-columns:minmax(0,1fr) 84px 132px;gap:8px;padding:10px 10px}
 .customer-name{font-size:15px;line-height:1.2}
 .price{height:38px;font-size:17px}
 .customer-note-input{height:38px;font-size:13px;padding:0 8px}
 .control-grid{grid-template-columns:1fr}
 .period-tabs{grid-template-columns:1fr}
 .sticky-inner{flex-direction:column;align-items:stretch}
 .sticky-actions{width:100%}
 .sticky-actions .btn{width:100%}
}
</style>
</head>
<body class="page-index">
<div class="wrapper">
    <div id="pageNoticeWrap">
    <?php if ($message !== '') { ?>
        <div class="notice <?php echo h($message_type); ?>" id="pageNotice"><?php echo h($message); ?></div>
    <?php } ?>
    </div>

    <form method="post" id="orderForm">
        <input type="hidden" name="order_date" value="<?php echo h($selected_date); ?>">
        <input type="hidden" name="order_period" value="<?php echo h($selected_period); ?>">
        <input type="hidden" name="save_token" value="<?php echo h($save_token); ?>">
        <input type="hidden" name="save_orders" id="saveOrdersFlag" value="">

        <div id="orderListWrap"><?php echo $order_list_html; ?></div>

        <div class="summary-overlay" id="summaryOverlay" aria-hidden="true">
            <div class="summary-dialog">
                <div class="summary-head">
                    <h2>สรุปยอดก่อนบันทึก</h2>
                    <button type="button" class="btn btn-light" onclick="hideSummary()">ปิด</button>
                </div>
                <div class="summary-content" id="summaryContent"></div>
                <div class="summary-actions">
                    <button type="button" class="btn btn-share" id="lineShareBtn">ส่งข้อความไลน์</button>
                    <button type="button" class="btn btn-green" id="confirmSaveBtn" onclick="forceSaveOrders()">ยืนยันบันทึก</button>
                    <button type="button" class="btn btn-light" onclick="hideSummary()">กลับไปแก้</button>
                </div>
            </div>
        </div>
    </form>

    <form method="get" class="panel" id="filterForm">
        <div class="panel-title">คีย์ออเดอร์ประจำวัน</div>
        <div class="control-grid">
            <div class="field">
                <label for="order_date">วันที่ส่ง</label>
                <input class="date-input" type="date" name="order_date" id="order_date" value="<?php echo h($selected_date); ?>">
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-block" id="loadListBtn">โหลดรายการ</button>
            </div>
        </div>
        <?php if (rounds_enabled()) { ?>
        <div class="period-tabs">
            <?php foreach ($periods as $code => $label) { ?>
                <label>
                    <input type="radio" name="order_period" value="<?php echo h($code); ?>" <?php echo $selected_period === $code ? 'checked' : ''; ?> data-period-switch="1">
                    <span><?php echo h($label); ?></span>
                </label>
            <?php } ?>
        </div>
        <div class="period-note">กำลังคีย์: <strong id="periodNoteLabel"><?php echo h(get_period_label($selected_period)); ?></strong></div>
        <?php } else { ?>
        <input type="hidden" name="order_period" value="all_day">
        <div class="period-note">กำลังคีย์: <strong>รวมทั้งวัน</strong></div>
        <?php } ?>
    </form>
</div>

<div class="sticky">
    <div class="sticky-inner">
        <div class="sticky-text">
            <div class="sticky-main" id="stickyMain">กรอกแล้ว 0 ร้าน • รวม 0 บาท</div>
            <div class="sticky-sub" id="stickySub">ยังไม่มีรายการ</div>
        </div>
        <div class="sticky-actions">
            <button type="button" class="btn btn-green" id="showSummaryBtn" onclick="showSummaryConfirm()">ดูสรุปยอด</button>
        </div>
    </div>
</div>

<div class="toast" id="shareToast"></div>
<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
function formatMoney(num){
    num = parseFloat(num || 0);
    if (isNaN(num)) num = 0;
    return num.toLocaleString('th-TH', {maximumFractionDigits:0}) + ' บาท';
}
function escapeHtml(text){
    return String(text || '').replace(/[&<>\"']/g, function(ch){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[ch] || ch;
    });
}
function formatDateForThai(dateValue){
    if (!dateValue) return '';
    var parts = String(dateValue).split('-');
    if (parts.length !== 3) return String(dateValue);
    return parts[2] + '/' + parts[1] + '/' + parts[0];
}
function formatDraftValue(value){
    return String(value == null ? '' : value).trim();
}
function getDraftStorageKey(){
    var orderDateInput = document.getElementById('order_date');
    var orderPeriodInput = document.querySelector('#orderForm input[name="order_period"]');
    var dateValue = orderDateInput ? String(orderDateInput.value || '') : '';
    var periodValue = orderPeriodInput ? String(orderPeriodInput.value || '') : 'all_day';
    return 'ice_delivery_index_draft:' + dateValue + ':' + periodValue;
}
function collectDraftData(){
    var draft = {prices: {}, notes: {}};
    priceInputs.forEach(function(input){
        var value = formatDraftValue(input.value);
        if (!value) return;
        if (input.name) {
            draft.prices[input.name] = value;
        }
    });
    noteInputs.forEach(function(input){
        var value = formatDraftValue(input.value);
        if (!value) return;
        if (input.name) {
            draft.notes[input.name] = value;
        }
    });
    return draft;
}
function saveDraftState(){
    try {
        var draft = collectDraftData();
        var hasPrices = Object.keys(draft.prices).length > 0;
        var hasNotes = Object.keys(draft.notes).length > 0;
        var storageKey = getDraftStorageKey();
        if (!hasPrices && !hasNotes) {
            window.localStorage.removeItem(storageKey);
            return;
        }
        window.localStorage.setItem(storageKey, JSON.stringify(draft));
    } catch (err) {
    }
}
function loadDraftState(){
    try {
        var raw = window.localStorage.getItem(getDraftStorageKey());
        if (!raw) return;
        var draft = JSON.parse(raw);
        if (!draft || typeof draft !== 'object') return;
        priceInputs.forEach(function(input){
            if (!input.name || !draft.prices || typeof draft.prices[input.name] === 'undefined') return;
            input.value = draft.prices[input.name];
        });
        noteInputs.forEach(function(input){
            if (!input.name || !draft.notes || typeof draft.notes[input.name] === 'undefined') return;
            input.value = draft.notes[input.name];
        });
    } catch (err) {
    }
}
function clearDraftState(){
    try {
        window.localStorage.removeItem(getDraftStorageKey());
    } catch (err) {
    }
}
function buildLineSummaryText(){
    var snapshot = getRowsSnapshot();
    var orderDateInput = document.getElementById('order_date');
    var dateText = formatDateForThai(orderDateInput ? orderDateInput.value : '');

    var roundText = '';
    var checkedPeriod = document.querySelector('input[name="order_period"]:checked');
    if (checkedPeriod) {
        var periodLabel = checkedPeriod.parentNode ? checkedPeriod.parentNode.querySelector('span') : null;
        roundText = periodLabel ? String(periodLabel.textContent || '').trim() : '';
    }

    var lines = ['สรุปยอดส่งน้ำแข็ง'];

    if (roundText) {
        lines.push(roundText);
    }

    if (dateText) {
        lines.push('วันที่ ' + dateText);
    }

    lines.push('');

    if (!snapshot.rows.length) {
        lines.push('ยังไม่มีรายการ');
    } else {
        snapshot.rows.forEach(function(row){
            lines.push(
                row.name + ' ' +
                Math.round(row.total).toLocaleString('th-TH') + ' บาท' +
                (row.note ? ' • หมายเหตุ: ' + row.note : '')
            );
        });
    }

    lines.push('');
    lines.push((roundText ? 'รวม' + roundText : 'รวมทั้งวัน') + ' ' + Math.round(snapshot.grandTotal).toLocaleString('th-TH') + ' บาท');

    return lines.join('\n');
}
function getRowsSnapshot(){
    var rows = [];
    var totalStores = 0;
    var grandTotal = 0;
    document.querySelectorAll('.order-row').forEach(function(row){
        var priceInput = row.querySelector('.price');
        var price = parseFloat(priceInput ? priceInput.value || 0 : 0);
        if (isNaN(price) || price <= 0) return;
        totalStores += 1;
        grandTotal += price;
        var noteInput = row.querySelector('.customer-note-input');
        rows.push({
            name: row.getAttribute('data-customer-name') || '',
            total: price,
            note: noteInput ? String(noteInput.value || '').trim() : ''
        });
    });
    return {rows: rows, totalStores: totalStores, grandTotal: grandTotal};
}
function updateSummaryBar(){
    var snapshot = getRowsSnapshot();
    var stickyMain = document.getElementById('stickyMain');
    var stickySub = document.getElementById('stickySub');
    if (stickyMain) {
        stickyMain.textContent = 'กรอกแล้ว ' + snapshot.totalStores + ' ร้าน • รวม ' + formatMoney(snapshot.grandTotal);
    }
    if (stickySub) {
        if (!snapshot.rows.length) {
            stickySub.textContent = 'ยังไม่มีรายการ';
        } else {
            stickySub.textContent = snapshot.rows.slice(0, 3).map(function(row){
                return row.name + ' ' + formatMoney(row.total);
            }).join(' | ');
        }
    }
}
function showSummaryConfirm(){
    var snapshot = getRowsSnapshot();
    var content = document.getElementById('summaryContent');
    var confirmBtn = document.getElementById('confirmSaveBtn');
    if (!content || !confirmBtn) return;
    if (!snapshot.rows.length) {
        content.innerHTML = '<div class="empty">ยังไม่มีรายการที่จะบันทึก</div>';
        confirmBtn.disabled = true;
    } else {
        var html = '<table class="summary-table"><tr><th>ลูกค้า</th><th>สรุปยอด</th></tr>';
        snapshot.rows.forEach(function(row){
            html += '<tr><td>' + escapeHtml(row.name) + (row.note ? '<span class="summary-note">หมายเหตุ: ' + escapeHtml(row.note) + '</span>' : '') + '</td><td>' + formatMoney(row.total) + '</td></tr>';
        });
        html += '<tr><th>รวมทั้งหมด</th><th>' + formatMoney(snapshot.grandTotal) + '</th></tr></table>';
        content.innerHTML = html;
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'ยืนยันบันทึก';
    }
    var overlay = document.getElementById('summaryOverlay');
    if (overlay) {
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
    }
}
function hideSummary(){
    var overlay = document.getElementById('summaryOverlay');
    if (!overlay) return;
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
}
function showPageNotice(message, type){
    var wrap = document.getElementById('pageNoticeWrap');
    if (!wrap) return;
    wrap.innerHTML = '<div class="notice ' + escapeHtml(type || 'success') + '" id="pageNotice">' + escapeHtml(message || '') + '</div>';
    try { window.scrollTo({top: 0, behavior: 'smooth'}); } catch (err) { window.scrollTo(0, 0); }
}
function setInputsDisabled(disabled){
    priceInputs.forEach(function(input){
        input.disabled = !!disabled;
    });
    noteInputs.forEach(function(input){
        input.disabled = !!disabled;
    });
}
function showToast(message) {
    var toast = document.getElementById('shareToast');
    if (!toast) return;
    toast.textContent = message || '';
    toast.classList.add('show');
    window.clearTimeout(showToast._timer);
    showToast._timer = window.setTimeout(function(){
        toast.classList.remove('show');
    }, 2200);
}
function fallbackLineShare() {
    var shareUrl = 'https://line.me/R/msg/text/?' + encodeURIComponent(buildLineSummaryText());
    window.location.href = shareUrl;
}
async function shareSummaryViaLine() {
    var shareText = buildLineSummaryText();
    var liffId = <?php echo json_encode($line_summary_liff_id, JSON_UNESCAPED_UNICODE); ?>;
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
async function forceSaveOrders(){
    var form = orderForm;
    var flag = saveOrdersFlag;
    var btn = document.getElementById('confirmSaveBtn');
    if (!form || !flag) {
        alert('ไม่พบฟอร์มบันทึก');
        return;
    }
    if (form.dataset.submitting === '1') return;

    form.dataset.submitting = '1';
    flag.value = '1';
    var formData = new FormData(form);
    setInputsDisabled(true);
    setPageBusy(true, 'save');

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'กำลังบันทึก...';
    }

    try {
        var response = await fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        var responseText = await response.text();
        var data = null;
        try {
            data = responseText ? JSON.parse(responseText) : null;
        } catch (parseErr) {
            throw new Error('server ไม่ได้ส่ง JSON กลับมา');
        }
        if (!response.ok || !data || !data.ok) {
            throw new Error(data && data.message ? data.message : 'บันทึกไม่สำเร็จ');
        }

        hideSummary();
        setSaveToken(data.save_token || '');
        showPageNotice(data.message || 'บันทึกออเดอร์เรียบร้อย', data.message_type || 'success');

        priceInputs.forEach(function(input){
            if (parseFloat(input.value || 0) > 0) {
                input.value = '';
            }
        });
        noteInputs.forEach(function(input){ input.value = ''; });
        clearDraftState();
        updateSummaryBar();
    } catch (err) {
        showPageNotice(err && err.message ? err.message : 'บันทึกไม่สำเร็จ', 'error');
    } finally {
        flag.value = '';
        form.dataset.submitting = '0';
        setInputsDisabled(false);
        setPageBusy(false, '');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'ยืนยันบันทึก';
        }
    }
}
var priceInputs = [];
var noteInputs = [];
var orderListWrap = document.getElementById('orderListWrap');
var filterForm = document.getElementById('filterForm');
var orderForm = document.getElementById('orderForm');
var saveOrdersFlag = document.getElementById('saveOrdersFlag');
var saveTokenInput = document.querySelector('#orderForm input[name="save_token"]');
var loadListBtn = document.getElementById('loadListBtn');
var showSummaryBtn = document.getElementById('showSummaryBtn');
var periodNoteLabel = document.getElementById('periodNoteLabel');
var draftSaveTimer = null;
var summaryUpdateTimer = null;
var activeListController = null;
var activeListRequestId = 0;

function setSaveToken(value){
    if (saveTokenInput && value) {
        saveTokenInput.value = value;
    }
}
function scheduleDraftSave(){
    window.clearTimeout(draftSaveTimer);
    draftSaveTimer = window.setTimeout(saveDraftState, 180);
}
function scheduleSummaryRefresh(){
    window.clearTimeout(summaryUpdateTimer);
    summaryUpdateTimer = window.setTimeout(updateSummaryBar, 80);
}
function refreshInputCollections(){
    priceInputs = Array.prototype.slice.call(document.querySelectorAll('.price'));
    noteInputs = Array.prototype.slice.call(document.querySelectorAll('.customer-note-input'));
}
function getSelectedPeriodInput(){
    return filterForm ? filterForm.querySelector('input[name="order_period"]:checked') : null;
}
function getSelectedPeriodValue(){
    var selected = getSelectedPeriodInput();
    return selected ? String(selected.value || '') : '';
}
function getSelectedPeriodLabel(){
    var selected = getSelectedPeriodInput();
    if (!selected) return '';
    var labelNode = selected.parentNode ? selected.parentNode.querySelector('span') : null;
    return labelNode ? String(labelNode.textContent || '').trim() : '';
}
function fallbackReloadList(periodValue){
    if (!filterForm) return;
    var params = new URLSearchParams(new FormData(filterForm));
    if (periodValue) {
        params.set('order_period', periodValue);
    }
    window.location.href = window.location.pathname + '?' + params.toString();
}
function bindInputEvents(){
    refreshInputCollections();
    priceInputs.forEach(function(input, index){
        input.addEventListener('input', function(){
            scheduleSummaryRefresh();
            scheduleDraftSave();
        });
        input.addEventListener('keydown', function(e){
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                var next = priceInputs[index + 1];
                if (next) {
                    next.focus();
                }
            }
        });
        input.addEventListener('contextmenu', function(e){
            e.preventDefault();
        });
        input.addEventListener('selectstart', function(e){
            e.preventDefault();
        });
    });
    noteInputs.forEach(function(input){
        input.addEventListener('input', function(){
            scheduleSummaryRefresh();
            scheduleDraftSave();
        });
    });
}
function setPageBusy(isBusy, modeText){
    document.body.classList.toggle('page-loading', !!isBusy);
    if (loadListBtn) {
        loadListBtn.disabled = !!isBusy;
        loadListBtn.textContent = isBusy && modeText === 'list' ? 'กำลังโหลด...' : 'โหลดรายการ';
    }
    if (showSummaryBtn) {
        showSummaryBtn.disabled = !!isBusy;
    }
    document.querySelectorAll('input[name="order_period"]').forEach(function(input){
        input.disabled = !!isBusy;
    });
}
async function loadOrderList(periodOverride){
    if (!filterForm || !orderListWrap) return;
    if (orderForm && orderForm.dataset.submitting === '1') return;

    saveDraftState();
    setPageBusy(true, 'list');
    orderListWrap.classList.add('list-loading');

    if (activeListController) {
        activeListController.abort();
    }
    activeListController = typeof AbortController !== 'undefined' ? new AbortController() : null;

    var requestedPeriod = periodOverride || getSelectedPeriodValue();
    var requestId = ++activeListRequestId;

    if (periodNoteLabel) {
        var selectedPeriodLabel = getSelectedPeriodLabel();
        if (selectedPeriodLabel) {
            periodNoteLabel.textContent = selectedPeriodLabel;
        }
    }

    var orderPeriodHiddenBeforeLoad = document.querySelector('#orderForm input[name="order_period"]');
    if (orderPeriodHiddenBeforeLoad && requestedPeriod) {
        orderPeriodHiddenBeforeLoad.value = requestedPeriod;
    }

    try {
        var params = new URLSearchParams(new FormData(filterForm));
        if (requestedPeriod) {
            params.set('order_period', requestedPeriod);
        }
        params.set('ajax_list', '1');
        var response = await fetch(window.location.pathname + '?' + params.toString(), {
            method: 'GET',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin',
            signal: activeListController ? activeListController.signal : undefined
        });
        var data = null;
        try {
            data = await response.json();
        } catch (parseErr) {
            fallbackReloadList(requestedPeriod);
            return;
        }
        if (!response.ok || !data || !data.ok) {
            throw new Error(data && data.message ? data.message : 'โหลดรายการไม่สำเร็จ');
        }
        if (requestId !== activeListRequestId) {
            return;
        }

        orderListWrap.innerHTML = data.html || '';
        setSaveToken(data.save_token || '');
        if (periodNoteLabel && data.period_label) {
            periodNoteLabel.textContent = data.period_label;
        }

        var orderDateHidden = document.querySelector('#orderForm input[name="order_date"]');
        var orderPeriodHidden = document.querySelector('#orderForm input[name="order_period"]');
        var orderDateInput = document.getElementById('order_date');
        if (orderDateHidden && data.selected_date) orderDateHidden.value = data.selected_date;
        if (orderPeriodHidden && data.selected_period) orderPeriodHidden.value = data.selected_period;
        if (orderDateInput && data.selected_date) orderDateInput.value = data.selected_date;

        bindInputEvents();
        loadDraftState();
        updateSummaryBar();
    } catch (err) {
        if (!err || err.name !== 'AbortError') {
            fallbackReloadList(requestedPeriod);
            return;
        }
    } finally {
        orderListWrap.classList.remove('list-loading');
        setPageBusy(false, '');
    }
}
document.getElementById('summaryOverlay').addEventListener('click', function(e){
    if (e.target === this) hideSummary();
});
var lineShareBtn = document.getElementById('lineShareBtn');
if (lineShareBtn) {
    lineShareBtn.addEventListener('click', function(){
        shareSummaryViaLine();
    });
}
if (filterForm) {
    filterForm.addEventListener('submit', function(e){
        e.preventDefault();
        loadOrderList();
    });
}
document.querySelectorAll('[data-period-switch="1"]').forEach(function(input){
    input.addEventListener('change', function(){
        loadOrderList(String(this.value || ''));
    });
    input.addEventListener('click', function(){
        var self = this;
        window.setTimeout(function(){
            if (self.checked) {
                loadOrderList(String(self.value || ''));
            }
        }, 0);
    });
});
window.addEventListener('beforeunload', function(){
    saveDraftState();
});
bindInputEvents();
loadDraftState();
updateSummaryBar();
</script>
</body>
</html>
