<?php
include_once __DIR__ . '/config.php';

if (!defined('LINE_CHANNEL_ACCESS_TOKEN')) {
    define('LINE_CHANNEL_ACCESS_TOKEN', 'W58nEUCjHOjWV3TWtO1aTmGo23jaLwkBHhHGDfnqknoggDGZonQ2b8x7NhAtco1Pe4onug6OYlmibZcjlM+T7OMe/WaJRApDIbXgVbvIHZ4+fDbppnDtENkVVtYVUWDJPVlirkV+BxsYjqKLlUo5/gdB04t89/1O/w1cDnyilFU=');
}
if (!defined('LINE_CHANNEL_SECRET')) {
    define('LINE_CHANNEL_SECRET', '0f42580f070eb3463e7d7fc1c5e323a1');
}
if (!defined('LINE_SITE_BASE_URL')) {
    define('LINE_SITE_BASE_URL', 'https://mcnkth.com/icedelivery');
}
if (!defined('LINE_MENU_IMAGE_DIR')) {
    define('LINE_MENU_IMAGE_DIR', __DIR__ . '/assets/line-richmenu');
}
if (!defined('LINE_MENU_TEMPLATE_DIR')) {
    define('LINE_MENU_TEMPLATE_DIR', LINE_MENU_IMAGE_DIR);
}
if (!defined('LINE_ADMIN_MENU_IMAGE')) {
    define('LINE_ADMIN_MENU_IMAGE', LINE_MENU_IMAGE_DIR . '/line-richmenu-admin.jpg');
}
if (!defined('LINE_FAMILY_MENU_IMAGE')) {
    define('LINE_FAMILY_MENU_IMAGE', LINE_MENU_IMAGE_DIR . '/line-richmenu-customer.jpg');
}
if (!defined('LINE_SETUP_SECRET_KEY')) {
    define('LINE_SETUP_SECRET_KEY', 'mcnkth');
}

function line_now() {
    return date('Y-m-d H:i:s');
}

function line_url($path) {
    return rtrim(LINE_SITE_BASE_URL, '/') . '/' . ltrim((string)$path, '/');
}

function line_liff_or_url($pathOrUrl) {
    $value = trim((string)$pathOrUrl);
    if ($value === '') {
        return line_url('index.php');
    }
    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }
    return line_url($value);
}


function line_menu_targets() {
    return array(
        'order' => line_liff_or_url('index.php'),
        'driver' => line_liff_or_url('driver.php'),
        'report' => line_liff_or_url('report.php'),
        'customers' => line_liff_or_url('customers.php'),
        'summary_today' => line_liff_or_url('report.php?view=today'),
        'admin' => line_liff_or_url('admin/login.php')
    );
}

function line_menu_target_catalog() {
    $targets = line_menu_targets();
    return array(
        'order' => array('label' => 'คีย์ออเดอร์', 'uri' => $targets['order']),
        'driver' => array('label' => 'คนส่ง', 'uri' => $targets['driver']),
        'report' => array('label' => 'รายงาน', 'uri' => $targets['report']),
        'customers' => array('label' => 'ลูกค้า', 'uri' => $targets['customers']),
        'summary_today' => array('label' => 'สรุปวันนี้', 'uri' => $targets['summary_today']),
        'admin' => array('label' => 'หลังบ้าน', 'uri' => $targets['admin']),
    );
}

function line_menu_default_configs() {
    return array(
        'family' => array('layout' => 6, 'slots' => array('order', 'driver', 'report', 'customers', 'summary_today', 'admin')),
        'admin' => array('layout' => 6, 'slots' => array('order', 'driver', 'report', 'customers', 'summary_today', 'admin')),
    );
}

function line_layout_options() {
    return array(3 => '3 ช่อง', 4 => '4 ช่อง', 6 => '6 ช่อง');
}

