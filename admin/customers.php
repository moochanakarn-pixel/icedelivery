<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$message = '';
$message_type = 'success';
$routeLabels = route_labels_enabled();
$deliveryOrder = delivery_order_enabled();
$roundsEnabled = rounds_enabled();
$mapsEnabled = maps_enabled();
$roundOptions = customer_round_options();

function admin_customer_redirect_with_flash($type, $message, $query = '') {
    set_flash_message($type, $message);
    admin_auth_redirect('customers.php' . ($query !== '' ? ('?' . ltrim($query, '?')) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_customer'])) {
        if (!csrf_validate('admin_customers_add')) {
            admin_customer_redirect_with_flash('error', 'คำขอเพิ่มลูกค้าไม่ถูกต้อง');
        }
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $route = $routeLabels ? max(1, (int)(isset($_POST['route']) ? $_POST['route'] : 1)) : 1;
        $routeOrder = $deliveryOrder ? max(0, (int)(isset($_POST['route_order']) ? $_POST['route_order'] : 0)) : 0;
        $preferredRound = $roundsEnabled ? normalize_customer_round(isset($_POST['preferred_round']) ? $_POST['preferred_round'] : 'all_day') : 'all_day';
        $noteText = trim(isset($_POST['note_text']) ? $_POST['note_text'] : '');
        $mapUrl = $mapsEnabled ? normalize_map_input(isset($_POST['map_url']) ? $_POST['map_url'] : '') : '';
        $iceTypesCsv = 'big,small,crush,pack';

        if ($name === '') {
            admin_customer_redirect_with_flash('error', 'กรุณากรอกชื่อลูกค้า');
        }

        $nameEsc = mysqli_real_escape_string($conn, $name);
        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $roundEsc = mysqli_real_escape_string($conn, $preferredRound);
        $noteEsc = mysqli_real_escape_string($conn, $noteText);
        $mapEsc = mysqli_real_escape_string($conn, $mapUrl);
        $iceEsc = mysqli_real_escape_string($conn, $iceTypesCsv);
        $ok = mysqli_query($conn, "INSERT INTO customers(name, phone, route, route_order, preferred_round, ice_types, map_url, note_text) VALUES('{$nameEsc}', '{$phoneEsc}', {$route}, {$routeOrder}, '{$roundEsc}', '{$iceEsc}', '{$mapEsc}', '{$noteEsc}')");
        if ($ok) {
            admin_log_action('add_customer', 'เพิ่มลูกค้า ' . $name);
            admin_customer_redirect_with_flash('success', 'เพิ่มลูกค้าเรียบร้อย');
        }
        admin_customer_redirect_with_flash('error', 'เพิ่มลูกค้าไม่สำเร็จ: ' . mysqli_error($conn));
    }

    if (isset($_POST['save_customer'])) {
        if (!csrf_validate('admin_customers_save')) {
            admin_customer_redirect_with_flash('error', 'คำขอแก้ไขลูกค้าไม่ถูกต้อง', http_build_query(array('q' => isset($_GET['q']) ? $_GET['q'] : '')));
        }
        $customerId = (int)(isset($_POST['customer_id']) ? $_POST['customer_id'] : 0);
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $route = $routeLabels ? max(1, (int)(isset($_POST['route']) ? $_POST['route'] : 1)) : 1;
        $routeOrder = $deliveryOrder ? max(0, (int)(isset($_POST['route_order']) ? $_POST['route_order'] : 0)) : 0;
        $preferredRound = $roundsEnabled ? normalize_customer_round(isset($_POST['preferred_round']) ? $_POST['preferred_round'] : 'all_day') : 'all_day';
        $noteText = trim(isset($_POST['note_text']) ? $_POST['note_text'] : '');
        $mapUrl = $mapsEnabled ? normalize_map_input(isset($_POST['map_url']) ? $_POST['map_url'] : '') : '';
        $iceTypesCsv = 'big,small,crush,pack';
        if ($customerId <= 0 || $name === '') {
            admin_customer_redirect_with_flash('error', 'ข้อมูลลูกค้าไม่ถูกต้อง');
        }
        $nameEsc = mysqli_real_escape_string($conn, $name);
        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $roundEsc = mysqli_real_escape_string($conn, $preferredRound);
        $noteEsc = mysqli_real_escape_string($conn, $noteText);
        $mapEsc = mysqli_real_escape_string($conn, $mapUrl);
        $iceEsc = mysqli_real_escape_string($conn, $iceTypesCsv);
        $ok = mysqli_query($conn, "UPDATE customers SET name='{$nameEsc}', phone='{$phoneEsc}', route={$route}, route_order={$routeOrder}, preferred_round='{$roundEsc}', ice_types='{$iceEsc}', map_url='{$mapEsc}', note_text='{$noteEsc}' WHERE id={$customerId} LIMIT 1");
        if ($ok) {
            admin_log_action('save_customer', 'อัปเดตลูกค้า ID ' . $customerId);
            admin_customer_redirect_with_flash('success', 'บันทึกลูกค้าเรียบร้อย', http_build_query(array('q' => isset($_GET['q']) ? $_GET['q'] : '')));
        }
        admin_customer_redirect_with_flash('error', 'บันทึกลูกค้าไม่สำเร็จ: ' . mysqli_error($conn));
    }

    if (isset($_POST['delete_customer'])) {
        if (!csrf_validate('admin_customers_delete')) {
            admin_customer_redirect_with_flash('error', 'คำขอลบลูกค้าไม่ถูกต้อง');
        }
        $customerId = (int)(isset($_POST['customer_id']) ? $_POST['customer_id'] : 0);
        if ($customerId <= 0) {
            admin_customer_redirect_with_flash('error', 'ไม่พบลูกค้าที่ต้องการลบ');
        }
        $rowRes = mysqli_query($conn, "SELECT name FROM customers WHERE id={$customerId} LIMIT 1");
        $row = $rowRes ? mysqli_fetch_assoc($rowRes) : null;
        mysqli_query($conn, "DELETE FROM order_items WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE customer_id={$customerId}) x)");
        mysqli_query($conn, "DELETE FROM orders WHERE customer_id={$customerId}");
        mysqli_query($conn, "DELETE FROM last_prices WHERE customer_id={$customerId}");
        $ok = mysqli_query($conn, "DELETE FROM customers WHERE id={$customerId} LIMIT 1");
        if ($ok) {
            admin_log_action('delete_customer', 'ลบลูกค้า ' . ($row && isset($row['name']) ? $row['name'] : ('ID ' . $customerId)));
            admin_customer_redirect_with_flash('success', 'ลบลูกค้าเรียบร้อย');
        }
        admin_customer_redirect_with_flash('error', 'ลบลูกค้าไม่สำเร็จ: ' . mysqli_error($conn));
    }
}

$search = trim(isset($_GET['q']) ? $_GET['q'] : '');
$where = array();
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $where[] = "(name LIKE '%{$safeSearch}%' OR phone LIKE '%{$safeSearch}%')";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$orderSql = customers_order_by_sql();
$rows = fetch_all_rows(mysqli_query($conn, "SELECT * FROM customers {$whereSql} ORDER BY {$orderSql}"));
$flash = consume_flash_message();
if ($flash) {
    $message = isset($flash['message']) ? (string)$flash['message'] : '';
    $message_type = isset($flash['type']) ? (string)$flash['type'] : 'success';
}

admin_render_header('ลูกค้า', 'เพิ่ม แก้ไข และลบลูกค้าได้จากหน้านี้ พร้อมป้องกัน CSRF');
?>
<?php if ($message !== '') { ?><div class="notice <?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php } ?>

<div class="card">
    <h2>เพิ่มลูกค้าใหม่</h2>
    <form method="post">
        <?php echo csrf_input('admin_customers_add'); ?>
        <div class="form-grid">
            <div class="field"><label>ชื่อลูกค้า</label><input class="input" type="text" name="name" required></div>
            <div class="field"><label>เบอร์โทร</label><input class="input" type="text" name="phone"></div>
            <?php if ($routeLabels) { ?><div class="field"><label>สายส่ง</label><input class="input" type="number" min="1" name="route" value="1"></div><?php } ?>
            <?php if ($deliveryOrder) { ?><div class="field"><label>ลำดับส่ง</label><input class="input" type="number" min="0" name="route_order" value="0"></div><?php } ?>
            <?php if ($roundsEnabled) { ?><div class="field"><label>รอบประจำ</label><select class="select" name="preferred_round"><?php foreach ($roundOptions as $code => $label) { ?><option value="<?php echo h($code); ?>"><?php echo h($label); ?></option><?php } ?></select></div><?php } ?>
            <?php if ($mapsEnabled) { ?><div class="field"><label>แผนที่</label><input class="input" type="text" name="map_url"></div><?php } ?>
            <div class="field"><label>หมายเหตุ</label><input class="input" type="text" name="note_text"></div>
        </div>
        <div class="btn-row"><button type="submit" name="add_customer" value="1" class="btn btn-primary">เพิ่มลูกค้า</button></div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="row">
        <div>
            <h2 style="margin:0 0 8px">รายชื่อลูกค้า</h2>
            <div class="muted">แก้ไขทีละแถวได้เลย</div>
        </div>
        <form method="get" class="actions-inline" style="margin:0">
            <input class="input" type="text" name="q" value="<?php echo h($search); ?>" placeholder="ค้นหาชื่อร้านหรือเบอร์">
            <button type="submit" class="btn btn-light">ค้นหา</button>
        </form>
    </div>

    <div class="list" style="margin-top:12px">
        <?php if (!$rows) { ?>
            <div class="item muted">ยังไม่มีข้อมูลลูกค้า</div>
        <?php } else { ?>
            <?php foreach ($rows as $row) { ?>
                <div class="item">
                    <form method="post">
                        <?php echo csrf_input('admin_customers_save'); ?>
                        <input type="hidden" name="customer_id" value="<?php echo (int)$row['id']; ?>">
                        <div class="form-grid">
                            <div class="field"><label>ชื่อลูกค้า</label><input class="input" type="text" name="name" value="<?php echo h($row['name']); ?>"></div>
                            <div class="field"><label>เบอร์โทร</label><input class="input" type="text" name="phone" value="<?php echo h($row['phone']); ?>"></div>
                            <?php if ($routeLabels) { ?><div class="field"><label>สายส่ง</label><input class="input" type="number" min="1" name="route" value="<?php echo (int)$row['route']; ?>"></div><?php } ?>
                            <?php if ($deliveryOrder) { ?><div class="field"><label>ลำดับส่ง</label><input class="input" type="number" min="0" name="route_order" value="<?php echo (int)$row['route_order']; ?>"></div><?php } ?>
                            <?php if ($roundsEnabled) { ?><div class="field"><label>รอบประจำ</label><select class="select" name="preferred_round"><?php foreach ($roundOptions as $code => $label) { ?><option value="<?php echo h($code); ?>" <?php echo (isset($row['preferred_round']) && $row['preferred_round'] === $code) ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php } ?></select></div><?php } ?>
                            <?php if ($mapsEnabled) { ?><div class="field"><label>แผนที่</label><input class="input" type="text" name="map_url" value="<?php echo h($row['map_url']); ?>"></div><?php } ?>
                            <div class="field"><label>หมายเหตุ</label><input class="input" type="text" name="note_text" value="<?php echo h($row['note_text']); ?>"></div>
                        </div>
                        <div class="btn-row">
                            <button type="submit" name="save_customer" value="1" class="btn btn-primary">บันทึก</button>
                        </div>
                    </form>
                    <form method="post" onsubmit="return confirm('ยืนยันการลบลูกค้ารายนี้?');" style="margin-top:8px">
                        <?php echo csrf_input('admin_customers_delete'); ?>
                        <input type="hidden" name="customer_id" value="<?php echo (int)$row['id']; ?>">
                        <button type="submit" name="delete_customer" value="1" class="btn btn-danger">ลบลูกค้า</button>
                    </form>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
<?php admin_render_footer('หน้านี้เป็นตัวจัดการลูกค้าแบบเต็มจากหลังบ้าน'); ?>
