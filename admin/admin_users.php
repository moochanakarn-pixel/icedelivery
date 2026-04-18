<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$adminRoles = admin_user_roles();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        set_flash_message('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่');
        admin_auth_redirect('admin_users.php');
    }
    if (isset($_POST['add_admin_user'])) {
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $fullName = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
        $role = isset($adminRoles[$_POST['role']]) ? $_POST['role'] : 'admin';
        $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
        if ($username === '' || $password === '' || strlen($password) < 6) {
            set_flash_message('error', 'กรอก username และ password อย่างน้อย 6 ตัวอักษร');
        } else {
            $safeUsername = mysqli_real_escape_string($conn, $username);
            $safeFullName = mysqli_real_escape_string($conn, $fullName);
            $safeRole = mysqli_real_escape_string($conn, $role);
            $safeHash = mysqli_real_escape_string($conn, admin_password_hash($password));
            $safeNow = mysqli_real_escape_string($conn, now_datetime());
            @mysqli_query($conn, "INSERT INTO admin_users(username, password_hash, full_name, role, is_active, created_at, updated_at)
                VALUES('{$safeUsername}', '{$safeHash}', '{$safeFullName}', '{$safeRole}', 1, '{$safeNow}', '{$safeNow}')");
            if (mysqli_errno($conn)) {
                set_flash_message('error', 'เพิ่มบัญชีหลังบ้านไม่สำเร็จ: ' . mysqli_error($conn));
            } else {
                admin_log_action('add_admin_user', 'เพิ่มบัญชีหลังบ้าน ' . $username);
                set_flash_message('success', 'เพิ่มบัญชีหลังบ้านเรียบร้อย');
            }
        }
        admin_auth_redirect('admin_users.php');
    }

    if (isset($_POST['save_admin_user'])) {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $fullName = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
        $role = isset($adminRoles[$_POST['role']]) ? $_POST['role'] : 'admin';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newPassword = trim(isset($_POST['new_password']) ? $_POST['new_password'] : '');
        $safeFullName = mysqli_real_escape_string($conn, $fullName);
        $safeRole = mysqli_real_escape_string($conn, $role);
        $safeNow = mysqli_real_escape_string($conn, now_datetime());
        $sql = "UPDATE admin_users SET full_name = '{$safeFullName}', role = '{$safeRole}', is_active = {$isActive}, updated_at = '{$safeNow}'";
        if ($newPassword !== '') {
            $sql .= ", password_hash = '" . mysqli_real_escape_string($conn, admin_password_hash($newPassword)) . "'";
        }
        $sql .= " WHERE id = {$id} LIMIT 1";
        @mysqli_query($conn, $sql);
        if (mysqli_errno($conn)) {
            set_flash_message('error', 'อัปเดตบัญชีหลังบ้านไม่สำเร็จ: ' . mysqli_error($conn));
        } else {
            admin_log_action('save_admin_user', 'อัปเดตบัญชีหลังบ้าน ID ' . $id);
            set_flash_message('success', 'บันทึกบัญชีหลังบ้านเรียบร้อย');
        }
        admin_auth_redirect('admin_users.php');
    }
}

$rows = fetch_all_rows(@mysqli_query($conn, "SELECT * FROM admin_users ORDER BY is_active DESC, id ASC"));
admin_render_header('บัญชีผู้ดูแลหลังบ้าน', 'สร้างผู้จัดการหรือแอดมินสำหรับเข้า /admin โดยไม่ต้องผ่าน LINE');
?>
<div class="card">
    <h2>เพิ่มบัญชีหลังบ้าน</h2>
    <form method="post">
            <?php echo csrf_input(); ?>
        <div class="form-grid">
            <div class="field"><label>Username</label><input type="text" name="username" class="input"></div>
            <div class="field"><label>ชื่อแสดงผล</label><input type="text" name="full_name" class="input"></div>
            <div class="field"><label>Role</label><select name="role" class="select"><?php foreach ($adminRoles as $code => $label) { ?><option value="<?php echo h($code); ?>"><?php echo h($label); ?></option><?php } ?></select></div>
            <div class="field"><label>Password</label><input type="password" name="password" class="input"></div>
        </div>
        <div class="btn-row"><button type="submit" name="add_admin_user" value="1" class="btn btn-success">+ เพิ่มบัญชี</button></div>
    </form>
</div>

<div class="list" style="margin-top:16px">
    <?php foreach ($rows as $row) { ?>
        <form method="post" class="item">
            <?php echo csrf_input(); ?>
            <div class="item-head">
                <div>
                    <div class="item-title"><?php echo h($row['username']); ?></div>
                    <div class="muted">เข้าใช้ล่าสุด: <?php echo h($row['last_login_at']); ?></div>
                </div>
                <div class="badge <?php echo (int)$row['is_active'] === 1 ? 'success' : 'danger'; ?>"><?php echo (int)$row['is_active'] === 1 ? 'active' : 'disabled'; ?></div>
            </div>
            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
            <div class="form-grid">
                <div class="field"><label>ชื่อแสดงผล</label><input type="text" name="full_name" class="input" value="<?php echo h($row['full_name']); ?>"></div>
                <div class="field"><label>Role</label><select name="role" class="select"><?php foreach ($adminRoles as $code => $label) { ?><option value="<?php echo h($code); ?>" <?php echo $row['role'] === $code ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php } ?></select></div>
                <div class="field"><label>รหัสผ่านใหม่ (ไม่กรอก = ใช้ของเดิม)</label><input type="password" name="new_password" class="input"></div>
                <div class="field"><label>สถานะ</label><label class="badge"><input type="checkbox" name="is_active" value="1" <?php echo (int)$row['is_active'] === 1 ? 'checked' : ''; ?> style="margin-right:6px">ใช้งานได้</label></div>
            </div>
            <div class="btn-row"><button type="submit" name="save_admin_user" value="1" class="btn btn-primary">บันทึก</button></div>
        </form>
    <?php } ?>
</div>
<?php admin_render_footer('บัญชีชุดนี้ใช้สำหรับหน้า /admin เท่านั้น จึงแยกจากผู้ใช้ LINE เพื่อให้ควบคุมสิทธิ์ได้ง่ายกว่า'); ?>