function line_ensure_menu_config_table() {
    global $conn;
    if (function_exists('table_exists') && !table_exists($conn, 'line_menu_configs')) {
        @mysqli_query($conn, "CREATE TABLE line_menu_configs (
            role VARCHAR(20) NOT NULL,
            layout_count INT(11) NOT NULL DEFAULT 3,
            slots_json TEXT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }
}

function line_normalize_layout_count($value) {
    $value = (int)$value;
    if (!in_array($value, array(3, 4, 6), true)) {
        return 6;
    }
    return $value;
}

function line_role_layout_count($role) {
    $configs = line_get_menu_configs();
    $role = line_normalize_role($role);
    return isset($configs[$role]['layout']) ? (int)$configs[$role]['layout'] : 6;
}

function line_normalize_slots_for_layout($layout, $slots, $defaultSlots, $catalog) {
    $layout = line_normalize_layout_count($layout);
    $normalized = array();
    if (!is_array($slots)) {
        $slots = array();
    }
    foreach ($slots as $slot) {
        $slot = trim((string)$slot);
        if ($slot === '' || !isset($catalog[$slot])) {
            continue;
        }
        if (!in_array($slot, $normalized, true)) {
            $normalized[] = $slot;
        }
        if (count($normalized) >= $layout) {
            break;
        }
    }
    foreach ($defaultSlots as $slot) {
        $slot = trim((string)$slot);
        if ($slot === '' || !isset($catalog[$slot])) {
            continue;
        }
        if (!in_array($slot, $normalized, true)) {
            $normalized[] = $slot;
        }
        if (count($normalized) >= $layout) {
            break;
        }
    }
    foreach (array_keys($catalog) as $slot) {
        if (!in_array($slot, $normalized, true)) {
            $normalized[] = $slot;
        }
        if (count($normalized) >= $layout) {
            break;
        }
    }
    return array_slice($normalized, 0, $layout);
}

function line_normalize_menu_config($role, $config) {
    $defaults = line_menu_default_configs();
    $catalog = line_menu_target_catalog();
    $role = line_normalize_role($role);
    $default = isset($defaults[$role]) ? $defaults[$role] : array('layout' => 6, 'slots' => array('order'));
    $layout = isset($config['layout']) ? line_normalize_layout_count($config['layout']) : (int)$default['layout'];
    $slots = isset($config['slots']) ? $config['slots'] : $default['slots'];
    $slots = line_normalize_slots_for_layout($layout, $slots, $default['slots'], $catalog);
    return array('layout' => $layout, 'slots' => $slots);
}

function line_get_menu_configs() {
    static $cache = null;
    static $cacheKey = null;
    $currentKey = isset($GLOBALS['__ice_line_menu_config_cache_reset']) ? (string)$GLOBALS['__ice_line_menu_config_cache_reset'] : '0';
    if ($cache !== null && $cacheKey === $currentKey) {
        return $cache;
    }
    $cache = line_get_menu_configs_fresh();
    $cacheKey = $currentKey;
    return $cache;
}

function line_clear_menu_config_cache() {
    $GLOBALS['__ice_line_menu_config_cache_reset'] = microtime(true);
}

function line_save_menu_configs($configs) {
    global $conn;
    line_ensure_menu_config_table();
    if (!table_exists($conn, 'line_menu_configs')) {
        return array('ok' => false, 'message' => 'ยังไม่มีตาราง line_menu_configs');
    }
    $defaults = line_menu_default_configs();
    foreach ($defaults as $role => $defaultConfig) {
        $config = isset($configs[$role]) ? $configs[$role] : $defaultConfig;
        $normalized = line_normalize_menu_config($role, $config);
        $roleEsc = mysqli_real_escape_string($conn, $role);
        $layout = (int)$normalized['layout'];
        $slotsJsonEsc = mysqli_real_escape_string($conn, json_encode(array_values($normalized['slots']), JSON_UNESCAPED_UNICODE));
        @mysqli_query($conn, "INSERT INTO line_menu_configs(role, layout_count, slots_json, updated_at) VALUES('{$roleEsc}', {$layout}, '{$slotsJsonEsc}', NOW()) ON DUPLICATE KEY UPDATE layout_count=VALUES(layout_count), slots_json=VALUES(slots_json), updated_at=VALUES(updated_at)");
        if (mysqli_errno($conn)) {
            return array('ok' => false, 'message' => 'บันทึกผังเมนูไม่สำเร็จ: ' . mysqli_error($conn));
        }
    }
    line_clear_menu_config_cache();
    return array('ok' => true, 'message' => 'บันทึกผังเมนู LINE แล้ว');
}

function line_get_menu_configs_fresh() {
    global $conn;
    $defaults = line_menu_default_configs();
    $configs = array();
    foreach ($defaults as $role => $config) {
        $configs[$role] = line_normalize_menu_config($role, $config);
    }
    line_ensure_menu_config_table();
    if (function_exists('table_exists') && table_exists($conn, 'line_menu_configs')) {
        $res = @mysqli_query($conn, "SELECT role, layout_count, slots_json FROM line_menu_configs");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $role = line_normalize_role($row['role']);
                $slots = json_decode((string)$row['slots_json'], true);
                if (!is_array($slots)) {
                    $slots = array();
                }
                $configs[$role] = line_normalize_menu_config($role, array('layout' => (int)$row['layout_count'], 'slots' => $slots));
            }
        }
    }
    return $configs;
}

function line_role_image_path($role) {
    $role = line_normalize_role($role);
    if ($role === 'admin') {
        return LINE_ADMIN_MENU_IMAGE;
    }
    return LINE_FAMILY_MENU_IMAGE;
}

