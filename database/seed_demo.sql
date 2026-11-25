-- =============================================================================
-- PPMS demo data (OPTIONAL, for development and the acceptance test only).
-- Do NOT load on a production server.
-- On a server that already has real accounts, use database/demo_accounts.sql instead
-- (no fixed ids, safe to re-run).
--   mysql -u root -p ppms < database/seed_demo.sql
--
-- All demo accounts use password: Demo@1234 (no forced change)
--   advisor1, advisor2           role advisor (+ committee)
--   committee1, committee2       role committee
--   student1 ... student6        role student (student codes 6510000001 ... 6510000006)
-- =============================================================================

SET NAMES utf8mb4;


SET @pw = '$2y$10$UVXelNRcIbFEE5srqPtWN.0Rh0481Dy/32iqkq0rR9ncoQJkePR82';

INSERT INTO users (id, username, email, password_hash, title_th, first_name_th, last_name_th, first_name_en, last_name_en, must_change_password) VALUES
 (101, 'advisor1',   'advisor1@ppms.local',   @pw, 'ผศ.ดร.', 'สมชาย',  'ใจดี',     'Somchai',  'Jaidee',    0),
 (102, 'advisor2',   'advisor2@ppms.local',   @pw, 'อ.ดร.',  'สมหญิง', 'รักเรียน', 'Somying',  'Rakrian',   0),
 (103, 'committee1', 'committee1@ppms.local', @pw, 'รศ.ดร.', 'วิชัย',  'ประเมินดี', 'Wichai',  'Pramoendee', 0),
 (104, 'committee2', 'committee2@ppms.local', @pw, 'ผศ.',    'กมล',   'ตรวจสอบ',  'Kamol',    'Truatsop',  0),
 (201, 'student1',   'student1@ppms.local'  , @pw, 'นาย',    'ธนา',   'ขยันยิ่ง', 'Thana',    'Khayanying', 0),
 (202, 'student2',   'student2@ppms.local'  , @pw, 'นางสาว', 'ปิยะ',  'ตั้งใจ',   'Piya',     'Tangjai',   0),
 (203, 'student3',   'student3@ppms.local'  , @pw, 'นาย',    'กิตติ', 'มุ่งมั่น', 'Kitti',    'Mungman',   0),
 (204, 'student4',   'student4@ppms.local'  , @pw, 'นางสาว', 'อรุณี', 'แสงทอง',  'Arunee',   'Saengthong', 0),
 (205, 'student5',   'student5@ppms.local'  , @pw, 'นาย',    'ภูมิ',  'พัฒนา',   'Phum',     'Phatthana', 0),
 (206, 'student6',   'student6@ppms.local'  , @pw, 'นางสาว', 'ดารา',  'ศรีสุข',  'Dara',     'Srisuk',    0);

INSERT INTO user_roles (user_id, role_id, granted_by) VALUES
 (101, 3, 1), (101, 4, 1),
 (102, 3, 1), (102, 4, 1),
 (103, 4, 1),
 (104, 4, 1),
 (201, 2, 1), (202, 2, 1), (203, 2, 1), (204, 2, 1), (205, 2, 1), (206, 2, 1);

INSERT INTO students (user_id, student_code, program, department, entry_year_be) VALUES
 (201, '6510000001', 'วิศวกรรมคอมพิวเตอร์', 'วิศวกรรมไฟฟ้าและคอมพิวเตอร์', 2565),
 (202, '6510000002', 'วิศวกรรมคอมพิวเตอร์', 'วิศวกรรมไฟฟ้าและคอมพิวเตอร์', 2565),
 (203, '6510000003', 'วิศวกรรมคอมพิวเตอร์', 'วิศวกรรมไฟฟ้าและคอมพิวเตอร์', 2565),
 (204, '6510000004', 'วิศวกรรมไฟฟ้า',      'วิศวกรรมไฟฟ้าและคอมพิวเตอร์', 2565),
 (205, '6510000005', 'วิศวกรรมไฟฟ้า',      'วิศวกรรมไฟฟ้าและคอมพิวเตอร์', 2565),
 (206, '6510000006', 'วิศวกรรมคอมพิวเตอร์', 'วิศวกรรมไฟฟ้าและคอมพิวเตอร์', 2565);

INSERT INTO deadlines (offering_id, deadline_type, due_at) VALUES
 (1, 'group_registration',   '2026-12-04 23:59:00'),
 (1, 'topic_submission',     '2026-12-25 23:59:00'),
 (1, 'proposal_submission',  '2027-02-12 23:59:00'),
 (1, 'committee_evaluation', '2027-02-26 23:59:00'),
 (1, 'revision_submission',  '2027-03-19 23:59:00');
