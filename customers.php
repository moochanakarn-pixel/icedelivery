<?php
include 'config.php';

$message = '';
$message_type = 'success';
$routeLabels = route_labels_enabled();
$deliveryOrder = delivery_order_enabled();
$roundsEnabled = rounds_enabled();
$mapsEnabled = maps_enabled();
$roundOptions = customer_round_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    if (!csrf_validate()) {
        $message = 'เซสชันหมดอายุ กรุณาลองใหม่';
        $message_type = 'error';
    } else {
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
    $route = $routeLabels ? max(1, (int)(isset($_POST['route']) ? $_POST['route'] : 1)) : 1;
    $routeOrder = $deliveryOrder ? max(1, (int)(isset($_POST['route_order']) ? $_POST['route_order'] : 1)) : 0;
    $preferredRound = $roundsEnabled ? normalize_customer_round(isset($_POST['preferred_round']) ? $_POST['preferred_round'] : default_period_code()) : default_period_code();
    $noteText = trim(isset($_POST['note_text']) ? $_POST['note_text'] : '');
    $mapUrl = $mapsEnabled ? normalize_map_input(isset($_POST['map_url']) ? $_POST['map_url'] : '') : '';
    $iceTypesCsv = 'big,small,crush,pack';

    if ($name === '') {
        $message = 'กรุณากรอกชื่อลูกค้า';
        $message_type = 'error';
    } else {
        $nameEsc = mysqli_real_escape_string($conn, $name);
        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $roundEsc = mysqli_real_escape_string($conn, $preferredRound);
        $noteEsc = mysqli_real_escape_string($conn, $noteText);
        $mapEsc = mysqli_real_escape_string($conn, $mapUrl);
        $iceEsc = mysqli_real_escape_string($conn, $iceTypesCsv);
        mysqli_query($conn, "INSERT INTO customers(name, phone, route, route_order, preferred_round, ice_types, map_url, note_text) VALUES('{$nameEsc}', '{$phoneEsc}', {$route}, {$routeOrder}, '{$roundEsc}', '{$iceEsc}', '{$mapEsc}', '{$noteEsc}')");
        if (mysqli_errno($conn)) {
            $message = 'เพิ่มลูกค้าไม่สำเร็จ: ' . mysqli_error($conn);
            $message_type = 'error';
        } else {
            $message = 'เพิ่มลูกค้าเรียบร้อย';
        }
    }
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
$rows = fetch_all_rows(@mysqli_query($conn, "SELECT * FROM customers {$whereSql} ORDER BY {$orderSql}"));
$statsRes = @mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers");
$statsRow = $statsRes ? mysqli_fetch_assoc($statsRes) : array('total' => 0);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>ลูกค้า</title>
<link rel="stylesheet" href="assets/mobile.css">
<link rel="stylesheet" href="assets/app.css?v=20260405c">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b7dda">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body class="page-customers">
<div class="wrap">
    <div class="hero">
        <h1>ลูกค้า</h1>
        <p>ใช้หน้านี้สำหรับเพิ่มลูกค้าใหม่แบบง่าย ๆ และดูรายชื่อลูกค้าทั้งหมด ส่วนการแก้ไขหรือลบ ให้ไปที่ Admin Backend</p>
    </div>

    <?php if ($message !== '') { ?><div class="notice <?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php } ?>

    <div class="stats">
        <div class="card"><div class="muted">ลูกค้าทั้งหมด</div><div style="font-size:30px;font-weight:bold;margin-top:6px"><?php echo number_format(isset($statsRow['total']) ? (int)$statsRow['total'] : 0); ?></div></div>
        <div class="card"><div class="muted">ค้นหา</div><form method="get" class="actions-inline" style="margin-top:8px"><input class="input" type="text" name="q" value="<?php echo h($search); ?>" placeholder="ชื่อร้านหรือเบอร์"><button type="submit" class="btn btn-light">ค้นหา</button></form></div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">เพิ่มลูกค้าใหม่</h2>
        <form method="post">
            <?php echo csrf_input(); ?>
            <div class="grid">
                <div class="field"><label>ชื่อลูกค้า / ชื่อร้าน</label><input class="input" type="text" name="name" required></div>
                <div class="field"><label>เบอร์โทร</label><input class="input" type="text" name="phone"></div>
                <?php if ($routeLabels) { ?><div class="field"><label>สายส่ง</label><input class="input" type="number" min="1" name="route" value="1"></div><?php } ?>
                <?php if ($deliveryOrder) { ?><div class="field"><label>ลำดับส่ง</label><input class="input" type="number" min="1" name="route_order" value="1"></div><?php } ?>
                <?php if ($roundsEnabled) { ?><div class="field"><label>รอบประจำ</label><select class="select" name="preferred_round"><?php foreach ($roundOptions as $code => $label) { ?><option value="<?php echo h($code); ?>"><?php echo h($label); ?></option><?php } ?></select></div><?php } ?>
                <?php if ($mapsEnabled) { ?><div class="field"><label>ลิงก์แผนที่ / พิกัด</label><input class="input" type="text" name="map_url" placeholder="เช่น 13.7,100.5 หรือ Google Maps URL"></div><?php } ?>
                <div class="field" style="grid-column:1/-1"><label>หมายเหตุ</label><input class="input" type="text" name="note_text" placeholder="เช่น ร้านเปิดสาย รับเงินปลายทาง"></div>
            </div>
            <div class="actions-inline" style="margin-top:14px"><button type="submit" name="add_customer" value="1" class="btn btn-primary">เพิ่มลูกค้า</button><a class="btn btn-light" href="admin/customers.php">ไปหน้าแก้ไขแบบเต็ม</a></div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">รายชื่อลูกค้า</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ร้าน</th>
                        <th>เบอร์</th>
                        <?php if ($routeLabels) { ?><th>สาย</th><?php } ?>
                        <?php if ($deliveryOrder) { ?><th>ลำดับส่ง</th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php $colspan = 2 + ($routeLabels ? 1 : 0) + ($deliveryOrder ? 1 : 0); ?>
                <?php if (!$rows) { ?>
                    <tr><td colspan="<?php echo $colspan; ?>"><div class="empty">ยังไม่พบข้อมูลลูกค้า</div></td></tr>
                <?php } else { foreach($rows as $row){ ?>
                    <tr>
                        <td><strong><?php echo h($row['name']); ?></strong><?php if (!empty($row['note_text'])) { ?><div class="muted" style="margin-top:6px"><?php echo h($row['note_text']); ?></div><?php } ?></td>
                        <td><?php echo h($row['phone'] !== '' ? $row['phone'] : '-'); ?></td>
                        <?php if ($routeLabels) { ?><td><span class="badge">สาย <?php echo (int)$row['route']; ?></span></td><?php } ?>
                        <?php if ($deliveryOrder) { ?><td><?php echo (int)$row['route_order']; ?></td><?php } ?>
                    </tr>
                <?php }} ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
