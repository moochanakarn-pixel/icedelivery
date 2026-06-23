# IceDelivery — Codebase Reference

## โครงสร้างไฟล์

```
icedelivery/
├── config.php              # Core: DB, session, CSRF, schema migration, helper functions
├── config.local.php        # ⛔ ไม่ commit — credentials & secrets (ดู .gitignore)
├── line_bootstrap.php      # LINE API: rich menu, webhook helpers, LIFF config
│
├── index.php               # 📱 คีย์ออเดอร์ (mobile-first, ใช้งานหน้าหลัก)
├── driver.php              # 🚚 คนส่ง — tabs: วันนี้/พรุ่งนี้/ค้างเก็บเงิน/ประวัติ
├── driver_sse.php          # SSE endpoint — ส่ง real-time order count ให้ driver.php
├── report.php              # 📊 รายงานยอดรายวัน/รายเดือน
├── customers.php           # redirect → admin/customers.php (ใช้ใน LINE LIFF)
├── line_webhook.php        # รับ webhook จาก LINE Official Account
├── save_fcm_token.php      # บันทึก FCM push token จากแอป
│
├── admin/
│   ├── _bootstrap.php      # Admin layout, nav, flash message, header/footer render
│   ├── index.php           # ภาพรวม admin — สถิติ, push notification form
│   ├── customers.php       # จัดการลูกค้า (เพิ่ม/แก้ไข/ลบ)
│   ├── admin_users.php     # จัดการผู้ดูแลระบบ
│   ├── line_richmenu.php   # สร้าง/อัปเดต LINE Rich Menu, sync ทุกคน
│   ├── line_users.php      # กำหนดสิทธิ์ผู้ใช้ LINE รายคน
│   ├── settings.php        # ตั้งค่าฟีเจอร์ระบบ (เปิด/ปิด)
│   ├── login.php           # หน้า login admin
│   ├── logout.php          # logout + clear remember token
│   └── send_push.php       # POST-only handler — ส่ง FCM push (เรียกจาก admin/index.php)
│
└── assets/
    ├── admin.css           # Styles สำหรับ admin panel
    ├── app.css             # Styles สำหรับ mobile pages (index, driver, report)
    ├── mobile.css          # Base mobile styles
    └── line-richmenu/      # รูปภาพ rich menu ที่ upload แล้ว
```

---

## config.local.php (ต้องสร้างเองบน server)

ไม่มีในรีโป — ต้องสร้างไฟล์นี้บน server เท่านั้น:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_PORT', 3307);          // port MySQL (Windows VPS ใช้ 3307)
define('DB_NAME', 'ice_delivery');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');

// LINE OA credentials
define('LINE_CHANNEL_ACCESS_TOKEN', '...');
define('LINE_CHANNEL_SECRET', '...');

// LINE LIFF ID (ถ้ามี)
define('LINE_LIFF_ID', '');
define('LINE_REPORT_SHARE_LIFF_ID', '');

