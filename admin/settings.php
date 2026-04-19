<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();
$user = admin_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        set_flash_message('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่');
        admin_auth_redirect('settings.php');
    }
    if (isset($_POST['save_flags'])) {
        set_app_setting($conn, 'enable_map_features', isset($_POST['enable_map_features']));
        set_app_setting($conn, 'enable_delivery_rounds', isset($_POST['enable_delivery_rounds']));
        set_app_setting($conn, 'enable_route_labels', isset($_POST['enable_route_labels']));
        set_app_setting($conn, 'enable_delivery_order', isset($_POST['enable_delivery_order']));
        set_app_setting($conn, 'enable_quick_tools', isset($_POST['enable_quick_tools']));
        admin_log_action('save_settings', 'บันทึกการตั้งค่าฟีเจอร์');
        set_flash_message('success', 'บันทึกการตั้งค่าเรียบร้อย');
        admin_auth_redirect('settings.php');
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        if ($newPassword !== $confirmPassword) {
            set_flash_message('error', 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
        } else {
            $error = '';
            if (admin_update_own_password((int)$user['id'], $currentPassword, $newPassword, $error)) {
                set_flash_message('success', 'เปลี่ยนรหัสผ่านเรียบร้อย');
            } else {
                set_flash_message('error', $error);
            }
        }
        admin_auth_redirect('settings.php');
    }
}

$mapEnabled = maps_enabled();
$roundsEnabled = rounds_enabled();
$routeLabelsEnabled = route_labels_enabled();
$deliveryOrderEnabled = delivery_order_enabled();
$quickToolsEnabled = quick_tools_enabled();

admin_render_header('ตั้งค่าหลังบ้าน', 'รวมค่าหลักที่ต้องใช้จริงไว้หน้าเดียว เพื่อลดความซับซ้อนเวลาจัดการผ่านมือถือ');
?>
<div class="grid-2">
    <div class="card">
        <h2>เปิด/ปิดฟีเจอร์หลัก</h2>
        <form method="post" id="flagsForm">
            <?php echo csrf_input(); ?>
            <div class="toggle-group">
                <div class="toggle-item">
                    <div class="toggle-info">
                        <div class="toggle-title">🗺️ แผนที่และจุดส่ง</div>
                        <div class="toggle-desc">เปิดให้แสดงลิงก์แผนที่และมาร์คจุดส่งของลูกค้า</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enable_map_features" value="1" <?php echo $mapEnabled ? 'checked' : ''; ?> onchange="autoSaveFlags()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-item">
                    <div class="toggle-info">
                        <div class="toggle-title">🔄 รอบการส่ง</div>
                        <div class="toggle-desc">แบ่งการส่งเป็นหลายรอบต่อวัน (r1–r7)</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enable_delivery_rounds" value="1" <?php echo $roundsEnabled ? 'checked' : ''; ?> onchange="autoSaveFlags()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-item">
                    <div class="toggle-info">
                        <div class="toggle-title">🛣️ สาย/โซนส่ง</div>
                        <div class="toggle-desc">แสดงและจัดกลุ่มลูกค้าตามสายส่ง</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enable_route_labels" value="1" <?php echo $routeLabelsEnabled ? 'checked' : ''; ?> onchange="autoSaveFlags()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-item">
                    <div class="toggle-info">
                        <div class="toggle-title">📋 ลำดับการส่ง</div>
                        <div class="toggle-desc">กำหนดลำดับการส่งให้แต่ละลูกค้า</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enable_delivery_order" value="1" <?php echo $deliveryOrderEnabled ? 'checked' : ''; ?> onchange="autoSaveFlags()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-item">
                    <div class="toggle-info">
                        <div class="toggle-title">⚡ เครื่องมือเร็ว</div>
                        <div class="toggle-desc">แสดงปุ่มทางลัดในหน้าทำงานหลัก</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enable_quick_tools" value="1" <?php echo $quickToolsEnabled ? 'checked' : ''; ?> onchange="autoSaveFlags()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" name="save_flags" value="1" class="btn btn-primary" id="saveFlagsBtn">บันทึกการตั้งค่า</button>
                <span id="autoSaveStatus" style="font-size:13px;color:#64748b;display:none;align-self:center">กำลังบันทึก...</span>
            </div>
        </form>
        <script>
        var autoSaveTimer = null;
        function autoSaveFlags() {
            clearTimeout(autoSaveTimer);
            var status = document.getElementById('autoSaveStatus');
            if (status) { status.textContent = 'กำลังบันทึก...'; status.style.display = 'inline'; }
            autoSaveTimer = setTimeout(function() {
                document.getElementById('saveFlagsBtn').click();
            }, 800);
        }
        </script>
    </div>
    <div class="card">
        <h2>เปลี่ยนรหัสผ่านของฉัน</h2>
        <form method="post">
            <?php echo csrf_input(); ?>
            <div class="field"><label>รหัสผ่านปัจจุบัน</label><input type="password" name="current_password" class="input"></div>
            <div class="field" style="margin-top:12px"><label>รหัสผ่านใหม่</label><input type="password" name="new_password" class="input"></div>
            <div class="field" style="margin-top:12px"><label>ยืนยันรหัสผ่านใหม่</label><input type="password" name="confirm_password" class="input"></div>
            <div class="btn-row"><button type="submit" name="change_password" value="1" class="btn btn-dark">เปลี่ยนรหัสผ่าน</button></div>
        </form>
        <div class="footer-note">รหัสผ่านใหม่ควรอย่างน้อย 6 ตัวอักษร และไม่ควรใช้รหัสเดาง่าย</div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>งานที่ไม่ได้ใช้บ่อย</h2>
    <div class="btn-row">
        <a class="btn btn-light" href="line_users.php">กำหนดสิทธิ์ LINE</a>
        <a class="btn btn-light" href="admin_users.php">บัญชีหลังบ้าน</a>
        <a class="btn btn-light" href="index.php">กลับหน้าภาพรวม</a>
    </div>
    <div class="footer-note">ลิงก์ที่ซ้ำซ้อนและหน้าตั้งค่าเดิมถูกตัดออกจากเมนูหลักแล้ว เพื่อให้หลังบ้านเหลือเฉพาะหน้าที่ใช้จริงบ่อย ๆ</div>
</div>
<?php admin_render_footer('ถ้าต้องการซ่อนฟังก์ชันจากหน้ามือถือหลัก แต่ยังให้ผู้จัดการใช้งานได้ ให้ย้ายมาควบคุมจากหลังบ้านนี้'); ?>
