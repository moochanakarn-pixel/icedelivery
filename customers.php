<?php
include 'config.php';

$routeLabels   = route_labels_enabled();
$deliveryOrder = delivery_order_enabled();
$roundsEnabled = rounds_enabled();
$mapsEnabled   = maps_enabled();
$roundOptions  = customer_round_options();

$message      = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    if (!csrf_validate()) {
        $message = 'เซสชันหมดอายุ กรุณาลองใหม่';
        $message_type = 'error';
    } else {
        $name        = trim(isset($_POST['name'])         ? $_POST['name']         : '');
        $phone       = trim(isset($_POST['phone'])        ? $_POST['phone']        : '');
        $route       = $routeLabels   ? max(1, (int)(isset($_POST['route'])         ? $_POST['route']         : 1)) : 1;
        $routeOrder  = $deliveryOrder ? max(1, (int)(isset($_POST['route_order'])   ? $_POST['route_order']   : 1)) : 0;
        $prefRound   = $roundsEnabled ? normalize_customer_round(isset($_POST['preferred_round']) ? $_POST['preferred_round'] : default_period_code()) : default_period_code();
        $noteText    = trim(isset($_POST['note_text'])    ? $_POST['note_text']    : '');
        $mapUrl      = $mapsEnabled   ? normalize_map_input(isset($_POST['map_url']) ? $_POST['map_url'] : '') : '';

        if ($name === '') {
            $message      = 'กรุณากรอกชื่อลูกค้า';
            $message_type = 'error';
        } else {
            $nameEsc = mysqli_real_escape_string($conn, $name);
            $dupRes  = @mysqli_query($conn, "SELECT id FROM customers WHERE name='{$nameEsc}' LIMIT 1");
            if ($dupRes && mysqli_num_rows($dupRes) > 0) {
                $message      = 'มีลูกค้าชื่อ "' . h($name) . '" อยู่แล้ว';
                $message_type = 'error';
            } else {
                $phoneEsc = mysqli_real_escape_string($conn, $phone);
                $roundEsc = mysqli_real_escape_string($conn, $prefRound);
                $noteEsc  = mysqli_real_escape_string($conn, $noteText);
                $mapEsc   = mysqli_real_escape_string($conn, $mapUrl);
                @mysqli_query($conn, "INSERT INTO customers(name,phone,route,route_order,preferred_round,ice_types,map_url,note_text) VALUES('{$nameEsc}','{$phoneEsc}',{$route},{$routeOrder},'{$roundEsc}','big,small,crush,pack','{$mapEsc}','{$noteEsc}')");
                if (mysqli_errno($conn)) {
                    $message      = 'เพิ่มลูกค้าไม่สำเร็จ';
                    $message_type = 'error';
                } else {
                    $message = 'เพิ่มลูกค้า "' . h($name) . '" เรียบร้อยแล้ว';
                }
            }
        }
    }
}