// FCM push notification
define('FCM_SERVER_KEY', '...');
```

---

## Database Tables

| ตาราง | หน้าที่ |
|-------|---------|
| `customers` | ข้อมูลลูกค้า |
| `orders` | ออเดอร์แต่ละวัน |
| `order_items` | รายการสินค้าในออเดอร์ |
| `last_prices` | ราคาล่าสุดของลูกค้าแต่ละราย |
| `admin_users` | บัญชีผู้ดูแลระบบ |
| `admin_remember_tokens` | Remember-me tokens |
| `activity_logs` | Log การกระทำของ admin |
| `app_settings` | ตั้งค่าฟีเจอร์ระบบ |
| `fcm_tokens` | FCM push tokens |
| `line_users` | LINE user ID, role, is_active |
| `line_richmenus` | Rich menu ID ที่สร้างใน LINE |
| `line_menu_configs` | Config ปุ่มใน rich menu แต่ละ role |
| `line_webhook_events` | Log events จาก LINE webhook |

### Indexes ที่สำคัญ
```sql
idx_customers_round_route        ON customers(preferred_round, route, route_order)
idx_orders_date_period           ON orders(order_date, order_period)
idx_orders_date_customer         ON orders(order_date, customer_id)
idx_orders_status                ON orders(status, order_date)
idx_orders_paid_date             ON orders(paid, order_date)
idx_order_items_order_id         ON order_items(order_id)
idx_orders_delivered_at          ON orders(delivered_at DESC)
```

---

## Schema Migration

`ICE_SCHEMA_VERSION` ใน `config.php` คือ version ปัจจุบัน  
ทุก request จะเรียก `ensure_schema_updates()` ซึ่ง:
1. อ่าน version จาก session cache (TTL 60 วินาที)
2. ถ้า version ไม่ตรง → query DB → รัน migration → update session
3. `SKIP_SCHEMA_UPDATES = true` ก่อน `include 'config.php'` เพื่อข้ามขั้นตอนนี้

**การ bump version** (เมื่อเพิ่ม table/column/index ใหม่):
```php
// config.php บรรทัด ~49
define('ICE_SCHEMA_VERSION', '2026-06-23-1');  // เปลี่ยนวันที่ + suffix
```

---

## Admin Roles

| Role | สิทธิ์ |
|------|--------|
| `superadmin` | ทุกอย่าง |
| `admin` | ทุกอย่างยกเว้น admin user management |
| `viewer` | ดูได้อย่างเดียว — ไม่เห็น admin_users, settings, line pages |

---

## LINE Rich Menu Roles

| Role | หมายความว่า |
|------|-------------|
| `family` | ครอบครัว — เห็นปุ่มจำกัด |
| `admin` | แอดมิน LINE — เห็นปุ่มทั้งหมด |

config ปุ่มแต่ละ role เก็บใน `line_menu_configs` table  
อัปเดตได้ที่ `admin/line_richmenu.php`

---

## driver.php — AJAX Tab Switching

Tab switching ใช้ AJAX เพื่อไม่ reload ทั้งหน้า:

- `?ajax_tab=1&view=<tab>` → server ส่ง JSON `{ok, html, title, outstanding_amount, today_pending, today_delivered}`
- JS cache ฝั่ง client TTL **90 วินาที** — tab ที่เคยโหลดไม่ต้องดึงใหม่
- `AbortController` ยกเลิก request เก่าเมื่อ user กด tab ใหม่เร็วๆ
- หลัง deliver/pay → `invalidateTodayCache()` ล้าง cache tab วันนี้

### Tab Views
| view | ข้อมูลที่โหลด |
|------|--------------|
| `today` | ออเดอร์วันนี้ |
| `tomorrow` | ออเดอร์พรุ่งนี้ |
| `outstanding` | กลุ่มค้างเก็บเงิน |
| `history` | ประวัติการส่ง 30 วัน |

---

## Security Checklist

- [x] CSRF token ทุก POST form (`csrf_input()` / `csrf_validate()`)
- [x] HTML escape ทุก output ด้วย `h()`
- [x] MySQLi prepared escaping ด้วย `mysqli_real_escape_string()`
- [x] Admin session check ทุก admin page (`admin_require_login()`)
- [x] `uploads/delivery/` มี `.htaccess` → `deny from all`
- [x] `config.local.php` อยู่ใน `.gitignore` — ไม่ commit credentials
- [x] Session cookie: `httponly=true`, `samesite=Lax`, `secure` ถ้า HTTPS
- [x] Remember-me token เก็บ hashed ใน DB

---

## Feature Flags (admin/settings.php)

| Key | Default | หมายความว่า |
|-----|---------|-------------|
| `enable_map_features` | false | แสดงฟิลด์แผนที่ในลูกค้า |
| `enable_delivery_rounds` | true | รอบเช้า/บ่าย/เย็น |
| `enable_route_labels` | false | สายส่ง (สาย 1, 2, 3...) |
| `enable_delivery_order` | true | ลำดับการส่ง |
| `enable_quick_tools` | true | ปุ่มลัดใน index.php |

ตรวจสอบฟังก์ชัน: `maps_enabled()`, `rounds_enabled()`, `route_labels_enabled()`, `delivery_order_enabled()`, `quick_tools_enabled()`
