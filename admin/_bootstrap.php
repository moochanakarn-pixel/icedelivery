<?php
$rootDir = dirname(__DIR__);
$lineBootstrap = $rootDir . '/line_bootstrap.php';
if (!is_file($lineBootstrap)) {
    $lineBootstrap = __DIR__ . '/line_bootstrap.php';
}
include_once $lineBootstrap;

function admin_nav_items() {
    return array(
        // หมวด: หน้าหลัก
        'overview' => array(
            'label' => 'หน้าหลัก',
            'items' => array(
                'index.php'         => array('icon' => 'grid',     'label' => 'ภาพรวม'),
                '../index.php'      => array('icon' => 'edit',     'label' => 'คีย์ออเดอร์'),
                '../report.php'     => array('icon' => 'chart',    'label' => 'รายงาน'),
            ),
        ),
        // หมวด: จัดการ
        'manage' => array(
            'label' => 'จัดการ',
            'items' => array(
                '../customers.php'  => array('icon' => 'users',    'label' => 'ลูกค้า'),
                '../driver.php'     => array('icon' => 'truck',    'label' => 'คนส่ง'),
                'admin_users.php'   => array('icon' => 'shield',   'label' => 'ผู้ดูแล'),
            ),
        ),
        // หมวด: LINE
        'line' => array(
            'label' => 'LINE',
            'items' => array(
                'line_richmenu.php' => array('icon' => 'menu',     'label' => 'Rich Menu'),
                'line_users.php'    => array('icon' => 'person',   'label' => 'สิทธิ์ผู้ใช้'),
            ),
        ),
        // หมวด: ระบบ
        'system' => array(
            'label' => 'ระบบ',
            'items' => array(
                'settings.php'      => array('icon' => 'settings', 'label' => 'ตั้งค่า'),
            ),
        ),
    );
}

function admin_nav_icon($name) {
    $icons = array(
        'grid'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'edit'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'chart'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>',
        'users'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'truck'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        'shield'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'menu'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
        'person'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'settings' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    );
    return isset($icons[$name]) ? $icons[$name] : '';
}

function admin_current_page_name() {
    return basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
}

function admin_render_header($title, $subtitle) {
    $user    = admin_current_user();
    $nav     = admin_nav_items();
    $current = admin_current_page_name();
    $flash   = consume_flash_message();
    // ใช้ strtoupper/substr แทน mb_ เพื่อรองรับ PHP ที่ไม่เปิด mbstring
    $initials = 'A';
    if ($user) {
        $name = $user['full_name'] !== '' ? $user['full_name'] : $user['username'];
        $name = trim((string)$name);
        $initials = $name !== '' ? strtoupper(substr($name, 0, 1)) : 'A';
        if ($initials === '') $initials = 'A';
    }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo h($title); ?> • Admin</title>
<?php $ab = is_file(dirname(__DIR__) . '/assets/admin.css') ? '../assets' : 'assets'; ?>
<link rel="stylesheet" href="<?php echo h($ab); ?>/admin.css?v=20260419">
</head>
<body>

<!-- ===== OVERLAY (mobile) ===== -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">❄</div>
    <div>
      <div class="sidebar-logo-title">ICE DELIVERY</div>
      <div class="sidebar-logo-sub">ระบบจัดการน้ำแข็ง</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($nav as $group): ?>
      <div class="nav-section"><?php echo h($group['label']); ?></div>
      <?php foreach ($group['items'] as $file => $info):
        $basename = basename((string)$file);
        $isActive = $basename === $current;
      ?>
        <a href="<?php echo h($file); ?>"
           class="nav-item<?php echo $isActive ? ' active' : ''; ?>"
           onclick="closeSidebar()">
          <span class="nav-icon"><?php echo admin_nav_icon($info['icon']); ?></span>
          <span class="nav-label"><?php echo h($info['label']); ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-bottom">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?php echo h($initials); ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?php echo h($user ? ($user['full_name'] !== '' ? $user['full_name'] : $user['username']) : ''); ?></div>
        <div class="sidebar-user-role"><?php echo h($user ? $user['role'] : ''); ?></div>
      </div>
    </div>
    <a href="logout.php" class="sidebar-logout">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      ออกจากระบบ
    </a>
  </div>
</aside>

<!-- ===== MAIN AREA ===== -->
<div class="admin-main" id="adminMain">

  <!-- TOPBAR -->
  <header class="admin-topbar">
    <div class="topbar-left">
      <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="เมนู">
        <span></span><span></span><span></span>
      </button>
      <div class="topbar-titles">
        <h1 class="topbar-title"><?php echo h($title); ?></h1>
        <p class="topbar-sub"><?php echo h($subtitle); ?></p>
      </div>
    </div>
    <div class="topbar-right">
      <span class="topbar-chip"><?php echo h($user ? ($user['full_name'] !== '' ? $user['full_name'] : $user['username']) : ''); ?></span>
    </div>
  </header>

  <!-- FLASH MESSAGE -->
  <?php if ($flash): ?>
    <div class="notice <?php echo h(isset($flash['type']) ? $flash['type'] : 'info'); ?>">
      <?php echo h(isset($flash['message']) ? $flash['message'] : ''); ?>
    </div>
  <?php endif; ?>

  <!-- CONTENT WRAPPER -->
  <div class="admin-content">
<?php
}

function admin_render_footer($note) {
?>
    <?php if ($note !== ''): ?>
      <p class="footer-note"><?php echo h($note); ?></p>
    <?php endif; ?>
  </div><!-- /.admin-content -->
</div><!-- /.admin-main -->

<script>
function toggleSidebar(){
  var s=document.getElementById('sidebar');
  var o=document.getElementById('sidebarOverlay');
  var open=s.classList.toggle('is-open');
  o.classList.toggle('is-open', open);
  document.body.classList.toggle('sidebar-open', open);
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('is-open');
  document.getElementById('sidebarOverlay').classList.remove('is-open');
  document.body.classList.remove('sidebar-open');
}
// ปิด sidebar เมื่อกด Escape
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeSidebar(); });
</script>
</body>
</html>
<?php
}
?>