$search = trim(isset($_GET['q']) ? (string)$_GET['q'] : '');
$whereSql = '';
if ($search !== '') {
    $safeLike = mysqli_real_escape_string($conn, str_replace(['\\','%','_'],['\\\\','\\%','\\_'], $search));
    $whereSql = "WHERE (name LIKE '%{$safeLike}%' ESCAPE '\\\\' OR phone LIKE '%{$safeLike}%' ESCAPE '\\\\')";
}
$rows     = fetch_all_rows(@mysqli_query($conn, "SELECT * FROM customers {$whereSql} ORDER BY " . customers_order_by_sql()));
$total    = (int)@mysqli_fetch_assoc(@mysqli_query($conn, "SELECT COUNT(*) AS c FROM customers"))['c'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>รายชื่อลูกค้า</title>
<link rel="stylesheet" href="assets/mobile.css">
<link rel="stylesheet" href="assets/app.css?v=20260623">
<meta name="theme-color" content="#0b7dda">
<meta name="apple-mobile-web-app-capable" content="yes">
<style>
*{box-sizing:border-box}
body{margin:0;background:#eef6ff;color:#15314a;font-family:Tahoma,Arial,sans-serif;font-size:17px;line-height:1.5}
.wrap{max-width:900px;margin:0 auto;padding:16px 12px 80px}
.app-nav{position:fixed;left:0;right:0;bottom:0;background:rgba(255,255,255,.97);backdrop-filter:blur(8px);border-top:1px solid #dfeaf6;display:flex;z-index:90;padding-bottom:env(safe-area-inset-bottom);box-shadow:0 -4px 16px rgba(0,0,0,.07)}
.app-nav a{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 4px;color:#7a96ae;font-size:11px;font-weight:700;text-decoration:none;gap:3px;transition:color .15s}
.app-nav a.active{color:#0b7dda}
.app-nav a svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.hero{background:linear-gradient(135deg,#0b7dda 0%,#1a9ee0 100%);color:#fff;border-radius:22px;padding:22px 20px 18px;margin-bottom:16px;box-shadow:0 8px 24px rgba(11,125,218,.22)}
.hero h1{margin:0 0 6px;font-size:26px;font-weight:800}
.hero p{margin:0;opacity:.88;font-size:15px}
.hero-stats{display:flex;gap:14px;margin-top:14px}
.hero-stat{background:rgba(255,255,255,.18);border-radius:12px;padding:8px 14px;text-align:center}
.hero-stat .num{font-size:24px;font-weight:bold}
.hero-stat .lbl{font-size:12px;opacity:.9;margin-top:2px}
.card{background:#fff;border-radius:18px;padding:18px;border:1px solid #dfeaf6;box-shadow:0 4px 14px rgba(0,0,0,.06);margin-bottom:14px}
.card h2{margin:0 0 14px;font-size:18px;color:#0b7dda;font-weight:700}
.notice{padding:13px 16px;border-radius:14px;margin-bottom:14px;font-weight:bold;font-size:15px}
.notice.success{background:#e9fff1;color:#0d7a3d;border:1px solid #a8efc8}
.notice.error{background:#fff0f0;color:#c0281f;border:1px solid #f5c6c6}
.field{margin-bottom:12px}
.field label{display:block;font-size:13px;font-weight:700;color:#55708a;margin-bottom:5px}
.input{width:100%;height:44px;border-radius:11px;border:1.5px solid #c7d8ea;padding:0 12px;font-size:16px;background:#fff;outline:none;transition:border-color .15s}
.input:focus{border-color:#0b7dda}
.select{width:100%;height:44px;border-radius:11px;border:1.5px solid #c7d8ea;padding:0 10px;font-size:16px;background:#fff}
.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.btn{display:inline-flex;align-items:center;justify-content:center;height:46px;padding:0 18px;border-radius:13px;border:none;font-size:16px;font-weight:700;cursor:pointer;transition:.15s}
.btn-primary{background:#0b7dda;color:#fff}
.btn-primary:active{background:#0963b3}
.btn-light{background:#eef7ff;color:#0b7dda;border:1px solid #cde0f6}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
.search-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end}
.clist{}
.citem{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #edf2f7}
.citem:last-child{border-bottom:0}
.cavatar{width:40px;height:40px;border-radius:12px;background:#dff0ff;color:#0b7dda;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:17px;flex-shrink:0}
.cinfo{flex:1;min-width:0}
.cname{font-weight:700;color:#17324d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cmeta{font-size:13px;color:#7a96ae;margin-top:2px}
.cbadge{display:inline-block;padding:2px 8px;border-radius:7px;font-size:12px;font-weight:700;background:#eef7ff;color:#0b7dda;margin-left:4px}
.empty{text-align:center;padding:28px;color:#8fadc6;font-size:16px}
.toggle-add{width:100%;height:46px;border-radius:13px;border:1.5px dashed #9ac3e8;background:#f5fbff;color:#0b7dda;font-size:16px;font-weight:700;cursor:pointer;margin-bottom:14px;transition:.15s}
.toggle-add:active{background:#ddf0ff}
.add-form{display:none}
.add-form.open{display:block}
</style>
</head>
<body>
<div class="wrap">

  <div class="hero">
    <h1>รายชื่อลูกค้า</h1>
    <p>ดูรายชื่อและเพิ่มลูกค้าได้จากหน้านี้ สำหรับแก้ไขหรือลบ ให้ไปที่หลังบ้าน Admin</p>
    <div class="hero-stats">
      <div class="hero-stat">
        <div class="num"><?php echo number_format($total); ?></div>
        <div class="lbl">ลูกค้าทั้งหมด</div>
      </div>
      <?php if ($search !== '') { ?>
      <div class="hero-stat">
        <div class="num"><?php echo number_format(count($rows)); ?></div>
        <div class="lbl">ผลค้นหา</div>
      </div>
      <?php } ?>
    </div>
  </div>

  <?php if ($message !== '') { ?>
    <div class="notice <?php echo h($message_type); ?>"><?php echo $message; ?></div>
  <?php } ?>

  <!-- ค้นหา -->
  <div class="card">
    <h2>ค้นหาลูกค้า</h2>
    <form method="get">
      <div class="search-row">
        <input type="text" name="q" class="input" value="<?php echo h($search); ?>" placeholder="ชื่อร้านหรือเบอร์โทร" autocomplete="off">
        <button type="submit" class="btn btn-primary">ค้นหา</button>
      </div>
    </form>
  </div>

  <!-- เพิ่มลูกค้า -->
  <button type="button" class="toggle-add" id="toggleAddBtn">+ เพิ่มลูกค้าใหม่</button>
  <div class="card add-form" id="addForm">
    <h2>เพิ่มลูกค้าใหม่</h2>
    <form method="post">
      <?php echo csrf_input(); ?>
      <div class="field-grid">
        <div class="field" style="grid-column:1/-1">
          <label>ชื่อลูกค้า / ชื่อร้าน <span style="color:#e33">*</span></label>
          <input type="text" name="name" class="input" required autocomplete="off">
        </div>
        <div class="field">
          <label>เบอร์โทร</label>
          <input type="tel" name="phone" class="input" autocomplete="off">
        </div>
        <?php if ($roundsEnabled) { ?>
        <div class="field">
          <label>รอบส่งประจำ</label>
          <select name="preferred_round" class="select">
            <?php foreach ($roundOptions as $code => $label) { ?>
              <option value="<?php echo h($code); ?>"><?php echo h($label); ?></option>
            <?php } ?>
          </select>
        </div>
        <?php } ?>
        <?php if ($routeLabels) { ?>
        <div class="field">
          <label>สายส่ง</label>
          <input type="number" name="route" class="input" value="1" min="1">
        </div>
        <?php } ?>
        <?php if ($deliveryOrder) { ?>
        <div class="field">
          <label>ลำดับส่ง</label>
          <input type="number" name="route_order" class="input" value="1" min="1">
        </div>
        <?php } ?>
        <?php if ($mapsEnabled) { ?>
        <div class="field" style="grid-column:1/-1">
          <label>ลิงก์แผนที่</label>
          <input type="text" name="map_url" class="input" placeholder="Google Maps URL หรือ 13.7,100.5">
        </div>
        <?php } ?>
        <div class="field" style="grid-column:1/-1">
          <label>หมายเหตุ</label>
          <input type="text" name="note_text" class="input" placeholder="เช่น เปิดสาย, รับเงินปลายทาง">
        </div>
      </div>
      <div class="btn-row">
        <button type="submit" name="add_customer" value="1" class="btn btn-primary">เพิ่มลูกค้า</button>
        <button type="button" class="btn btn-light" id="cancelAddBtn">ยกเลิก</button>
      </div>
    </form>
  </div>

  <!-- รายชื่อ -->
  <div class="card">
    <h2>รายชื่อลูกค้า<?php if ($search !== '') { echo ' — "' . h($search) . '"'; } ?></h2>
    <?php if (!$rows) { ?>
      <div class="empty"><?php echo $search !== '' ? 'ไม่พบลูกค้าที่ตรงกับ "' . h($search) . '"' : 'ยังไม่มีลูกค้าในระบบ'; ?></div>
    <?php } else { ?>
      <div class="clist">
        <?php foreach ($rows as $row) {
          $initials = mb_strtoupper(mb_substr(trim((string)$row['name']), 0, 1, 'UTF-8'), 'UTF-8');
          if ($initials === '') $initials = '?';
          $meta = array();
          if (!empty($row['phone'])) $meta[] = $row['phone'];
          if ($routeLabels && !empty($row['route'])) $meta[] = 'สาย ' . (int)$row['route'];
          if ($roundsEnabled && !empty($row['preferred_round'])) $meta[] = get_customer_round_label($row['preferred_round']);
        ?>
        <div class="citem">
          <div class="cavatar"><?php echo h($initials); ?></div>
          <div class="cinfo">
            <div class="cname"><?php echo h($row['name']); ?></div>
            <?php if ($meta) { ?><div class="cmeta"><?php echo h(implode(' · ', $meta)); ?></div><?php } ?>
            <?php if (!empty($row['note_text'])) { ?><div class="cmeta" style="color:#8a6000"><?php echo h($row['note_text']); ?></div><?php } ?>
          </div>
        </div>
        <?php } ?>
      </div>
    <?php } ?>
  </div>

</div>

<script>
(function(){
  var toggleBtn  = document.getElementById('toggleAddBtn');
  var cancelBtn  = document.getElementById('cancelAddBtn');
  var addForm    = document.getElementById('addForm');
  if (!toggleBtn || !addForm) return;
  toggleBtn.addEventListener('click', function(){
    addForm.classList.toggle('open');
    toggleBtn.style.display = 'none';
    addForm.querySelector('input[name="name"]').focus();
  });
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function(){
      addForm.classList.remove('open');
      toggleBtn.style.display = '';
    });
  }
  <?php if ($message_type === 'success' && $message !== '') { ?>
  addForm.classList.remove('open');
  toggleBtn.style.display = '';
  <?php } elseif ($message_type === 'error' && $message !== '') { ?>
  addForm.classList.add('open');
  toggleBtn.style.display = 'none';
  <?php } ?>
})();
</script>
<nav class="app-nav">
  <a href="index.php"><svg viewBox="0 0 24 24"><path d="M9 11l3-8 3 8M5 19h14"/><line x1="12" y1="3" x2="12" y2="19"/></svg>คีย์ออเดอร์</a>
  <a href="driver.php"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>คนส่ง</a>
  <a href="report.php"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>รายงาน</a>
  <a href="customers.php" class="active"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>ลูกค้า</a>
</nav>
</body>
</html>
