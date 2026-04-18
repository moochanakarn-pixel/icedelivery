<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();

function line_sync_notice_text($res) {
    if (!is_array($res)) {
        return 'sync ไม่สำเร็จ';
    }
    if (!empty($res['ok'])) {
        return 'sync rich menu เรียบร้อย';
    }
    return 'sync rich menu ไม่สำเร็จ: ' . line_api_error_text($res);
}

$lineRoles = line_role_labels();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        set_flash_message('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่');
        admin_auth_redirect('line_users.php');
    }

    if (isset($_POST['sync_one_line_user'])) {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $userRes = @mysqli_query($conn, "SELECT line_user_id, role FROM line_users WHERE id = {$id} LIMIT 1");
        $userRow = $userRes ? mysqli_fetch_assoc($userRes) : null;
        if (!$userRow) {
            set_flash_message('error', 'ไม่พบผู้ใช้ LINE');
        } else {
            $sync = line_sync_user_menu($userRow['line_user_id']);
            set_flash_message(!empty($sync['ok']) ? 'success' : 'info', line_sync_notice_text($sync));
            admin_log_action('sync_one_line_user', 'sync menu ให้ ' . $userRow['line_user_id']);
        }
        admin_auth_redirect('line_users.php');
    }

    if (isset($_POST['save_line_user'])) {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $role = line_normalize_role(isset($_POST['role']) ? $_POST['role'] : 'family');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $userRes = @mysqli_query($conn, "SELECT line_user_id FROM line_users WHERE id = {$id} LIMIT 1");
        $userRow = $userRes ? mysqli_fetch_assoc($userRes) : null;
        @mysqli_query($conn, "UPDATE line_users SET role = '" . mysqli_real_escape_string($conn, $role) . "', is_active = {$isActive}, updated_at = '" . mysqli_real_escape_string($conn, now_datetime()) . "' WHERE id = {$id} LIMIT 1");
        if (mysqli_errno($conn)) {
            set_flash_message('error', 'อัปเดตผู้ใช้ LINE ไม่สำเร็จ: ' . mysqli_error($conn));
        } else {
            if ($userRow && $isActive === 1) {
                $sync = line_sync_user_menu($userRow['line_user_id']);
                set_flash_message(!empty($sync['ok']) ? 'success' : 'info', 'บันทึกสิทธิ์แล้ว • ' . line_sync_notice_text($sync));
            } else {
                set_flash_message('success', 'บันทึกผู้ใช้ LINE เรียบร้อย');
            }
            admin_log_action('save_line_user', 'อัปเดต ' . ($userRow ? $userRow['line_user_id'] : $id) . ' เป็น ' . $role);
        }
        admin_auth_redirect('line_users.php');
    }
}

$search = trim(isset($_GET['q']) ? $_GET['q'] : '');
$where = '';
if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $where = "WHERE display_name LIKE '%{$safe}%' OR line_user_id LIKE '%{$safe}%'";
}

$rows = fetch_all_rows(@mysqli_query($conn, "SELECT * FROM line_users {$where} ORDER BY is_active DESC, COALESCE(last_seen_at, updated_at, created_at) DESC, id DESC LIMIT 300"));
$counts = fetch_all_rows(@mysqli_query($conn, "SELECT role, COUNT(*) AS total FROM line_users WHERE is_active = 1 GROUP BY role ORDER BY FIELD(role,'family','admin')"));

