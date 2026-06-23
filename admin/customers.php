<?php
include_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$message = '';
$message_type = 'success';
$routeLabels = route_labels_enabled();
$deliveryOrder = delivery_order_enabled();
$roundsEnabled = rounds_enabled();
$mapsEnabled = maps_enabled();
$roundOptions = customer_round_options();

function admin_customer_redirect_with_flash($type, $message, $query = '') {
    set_flash_message($type, $message);
    admin_auth_redirect('customers.php' . ($query !== '' ? ('?' . ltrim($query, '?')) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_customer'])) {
        if (!csrf_validate()) {
            admin_customer_redirect_with_flash('error', 'คำขอเพิ่มลูกค้าไม่ถูกต้อง');
        }
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $route = $routeLabels ? max(1, (int)(isset($_POST['route']) ? $_POST['route'] : 1)) : 1;
        $routeOrder = $deliveryOrder ? max(0, (int)(isset($_POST['route_order']) ? $_POST['route_order'] : 0)) : 0;
        $preferredRound = $roundsEnabled ? normalize_customer_round(isset($_POST['preferred_round']) ? $_POST['preferred_round'] : 'all_day') : 'all_day';
        $noteText = trim(isset($_POST['note_text']) ? $_POST['note_text'] : '');
        $mapUrl = $mapsEnabled ? normalize_map_input(isset($_POST['map_url']) ? $_POST['map_url'] : '') : '';
        $iceTypesCsv = 'big,small,crush,pack';
        $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
        $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

        if ($name === '') {
            admin_customer_redirect_with_flash('error', 'กรุณากรอกชื่อลูกค้า');
        }

        $nameEsc = mysqli_real_escape_string($conn, $name);
        $dupRes = @mysqli_query($conn, "SELECT id FROM customers WHERE name='{$nameEsc}' LIMIT 1");
        if ($dupRes && mysqli_num_rows($dupRes) > 0) {
            admin_customer_redirect_with_flash('error', 'มีลูกค้าชื่อ "' . $name . '" อยู่แล้ว');
        }

        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $roundEsc = mysqli_real_escape_string($conn, $preferredRound);
        $noteEsc = mysqli_real_escape_string($conn, $noteText);
        $mapEsc = mysqli_real_escape_string($conn, $mapUrl);
        $iceEsc = mysqli_real_escape_string($conn, $iceTypesCsv);
        $latSql = $latitude !== null ? round($latitude, 7) : 'NULL';
        $lngSql = $longitude !== null ? round($longitude, 7) : 'NULL';
        $ok = mysqli_query($conn, "INSERT INTO customers(name, phone, route, route_order, preferred_round, ice_types, map_url, note_text, latitude, longitude) VALUES('{$nameEsc}', '{$phoneEsc}', {$route}, {$routeOrder}, '{$roundEsc}', '{$iceEsc}', '{$mapEsc}', '{$noteEsc}', {$latSql}, {$lngSql})");
        if ($ok) {
            admin_log_action('add_customer', 'เพิ่มลูกค้า ' . $name);
            admin_customer_redirect_with_flash('success', 'เพิ่มลูกค้าเรียบร้อย');
        }
        admin_customer_redirect_with_flash('error', 'เพิ่มลูกค้าไม่สำเร็จ: ' . mysqli_error($conn));
    }

    if (isset($_POST['save_customer'])) {
        if (!csrf_validate()) {
            admin_customer_redirect_with_flash('error', 'คำขอแก้ไขลูกค้าไม่ถูกต้อง', http_build_query(array('q' => isset($_GET['q']) ? $_GET['q'] : '')));
        }
        $customerId = (int)(isset($_POST['customer_id']) ? $_POST['customer_id'] : 0);
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $route = $routeLabels ? max(1, (int)(isset($_POST['route']) ? $_POST['route'] : 1)) : 1;
        $routeOrder = $deliveryOrder ? max(0, (int)(isset($_POST['route_order']) ? $_POST['route_order'] : 0)) : 0;
        $preferredRound = $roundsEnabled ? normalize_customer_round(isset($_POST['preferred_round']) ? $_POST['preferred_round'] : 'all_day') : 'all_day';
        $noteText = trim(isset($_POST['note_text']) ? $_POST['note_text'] : '');
        $mapUrl = $mapsEnabled ? normalize_map_input(isset($_POST['map_url']) ? $_POST['map_url'] : '') : '';
        $iceTypesCsv = 'big,small,crush,pack';
        $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
        $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
        if ($customerId <= 0 || $name === '') {
            admin_customer_redirect_with_flash('error', 'ข้อมูลลูกค้าไม่ถูกต้อง');
        }
        $nameEsc = mysqli_real_escape_string($conn, $name);
        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $roundEsc = mysqli_real_escape_string($conn, $preferredRound);
        $noteEsc = mysqli_real_escape_string($conn, $noteText);
        $mapEsc = mysqli_real_escape_string($conn, $mapUrl);
        $iceEsc = mysqli_real_escape_string($conn, $iceTypesCsv);
        $latSql = $latitude !== null ? round($latitude, 7) : 'NULL';
        $lngSql = $longitude !== null ? round($longitude, 7) : 'NULL';
        $ok = mysqli_query($conn, "UPDATE customers SET name='{$nameEsc}', phone='{$phoneEsc}', route={$route}, route_order={$routeOrder}, preferred_round='{$roundEsc}', ice_types='{$iceEsc}', map_url='{$mapEsc}', note_text='{$noteEsc}', latitude={$latSql}, longitude={$lngSql} WHERE id={$customerId} LIMIT 1");
        if ($ok) {
            admin_log_action('save_customer', 'อัปเดตลูกค้า ID ' . $customerId);
            admin_customer_redirect_with_flash('success', 'บันทึกลูกค้าเรียบร้อย', http_build_query(array('q' => isset($_GET['q']) ? $_GET['q'] : '')));
        }
        admin_customer_redirect_with_flash('error', 'บันทึกลูกค้าไม่สำเร็จ: ' . mysqli_error($conn));
    }

    if (isset($_POST['delete_customer'])) {
        if (!csrf_validate()) {
            admin_customer_redirect_with_flash('error', 'คำขอลบลูกค้าไม่ถูกต้อง');
        }
        $customerId = (int)(isset($_POST['customer_id']) ? $_POST['customer_id'] : 0);
        if ($customerId <= 0) {
            admin_customer_redirect_with_flash('error', 'ไม่พบลูกค้าที่ต้องการลบ');
        }
        $rowRes = mysqli_query($conn, "SELECT name FROM customers WHERE id={$customerId} LIMIT 1");
        $row = $rowRes ? mysqli_fetch_assoc($rowRes) : null;
        mysqli_query($conn, "DELETE FROM order_items WHERE order_id IN (SELECT id FROM (SELECT id FROM orders WHERE customer_id={$customerId}) x)");
        mysqli_query($conn, "DELETE FROM orders WHERE customer_id={$customerId}");
        mysqli_query($conn, "DELETE FROM last_prices WHERE customer_id={$customerId}");
        $ok = mysqli_query($conn, "DELETE FROM customers WHERE id={$customerId} LIMIT 1");
        if ($ok) {
            admin_log_action('delete_customer', 'ลบลูกค้า ' . ($row && isset($row['name']) ? $row['name'] : ('ID ' . $customerId)));
            admin_customer_redirect_with_flash('success', 'ลบลูกค้าเรียบร้อย');
        }
        admin_customer_redirect_with_flash('error', 'ลบลูกค้าไม่สำเร็จ: ' . mysqli_error($conn));
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
$rows = fetch_all_rows(mysqli_query($conn, "SELECT * FROM customers {$whereSql} ORDER BY {$orderSql}"));
$flash = consume_flash_message();
if ($flash) {
    $message = isset($flash['message']) ? (string)$flash['message'] : '';
    $message_type = isset($flash['type']) ? (string)$flash['type'] : 'success';
}

admin_render_header('ลูกค้า', 'เพิ่ม แก้ไข และลบลูกค้าได้จากหน้านี้ พร้อมป้องกัน CSRF');
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.gps-btn-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:4px; }
.gps-label-display { font-size:13px; color:var(--text-muted,#888); }
.gps-label-display.has-pin { color:var(--success,#16a34a); font-weight:500; }

#mapPickerOverlay {
    display:none; position:fixed; inset:0; z-index:9000;
    background:rgba(0,0,0,.55); align-items:center; justify-content:center;
}
#mapPickerOverlay.open { display:flex; }
#mapPickerBox {
    background:#fff; border-radius:12px; width:min(700px,96vw);
    max-height:92vh; display:flex; flex-direction:column; overflow:hidden;
    box-shadow:0 8px 40px rgba(0,0,0,.3);
}
#mapPickerHeader {
    padding:14px 16px; border-bottom:1px solid #eee;
    display:flex; align-items:center; justify-content:space-between;
    font-weight:600; font-size:15px;
}
#mapPickerSearchRow {
    padding:10px 12px; display:flex; gap:6px; border-bottom:1px solid #eee; flex-wrap:wrap;
}
#mapSearchInput { flex:1; min-width:0; padding:7px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
#mapSearchResults {
    max-height:180px; overflow-y:auto; background:#fff;
    border:1px solid #ddd; border-radius:6px; margin:0 12px 4px;
    display:none;
}
#mapSearchResults .sr-item {
    padding:8px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f0f0f0;
}
#mapSearchResults .sr-item:hover { background:#f5f5f5; }
#mapPickerMap { flex:1; min-height:300px; }
#mapPickerFooter {
    padding:10px 14px; border-top:1px solid #eee;
    display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;
}
#mapPickerCoordsDisplay { font-size:13px; color:#555; }
.mp-btn { padding:7px 16px; border-radius:6px; border:none; cursor:pointer; font-size:13px; font-weight:500; }
.mp-btn-primary { background:#2563eb; color:#fff; }
.mp-btn-primary:hover { background:#1d4ed8; }
.mp-btn-ghost { background:#f3f4f6; color:#333; }
.mp-btn-ghost:hover { background:#e5e7eb; }
.mp-btn-gps { background:#059669; color:#fff; }
.mp-btn-gps:hover { background:#047857; }
</style>

<?php if ($message !== '') { ?><div class="notice <?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php } ?>

<div class="card">
    <h2>เพิ่มลูกค้าใหม่</h2>
    <form method="post" id="form_new">
        <?php echo csrf_input(); ?>
        <div class="form-grid">
            <div class="field"><label>ชื่อลูกค้า</label><input class="input" type="text" name="name" required></div>
            <div class="field"><label>เบอร์โทร</label><input class="input" type="text" name="phone"></div>
            <?php if ($routeLabels) { ?><div class="field"><label>สายส่ง</label><input class="input" type="number" min="1" name="route" value="1"></div><?php } ?>
            <?php if ($deliveryOrder) { ?><div class="field"><label>ลำดับส่ง</label><input class="input" type="number" min="0" name="route_order" value="0"></div><?php } ?>
            <?php if ($roundsEnabled) { ?><div class="field"><label>รอบประจำ</label><select class="select" name="preferred_round"><?php foreach ($roundOptions as $code => $label) { ?><option value="<?php echo h($code); ?>"><?php echo h($label); ?></option><?php } ?></select></div><?php } ?>
            <?php if ($mapsEnabled) { ?><div class="field"><label>แผนที่</label><input class="input" type="text" name="map_url"></div><?php } ?>
            <div class="field"><label>หมายเหตุ</label><input class="input" type="text" name="note_text"></div>
            <div class="field" style="grid-column:1/-1">
                <label>พิกัด GPS</label>
                <input type="hidden" name="latitude" id="lat_new">
                <input type="hidden" name="longitude" id="lng_new">
                <div class="gps-btn-row">
                    <span class="gps-label-display" id="gpslabel_new">ยังไม่ได้ตั้งพิกัด</span>
                    <button type="button" class="btn btn-light" onclick="openMapPicker('new')">📍 เลือกพิกัด</button>
                    <button type="button" class="btn btn-light" onclick="clearPin('new')">ล้างพิกัด</button>
                </div>
            </div>
        </div>
        <div class="btn-row"><button type="submit" name="add_customer" value="1" class="btn btn-primary">เพิ่มลูกค้า</button></div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="row">
        <div>
            <h2 style="margin:0 0 8px">รายชื่อลูกค้า</h2>
            <div class="muted">แก้ไขทีละแถวได้เลย</div>
        </div>
        <form method="get" class="actions-inline" style="margin:0">
            <input class="input" type="text" name="q" value="<?php echo h($search); ?>" placeholder="ค้นหาชื่อร้านหรือเบอร์">
            <button type="submit" class="btn btn-light">ค้นหา</button>
        </form>
    </div>

    <div class="list" style="margin-top:12px">
        <?php if (!$rows) { ?>
            <div class="item muted">ยังไม่มีข้อมูลลูกค้า</div>
        <?php } else { ?>
            <?php foreach ($rows as $row) {
                $cid = (int)$row['id'];
                $curLat = isset($row['latitude']) && $row['latitude'] !== null ? (float)$row['latitude'] : null;
                $curLng = isset($row['longitude']) && $row['longitude'] !== null ? (float)$row['longitude'] : null;
                $hasPin = $curLat !== null && $curLng !== null;
            ?>
                <div class="item">
                    <form method="post" id="form_<?php echo $cid; ?>">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="customer_id" value="<?php echo $cid; ?>">
                        <div class="form-grid">
                            <div class="field"><label>ชื่อลูกค้า</label><input class="input" type="text" name="name" value="<?php echo h($row['name']); ?>"></div>
                            <div class="field"><label>เบอร์โทร</label><input class="input" type="text" name="phone" value="<?php echo h($row['phone']); ?>"></div>
                            <?php if ($routeLabels) { ?><div class="field"><label>สายส่ง</label><input class="input" type="number" min="1" name="route" value="<?php echo (int)$row['route']; ?>"></div><?php } ?>
                            <?php if ($deliveryOrder) { ?><div class="field"><label>ลำดับส่ง</label><input class="input" type="number" min="0" name="route_order" value="<?php echo (int)$row['route_order']; ?>"></div><?php } ?>
                            <?php if ($roundsEnabled) { ?><div class="field"><label>รอบประจำ</label><select class="select" name="preferred_round"><?php foreach ($roundOptions as $code => $label) { ?><option value="<?php echo h($code); ?>" <?php echo (isset($row['preferred_round']) && $row['preferred_round'] === $code) ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php } ?></select></div><?php } ?>
                            <?php if ($mapsEnabled) { ?><div class="field"><label>แผนที่</label><input class="input" type="text" name="map_url" value="<?php echo h($row['map_url']); ?>"></div><?php } ?>
                            <div class="field"><label>หมายเหตุ</label><input class="input" type="text" name="note_text" value="<?php echo h($row['note_text']); ?>"></div>
                            <div class="field" style="grid-column:1/-1">
                                <label>พิกัด GPS</label>
                                <input type="hidden" name="latitude" id="lat_<?php echo $cid; ?>" value="<?php echo $hasPin ? $curLat : ''; ?>">
                                <input type="hidden" name="longitude" id="lng_<?php echo $cid; ?>" value="<?php echo $hasPin ? $curLng : ''; ?>">
                                <div class="gps-btn-row">
                                    <span class="gps-label-display <?php echo $hasPin ? 'has-pin' : ''; ?>" id="gpslabel_<?php echo $cid; ?>">
                                        <?php if ($hasPin) { echo '📍 ' . round($curLat,5) . ', ' . round($curLng,5); } else { echo 'ยังไม่ได้ตั้งพิกัด'; } ?>
                                    </span>
                                    <button type="button" class="btn btn-light" onclick="openMapPicker('<?php echo $cid; ?>')">📍 เลือกพิกัด</button>
                                    <?php if ($hasPin) { ?><button type="button" class="btn btn-light" onclick="clearPin('<?php echo $cid; ?>')">ล้างพิกัด</button><?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="btn-row" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
                            <button type="submit" name="save_customer" value="1" class="btn btn-primary">บันทึก</button>
                            <button type="button" class="btn btn-danger" onclick="submitDelete(<?php echo $cid; ?>)">ลบลูกค้า</button>
                        </div>
                    </form>
                    <form method="post" id="delform_<?php echo $cid; ?>" onsubmit="return confirm('ยืนยันการลบลูกค้ารายนี้?');" style="display:none">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="customer_id" value="<?php echo $cid; ?>">
                        <input type="hidden" name="delete_customer" value="1">
                    </form>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<!-- ===== MAP PICKER MODAL ===== -->
<div id="mapPickerOverlay" onclick="if(event.target===this)closeMapPicker()">
  <div id="mapPickerBox">
    <div id="mapPickerHeader">
      <span>📍 เลือกพิกัด</span>
      <button class="mp-btn mp-btn-ghost" style="padding:4px 10px" onclick="closeMapPicker()">✕</button>
    </div>
    <div id="mapPickerSearchRow">
      <input id="mapSearchInput" type="text" placeholder="ค้นหาสถานที่ เช่น ร้านสมชาย ลาดพร้าว..." autocomplete="off">
      <button class="mp-btn mp-btn-primary" onclick="doMapSearch()">ค้นหา</button>
      <button class="mp-btn mp-btn-gps" onclick="useMyLocation()">📡 ตำแหน่งปัจจุบัน</button>
    </div>
    <div id="mapSearchResults"></div>
    <div id="mapPickerMap"></div>
    <div id="mapPickerFooter">
      <span id="mapPickerCoordsDisplay">จิ้มที่แผนที่เพื่อเลือกพิกัด</span>
      <div style="display:flex;gap:8px">
        <button class="mp-btn mp-btn-ghost" onclick="closeMapPicker()">ยกเลิก</button>
        <button class="mp-btn mp-btn-primary" id="mapPickerConfirmBtn" onclick="confirmPin()" disabled>ยืนยัน</button>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var _map = null, _marker = null, _pinLat = null, _pinLng = null, _targetId = null;

function openMapPicker(id) {
    _targetId = id;
    var overlay = document.getElementById('mapPickerOverlay');
    overlay.classList.add('open');

    if (!_map) {
        _map = L.map('mapPickerMap').setView([13.7563, 100.5018], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19
        }).addTo(_map);
        _map.on('click', function(e) { setPin(e.latlng.lat, e.latlng.lng); });
    } else {
        setTimeout(function(){ _map.invalidateSize(); }, 100);
    }

    // ถ้ามีพิกัดเดิมให้แสดงก่อน
    var latVal = document.getElementById('lat_' + id) ? document.getElementById('lat_' + id).value : '';
    var lngVal = document.getElementById('lng_' + id) ? document.getElementById('lng_' + id).value : '';
    if (latVal !== '' && lngVal !== '') {
        var lt = parseFloat(latVal), ln = parseFloat(lngVal);
        setPin(lt, ln);
        _map.setView([lt, ln], 16);
    } else {
        _pinLat = null; _pinLng = null;
        if (_marker) { _map.removeLayer(_marker); _marker = null; }
        updateCoordsDisplay();
    }

    document.getElementById('mapSearchInput').value = '';
    document.getElementById('mapSearchResults').style.display = 'none';
    setTimeout(function(){ _map.invalidateSize(); }, 200);
}

function closeMapPicker() {
    document.getElementById('mapPickerOverlay').classList.remove('open');
    document.getElementById('mapSearchResults').style.display = 'none';
}

function setPin(lat, lng) {
    _pinLat = lat; _pinLng = lng;
    if (_marker) {
        _marker.setLatLng([lat, lng]);
    } else {
        _marker = L.marker([lat, lng], {draggable: true}).addTo(_map);
        _marker.on('dragend', function(e) {
            var p = e.target.getLatLng();
            _pinLat = p.lat; _pinLng = p.lng;
            updateCoordsDisplay();
        });
    }
    updateCoordsDisplay();
    document.getElementById('mapPickerConfirmBtn').disabled = false;
}

function updateCoordsDisplay() {
    var el = document.getElementById('mapPickerCoordsDisplay');
    if (_pinLat !== null) {
        el.textContent = '📍 ' + _pinLat.toFixed(6) + ', ' + _pinLng.toFixed(6);
    } else {
        el.textContent = 'จิ้มที่แผนที่เพื่อเลือกพิกัด';
        document.getElementById('mapPickerConfirmBtn').disabled = true;
    }
}

function confirmPin() {
    if (_pinLat === null || _targetId === null) return;
    document.getElementById('lat_' + _targetId).value = _pinLat.toFixed(7);
    document.getElementById('lng_' + _targetId).value = _pinLng.toFixed(7);
    var label = document.getElementById('gpslabel_' + _targetId);
    if (label) {
        label.textContent = '📍 ' + _pinLat.toFixed(5) + ', ' + _pinLng.toFixed(5);
        label.classList.add('has-pin');
    }
    closeMapPicker();
}

function clearPin(id) {
    document.getElementById('lat_' + id).value = '';
    document.getElementById('lng_' + id).value = '';
    var label = document.getElementById('gpslabel_' + id);
    if (label) { label.textContent = 'ยังไม่ได้ตั้งพิกัด'; label.classList.remove('has-pin'); }
}

function doMapSearch() {
    var q = document.getElementById('mapSearchInput').value.trim();
    if (!q) return;
    var resultsEl = document.getElementById('mapSearchResults');
    resultsEl.innerHTML = '<div class="sr-item" style="color:#888">กำลังค้นหา...</div>';
    resultsEl.style.display = 'block';
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' + encodeURIComponent(q), {
        headers: { 'Accept-Language': 'th,en' }
    }).then(function(r){ return r.json(); }).then(function(data) {
        if (!data.length) {
            resultsEl.innerHTML = '<div class="sr-item" style="color:#888">ไม่พบสถานที่</div>';
            return;
        }
        resultsEl.innerHTML = '';
        data.forEach(function(item) {
            var div = document.createElement('div');
            div.className = 'sr-item';
            div.textContent = item.display_name;
            div.onclick = function() {
                var lt = parseFloat(item.lat), ln = parseFloat(item.lon);
                setPin(lt, ln);
                _map.setView([lt, ln], 17);
                resultsEl.style.display = 'none';
            };
            resultsEl.appendChild(div);
        });
    }).catch(function() {
        resultsEl.innerHTML = '<div class="sr-item" style="color:#c00">ค้นหาไม่สำเร็จ ลองใหม่</div>';
    });
}

function useMyLocation() {
    if (!navigator.geolocation) { alert('เบราว์เซอร์นี้ไม่รองรับ GPS'); return; }
    navigator.geolocation.getCurrentPosition(function(pos) {
        var lt = pos.coords.latitude, ln = pos.coords.longitude;
        setPin(lt, ln);
        _map.setView([lt, ln], 17);
    }, function() { alert('ไม่สามารถดึงตำแหน่งได้ กรุณาอนุญาตการเข้าถึง GPS'); });
}

function submitDelete(id) {
    if (confirm('ยืนยันการลบลูกค้ารายนี้?')) {
        document.getElementById('delform_' + id).submit();
    }
}

// กด Enter ในช่องค้นหาแผนที่
document.getElementById('mapSearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); doMapSearch(); }
});
</script>

<?php admin_render_footer('หน้านี้เป็นตัวจัดการลูกค้าแบบเต็มจากหลังบ้าน'); ?>