function line_role_image_relpath($role) {
    return 'assets/line-richmenu/' . basename(line_role_image_path($role));
}

function line_menu_image_for($role, $layout) {
    $role = line_normalize_role($role);
    $primary = line_role_image_path($role);
    if (is_file($primary)) {
        return $primary;
    }

    $legacyRoot = __DIR__;
    $candidates = array(
        LINE_MENU_IMAGE_DIR . '/line-richmenu-' . $role . '.jpg',
        LINE_MENU_IMAGE_DIR . '/line-richmenu-customer.jpg',
        LINE_MENU_IMAGE_DIR . '/line-richmenu-' . $role . '.png',
        $legacyRoot . '/line-richmenu-' . $role . '.jpg',
        $legacyRoot . '/line-richmenu-' . $role . '.png',
    );
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return $primary;
}

function line_can_process_images() {
    return function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled') && function_exists('imagejpeg');
}

function line_process_menu_image_upload($tmpPath, $targetPath) {
    if (!is_file($tmpPath)) {
        return array('ok' => false, 'message' => 'ไม่พบไฟล์อัปโหลด');
    }

    $info = @getimagesize($tmpPath);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return array('ok' => false, 'message' => 'ไฟล์รูปไม่ถูกต้อง');
    }

    $mime = isset($info['mime']) ? strtolower((string)$info['mime']) : '';
    if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) {
        return array('ok' => false, 'message' => 'รองรับเฉพาะ JPG, PNG หรือ WEBP');
    }

    $dir = dirname($targetPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    if (!line_can_process_images()) {
        if (@move_uploaded_file($tmpPath, $targetPath)) {
            return array('ok' => true, 'message' => 'อัปโหลดรูปสำเร็จ');
        }
        if (@copy($tmpPath, $targetPath)) {
            return array('ok' => true, 'message' => 'คัดลอกรูปสำเร็จ');
        }
        return array('ok' => false, 'message' => 'ไม่สามารถบันทึกรูปได้');
    }

    $source = null;
    if ($mime === 'image/jpeg') {
        $source = @imagecreatefromjpeg($tmpPath);
    } elseif ($mime === 'image/png') {
        $source = @imagecreatefrompng($tmpPath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $source = @imagecreatefromwebp($tmpPath);
    }
    if (!$source) {
        return array('ok' => false, 'message' => 'อ่านไฟล์รูปไม่สำเร็จ');
    }

    $targetW = 2500;
    $targetH = 1686;
    $dst = imagecreatetruecolor($targetW, $targetH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    $srcW = imagesx($source);
    $srcH = imagesy($source);
    $srcRatio = $srcW / max(1, $srcH);
    $targetRatio = $targetW / $targetH;
    if ($srcRatio > $targetRatio) {
        $newH = $targetH;
        $newW = (int)round($targetH * $srcRatio);
    } else {
        $newW = $targetW;
        $newH = (int)round($targetW / max(0.0001, $srcRatio));
    }
    $dstX = (int)floor(($targetW - $newW) / 2);
    $dstY = (int)floor(($targetH - $newH) / 2);
    imagecopyresampled($dst, $source, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

    $saved = false;
    $quality = 82;
    while ($quality >= 62) {
        ob_start();
        imagejpeg($dst, null, $quality);
        $jpegData = ob_get_clean();
        if ($jpegData !== false && strlen($jpegData) <= 350000) {
            $saved = @file_put_contents($targetPath, $jpegData) !== false;
            break;
        }
        $quality -= 5;
    }
    if (!$saved) {
        $saved = @imagejpeg($dst, $targetPath, 75);
    }

    imagedestroy($source);
    imagedestroy($dst);

    if (!$saved) {
        return array('ok' => false, 'message' => 'บันทึกรูปไม่สำเร็จ');
    }
    return array('ok' => true, 'message' => 'อัปโหลดรูปสำเร็จ');
}

function line_handle_menu_image_uploads($files) {
    $results = array();
    foreach (array('family', 'admin') as $role) {
        if (!isset($files['error'][$role])) {
            continue;
        }
        $error = (int)$files['error'][$role];
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $results[$role] = array('ok' => false, 'message' => 'อัปโหลดไฟล์ไม่สำเร็จ');
            continue;
        }
        $tmpName = isset($files['tmp_name'][$role]) ? $files['tmp_name'][$role] : '';
        $results[$role] = line_process_menu_image_upload($tmpName, line_role_image_path($role));
    }
    return $results;
}

function line_prepare_menu_image_binary($imagePath) {
    if (!is_file($imagePath)) {
        return array('ok' => false, 'message' => 'image not found: ' . $imagePath);
    }

    if (!line_can_process_images()) {
        $binary = @file_get_contents($imagePath);
        if ($binary === false || $binary === '') {
            return array('ok' => false, 'message' => 'unable to read image: ' . $imagePath);
        }
        return array('ok' => true, 'binary' => $binary, 'content_type' => line_detect_image_content_type($imagePath));
    }

    $info = @getimagesize($imagePath);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return array('ok' => false, 'message' => 'ไฟล์รูปไม่ถูกต้อง');
    }

    $mime = isset($info['mime']) ? strtolower((string)$info['mime']) : '';
    $source = null;
    if ($mime === 'image/jpeg') {
        $source = @imagecreatefromjpeg($imagePath);
    } elseif ($mime === 'image/png') {
        $source = @imagecreatefrompng($imagePath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $source = @imagecreatefromwebp($imagePath);
    }
    if (!$source) {
        $binary = @file_get_contents($imagePath);
        if ($binary === false || $binary === '') {
            return array('ok' => false, 'message' => 'unable to read image: ' . $imagePath);
        }
        return array('ok' => true, 'binary' => $binary, 'content_type' => line_detect_image_content_type($imagePath));
    }

    $targetW = 2500;
    $targetH = 1686;
    $dst = imagecreatetruecolor($targetW, $targetH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    $srcW = imagesx($source);
    $srcH = imagesy($source);
    $srcRatio = $srcW / max(1, $srcH);
    $targetRatio = $targetW / $targetH;
    if ($srcRatio > $targetRatio) {
        $newH = $targetH;
        $newW = (int)round($targetH * $srcRatio);
    } else {
        $newW = $targetW;
        $newH = (int)round($targetW / max(0.0001, $srcRatio));
    }
    $dstX = (int)floor(($targetW - $newW) / 2);
    $dstY = (int)floor(($targetH - $newH) / 2);
    imagecopyresampled($dst, $source, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

    $jpegData = false;
    $quality = 82;
    while ($quality >= 60) {
        ob_start();
        imagejpeg($dst, null, $quality);
        $candidate = ob_get_clean();
        if ($candidate !== false && strlen($candidate) <= 1000000) {
            $jpegData = $candidate;
            break;
        }
        $quality -= 4;
    }
    if ($jpegData === false) {
        ob_start();
        imagejpeg($dst, null, 70);
        $jpegData = ob_get_clean();
    }

    imagedestroy($source);
    imagedestroy($dst);

    if ($jpegData === false || $jpegData === '') {
        return array('ok' => false, 'message' => 'แปลงรูปเพื่ออัปโหลดไม่สำเร็จ');
    }

    return array('ok' => true, 'binary' => $jpegData, 'content_type' => 'image/jpeg');
}

function line_role_image_stats($role) {
    $path = line_role_image_path($role);
    $out = array('path' => $path, 'exists' => is_file($path), 'width' => 0, 'height' => 0, 'bytes' => 0);
    if (!$out['exists']) {
        return $out;
    }
    $out['bytes'] = (int)@filesize($path);
    $info = @getimagesize($path);
    if ($info && !empty($info[0]) && !empty($info[1])) {
        $out['width'] = (int)$info[0];
        $out['height'] = (int)$info[1];
    }
    return $out;
}

function line_menu_blueprints() {
    $catalog = line_menu_target_catalog();
    $configs = line_get_menu_configs_fresh();
    $labels = array(
        'family' => 'เมนูครอบครัว',
        'admin' => 'เมนูแอดมิน',
    );
    $blueprints = array();
    foreach (array('family', 'admin') as $role) {
        $config = isset($configs[$role]) ? $configs[$role] : line_normalize_menu_config($role, array());
        $buttons = array();
        foreach ($config['slots'] as $slotKey) {
            if (!isset($catalog[$slotKey])) {
                continue;
            }
            $buttons[] = array(
                'key' => $slotKey,
                'label' => $catalog[$slotKey]['label'],
                'uri' => $catalog[$slotKey]['uri'],
            );
        }
        $blueprints[$role] = array(
            'name' => $role . '-menu',
            'chatBarText' => isset($labels[$role]) ? $labels[$role] : 'เมนู LINE',
            'image' => line_menu_image_for($role, $config['layout']),
            'layout' => (string)$config['layout'],
            'buttons' => $buttons,
        );
    }
    return $blueprints;
}

function line_area_specs($layout) {
    if ((string)$layout === '3') {
        return array(
            array('x' => 0, 'y' => 0, 'width' => 833, 'height' => 1686),
            array('x' => 833, 'y' => 0, 'width' => 834, 'height' => 1686),
            array('x' => 1667, 'y' => 0, 'width' => 833, 'height' => 1686),
        );
    }
    if ((string)$layout === '4') {
        return array(
            array('x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843),
            array('x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843),
            array('x' => 0, 'y' => 843, 'width' => 1250, 'height' => 843),
            array('x' => 1250, 'y' => 843, 'width' => 1250, 'height' => 843),
        );
    }
    return array(
        array('x' => 0, 'y' => 0, 'width' => 833, 'height' => 843),
        array('x' => 833, 'y' => 0, 'width' => 834, 'height' => 843),
        array('x' => 1667, 'y' => 0, 'width' => 833, 'height' => 843),
        array('x' => 0, 'y' => 843, 'width' => 833, 'height' => 843),
        array('x' => 833, 'y' => 843, 'width' => 834, 'height' => 843),
        array('x' => 1667, 'y' => 843, 'width' => 833, 'height' => 843),
    );
}

function line_menu_definitions() {
    $blueprints = line_menu_blueprints();
    $definitions = array();

    foreach ($blueprints as $role => $blueprint) {
        $areas = array();
        $specs = line_area_specs($blueprint['layout']);
        foreach ($blueprint['buttons'] as $index => $button) {
            if (!isset($specs[$index])) {
                continue;
            }
            $areas[] = array(
                'bounds' => $specs[$index],
                'action' => array(
                    'type' => 'uri',
                    'label' => $button['label'],
                    'uri' => $button['uri']
                )
            );
        }
        $definitions[$role] = array(
            'name' => $blueprint['name'],
            'chatBarText' => $blueprint['chatBarText'],
            'image' => $blueprint['image'],
            'size' => array('width' => 2500, 'height' => 1686),
            'areas' => $areas,
        );
    }

    return $definitions;
}

function line_role_button_labels($role) {
    $role = line_menu_role($role);
    $blueprints = line_menu_blueprints();
    if (!isset($blueprints[$role]['buttons'])) {
        return array();
    }
    $labels = array();
    foreach ($blueprints[$role]['buttons'] as $button) {
        $labels[] = (string)$button['label'];
    }
    return $labels;
}

function line_role_button_count($role) {
    return count(line_role_button_labels($role));
}

function line_verify_signature($body, $signature) {
    $expected = base64_encode(hash_hmac('sha256', (string)$body, LINE_CHANNEL_SECRET, true));
    return hash_equals($expected, (string)$signature);
}

function line_api_base_url($path) {
    if (preg_match('#^/v2/bot/richmenu/[^/]+/content$#', (string)$path)) {
        return 'https://api-data.line.me';
    }
    return 'https://api.line.me';
}

function line_ca_file_candidates() {
    $items = array();
    $items[] = __DIR__ . '/cacert.pem';
    $curlCainfo = trim((string)ini_get('curl.cainfo'));
    if ($curlCainfo !== '') {
        $items[] = $curlCainfo;
    }
    $opensslCafile = trim((string)ini_get('openssl.cafile'));
    if ($opensslCafile !== '') {
        $items[] = $opensslCafile;
    }
    $unique = array();
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item !== '' && is_file($item)) {
            $unique[$item] = $item;
        }
    }
    return array_values($unique);
}

function line_api_request($method, $path, $body = null, $headers = array(), $contentType = 'application/json') {
    $url = line_api_base_url($path) . $path;
    $method = strtoupper((string)$method);

    $baseHeaders = array(
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN,
        'User-Agent: IceDeliveryLineBot/1.0'
    );
    if ($contentType !== '') {
        $baseHeaders[] = 'Content-Type: ' . $contentType;
    }
    foreach ($headers as $header) {
        $baseHeaders[] = $header;
    }

    $caCandidates = line_ca_file_candidates();
    $caFile = !empty($caCandidates) ? $caCandidates[0] : '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $baseHeaders);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($caFile !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, $caFile);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        return array(
            'ok' => $curlError === '' && $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'error' => $curlError,
            'body' => is_string($response) ? $response : '',
            'json' => json_decode((string)$response, true)
        );
    }

    if (!ini_get('allow_url_fopen')) {
        return array(
            'ok' => false,
            'status' => 0,
            'error' => 'cURL is not enabled and allow_url_fopen is off',
            'body' => '',
            'json' => null
        );
    }

    $sslOpts = array(
        'verify_peer' => true,
        'verify_peer_name' => true,
    );
    if ($caFile !== '') {
        $sslOpts['cafile'] = $caFile;
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => $method,
            'header' => implode("\r\n", $baseHeaders) . "\r\n",
            'content' => $body !== null ? (string)$body : '',
            'ignore_errors' => true,
            'timeout' => 25,
        ),
        'ssl' => $sslOpts,
    ));

    $response = '';
    $rawHeaders = array();
    $httpCode = 0;
    $error = '';

    $fp = @fopen($url, 'rb', false, $context);
    if ($fp === false) {
        $error = 'HTTP request failed without cURL';
        if (function_exists('http_get_last_response_headers')) {
            $tmpHeaders = http_get_last_response_headers();
            if (is_array($tmpHeaders)) {
                $rawHeaders = $tmpHeaders;
            }
        }
    } else {
        $meta = stream_get_meta_data($fp);
        if (!empty($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
            $rawHeaders = $meta['wrapper_data'];
        }
        $streamBody = stream_get_contents($fp);
        if ($streamBody !== false) {
            $response = $streamBody;
        }
        fclose($fp);
    }

    if (!empty($rawHeaders) && preg_match('/\s(\d{3})\s/', $rawHeaders[0], $m)) {
        $httpCode = (int)$m[1];
    }

    return array(
        'ok' => $error === '' && $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'error' => $error,
        'body' => $response,
        'json' => json_decode((string)$response, true)
    );
}

function line_db_escape($value) {
    global $conn;
    return mysqli_real_escape_string($conn, (string)$value);
}

function line_profile($userId) {
    $userId = trim((string)$userId);
    if ($userId === '') {
        return null;
    }
    $res = line_api_request('GET', '/v2/bot/profile/' . rawurlencode($userId), null, array(), '');
    if (!empty($res['ok']) && is_array($res['json'])) {
        return $res['json'];
    }
    return null;
}

function line_upsert_user($lineUserId, $displayName, $role, $eventType) {
    global $conn;

    $lineUserId = trim((string)$lineUserId);
    if ($lineUserId === '') {
        return false;
    }

    $displayName = trim((string)$displayName);
    $role = line_normalize_role($role);
    $eventType = trim((string)$eventType);
    $now = line_now();

    $userEsc = line_db_escape($lineUserId);
    $nameEsc = line_db_escape($displayName);
    $roleEsc = line_db_escape($role);
    $eventEsc = line_db_escape($eventType);
    $nowEsc = line_db_escape($now);

    $sql = "
        INSERT INTO line_users(line_user_id, display_name, role, is_active, created_at, updated_at, last_seen_at, last_event_type)
        VALUES('{$userEsc}', '{$nameEsc}', '{$roleEsc}', 1, '{$nowEsc}', '{$nowEsc}', '{$nowEsc}', '{$eventEsc}')
        ON DUPLICATE KEY UPDATE
            display_name = CASE WHEN '{$nameEsc}' <> '' THEN '{$nameEsc}' ELSE display_name END,
            role = CASE WHEN role IS NULL OR role = '' THEN '{$roleEsc}' ELSE role END,
            is_active = 1,
            updated_at = '{$nowEsc}',
            last_seen_at = '{$nowEsc}',
            last_event_type = '{$eventEsc}'
    ";

    return @mysqli_query($conn, $sql);
}

function line_find_role($lineUserId) {
    global $conn;
    $userEsc = line_db_escape($lineUserId);
    $sql = "SELECT role FROM line_users WHERE line_user_id = '{$userEsc}' LIMIT 1";
    $res = @mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return line_normalize_role(isset($row['role']) ? $row['role'] : 'family');
    }
    return 'family';
}

