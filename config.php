<?php
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('Asia/Bangkok');
if (ob_get_level() === 0) {
    if (!headers_sent() && extension_loaded('zlib')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
if (!headers_sent() && session_id() === '') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
    }
    @ini_set('session.use_strict_mode', '1');
}
if (session_id() === '') {
    @session_start();
}

require_once __DIR__ . '/config.local.php';
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$conn) {
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8');
mysqli_report(MYSQLI_REPORT_OFF);

define('DEFAULT_ENABLE_MAP_FEATURES', false);
define('DEFAULT_ENABLE_DELIVERY_ROUNDS', true);
define('DEFAULT_ENABLE_ROUTE_LABELS', false);
define('DEFAULT_ENABLE_DELIVERY_ORDER', true);
define('DEFAULT_ENABLE_QUICK_TOOLS', true);

if (!defined('LINE_REPORT_SHARE_LIFF_ID')) {
    define('LINE_REPORT_SHARE_LIFF_ID', '');
}

if (!defined('ICE_SCHEMA_VERSION')) {
    define('ICE_SCHEMA_VERSION', '2026-04-05-2');
}

function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function fetch_all_rows($result) {
    $rows = array();
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function column_exists($conn, $table, $column) {
    static $cache = array();
    $table = (string)$table;
    $column = (string)$column;
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $table_sql = mysqli_real_escape_string($conn, $table);
    $column_sql = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$table_sql}` LIKE '{$column_sql}'");
    $cache[$key] = $res && mysqli_num_rows($res) > 0;
    return $cache[$key];
}

function index_exists($conn, $table, $index_name) {
    static $cache = array();
    $table = (string)$table;
    $index_name = (string)$index_name;
    $key = $table . '.' . $index_name;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $table_sql = mysqli_real_escape_string($conn, $table);
    $index_sql = mysqli_real_escape_string($conn, $index_name);
    $res = mysqli_query($conn, "SHOW INDEX FROM `{$table_sql}` WHERE Key_name = '{$index_sql}'");
    $cache[$key] = $res && mysqli_num_rows($res) > 0;
    return $cache[$key];
}

function table_exists($conn, $table) {
    static $cache = array();
    $table = (string)$table;
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $table_sql = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table_sql}'");
    $cache[$table] = $res && mysqli_num_rows($res) > 0;
    return $cache[$table];
}


function now_datetime() {
    return date('Y-m-d H:i:s');
}

function session_cache_get($key, $ttl = 60) {
    if (!isset($_SESSION['_ice_cache']) || !is_array($_SESSION['_ice_cache'])) {
        return null;
    }
    if (!isset($_SESSION['_ice_cache'][$key]) || !is_array($_SESSION['_ice_cache'][$key])) {
        return null;
    }
    $entry = $_SESSION['_ice_cache'][$key];
    $savedAt = isset($entry['time']) ? (int)$entry['time'] : 0;
    if ($savedAt <= 0 || (time() - $savedAt) > $ttl) {
        unset($_SESSION['_ice_cache'][$key]);
        return null;
    }
    return array_key_exists('value', $entry) ? $entry['value'] : null;
}

function session_cache_set($key, $value) {
    if (!isset($_SESSION['_ice_cache']) || !is_array($_SESSION['_ice_cache'])) {
        $_SESSION['_ice_cache'] = array();
    }
    $_SESSION['_ice_cache'][$key] = array('time' => time(), 'value' => $value);
    return $value;
}

function session_cache_delete($key) {
    if (isset($_SESSION['_ice_cache'][$key])) {
        unset($_SESSION['_ice_cache'][$key]);
    }
}


function csrf_token() {
    if (empty($_SESSION['ice_csrf_token']) || !is_string($_SESSION['ice_csrf_token'])) {
        $_SESSION['ice_csrf_token'] = function_exists('random_bytes') ? bin2hex(random_bytes(16)) : md5(uniqid((string)mt_rand(), true));
    }
    return $_SESSION['ice_csrf_token'];
}

function csrf_input($name = 'csrf_token') {
    return '<input type="hidden" name="' . h($name) . '" value="' . h(csrf_token()) . '">';
}

function csrf_validate($token = null) {
    if ($token === null) {
        $token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    }
    $sessionToken = isset($_SESSION['ice_csrf_token']) ? (string)$_SESSION['ice_csrf_token'] : '';
    if ($token === '' || $sessionToken === '') {
        return false;
    }
    if (function_exists('hash_equals')) {
        return hash_equals($sessionToken, $token);
    }
    if (strlen($token) !== strlen($sessionToken)) {
        return false;
    }
    $result = 0;
    for ($i = 0; $i < strlen($token); $i++) {
        $result |= ord($token[$i]) ^ ord($sessionToken[$i]);
    }
    return $result === 0;
}

function admin_password_hash($password) {
    $password = (string)$password;
    if (function_exists('password_hash')) {
        return 'bcrypt:' . password_hash($password, PASSWORD_BCRYPT);
    }
    return 'sha1:' . sha1($password);
}

function admin_password_verify($password, $storedHash) {
    $password = (string)$password;
    $storedHash = (string)$storedHash;

    if (strpos($storedHash, 'bcrypt:') === 0 && function_exists('password_verify')) {
        return password_verify($password, substr($storedHash, 7));
    }
    if (strpos($storedHash, 'sha1:') === 0) {
        return sha1($password) === substr($storedHash, 5);
    }
    if (strpos($storedHash, '$2y$') === 0 && function_exists('password_verify')) {
        return password_verify($password, $storedHash);
    }
    return sha1($password) === $storedHash;
}

function line_normalize_role($role) {
    $role = strtolower(trim((string)$role));
    if (in_array($role, array('customer', 'driver', 'manager', 'family'), true)) {
        return 'family';
    }
    if ($role === 'admin') {
        return 'admin';
    }
    return 'family';
}

function line_menu_role($role) {
    return line_normalize_role($role);
}

function line_role_labels() {
    return array(
        'family' => 'ครอบครัว',
        'admin' => 'แอดมิน'
    );
}

function line_role_label_th($role) {
    $labels = line_role_labels();
    $role = line_normalize_role($role);
    return isset($labels[$role]) ? $labels[$role] : 'ไม่ระบุ';
}

function line_role_badge_class($role) {
    $role = line_normalize_role($role);
    if ($role === 'admin') {
        return 'dark';
    }
    return 'info';
}

function ensure_schema_updates($conn) {
    if (!column_exists($conn, 'customers', 'preferred_round')) {
        @mysqli_query($conn, "ALTER TABLE customers ADD preferred_round VARCHAR(20) NOT NULL DEFAULT 'r1'");
    }
    if (!column_exists($conn, 'orders', 'order_period')) {
        @mysqli_query($conn, "ALTER TABLE orders ADD order_period VARCHAR(20) NOT NULL DEFAULT 'r1' AFTER order_date");
        @mysqli_query($conn, "UPDATE orders SET order_period='r1' WHERE order_period='' OR order_period IS NULL OR order_period='all_day'");
    }
    if (!column_exists($conn, 'customers', 'ice_types')) {
        @mysqli_query($conn, "ALTER TABLE customers ADD ice_types VARCHAR(100) DEFAULT 'big,small,crush,pack'");
    }
    if (!column_exists($conn, 'customers', 'map_url')) {
        @mysqli_query($conn, "ALTER TABLE customers ADD map_url TEXT NULL");
    }
    if (!column_exists($conn, 'customers', 'note_text')) {
        @mysqli_query($conn, "ALTER TABLE customers ADD note_text VARCHAR(255) DEFAULT NULL");
    }
    if (!column_exists($conn, 'customers', 'phone')) {
        @mysqli_query($conn, "ALTER TABLE customers ADD phone VARCHAR(30) DEFAULT NULL");
    }
    if (!column_exists($conn, 'orders', 'delivery_note')) {
        @mysqli_query($conn, "ALTER TABLE orders ADD delivery_note VARCHAR(255) DEFAULT NULL");
    }
    if (!column_exists($conn, 'customers', 'delivery_point_url')) {
        @mysqli_query($conn, "ALTER TABLE customers ADD delivery_point_url TEXT NULL");
    }
    if (!column_exists($conn, 'customers', 'delivery_point_updated_at')) {
        @mysqli_query($conn, "ALTER TABLE customers ADD delivery_point_updated_at DATETIME NULL");
    }


    $preferredRoundMeta = @mysqli_fetch_assoc(@mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'preferred_round'"));
    if ($preferredRoundMeta && ((string)$preferredRoundMeta['Null'] !== 'NO' || (string)$preferredRoundMeta['Default'] !== 'r1')) {
        @mysqli_query($conn, "ALTER TABLE customers MODIFY preferred_round VARCHAR(20) NOT NULL DEFAULT 'r1'");
    }
    $orderPeriodMeta = @mysqli_fetch_assoc(@mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'order_period'"));
    if ($orderPeriodMeta && ((string)$orderPeriodMeta['Null'] !== 'NO' || (string)$orderPeriodMeta['Default'] !== 'r1')) {
        @mysqli_query($conn, "ALTER TABLE orders MODIFY order_period VARCHAR(20) NOT NULL DEFAULT 'r1'");
    }
    $iceTypesMeta = @mysqli_fetch_assoc(@mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'ice_types'"));
    if ($iceTypesMeta && (string)$iceTypesMeta['Default'] !== 'big,small,crush,pack') {
        @mysqli_query($conn, "ALTER TABLE customers MODIFY ice_types VARCHAR(100) DEFAULT 'big,small,crush,pack'");
    }


    if (!index_exists($conn, 'customers', 'idx_customers_round_route')) {
        @mysqli_query($conn, "CREATE INDEX idx_customers_round_route ON customers(preferred_round, route, route_order)");
    }
    if (!index_exists($conn, 'orders', 'idx_orders_date_period')) {
        @mysqli_query($conn, "CREATE INDEX idx_orders_date_period ON orders(order_date, order_period)");
    }
    if (!index_exists($conn, 'orders', 'idx_orders_date_customer')) {
        @mysqli_query($conn, "CREATE INDEX idx_orders_date_customer ON orders(order_date, customer_id)");
    }

    if (!table_exists($conn, 'app_settings')) {
        @mysqli_query($conn, "CREATE TABLE app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(50) NOT NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    @mysqli_query($conn, "INSERT IGNORE INTO app_settings(setting_key, setting_value, updated_at) VALUES
        ('enable_map_features', '0', NOW()),
        ('enable_delivery_rounds', '0', NOW()),
        ('enable_route_labels', '0', NOW()),
        ('enable_delivery_order', '1', NOW()),
        ('enable_quick_tools', '1', NOW())");

    if (!table_exists($conn, 'admin_users')) {
        @mysqli_query($conn, "CREATE TABLE admin_users (
            id INT(11) NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) DEFAULT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            last_login_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admin_username (username),
            KEY idx_admin_role_active (role, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    if (!table_exists($conn, 'activity_logs')) {
        @mysqli_query($conn, "CREATE TABLE activity_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            actor_type VARCHAR(20) DEFAULT NULL,
            actor_name VARCHAR(100) DEFAULT NULL,
            action_key VARCHAR(100) DEFAULT NULL,
            details TEXT,
            created_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_activity_created (created_at),
            KEY idx_activity_actor (actor_type, actor_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    if (!table_exists($conn, 'line_users')) {
        @mysqli_query($conn, "CREATE TABLE line_users (
            id INT(11) NOT NULL AUTO_INCREMENT,
            line_user_id VARCHAR(50) NOT NULL,
            display_name VARCHAR(150) DEFAULT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'family',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            last_event_type VARCHAR(30) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_line_user_id (line_user_id),
            KEY idx_line_role_active (role, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    if (!table_exists($conn, 'line_richmenus')) {
        @mysqli_query($conn, "CREATE TABLE line_richmenus (
            role VARCHAR(20) NOT NULL,
            richmenu_id VARCHAR(100) NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    if (!table_exists($conn, 'line_webhook_events')) {
        @mysqli_query($conn, "CREATE TABLE line_webhook_events (
            id INT(11) NOT NULL AUTO_INCREMENT,
            webhook_event_id VARCHAR(100) NOT NULL,
            event_type VARCHAR(30) DEFAULT NULL,
            line_user_id VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_webhook_event_id (webhook_event_id),
            KEY idx_webhook_line_user (line_user_id),
            KEY idx_webhook_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    if (!table_exists($conn, 'line_menu_configs')) {
        @mysqli_query($conn, "CREATE TABLE line_menu_configs (
            role VARCHAR(20) NOT NULL,
            layout_count INT(11) NOT NULL DEFAULT 3,
            slots_json TEXT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    if (!table_exists($conn, 'last_prices')) {
        @mysqli_query($conn, "CREATE TABLE last_prices (
            customer_id INT(11) NOT NULL,
            ice_type VARCHAR(20) NOT NULL,
            price INT(11) NOT NULL DEFAULT 0,
            updated_at DATETIME NULL,
            PRIMARY KEY (customer_id, ice_type),
            KEY idx_last_prices_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    if (table_exists($conn, 'last_prices')) {
        if (!column_exists($conn, 'last_prices', 'updated_at')) {
            @mysqli_query($conn, "ALTER TABLE last_prices ADD updated_at DATETIME NULL");
        }
        @mysqli_query($conn, "UPDATE last_prices SET updated_at = NOW() WHERE updated_at IS NULL");
    }

    if (table_exists($conn, 'line_webhook_events') && column_exists($conn, 'line_webhook_events', 'created_at')) {
        @mysqli_query($conn, "DELETE FROM line_webhook_events WHERE created_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
    if (table_exists($conn, 'activity_logs') && column_exists($conn, 'activity_logs', 'created_at')) {
        @mysqli_query($conn, "DELETE FROM activity_logs WHERE created_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
    }

    if (table_exists($conn, 'line_richmenus')) {
        @mysqli_query($conn, "DELETE FROM line_richmenus WHERE role IN ('customer','driver','manager')");
        @mysqli_query($conn, "INSERT IGNORE INTO line_richmenus(role, richmenu_id, updated_at) VALUES
            ('family', '', NOW()),
            ('admin', '', NOW())");
    }

    if (table_exists($conn, 'line_menu_configs')) {
        @mysqli_query($conn, "DELETE FROM line_menu_configs WHERE role IN ('customer','driver','manager')");
        $defaultConfigs = array(
            'family' => array('layout_count' => 6, 'slots_json' => json_encode(array('order', 'driver', 'report', 'customers', 'summary_today', 'admin'))),
            'admin' => array('layout_count' => 6, 'slots_json' => json_encode(array('order', 'driver', 'report', 'customers', 'summary_today', 'admin'))),
        );
        foreach ($defaultConfigs as $seedRole => $seedConfig) {
            $seedRoleEsc = mysqli_real_escape_string($conn, $seedRole);
            $layoutCount = (int)$seedConfig['layout_count'];
            $slotsJsonEsc = mysqli_real_escape_string($conn, (string)$seedConfig['slots_json']);
            @mysqli_query($conn, "INSERT IGNORE INTO line_menu_configs(role, layout_count, slots_json, updated_at) VALUES('{$seedRoleEsc}', {$layoutCount}, '{$slotsJsonEsc}', NOW())");
        }
    }

    if (table_exists($conn, 'admin_users')) {
        $seedRes = @mysqli_query($conn, "SELECT COUNT(*) AS total FROM admin_users");
        $seedRow = $seedRes ? mysqli_fetch_assoc($seedRes) : array('total' => 0);
        $seedTotal = isset($seedRow['total']) ? (int)$seedRow['total'] : 0;
        if ($seedTotal === 0) {
            $username = mysqli_real_escape_string($conn, 'admin');
            $fullName = mysqli_real_escape_string($conn, 'ผู้ดูแลระบบ');
            $passwordHash = mysqli_real_escape_string($conn, admin_password_hash('Lucky1234'));
            $now = mysqli_real_escape_string($conn, now_datetime());
            @mysqli_query($conn, "INSERT INTO admin_users(username, password_hash, full_name, role, is_active, created_at, updated_at)
                VALUES('{$username}', '{$passwordHash}', '{$fullName}', 'admin', 1, '{$now}', '{$now}')");
        }
    }

    if (table_exists($conn, 'app_settings')) {
        $schemaVersionEsc = mysqli_real_escape_string($conn, ICE_SCHEMA_VERSION);
        @mysqli_query($conn, "INSERT INTO app_settings(setting_key, setting_value, updated_at) VALUES('ice_schema_version', '{$schemaVersionEsc}', NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=VALUES(updated_at)");
        session_cache_set('ice_schema_version', ICE_SCHEMA_VERSION);
    }
}
if (!defined('SKIP_SCHEMA_UPDATES') || SKIP_SCHEMA_UPDATES !== true) {
    $shouldRunSchemaUpdates = false;
    if (!table_exists($conn, 'app_settings')) {
        $shouldRunSchemaUpdates = true;
    } else {
        $currentSchemaVersion = session_cache_get('ice_schema_version', 60);
        if ($currentSchemaVersion === null) {
            $schemaRes = @mysqli_query($conn, "SELECT setting_value FROM app_settings WHERE setting_key='ice_schema_version' LIMIT 1");
            $schemaRow = $schemaRes ? mysqli_fetch_assoc($schemaRes) : null;
            $currentSchemaVersion = $schemaRow && isset($schemaRow['setting_value']) ? (string)$schemaRow['setting_value'] : '';
            session_cache_set('ice_schema_version', $currentSchemaVersion);
        }
        if ($currentSchemaVersion !== ICE_SCHEMA_VERSION) {
            $shouldRunSchemaUpdates = true;
        }
    }
    if ($shouldRunSchemaUpdates) {
        ensure_schema_updates($conn);
    }
}

function app_setting_defaults() {
    return array(
        'enable_map_features' => DEFAULT_ENABLE_MAP_FEATURES ? '1' : '0',
        'enable_delivery_rounds' => DEFAULT_ENABLE_DELIVERY_ROUNDS ? '1' : '0',
        'enable_route_labels' => DEFAULT_ENABLE_ROUTE_LABELS ? '1' : '0',
        'enable_delivery_order' => DEFAULT_ENABLE_DELIVERY_ORDER ? '1' : '0',
        'enable_quick_tools' => DEFAULT_ENABLE_QUICK_TOOLS ? '1' : '0',
    );
}

function get_app_settings($conn) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $sessionCached = session_cache_get('app_settings', 60);
    if (is_array($sessionCached)) {
        $cache = $sessionCached;
        return $cache;
    }
    $cache = app_setting_defaults();
    if (table_exists($conn, 'app_settings')) {
        $rows = fetch_all_rows(mysqli_query($conn, "SELECT setting_key, setting_value FROM app_settings"));
        foreach ($rows as $row) {
            $cache[$row['setting_key']] = (string)$row['setting_value'];
        }
    }
    session_cache_set('app_settings', $cache);
    return $cache;
}

function app_setting_enabled($conn, $key) {
    $settings = get_app_settings($conn);
    return isset($settings[$key]) && (string)$settings[$key] === '1';
}

function set_app_setting($conn, $key, $enabled) {
    $safe_key = mysqli_real_escape_string($conn, $key);
    $value = $enabled ? '1' : '0';
    $safe_value = mysqli_real_escape_string($conn, $value);
    @mysqli_query($conn, "INSERT INTO app_settings(setting_key, setting_value, updated_at) VALUES('{$safe_key}', '{$safe_value}', NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()");
    session_cache_delete('app_settings');
}


function maps_enabled() {
    global $conn;
    return app_setting_enabled($conn, 'enable_map_features');
}

function rounds_enabled() {
    global $conn;
    return app_setting_enabled($conn, 'enable_delivery_rounds');
}

function route_labels_enabled() {
    global $conn;
    return app_setting_enabled($conn, 'enable_route_labels');
}

function delivery_order_enabled() {
    global $conn;
    return app_setting_enabled($conn, 'enable_delivery_order');
}

function quick_tools_enabled() {
    global $conn;
    return app_setting_enabled($conn, 'enable_quick_tools');
}


function admin_user_roles() {
    return array(
        'admin' => 'แอดมิน'
    );
}

function admin_auth_redirect($path) {
    header('Location: ' . $path);
    exit;
}

function admin_current_user() {
    return isset($_SESSION['ice_admin_user']) && is_array($_SESSION['ice_admin_user']) ? $_SESSION['ice_admin_user'] : null;
}

function admin_is_logged_in() {
    $user = admin_current_user();
    return is_array($user) && !empty($user['id']);
}

function admin_login($username, $password, &$errorMessage) {
    global $conn;

    $username = trim((string)$username);
    $password = (string)$password;
    $errorMessage = '';

    if ($username === '' || $password === '') {
        $errorMessage = 'กรอกชื่อผู้ใช้และรหัสผ่านก่อน';
        return false;
    }

    $safeUsername = mysqli_real_escape_string($conn, $username);
    $sql = "SELECT * FROM admin_users WHERE username = '{$safeUsername}' AND is_active = 1 LIMIT 1";
    $res = @mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;

    if (!$row || !admin_password_verify($password, isset($row['password_hash']) ? $row['password_hash'] : '')) {
        $errorMessage = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        return false;
    }

    if (function_exists('session_regenerate_id')) {
        @session_regenerate_id(true);
    }

    $_SESSION['ice_admin_user'] = array(
        'id' => (int)$row['id'],
        'username' => (string)$row['username'],
        'full_name' => (string)$row['full_name'],
        'role' => (string)$row['role']
    );

    $id = (int)$row['id'];
    $now = mysqli_real_escape_string($conn, now_datetime());
    @mysqli_query($conn, "UPDATE admin_users SET last_login_at = '{$now}', updated_at = '{$now}' WHERE id = {$id} LIMIT 1");
    admin_log_action('admin_login', 'เข้าสู่ระบบหลังบ้าน');
    return true;
}

function admin_logout() {
    if (admin_is_logged_in()) {
        admin_log_action('admin_logout', 'ออกจากระบบหลังบ้าน');
    }
    unset($_SESSION['ice_admin_user']);
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        @setcookie(session_name(), '', time() - 42000, $params['path'], isset($params['domain']) ? $params['domain'] : '', !empty($params['secure']), !empty($params['httponly']));
    }
    if (session_id() !== '') {
        @session_destroy();
    }
}

function admin_require_login() {
    if (!admin_is_logged_in()) {
        admin_auth_redirect('login.php');
    }
}

function admin_update_own_password($userId, $currentPassword, $newPassword, &$errorMessage) {
    global $conn;

    $errorMessage = '';
    $userId = (int)$userId;
    $currentPassword = (string)$currentPassword;
    $newPassword = trim((string)$newPassword);

    if ($userId <= 0) {
        $errorMessage = 'ไม่พบบัญชีผู้ใช้';
        return false;
    }
    if ($newPassword === '' || strlen($newPassword) < 6) {
        $errorMessage = 'รหัสผ่านใหม่ต้องอย่างน้อย 6 ตัวอักษร';
        return false;
    }

    $res = @mysqli_query($conn, "SELECT password_hash FROM admin_users WHERE id = {$userId} LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row || !admin_password_verify($currentPassword, isset($row['password_hash']) ? $row['password_hash'] : '')) {
        $errorMessage = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        return false;
    }

    $hash = mysqli_real_escape_string($conn, admin_password_hash($newPassword));
    $now = mysqli_real_escape_string($conn, now_datetime());
    $ok = @mysqli_query($conn, "UPDATE admin_users SET password_hash = '{$hash}', updated_at = '{$now}' WHERE id = {$userId} LIMIT 1");
    if (!$ok) {
        $errorMessage = 'บันทึกรหัสผ่านใหม่ไม่สำเร็จ';
        return false;
    }

    admin_log_action('change_password', 'เปลี่ยนรหัสผ่านของตัวเอง');
    return true;
}

function admin_log_action($actionKey, $details) {
    global $conn;
    if (!table_exists($conn, 'activity_logs')) {
        return false;
    }

    $user = admin_current_user();
    $actorName = $user ? ((string)$user['username']) : 'system';
    $actorType = $user ? 'admin' : 'system';
    $actionEsc = mysqli_real_escape_string($conn, (string)$actionKey);
    $detailsEsc = mysqli_real_escape_string($conn, (string)$details);
    $actorNameEsc = mysqli_real_escape_string($conn, $actorName);
    $actorTypeEsc = mysqli_real_escape_string($conn, $actorType);
    $now = mysqli_real_escape_string($conn, now_datetime());

    return @mysqli_query($conn, "INSERT INTO activity_logs(actor_type, actor_name, action_key, details, created_at)
        VALUES('{$actorTypeEsc}', '{$actorNameEsc}', '{$actionEsc}', '{$detailsEsc}', '{$now}')");
}

function set_flash_message($type, $message) {
    $_SESSION['ice_flash_message'] = array(
        'type' => (string)$type,
        'message' => (string)$message
    );
}

function consume_flash_message() {
    if (!isset($_SESSION['ice_flash_message']) || !is_array($_SESSION['ice_flash_message'])) {
        return null;
    }
    $flash = $_SESSION['ice_flash_message'];
    unset($_SESSION['ice_flash_message']);
    return $flash;
}



function ice_types() {
    return array(
        'big'   => 'น้ำแข็งหลอดใหญ่',
        'small' => 'น้ำแข็งหลอดเล็ก',
        'crush' => 'น้ำแข็งป่น',
        'pack'  => 'น้ำแข็งซอง'
    );
}

function default_period_code() {
    return 'r1';
}

function order_periods() {
    return array(
        'r1' => 'รอบ 1',
        'r2' => 'รอบ 2',
        'r3' => 'รอบ 3',
        'r4' => 'รอบ 4',
        'r5' => 'รอบ 5',
        'r6' => 'รอบ 6',
        'r7' => 'รอบ 7'
    );
}

function customer_round_options() {
    if (!rounds_enabled()) {
        return array('all_day' => 'รวมทั้งวัน');
    }
    return order_periods();
}

function normalize_period($value) {
    if (!rounds_enabled()) {
        return 'all_day';
    }
    $periods = order_periods();
    return isset($periods[$value]) ? $value : default_period_code();
}

function normalize_customer_round($value) {
    if (trim((string)$value) === 'all_day') {
        return 'all_day';
    }
    $rounds = order_periods();
    return isset($rounds[$value]) ? $value : default_period_code();
}

function get_period_label($code) {
    $periods = order_periods();
    return isset($periods[$code]) ? $periods[$code] : (rounds_enabled() ? $code : 'รวมทั้งวัน');
}

function get_customer_round_label($code) {
    $rounds = customer_round_options();
    return isset($rounds[$code]) ? $rounds[$code] : (rounds_enabled() ? $code : 'รวมทั้งวัน');
}

function customer_matches_period($customer_round, $selected_period) {
    if (!rounds_enabled()) {
        return true;
    }
    $raw_customer_round = trim((string)$customer_round);
    if ($raw_customer_round === 'all_day') {
        return true;
    }
    $customer_round = normalize_customer_round($customer_round);
    $selected_period = normalize_period($selected_period);
    return $customer_round === $selected_period;
}

function customers_order_by_sql($prefix = '') {
    $prefix = $prefix !== '' ? rtrim($prefix, '.') . '.' : '';

    if (route_labels_enabled() && delivery_order_enabled()) {
        return $prefix . "route ASC, " . $prefix . "route_order ASC, " . $prefix . "name ASC";
    }
    if (delivery_order_enabled()) {
        return $prefix . "route_order ASC, " . $prefix . "name ASC, " . $prefix . "id ASC";
    }
    if (route_labels_enabled()) {
        return $prefix . "route ASC, " . $prefix . "name ASC, " . $prefix . "id ASC";
    }
    return $prefix . "name ASC, " . $prefix . "id ASC";
}


function customer_sort_sql($prefix = '') {
    return customers_order_by_sql($prefix);
}

function customer_sort_summary($route, $route_order, $fallback = 'เรียงตามชื่อร้าน') {
    $parts = array();
    if (route_labels_enabled()) {
        $parts[] = 'สาย ' . (int)$route;
    }
    if (delivery_order_enabled()) {
        $parts[] = 'ลำดับ ' . (int)$route_order;
    }
    return $parts ? implode(' • ', $parts) : $fallback;
}

function orders_period_order_sql($field = 'orders.order_period') {
    if (!rounds_enabled()) {
        return "''";
    }
    return "FIELD({$field}, 'r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'r7')";
}

function get_ice_name($code) {
    $types = ice_types();
    return isset($types[$code]) ? $types[$code] : $code;
}

function to_decimal($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }
    return round((float)$value, 1);
}

function to_int_val($value) {
    return (int)round((float)$value);
}

function sanitize_phone($value) {
    return preg_replace('/[^0-9+]/', '', (string)$value);
}

function normalize_map_input($value) {
    if (!maps_enabled()) {
        return '';
    }
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', $value)) {
        return 'https://maps.google.com/?q=' . str_replace(' ', '', $value);
    }
    return $value;
}

function coords_to_google_maps_url($lat, $lng) {
    if (!maps_enabled()) {
        return '';
    }
    $lat = trim((string)$lat);
    $lng = trim((string)$lng);
    if ($lat === '' || $lng === '') {
        return '';
    }
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
}

function customer_has_marked_point($customer) {
    if (!maps_enabled()) {
        return false;
    }
    return !empty($customer['delivery_point_url']) || !empty($customer['map_url']);
}

function customer_map_link($customer) {
    if (!maps_enabled()) {
        return '';
    }
    if (!empty($customer['delivery_point_url'])) {
        return trim((string)$customer['delivery_point_url']);
    }
    return isset($customer['map_url']) ? trim((string)$customer['map_url']) : '';
}

function customer_map_label($customer) {
    if (!empty($customer['delivery_point_url'])) {
        return '📌 จุดส่งจริง';
    }
    return '📍 แผนที่';
}

function parse_customer_ice_types_from_post($post_key = 'ice_types') {
    $types = ice_types();
    $selected = isset($_POST[$post_key]) && is_array($_POST[$post_key]) ? $_POST[$post_key] : array();
    $allowed = array();
    foreach ($selected as $code) {
        if (isset($types[$code])) {
            $allowed[] = $code;
        }
    }
    if (!$allowed) {
        $allowed = array_keys($types);
    }
    return implode(',', $allowed);
}

function customer_allowed_ice_types($customer) {
    $all = array_keys(ice_types());
    $raw = isset($customer['ice_types']) ? trim((string)$customer['ice_types']) : '';
    if ($raw === '') {
        return $all;
    }
    $parts = array();
    foreach (explode(',', $raw) as $code) {
        $code = trim($code);
        if (in_array($code, $all, true)) {
            $parts[] = $code;
        }
    }
    return $parts ? $parts : $all;
}