admin_render_header('สิทธิ์ผู้ใช้ LINE', 'กำหนดว่าใครเป็นครอบครัว และใครเป็นแอดมิน พร้อมรีเฟรชเมนูของแต่ละคนได้จากหน้านี้');
?>
<div class="grid-2">
    <div class="card">
        <h2>สรุปผู้ใช้ LINE ที่ active</h2>
        <?php if (!$counts) { ?>
            <div class="muted">ยังไม่มีผู้ใช้ LINE ในระบบ</div>
        <?php } else { ?>
            <?php foreach ($counts as $row) { ?>
                <div class="row">
                    <div><?php echo h(line_role_label_th($row['role'])); ?></div>
                    <div class="badge success"><?php echo number_format($row['total']); ?></div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <div class="card">
        <h2>ค้นหาผู้ใช้</h2>
        <form method="get">
            <div class="field"><label>ชื่อใน LINE หรือ user ID</label><input type="text" name="q" class="input" value="<?php echo h($search); ?>"></div>
            <div class="btn-row"><button type="submit" class="btn btn-primary">ค้นหา</button></div>
        </form>
        <div class="footer-note">ถ้าเพิ่งเพิ่มคนใหม่ ให้คนนั้นทัก OA เข้ามา 1 ครั้งก่อน แล้วชื่อจะเข้ามาในหน้านี้</div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>กำหนดสิทธิ์รายคน</h2>
    <div class="footer-note" style="margin-bottom:12px">หน้านี้ใช้สำหรับเปลี่ยนสิทธิ์ของผู้ใช้ LINE ทีละคน และรีเฟรชเมนูเฉพาะคนนั้นเท่านั้น</div>
    <div class="btn-row">
        <a href="line_richmenu.php" class="btn btn-dark">ไปหน้ากำหนด LINE rich</a>
    </div>
    <div class="footer-note" style="margin-top:12px">การสร้าง/อัปเดต rich menu ใหม่ และการ sync เมนูทุกคน ถูกย้ายไปไว้ที่หน้า <a href="line_richmenu.php">กำหนด LINE rich</a> หน้าเดียวแล้ว</div>
</div>

<div class="card" style="margin-top:16px">
    <h2>รายการผู้ใช้ LINE</h2>
    <?php if (!$rows) { ?>
        <div class="muted">ยังไม่มีผู้ใช้ LINE ที่ตรงกับเงื่อนไขค้นหา</div>
    <?php } else { ?>
        <div class="list">
            <?php foreach ($rows as $row) { ?>
                <div class="item">
                    <div class="item-head">
                        <div>
                            <div class="item-title"><?php echo h(trim((string)$row['display_name']) !== '' ? $row['display_name'] : 'ยังไม่ทราบชื่อ'); ?></div>
                            <div class="muted" style="margin-top:6px"><?php echo h($row['line_user_id']); ?></div>
                            <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px">
                                <span class="badge <?php echo h(line_role_badge_class($row['role'])); ?>">สิทธิ์: <?php echo h(line_role_label_th($row['role'])); ?></span>
                                <?php if (function_exists('line_role_button_count')) { ?>
                                <span class="badge">เห็น <?php echo number_format(line_role_button_count($row['role'])); ?> ช่อง</span>
                                <?php } ?>
                                <?php if ((int)$row['is_active'] === 1) { ?><span class="badge success">active</span><?php } else { ?><span class="badge danger">ปิดใช้งาน</span><?php } ?>
                            </div>
                        </div>
                        <div class="muted">ล่าสุด: <?php echo h($row['last_seen_at']); ?></div>
                    </div>
                    <form method="post">
            <?php echo csrf_input(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                        <div class="form-grid">
                            <div class="field">
                                <label>สิทธิ์</label>
                                <select name="role" class="select">
                                    <?php foreach ($lineRoles as $roleValue => $roleLabel) { ?>
                                        <option value="<?php echo h($roleValue); ?>" <?php echo $row['role'] === $roleValue ? 'selected' : ''; ?>><?php echo h($roleLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>สถานะ</label>
                                <label class="badge"><input type="checkbox" name="is_active" value="1" style="margin-right:6px" <?php echo (int)$row['is_active'] === 1 ? 'checked' : ''; ?>>เปิดใช้งาน</label>
                            </div>
                        </div>
                        <?php if (function_exists('line_role_button_labels')) { ?>
                        <div class="footer-note">ปุ่มที่ role นี้จะเห็น: <?php echo h(implode(' • ', line_role_button_labels($row['role']))); ?></div>
                        <?php } ?>
                        <div class="btn-row">
                            <button type="submit" name="save_line_user" value="1" class="btn btn-primary">บันทึกสิทธิ์</button>
                            <button type="submit" name="sync_one_line_user" value="1" class="btn btn-light">รีเฟรชเมนูคนนี้</button>
                        </div>
                    </form>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
<?php admin_render_footer('หน้านี้ใช้เปลี่ยนสิทธิ์ผู้ใช้ LINE ทีละคน ส่วนการสร้างเมนูใหม่และ sync ทุกคน ให้ใช้หน้ากำหนด LINE rich'); ?>