function line_get_richmenu_id_by_role($role) {
    global $conn;
    $roleEsc = line_db_escape(line_menu_role($role));
    $sql = "SELECT richmenu_id FROM line_richmenus WHERE role = '{$roleEsc}' LIMIT 1";
    $res = @mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return trim((string)$row['richmenu_id']);
    }
    return '';
}

function line_assign_richmenu($lineUserId, $richMenuId) {
    $lineUserId = trim((string)$lineUserId);
    $richMenuId = trim((string)$richMenuId);
    if ($lineUserId === '' || $richMenuId === '') {
        return array('ok' => false, 'status' => 0, 'body' => 'missing user or rich menu');
    }
    return line_api_request('POST', '/v2/bot/user/' . rawurlencode($lineUserId) . '/richmenu/' . rawurlencode($richMenuId), '', array(), '');
}

function line_reply_text($replyToken, $text) {
    $replyToken = trim((string)$replyToken);
    $text = trim((string)$text);
    if ($replyToken === '' || $text === '') {
        return array('ok' => false, 'status' => 0, 'body' => 'missing reply token or text');
    }

    $payload = array(
        'replyToken' => $replyToken,
        'messages' => array(
            array('type' => 'text', 'text' => $text)
        )
    );

    return line_api_request('POST', '/v2/bot/message/reply', json_encode($payload, JSON_UNESCAPED_UNICODE));
}

