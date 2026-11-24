# คู่มือติดตั้ง PPMS บน InfinityFree

ใช้กับไฟล์ `ppms-infinityfree.zip` (สร้างด้วย `php tools/package.php`) ซึ่งมี 2 ส่วน

| โฟลเดอร์ใน zip | ทำอะไร |
| --- | --- |
| `htdocs/` | อัปโหลด **ไฟล์ข้างใน** ไปไว้ในโฟลเดอร์ `htdocs` ของโฮสต์ |
| `database/` | import ผ่าน phpMyAdmin เท่านั้น ห้ามอัปโหลดขึ้นเว็บ |

> ข้อควรรู้: InfinityFree ไม่อนุญาต MySQL trigger และ foreign key จึงต้องใช้ไฟล์
> `schema_shared_hosting.sql` (ไม่ใช่ `schema.sql`) กฎทางธุรกิจทั้งหมดยังบังคับใช้ครบ
> โดยชั้น PHP ของระบบ (ทดสอบแล้วกับสิทธิ์ฐานข้อมูลแบบเดียวกับ InfinityFree)

## 1. สร้างบัญชีและเว็บไซต์

1. สมัครที่ https://infinityfree.com แล้วกด **Create Account**
2. เลือก subdomain ฟรี (เช่น `ppms-ece.infinityfreeapp.com`) หรือใช้โดเมนของตัวเอง
3. รอให้บัญชีพร้อมใช้งาน (ปกติไม่กี่นาที) แล้วเข้า **Control Panel**

## 2. สร้างฐานข้อมูล

1. Control Panel > **MySQL Databases** > ตั้งชื่อ เช่น `ppms` แล้วกด **Create Database**
2. จดค่าต่อไปนี้จากหน้าเดียวกัน (ใช้ในขั้นตอนที่ 5)
   - **MySQL Hostname** เช่น `sql123.infinityfree.com` (ไม่ใช่ `localhost`)
   - **MySQL DB Name** เช่น `if0_12345678_ppms`
   - **MySQL Username** เช่น `if0_12345678`
   - **MySQL Password** = รหัสผ่านของ hosting account (ดูได้ที่ Account > Account Details)

## 3. Import ฐานข้อมูล

1. ที่หน้า MySQL Databases กด **Admin** ข้างฐานข้อมูล เพื่อเปิด phpMyAdmin
2. คลิกชื่อฐานข้อมูลทางซ้ายให้ถูกเลือกก่อน
3. แท็บ **Import** > เลือกไฟล์แล้วกด **Import** ทีละไฟล์ตามลำดับ
   1. `database/schema_shared_hosting.sql`
   2. `database/seed.sql`
   3. `database/seed_demo.sql` *(ไม่บังคับ: บัญชีทดลองสำหรับเดโมเท่านั้น)*
4. ตรวจว่ามี 32 ตาราง

## 4. อัปโหลดไฟล์

