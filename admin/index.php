<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$today = date('Y-m-d');
$currentMonth = date('Y-m');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

function admin_scalar($sql, $field) {
    global $conn;
    $res = @mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row && isset($row[$field]) && $row[$field] !== null ? $row[$field] : 0;
}

$totalCustomers = (int)admin_scalar("SELECT COUNT(*) AS total FROM customers", 'total');
$todayOrders = (int)admin_scalar("SELECT COUNT(*) AS total FROM orders WHERE order_date = '{$today}'", 'total');
$todaySales = (float)admin_scalar("SELECT COALESCE(SUM(order_items.qty * order_items.price), 0) AS total FROM orders LEFT JOIN order_items ON order_items.order_id = orders.id WHERE orders.order_date = '{$today}'", 'total');
$pendingMoney = (int)admin_scalar("SELECT COUNT(*) AS total FROM orders WHERE order_date = '{$today}' AND status <> 'paid'", 'total');
$activeLineUsers = (int)admin_scalar("SELECT COUNT(*) AS total FROM line_users WHERE is_active = 1", 'total');
$adminUsers = (int)admin_scalar("SELECT COUNT(*) AS total FROM admin_users WHERE is_active = 1", 'total');
$monthTop = fetch_all_rows(@mysqli_query($conn, "
    SELECT customers.name, COALESCE(SUM(order_items.qty * order_items.price),0) AS total
    FROM orders
    JOIN customers ON customers.id = orders.customer_id
    LEFT JOIN order_items ON order_items.order_id = orders.id
    WHERE orders.order_date >= '{$monthStart}' AND orders.order_date <= '{$monthEnd}'
    GROUP BY customers.id
    ORDER BY total DESC, customers.name ASC
    LIMIT 5
"));
$recentLineUsers = fetch_all_rows(@mysqli_query($conn, "SELECT display_name, line_user_id, role, last_seen_at FROM line_users ORDER BY COALESCE(last_seen_at, updated_at, created_at) DESC, id DESC LIMIT 6"));
$recentActivities = fetch_all_rows(@mysqli_query($conn, "SELECT actor_name, action_key, details, created_at FROM activity_logs ORDER BY id DESC LIMIT 8"));

admin_render_header('ภาพรวมหลังบ้าน', 'รวมงานจัดการที่ไม่ต้องโชว์ใน LINE และทำงานได้สะดวกทั้งมือถือกับคอม');
?>
<div class="stats-grid">
    <div class="card"><div class="stat-label">ลูกค้าทั้งหมด</div><div class="stat-value"><?php echo number_format($totalCustomers); ?></div></div>
    <div class="card"><div class="stat-label">ออเดอร์วันนี้</div><div class="stat-value"><?php echo number_format($todayOrders); ?></div></div>
    <div class="card"><div class="stat-label">ยอดขายวันนี้</div><div class="stat-value"><?php echo number_format($todaySales); ?></div><div class="muted">บาท</div></div>
    <div class="card"><div class="stat-label">ค้างรับเงินวันนี้</div><div class="stat-value"><?php echo number_format($pendingMoney); ?></div></div>
    <div class="card"><div class="stat-label">ผู้ใช้ LINE ที่ active</div><div class="stat-value"><?php echo number_format($activeLineUsers); ?></div></div>
    <div class="card"><div class="stat-label">บัญชีหลังบ้าน</div><div class="stat-value"><?php echo number_format($adminUsers); ?></div></div>
</div>

<div class="grid-2" style="margin-top:16px">
    <div class="card">
        <h2>ลูกค้ายอดสูงเดือนนี้</h2>
        <?php if (!$monthTop) { ?>
            <div class="muted">ยังไม่มีข้อมูล</div>
        <?php } else { ?>
            <?php foreach ($monthTop as $index => $row) { ?>
                <div class="row">
                    <div><strong><?php echo ($index + 1) . '. ' . h($row['name']); ?></strong></div>
                    <div class="badge success"><?php echo number_format($row['total']); ?> บาท</div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <div class="card">
        <h2>ผู้ใช้ LINE ล่าสุด</h2>
        <?php if (!$recentLineUsers) { ?>
            <div class="muted">ยังไม่มีผู้ใช้ LINE เข้ามา</div>
        <?php } else { ?>
            <?php foreach ($recentLineUsers as $row) { ?>
                <div class="row">
                    <div><strong><?php echo h($row['display_name'] !== '' ? $row['display_name'] : 'ยังไม่ทราบชื่อ'); ?></strong></div>
                    <div>
                        <span class="badge"><?php echo h($row['role']); ?></span>
                        <div class="muted" style="margin-top:4px"><?php echo h($row['last_seen_at']); ?></div>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>กิจกรรมล่าสุด</h2>
    <?php if (!$recentActivities) { ?>
        <div class="muted">ยังไม่มี log การใช้งาน</div>
    <?php } else { ?>
        <?php foreach ($recentActivities as $row) { ?>
            <div class="row">
                <div>
                    <div><strong><?php echo h($row['actor_name']); ?></strong> · <?php echo h($row['action_key']); ?></div>
                    <div class="muted"><?php echo h($row['details']); ?></div>
                </div>
                <div class="muted"><?php echo h($row['created_at']); ?></div>
            </div>
        <?php } ?>
    <?php } ?>
</div>
<div class="card" style="margin-top:16px">
    <h2>📣 ส่ง Push Notification</h2>
    <form method="post" action="send_push.php">
        <?php echo csrf_input(); ?>
        <div class="form-grid">
            <div class="field"><label>หัวข้อ</label><input class="input" type="text" name="title" placeholder="เช่น ออเดอร์ใหม่!" required></div>
            <div class="field"><label>ข้อความ</label><input class="input" type="text" name="body" placeholder="เช่น มีออเดอร์ใหม่ 3 ร้าน" required></div>
        </div>
        <div class="btn-row"><button type="submit" class="btn btn-primary">ส่งแจ้งเตือน</button></div>
    </form>
</div>
<?php admin_render_footer(''); ?>