function line_store_processed_event($webhookEventId, $eventType, $lineUserId) {
    global $conn;
    $eventId = trim((string)$webhookEventId);
    if ($eventId === '') {
        return true;
    }

    $eventEsc = line_db_escape($eventId);
    $typeEsc = line_db_escape($eventType);
    $userEsc = line_db_escape($lineUserId);
    $now = line_db_escape(line_now());

    $sql = "INSERT IGNORE INTO line_webhook_events (webhook_event_id, event_type, line_user_id, created_at)
            VALUES ('{$eventEsc}', '{$typeEsc}', '{$userEsc}', '{$now}')";
    @mysqli_query($conn, $sql);

    return mysqli_affected_rows($conn) > 0;
}

function line_sync_user_menu($lineUserId) {
    $role = line_find_role($lineUserId);
    $richMenuId = line_get_richmenu_id_by_role($role);
    if ($richMenuId === '') {
        return array('ok' => false, 'status' => 0, 'body' => 'no rich menu mapped for role ' . $role);
    }
    return line_assign_richmenu($lineUserId, $richMenuId);
}

function line_require_setup_key() {
    $key = isset($_GET['key']) ? (string)$_GET['key'] : '';
    if ($key !== LINE_SETUP_SECRET_KEY) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'forbidden';
        exit;
    }
}

