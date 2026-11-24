# PPMS — Project Preparation Management System

ระบบบริหารจัดการรายวิชาเตรียมความพร้อมโครงงาน (Project Topic Approval and Proposal Evaluation Platform)
ภาควิชาวิศวกรรมไฟฟ้าและคอมพิวเตอร์ คณะวิทยาศาสตร์และวิศวกรรมศาสตร์

- PHP 8.2+ · PDO · MySQL 8+ / MariaDB 10.6+ · Bootstrap 5 · Chart.js
- โครงสร้างแบบ modular MVC ไม่พึ่ง framework ใหญ่ ติดตั้งบน XAMPP หรือ shared hosting ได้
- เอกสารออกแบบ Phase 1 (SRS, workflow, RBAC, data dictionary): ดู `docs/phase-status.md`

## สถานะการพัฒนา

| Phase | เนื้อหา | สถานะ |
| --- | --- | --- |
| 1 | Requirement Analysis (SRS, workflows, RBAC, data dictionary) | เสร็จ (diagram บางส่วนยังทำต่อ) |
| 2 | Workflow design และ architecture | state machine ใน `app/Services/Workflow` |
| 3 | Database (`database/schema.sql`, 32 ตาราง, 23 triggers) | **เสร็จ ทดสอบแล้ว** |
| 4 | Authentication และ Role Management | **เสร็จ ทดสอบแล้ว** |
| 5–14 | Group registration ถึง Deployment | ยังไม่เริ่ม |

รายละเอียดสิ่งที่ทำแล้ว/ข้อจำกัด: `docs/phase-status.md`

## การติดตั้งบน XAMPP (Windows / macOS)

1. คัดลอกโฟลเดอร์ `ppms` ไปไว้ใน `htdocs` เช่น `C:\xampp\htdocs\ppms`
2. เปิด XAMPP Control Panel แล้ว Start **Apache** และ **MySQL**
3. เปิด phpMyAdmin (`http://localhost/phpmyadmin`) แล้วสร้างฐานข้อมูลชื่อ `ppms` (Collation `utf8mb4_unicode_ci`)
   จากนั้นคลิกเลือกฐานข้อมูล `ppms` แล้วใช้แท็บ **Import** ตามลำดับ
   1. `database/schema.sql` (import ซ้ำได้ แต่จะลบข้อมูลเดิมทั้งหมด)
   2. `database/seed.sql` (บทบาท ผู้ดูแลระบบ rubric และค่าตั้งต้น)
   3. `database/seed_demo.sql` (ไม่บังคับ: ผู้ใช้ทดลอง ห้ามใช้บน production)

   หรือใช้ command line: `mysql -u root -p ppms < database/schema.sql` แล้ว `mysql -u root -p ppms < database/seed.sql`
4. คัดลอก `config/local.example.php` เป็น `config/local.php` แล้วแก้ค่า
   - `base_path` = `/ppms/public` และ `url` = `http://localhost/ppms/public`
   - ข้อมูลเชื่อมต่อฐานข้อมูล (XAMPP ค่าเริ่มต้น user `root` ไม่มีรหัสผ่าน)
5. เปิด `http://localhost/ppms/public`
6. เข้าสู่ระบบด้วย `admin` / `Admin@1234` ระบบจะบังคับให้เปลี่ยนรหัสผ่านทันที

ไม่จำเป็นต้องติดตั้ง Composer: `public/index.php` มี autoloader สำรอง หากมี Composer ให้รัน `composer dump-autoload`

### InfinityFree / shared hosting ที่ไม่อนุญาต trigger

ดูคู่มือ [`docs/deploy-infinityfree.md`](docs/deploy-infinityfree.md) และสร้างไฟล์สำหรับอัปโหลดด้วย
`php tools/package.php` (ได้ `build/ppms-infinityfree.zip`) โฮสต์ประเภทนี้ใช้
`database/schema_shared_hosting.sql` ซึ่งสร้างอัตโนมัติจาก `schema.sql` ด้วย `php tools/build-shared-schema.php`
(ไม่มี trigger และ foreign key ส่วนกฎทั้งหมดบังคับในชั้น PHP)

### Production (เซิร์ฟเวอร์ที่ควบคุมได้)