แนะนำ FTP ด้วย [FileZilla](https://filezilla-project.org) เพราะอัปโหลดทั้งโฟลเดอร์ได้ในครั้งเดียว

1. Control Panel > **FTP Details**: จด FTP Hostname, Username และ Password
2. เชื่อมต่อด้วย FileZilla แล้วเปิดโฟลเดอร์ `htdocs` บนเซิร์ฟเวอร์
3. ลบไฟล์ตัวอย่างที่ InfinityFree ใส่ไว้ใน `htdocs` (ถ้ามี)
4. ลาก **ไฟล์ทั้งหมดข้างในโฟลเดอร์ `htdocs/` ของ zip** ไปวาง (ไม่ใช่ลากโฟลเดอร์ `htdocs` ทั้งก้อน)
   ต้องได้โครงสร้างแบบนี้บนเซิร์ฟเวอร์

   ```text
   htdocs/
     .htaccess        <- สำคัญ: ต้องมีไฟล์นี้ (FileZilla แสดงไฟล์ที่ขึ้นต้นด้วยจุดเป็นค่าเริ่มต้น)
     app/  config/  public/  resources/  routes/  storage/
   ```

## 5. ตั้งค่าการเชื่อมต่อ

1. ในโฟลเดอร์ `htdocs/config/` เปลี่ยนชื่อ `local.infinityfree.example.php` เป็น `local.php`
2. แก้ไข `local.php` (คลิกขวา > View/Edit ใน FileZilla หรือใช้ File Manager ของ InfinityFree)
   แทนค่าทุกจุดที่เขียนว่า `CHANGE` ด้วยค่าจากขั้นตอนที่ 2 และที่อยู่เว็บไซต์
3. บันทึกไฟล์

## 6. เปิด SSL (HTTPS)

1. Control Panel > **Free SSL Certificates** > ขอใบรับรองสำหรับโดเมนของเว็บไซต์ แล้วติดตั้งตามขั้นตอน
2. ระหว่างที่ SSL ยังไม่พร้อม ให้ตั้ง `'secure_cookie' => false` ใน `local.php` ชั่วคราว
   มิฉะนั้นจะเข้าสู่ระบบไม่ได้ (เบราว์เซอร์ไม่ส่ง cookie ผ่าน http)
3. เมื่อ SSL ใช้งานได้ เปลี่ยนกลับเป็น `true` และใช้ `https://` ใน `url`

## 7. เข้าสู่ระบบครั้งแรก (ทำทันทีหลัง import)

1. เปิดเว็บไซต์ จะเข้าสู่หน้า Login
2. ใช้ `admin` / `Admin@1234` แล้วตั้งรหัสผ่านใหม่ทันที (ระบบบังคับ)
   รหัสเริ่มต้นนี้อยู่ใน source code สาธารณะ จึงต้องเปลี่ยนก่อนแจ้งลิงก์ให้ผู้อื่น
3. ถ้า import `seed_demo.sql` ไว้: บัญชีทดลองทั้งหมดใช้รหัส `Demo@1234`
   ควรปิดใช้งานบัญชีเหล่านี้ (เมนูจัดการผู้ใช้) ก่อนใช้กับข้อมูลจริง

## 8. ปรับค่าตามข้อจำกัดของโฮสต์

- ขนาดไฟล์อัปโหลดสูงสุดของโฮสต์อาจน้อยกว่าค่าตั้งต้นของระบบ (Proposal 20 MB)
  ตรวจค่า `upload_max_filesize` ที่ Control Panel > **PHP Config / PHP Info**
  แล้วปรับ `upload.proposal_max_mb` ในตาราง `system_settings` ให้ไม่เกินค่านั้น
- แก้รหัสวิชาและวันที่ภาคการศึกษา (ค่า `[CONFIRM]` ใน `seed.sql`)

## การอัปเดตเวอร์ชันใหม่

1. สร้าง zip ใหม่ (`php tools/package.php`) แล้วอัปโหลดทับโฟลเดอร์ `app`, `public`, `resources`, `routes`
   **ห้ามทับ** `config/local.php` และ `storage/uploads/`
2. ถ้าเวอร์ชันใหม่เปลี่ยนฐานข้อมูล จะมีไฟล์ migration แยกให้ ห้าม import `schema_shared_hosting.sql` ซ้ำ
   เพราะจะลบข้อมูลทั้งหมด

## แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ / วิธีแก้ |
| --- | --- |
| หน้าแสดง "Error 500" หรือ "ระบบขัดข้อง" | ค่าฐานข้อมูลใน `config/local.php` ผิด (hostname ต้องเป็น `sqlXXX.infinityfree.com`) ตรวจ log ที่ `storage/logs/` |
| เข้า Login แล้วเด้งกลับหน้าเดิม / "แบบฟอร์มหมดอายุ" (419) | ใช้ `http://` แต่ `secure_cookie` เป็น `true` ดูขั้นตอนที่ 6 |
| หน้าเว็บไม่มีสี / ไม่มี CSS | ไม่ได้อัปโหลด `.htaccess` ที่ราก `htdocs` หรืออัปโหลดเป็น `htdocs/htdocs/...` |
| เห็นรายการไฟล์หรือ 404 ทุกหน้า | ไฟล์ไม่ได้อยู่ระดับเดียวกับ `htdocs` ตามขั้นตอนที่ 4 |
| Import แล้วขึ้น "Access denied" เรื่อง TRIGGER | import `schema.sql` ผิดไฟล์ ให้ใช้ `schema_shared_hosting.sql` |

## ข้อจำกัดของโฮสต์ฟรี

เหมาะกับเดโมและทดสอบ ไม่เหมาะกับการใช้งานจริงที่มีข้อมูลนิสิต
ไม่มี SLA, ไม่มีระบบสำรองข้อมูลอัตโนมัติ (ควร Export ฐานข้อมูลจาก phpMyAdmin และดาวน์โหลด
`storage/uploads/` เป็นระยะ) และบัญชีอาจถูกระงับหากใช้ทรัพยากรเกินเกณฑ์