function line_save_role_menu($role, $richMenuId) {
    global $conn;
    $roleEsc = line_db_escape($role);
    $idEsc = line_db_escape($richMenuId);
    $now = line_db_escape(line_now());

    $sql = "
        INSERT INTO line_richmenus (role, richmenu_id, updated_at)
        VALUES ('{$roleEsc}', '{$idEsc}', '{$now}')
        ON DUPLICATE KEY UPDATE richmenu_id = '{$idEsc}', updated_at = '{$now}'
    ";
    return @mysqli_query($conn, $sql);
}

function line_detect_image_content_type($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return 'image/jpeg';
    }
    return 'image/png';
}

function line_is_placeholder_value($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return true;
    }
    $placeholders = array(
        'PUT_YOUR_CHANNEL_ACCESS_TOKEN_HERE',
        'PUT_YOUR_CHANNEL_SECRET_HERE',
        'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY',
        'ใส่ของจริง',
        'ใส่ token ใหม่ของ Messaging API',
        'ใส่ secret ใหม่ของ Messaging API'
    );
    return in_array($value, $placeholders, true);
}

function line_config_ready() {
    return !line_is_placeholder_value(LINE_CHANNEL_ACCESS_TOKEN) && !line_is_placeholder_value(LINE_CHANNEL_SECRET);
}

