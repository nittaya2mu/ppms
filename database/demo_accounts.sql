-- PPMS: สร้าง/ซ่อมบัญชี demo (รันซ้ำได้, ใช้ได้ทั้งกรณีเคยหรือไม่เคย import seed_demo.sql)
-- รหัสผ่านทุกบัญชี demo = Demo@1234
-- ไม่กำหนดเลข id ตายตัว จึงไม่ชนกับบัญชีจริงที่มีอยู่แล้ว
SET NAMES utf8mb4;
SET @pw = '$2y$10$UVXelNRcIbFEE5srqPtWN.0Rh0481Dy/32iqkq0rR9ncoQJkePR82';

-- 1) บัญชีนิสิต demo ชื่อเดิม (6510000001 ...) เปลี่ยนเป็น student1 ...
UPDATE users SET username = CONCAT('student', CAST(RIGHT(username, 1) AS UNSIGNED)),
                 email    = CONCAT('student', CAST(RIGHT(username, 1) AS UNSIGNED), '@ppms.local')
 WHERE username IN ('6510000001','6510000002','6510000003','6510000004','6510000005','6510000006')
   AND email LIKE '%@ppms.local';

-- 2) สร้างบัญชีที่ยังไม่มี / ตั้งรหัสผ่านใหม่และปลดล็อกบัญชีที่มีแล้ว
INSERT INTO users (username, email, password_hash, title_th, first_name_th, last_name_th, first_name_en, last_name_en, is_active, must_change_password) VALUES
 ('advisor1',   'advisor1@ppms.local',   @pw, 'ผศ.ดร.', 'สมชาย',  'ใจดี',      'Somchai', 'Jaidee',     1, 0),
 ('advisor2',   'advisor2@ppms.local',   @pw, 'อ.ดร.',  'สมหญิง', 'รักเรียน',  'Somying', 'Rakrian',    1, 0),
 ('committee1', 'committee1@ppms.local', @pw, 'รศ.ดร.', 'วิชัย',  'ประเมินดี', 'Wichai',  'Pramoendee', 1, 0),
 ('committee2', 'committee2@ppms.local', @pw, 'ผศ.',    'กมล',   'ตรวจสอบ',   'Kamol',   'Truatsop',   1, 0),
 ('student1',   'student1@ppms.local',   @pw, 'นาย',    'ธนา',   'ขยันยิ่ง',  'Thana',   'Khayanying', 1, 0),
 ('student2',   'student2@ppms.local',   @pw, 'นางสาว', 'ปิยะ',  'ตั้งใจ',    'Piya',    'Tangjai',    1, 0),
 ('student3',   'student3@ppms.local',   @pw, 'นาย',    'กิตติ', 'มุ่งมั่น',  'Kitti',   'Mungman',    1, 0),
 ('student4',   'student4@ppms.local',   @pw, 'นางสาว', 'อรุณี', 'แสงทอง',   'Arunee',  'Saengthong', 1, 0),
 ('student5',   'student5@ppms.local',   @pw, 'นาย',    'ภูมิ',  'พัฒนา',    'Phum',    'Phatthana',  1, 0),
 ('student6',   'student6@ppms.local',   @pw, 'นางสาว', 'ดารา',  'ศรีสุข',   'Dara',    'Srisuk',     1, 0)
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1, must_change_password = 0,
                        failed_login_count = 0, locked_until = NULL, deleted_at = NULL;

-- 3) บทบาท: advisor = Advisor + Committee, committee = Committee, student = Student
INSERT IGNORE INTO user_roles (user_id, role_id, granted_by)
 SELECT u.id, r.role_id, 1 FROM users u JOIN (
   SELECT 'advisor1' n, 3 role_id UNION ALL SELECT 'advisor1', 4 UNION ALL SELECT 'advisor2', 3 UNION ALL SELECT 'advisor2', 4
   UNION ALL SELECT 'committee1', 4 UNION ALL SELECT 'committee2', 4
   UNION ALL SELECT 'student1', 2 UNION ALL SELECT 'student2', 2 UNION ALL SELECT 'student3', 2
   UNION ALL SELECT 'student4', 2 UNION ALL SELECT 'student5', 2 UNION ALL SELECT 'student6', 2) r ON r.n = u.username;
UPDATE user_roles ur JOIN users u ON u.id = ur.user_id SET ur.is_active = 1 WHERE u.email LIKE '%@ppms.local' AND u.username <> 'admin';

-- 4) ข้อมูลนิสิต (รหัสนิสิต 6510000001 ... 6510000006)
INSERT IGNORE INTO students (user_id, student_code, program, department, entry_year_be)
 SELECT u.id, CONCAT('651000000', RIGHT(u.username, 1)),
        IF(RIGHT(u.username, 1) IN ('4','5'), 'วิศวกรรมไฟฟ้า', 'วิศวกรรมคอมพิวเตอร์'), 'วิศวกรรมไฟฟ้าและคอมพิวเตอร์', 2565
 FROM users u WHERE u.username IN ('student1','student2','student3','student4','student5','student6');

-- ผลลัพธ์: ควรเห็น 10 บัญชี roles ครบ และ hash_ok = 1
SELECT u.username, u.email, u.is_active, u.locked_until, LENGTH(u.password_hash) = 60 AS hash_ok,
       GROUP_CONCAT(ur.role_id ORDER BY ur.role_id) AS roles, s.student_code
FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN students s ON s.user_id = u.id
WHERE u.email LIKE '%@ppms.local' AND u.username <> 'admin' GROUP BY u.id ORDER BY u.username;
