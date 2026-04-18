<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$roles = array('family', 'admin');
$roleLabels = line_role_labels();
$layoutOptions = line_layout_options();
$targetCatalog = line_menu_target_catalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        set_flash_message('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่');
        admin_auth_redirect('line_richmenu.php');
    }
    $imageMessages = array();
    if (!empty($_FILES['role_image']) && is_array($_FILES['role_image'])) {
        $uploadResults = line_handle_menu_image_uploads($_FILES['role_image']);
        foreach ($uploadResults as $uploadRole => $uploadResult) {
            $prefix = line_role_label_th($uploadRole) . ': ';
            $imageMessages[] = $prefix . (isset($uploadResult['message']) ? $uploadResult['message'] : '');
            if (empty($uploadResult['ok'])) {
                set_flash_message('error', implode(' | ', $imageMessages));
                admin_auth_redirect('line_richmenu.php');
            }
        }
    }

    if (isset($_POST['save_role_images'])) {
        if (count($imageMessages)) {
            set_flash_message('success', implode(' | ', $imageMessages));
            admin_log_action('save_line_role_images', 'อัปโหลดรูป LINE rich menu ใหม่');
        } else {
            set_flash_message('success', 'ยังไม่ได้เลือกรูปใหม่');
        }
        admin_auth_redirect('line_richmenu.php');
    }


    if (isset($_POST['rebuild_richmenus_only'])) {
        $reset = isset($_POST['reset_before_setup']) ? true : false;
        $setup = line_setup_all_richmenus($reset);
        $itemTexts = array();
        if (!empty($setup['items']) && is_array($setup['items'])) {
            foreach ($setup['items'] as $item) {
                $itemTexts[] = line_role_label_th($item['role']) . ': ' . $item['message'];
            }
        }
        $message = $setup['message'] . (count($itemTexts) ? ' • ' . implode(' | ', $itemTexts) : '');
        set_flash_message(!empty($setup['ok']) ? 'success' : 'error', $message);
        admin_log_action('rebuild_line_richmenus_only', $reset ? 'ลบเมนูเดิมก่อนสร้างใหม่' : 'สร้าง rich menu ใหม่');
        admin_auth_redirect('line_richmenu.php');
    }

    if (isset($_POST['sync_all_line_users'])) {
        $syncAll = line_sync_all_active_users();
        $itemTexts = array();
        if (!empty($syncAll['items']) && is_array($syncAll['items'])) {
            foreach ($syncAll['items'] as $item) {
                if (empty($item['ok'])) {
                    $name = trim((string)$item['display_name']) !== '' ? trim((string)$item['display_name']) : $item['line_user_id'];
                    $itemTexts[] = $name . ': ' . $item['message'];
                }
            }
        }
        $message = $syncAll['message'] . (count($itemTexts) ? ' • ' . implode(' | ', $itemTexts) : '');
        set_flash_message(!empty($syncAll['ok']) ? 'success' : 'info', $message);
        admin_log_action('sync_all_line_users', 'sync rich menu ให้ผู้ใช้ LINE ทั้งหมดจากหน้ากำหนด LINE rich');
        admin_auth_redirect('line_richmenu.php');
    }

    if (isset($_POST['reset_default_line_menu'])) {
        $save = line_save_menu_configs(line_menu_default_configs());
        if (!empty($save['ok'])) {
            set_flash_message('success', 'รีเซ็ตผังเมนู LINE กลับค่าแนะนำแล้ว');
            admin_log_action('reset_line_menu_config', 'รีเซ็ตผัง LINE rich menu กลับค่าเริ่มต้น');
        } else {
            set_flash_message('error', $save['message']);
        }
        admin_auth_redirect('line_richmenu.php');
    }

    if (isset($_POST['save_line_menu_config']) || isset($_POST['save_and_rebuild'])) {
        $configs = array();
        foreach ($roles as $role) {
            $layout = isset($_POST['layout'][$role]) ? (int)$_POST['layout'][$role] : 6;
            $slots = isset($_POST['slot'][$role]) && is_array($_POST['slot'][$role]) ? array_values($_POST['slot'][$role]) : array();
            $configs[$role] = array(
                'layout' => $layout,
                'slots' => $slots,
            );
        }

        $save = line_save_menu_configs($configs);
        if (empty($save['ok'])) {
            set_flash_message('error', $save['message']);
            admin_auth_redirect('line_richmenu.php');
        }

        $message = $save['message'];
        if (count($imageMessages)) {
            $message .= ' • ' . implode(' | ', $imageMessages);
        }
        if (isset($_POST['save_and_rebuild'])) {
            $reset = isset($_POST['reset_before_setup']) ? true : false;
            $setup = line_setup_all_richmenus($reset);
            $itemTexts = array();
            if (!empty($setup['items']) && is_array($setup['items'])) {
                foreach ($setup['items'] as $item) {
                    $itemTexts[] = line_role_label_th($item['role']) . ': ' . $item['message'];
                }
            }
            $message .= ' • ' . $setup['message'] . (count($itemTexts) ? ' • ' . implode(' | ', $itemTexts) : '');
            if (!empty($_POST['sync_after_rebuild']) && !empty($setup['ok'])) {
                $syncAll = line_sync_all_active_users();
                $message .= ' • ' . $syncAll['message'];
            }
            set_flash_message(!empty($setup['ok']) ? 'success' : 'error', $message);
            admin_log_action('save_line_menu_and_rebuild', 'บันทึกผัง LINE rich menu และสร้างเมนูใหม่');
        } else {
            set_flash_message('success', $message);
            admin_log_action('save_line_menu_config', 'บันทึกผัง LINE rich menu');
        }
        admin_auth_redirect('line_richmenu.php');
    }
}