function line_api_error_text($res) {
    if (!is_array($res)) {
        return 'unknown error';
    }
    if (!empty($res['error'])) {
        return (string)$res['error'];
    }
    if (!empty($res['json']) && is_array($res['json'])) {
        if (!empty($res['json']['message'])) {
            return (string)$res['json']['message'];
        }
        return json_encode($res['json'], JSON_UNESCAPED_UNICODE);
    }
    if (isset($res['body']) && trim((string)$res['body']) !== '') {
        return trim((string)$res['body']);
    }
    if (isset($res['status']) && (int)$res['status'] > 0) {
        return 'HTTP ' . (int)$res['status'];
    }
    return 'unknown error';
}

function line_delete_richmenu($richMenuId) {
    $richMenuId = trim((string)$richMenuId);
    if ($richMenuId === '') {
        return array('ok' => false, 'status' => 0, 'body' => 'missing rich menu id');
    }
    return line_api_request('DELETE', '/v2/bot/richmenu/' . rawurlencode($richMenuId), null, array(), '');
}

function line_create_richmenu($role, $definition) {
    $payload = array(
        'size' => $definition['size'],
        'selected' => false,
        'name' => $definition['name'],
        'chatBarText' => $definition['chatBarText'],
        'areas' => $definition['areas']
    );

    $createRes = line_api_request('POST', '/v2/bot/richmenu', json_encode($payload, JSON_UNESCAPED_UNICODE));
    if (empty($createRes['ok'])) {
        return $createRes;
    }

    $richMenuId = isset($createRes['json']['richMenuId']) ? (string)$createRes['json']['richMenuId'] : '';
    if ($richMenuId === '') {
        return array('ok' => false, 'status' => (int)$createRes['status'], 'body' => 'missing richMenuId', 'json' => $createRes['json']);
    }

    $imagePath = $definition['image'];
    if (!is_file($imagePath)) {
        return array('ok' => false, 'status' => 0, 'body' => 'image not found: ' . $imagePath, 'richMenuId' => $richMenuId);
    }

    $prepared = line_prepare_menu_image_binary($imagePath);
    if (empty($prepared['ok'])) {
        return array('ok' => false, 'status' => 0, 'body' => isset($prepared['message']) ? $prepared['message'] : 'prepare image failed', 'richMenuId' => $richMenuId);
    }

    $binary = isset($prepared['binary']) ? $prepared['binary'] : '';
    $contentType = !empty($prepared['content_type']) ? $prepared['content_type'] : 'image/jpeg';
    $uploadRes = line_api_request('POST', '/v2/bot/richmenu/' . rawurlencode($richMenuId) . '/content', $binary, array(), $contentType);

    if (empty($uploadRes['ok'])) {
        line_delete_richmenu($richMenuId);
        return array(
            'ok' => false,
            'status' => isset($uploadRes['status']) ? (int)$uploadRes['status'] : 0,
            'body' => isset($uploadRes['body']) ? $uploadRes['body'] : '',
            'error' => isset($uploadRes['error']) ? $uploadRes['error'] : '',
            'json' => isset($uploadRes['json']) ? $uploadRes['json'] : null,
            'richMenuId' => $richMenuId,
        );
    }

    line_save_role_menu($role, $richMenuId);

    return array(
        'ok' => true,
        'status' => 200,
        'body' => 'created',
        'richMenuId' => $richMenuId,
    );
}