- ตั้ง document root ไปที่โฟลเดอร์ `public/` เท่านั้น
- ใน `config/local.php` ตั้ง `'env' => 'production'`, `'debug' => false` และ `'session' => ['secure_cookie' => true]` เมื่อใช้ HTTPS
- ให้ web server เขียนได้เฉพาะ `storage/uploads` และ `storage/logs`
- ห้าม import `seed_demo.sql`

### บัญชีทดลอง (จาก `seed_demo.sql`, รหัสผ่าน `Demo@1234`)

| Username | บทบาท |
| --- | --- |
| `advisor1`, `advisor2` | Advisor + Committee (สลับบทบาทได้ที่เมนูผู้ใช้) |
| `committee1`, `committee2` | Committee |
| `6510000001` … `6510000006` | Student |

## การทดสอบ

```bash
php tests/run.php                      # ทุกชุดทดสอบ (schema เต็ม: trigger + foreign key)
PPMS_SCHEMA=shared php tests/run.php   # ทดสอบกับ schema สำหรับ shared hosting
php tests/run.php Schema               # เฉพาะชื่อที่มีคำว่า Schema
```

Integration tests สร้างฐานข้อมูลทดสอบ `ppms_test` ใหม่ก่อนทุกการทดสอบ บัญชี MySQL ใน `config/local.php` ต้องมีสิทธิ์ CREATE/DROP บน `ppms_test` (กำหนดชื่ออื่นได้ด้วย `PPMS_TEST_DB`) ไม่แตะฐานข้อมูลหลัก

ปัจจุบัน: 53 tests ผ่านทั้งหมดกับ schema เต็ม และ 48 ผ่าน + 5 ข้าม (ทดสอบ trigger โดยตรง) กับ schema แบบ shared hosting บน PHP 8.3 + MariaDB 10.11

## โครงสร้างโฟลเดอร์

```text
app/
  Controllers/        AuthController, DashboardController, Admin/UserController, …
  Core/               App, Router, Request, Response, View, Database (PDO), Session, Csrf, Auth
  Middleware/         auth, guest, csrf, role:<roles>, password (บังคับเปลี่ยนรหัส)
  Models/             User
  Services/           AuthService, AuditLogger, NotificationService, SettingsService, ScoreCalculator
  Services/Rules/     CommitteeRules, EvaluationRules, VersionRules (กฎเดียวกับ trigger)
  Services/Workflow/  StateMachine, Workflows (group/topic/proposal), WorkflowEngine
  Validators/         Validator
config/               app.php, database.php, local.example.php, local.infinityfree.example.php
database/             schema.sql, schema_shared_hosting.sql (generated), seed.sql, seed_demo.sql
public/               index.php (front controller), assets/
resources/views/      layouts, auth, dashboard, admin, errors, notifications
resources/lang/th.php ข้อความภาษาไทย
routes/web.php        เส้นทางและ middleware
storage/              uploads/ (นอก web root), logs/
tests/                run.php, Unit/, Integration/
tools/                build-shared-schema.php, package.php
```

## หลักการด้านความปลอดภัยที่ใช้แล้ว

- `password_hash()` / `password_verify()` และล็อกบัญชีเมื่อเข้าสู่ระบบผิด 5 ครั้ง (15 นาที)
- PDO prepared statements ทุก query (`ATTR_EMULATE_PREPARES = false`)
- CSRF token ทุก POST, session cookie แบบ HttpOnly + SameSite, regenerate id หลัง login, idle timeout
- Escape output ด้วย `e()`, Content-Security-Policy ไม่อนุญาต inline script
- RBAC 3 ชั้น: role ของ route, ความเป็นเจ้าของ record, และสถานะ workflow
- ตารางคำตัดสินและ log เป็น append-only และผลประเมินที่ส่งแล้วถูกล็อก บังคับ 2 ชั้น: trigger ในฐานข้อมูล และ `IntegrityGuard` / `Services/Rules` ในชั้น PHP (ชั้น PHP ทำงานแม้โฮสต์ไม่อนุญาต trigger)
- Audit log ทุกการเข้าสู่ระบบ การเปลี่ยนสถานะ และการจัดการผู้ใช้ (ไม่บันทึกรหัสผ่าน)