$configs = line_get_menu_configs();
$statusRows = line_role_menu_status_rows();

function line_menu_preview_src($role) {
    $path = line_role_image_path($role);
    return '../' . line_role_image_relpath($role) . '?v=' . (is_file($path) ? filemtime($path) : time());
}

function line_menu_file_note($role) {
    $stats = line_role_image_stats($role);
    if (empty($stats['exists'])) {
        return 'ยังไม่มีไฟล์รูป';
    }
    return $stats['width'] . 'x' . $stats['height'] . ' • ' . number_format($stats['bytes'] / 1024, 1) . ' KB';
}

admin_render_header('เมนู LINE', 'กำหนดเมนู LINE ให้เรียบง่าย เหลือเฉพาะ ครอบครัว และ แอดมิน');
?>
<div class="card">
    <h2>ภาพรวมเมนูปัจจุบัน</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>สิทธิ์</th>
                    <th>จำนวนช่อง</th>
                    <th>ปุ่มที่เห็นใน LINE</th>
                    <th>richMenu ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusRows as $row) { ?>
                    <?php $effectiveId = $row['effective_richmenu_id'] !== '' ? $row['effective_richmenu_id'] : $row['richmenu_id']; ?>
                    <tr>
                        <td><span class="badge <?php echo h(line_role_badge_class($row['role'])); ?>"><?php echo h(line_role_label_th($row['role'])); ?></span></td>
                        <td><?php echo number_format($row['button_count']); ?> ช่อง</td>
                        <td><?php echo h($row['buttons_text']); ?></td>
                        <td><?php echo $effectiveId !== '' ? '<span class="badge success">' . h(substr($effectiveId, 0, 24)) . '...</span>' : '<span class="badge danger">ยังไม่ได้สร้าง</span>'; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="footer-note">หน้านี้เป็นศูนย์กลางของ LINE rich menu ทั้งหมด • สร้างเมนูใหม่ • sync เมนูทุกคน • อัปโหลดรูป • และกำหนดว่าสิทธิ์ไหนเห็นกี่ช่อง</div>
    <form method="post" style="margin-top:12px">
        <?php echo csrf_input(); ?>
        <div class="field">
            <label class="badge"><input type="checkbox" name="reset_before_setup" value="1" style="margin-right:6px">ลบเมนูเดิมก่อนสร้างใหม่</label>
        </div>
        <div class="btn-row">
            <button type="submit" name="rebuild_richmenus_only" value="1" class="btn btn-dark">สร้าง/อัปเดต rich menu</button>
            <button type="submit" name="sync_all_line_users" value="1" class="btn btn-success">sync เมนูทุกคน</button>
        </div>
    </form>
</div>

<form method="post" enctype="multipart/form-data" class="card" style="margin-top:16px">
    <?php echo csrf_input(); ?>
    <h2>กำหนดเมนูตามสิทธิ์</h2>
    <div class="footer-note" style="margin-bottom:12px">เลือกจำนวนช่องก่อน แล้วกำหนดปุ่มตามลำดับของช่อง พร้อมอัปโหลดรูปจริงของแต่ละสิทธิ์ได้จากหน้านี้เลย</div>

    <div class="list">
        <?php foreach ($roles as $role) { ?>
            <?php $cfg = isset($configs[$role]) ? $configs[$role] : line_normalize_menu_config($role, array()); ?>
            <div class="item" style="padding:16px">
                <div class="item-head">
                    <div>
                        <div class="item-title"><?php echo h(line_role_label_th($role)); ?></div>
                        <div class="muted" style="margin-top:6px">ตอนนี้เห็น <?php echo number_format((int)$cfg['layout']); ?> ช่อง</div>
                    </div>
                    <span class="badge <?php echo h(line_role_badge_class($role)); ?>"><?php echo h($role); ?></span>
                </div>

                <div class="grid-2" style="align-items:start">
                    <div>
                        <div class="field">
                            <label>จำนวนช่องที่เห็น</label>
                            <select name="layout[<?php echo h($role); ?>]" class="select js-layout" data-role="<?php echo h($role); ?>">
                                <?php foreach ($layoutOptions as $layoutValue => $layoutLabel) { ?>
                                    <option value="<?php echo (int)$layoutValue; ?>" <?php echo (int)$cfg['layout'] === (int)$layoutValue ? 'selected' : ''; ?>><?php echo h($layoutLabel); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
                            <?php for ($i = 0; $i < 6; $i++) { ?>
                                <?php $selected = isset($cfg['slots'][$i]) ? $cfg['slots'][$i] : ''; ?>
                                <div class="field js-slot-wrap" data-role="<?php echo h($role); ?>" data-slot-index="<?php echo $i + 1; ?>">
                                    <label>ช่องที่ <?php echo $i + 1; ?></label>
                                    <select name="slot[<?php echo h($role); ?>][<?php echo $i; ?>]" class="select">
                                        <option value="">-- ไม่ใช้ --</option>
                                        <?php foreach ($targetCatalog as $targetKey => $targetInfo) { ?>
                                            <option value="<?php echo h($targetKey); ?>" <?php echo $selected === $targetKey ? 'selected' : ''; ?>><?php echo h($targetInfo['label']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div>
                        <div class="field"><label>รูปที่ใช้จริงใน LINE</label></div>
                        <img src="<?php echo h(line_menu_preview_src($role)); ?>" alt="preview-<?php echo h($role); ?>" style="width:100%;max-width:420px;border-radius:18px;border:1px solid #d6e5f3;display:block;background:#fff">
                        <div class="footer-note" style="margin-top:8px">ไฟล์ปัจจุบัน: <?php echo h(line_menu_file_note($role)); ?></div>
                        <div class="field" style="margin-top:10px">
                            <label>เปลี่ยนรูปของ <?php echo h(line_role_label_th($role)); ?></label>
                            <input type="file" name="role_image[<?php echo h($role); ?>]" accept="image/png,image/jpeg,image/webp" class="input">
                        </div>
                        <div class="footer-note" style="margin-top:8px">ตอนนี้ใช้รูปจริงแค่ 1 ไฟล์ต่อ 1 สิทธิ์ เก็บรวมไว้ในโฟลเดอร์เดียว และระบบจะย่อ/แปลงให้เหมาะกับ LINE อัตโนมัติ</div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="field" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:12px">
        <label class="badge"><input type="checkbox" name="reset_before_setup" value="1" style="margin-right:6px">ลบเมนูเดิมก่อนสร้างใหม่</label>
        <label class="badge"><input type="checkbox" name="sync_after_rebuild" value="1" style="margin-right:6px" checked>sync เมนูทุกคนหลังสร้างใหม่</label>
    </div>
    <div class="btn-row">
        <button type="submit" name="save_role_images" value="1" class="btn btn-light">บันทึกรูปที่เลือก</button>
        <button type="submit" name="save_line_menu_config" value="1" class="btn btn-primary">บันทึกผังเมนู</button>
        <button type="submit" name="save_and_rebuild" value="1" class="btn btn-dark">บันทึก + สร้าง rich menu ใหม่</button>
        <button type="submit" name="reset_default_line_menu" value="1" class="btn btn-light" onclick="return confirm('รีเซ็ตกลับเป็นค่าที่แนะนำตอนแรก?');">รีเซ็ตค่าแนะนำ</button>
    </div>
    <div class="footer-note" style="margin-top:10px">ไฟล์รูปที่ต้องใช้จริงมีแค่ 2 ไฟล์: family / admin เท่านั้น • ถ้าเปลี่ยนรูปแล้ว ให้กดบันทึก + สร้างเมนูใหม่จากหน้านี้</div>
</form>

<div class="card" style="margin-top:16px">
    <h2>รายการปุ่มที่เลือกได้</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ชื่อปุ่ม</th>
                    <th>ปลายทาง</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($targetCatalog as $target) { ?>
                    <tr>
                        <td><?php echo h($target['label']); ?></td>
                        <td class="muted"><?php echo h($target['uri']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function(){
    function updateRole(role){
        var layout = document.querySelector('.js-layout[data-role="'+role+'"]');
        if(!layout) return;
        var count = parseInt(layout.value || '6', 10);
        var wraps = document.querySelectorAll('.js-slot-wrap[data-role="'+role+'"]');
        wraps.forEach(function(wrap){
            var idx = parseInt(wrap.getAttribute('data-slot-index') || '0', 10);
            wrap.style.opacity = idx <= count ? '1' : '0.45';
        });
    }
    document.querySelectorAll('.js-layout').forEach(function(el){
        updateRole(el.getAttribute('data-role'));
        el.addEventListener('change', function(){ updateRole(el.getAttribute('data-role')); });
    });
})();
</script>
<?php admin_render_footer('หน้านี้เป็นศูนย์กลางของ LINE rich menu • สร้างใหม่ • sync ทุกคน • อัปโหลดรูป • และกำหนดว่าสิทธิ์ไหนเห็นกี่ช่อง'); ?>