function line_setup_all_richmenus($reset) {
    $items = array();
    if (!line_config_ready()) {
        return array('ok' => false, 'message' => 'ยังไม่ได้ตั้งค่า LINE_CHANNEL_ACCESS_TOKEN หรือ LINE_CHANNEL_SECRET', 'items' => $items);
    }

    $definitions = line_menu_definitions();
    $overallOk = true;

    foreach ($definitions as $role => $definition) {
        $detail = array('role' => $role, 'ok' => false, 'status' => 0, 'message' => '', 'richMenuId' => '');

        if ($reset) {
            $oldId = line_get_richmenu_id_by_role($role);
            if ($oldId !== '') {
                line_delete_richmenu($oldId);
                line_save_role_menu($role, '');
            }
        }

        $res = line_create_richmenu($role, $definition);
        $detail['ok'] = !empty($res['ok']);
        $detail['status'] = isset($res['status']) ? (int)$res['status'] : 0;
        $detail['richMenuId'] = !empty($res['richMenuId']) ? (string)$res['richMenuId'] : '';
        $detail['message'] = $detail['ok'] ? 'สร้างเมนูสำเร็จ' : line_api_error_text($res);
        $items[] = $detail;
        if (!$detail['ok']) {
            $overallOk = false;
        }
    }

    return array(
        'ok' => $overallOk,
        'message' => $overallOk ? 'สร้าง rich menu ครบแล้ว' : 'สร้าง rich menu ได้ไม่ครบ',
        'items' => $items,
    );
}

function line_sync_all_active_users() {
    global $conn;
    $items = array();
    $overallOk = true;

    if (!line_config_ready()) {
        return array('ok' => false, 'message' => 'ยังไม่ได้ตั้งค่า LINE token/secret', 'items' => $items);
    }

    $res = @mysqli_query($conn, "SELECT line_user_id, role, display_name FROM line_users WHERE is_active = 1 ORDER BY id ASC");
    if (!$res) {
        return array('ok' => false, 'message' => 'อ่านรายชื่อผู้ใช้ LINE ไม่สำเร็จ', 'items' => $items);
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $sync = line_sync_user_menu($row['line_user_id']);
        $ok = !empty($sync['ok']);
        $items[] = array(
            'line_user_id' => $row['line_user_id'],
            'display_name' => $row['display_name'],
            'role' => $row['role'],
            'ok' => $ok,
            'status' => isset($sync['status']) ? (int)$sync['status'] : 0,
            'message' => $ok ? 'sync สำเร็จ' : line_api_error_text($sync),
        );
        if (!$ok) {
            $overallOk = false;
        }
    }

    return array(
        'ok' => $overallOk,
        'message' => $overallOk ? 'sync rich menu ให้ทุกคนแล้ว' : 'sync rich menu ได้บางคน',
        'items' => $items,
    );
}

function line_role_menu_status_rows() {
    $roles = array('family', 'admin');
    $rows = array();
    foreach ($roles as $role) {
        $menuRole = line_menu_role($role);
        $rows[] = array(
            'role' => $role,
            'menu_role' => $menuRole,
            'richmenu_id' => line_get_richmenu_id_by_role($role),
            'effective_richmenu_id' => line_get_richmenu_id_by_role($menuRole),
            'button_count' => line_role_button_count($role),
            'buttons_text' => implode(' • ', line_role_button_labels($role)),
        );
    }
    return $rows;
}
?>
