-- =============================================================================
-- PPMS seed data (required). Run after schema.sql.
--   mysql -u root -p ppms < database/seed.sql   (select the PPMS database first in phpMyAdmin)
--
-- Default administrator:  username = admin  /  password = Admin@1234
-- The system forces a password change on first login.
--
-- Values marked [CONFIRM] are placeholders: replace them with the department's
-- real course code and semester dates through the Admin screens or here.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';


-- Roles (T01) -----------------------------------------------------------------
INSERT INTO roles (id, code, name_th, name_en) VALUES
 (1, 'admin',     'ผู้ดูแลระบบ / ผู้ประสานงานรายวิชา', 'System Administrator / Course Coordinator'),
 (2, 'student',   'นิสิต',                              'Student'),
 (3, 'advisor',   'อาจารย์ที่ปรึกษาโครงงาน',            'Project Advisor'),
 (4, 'committee', 'กรรมการประเมินโครงงาน',              'Project Committee');

-- Default administrator (T02, T03) -------------------------------------------
INSERT INTO users (id, username, email, password_hash, title_th, first_name_th, last_name_th,
                   first_name_en, last_name_en, is_active, must_change_password)
VALUES (1, 'admin', 'admin@ppms.local',
        '$2y$10$yasx1Q1vVQ6MxEu44UHPU.phEtdsfcqmnbkEbURXs5iBJsG.7DJ7e',
        NULL, 'ผู้ดูแล', 'ระบบ', 'System', 'Administrator', 1, 1);

INSERT INTO user_roles (user_id, role_id, is_active, granted_by) VALUES (1, 1, 1, NULL);

-- Academic structure (T05-T08) [CONFIRM dates and course code] ----------------
INSERT INTO academic_years (id, year_be, start_date, end_date, is_current)
VALUES (1, 2569, '2026-06-01', '2027-05-31', 1);

INSERT INTO semesters (id, academic_year_id, term_no, start_date, end_date)
VALUES (1, 1, 1, '2026-06-22', '2026-10-31'),
       (2, 1, 2, '2026-11-09', '2027-03-31');

INSERT INTO courses (id, course_code, name_th, name_en, credits, is_active)
VALUES (1, 'PROJ-PREP', 'การเตรียมความพร้อมโครงงาน', 'Project Preparation', NULL, 1);

INSERT INTO course_offerings (id, course_id, semester_id, coordinator_id, status)
VALUES (1, 1, 2, 1, 'open');

-- Default evaluation rubric (T21), Section 9.1 --------------------------------
INSERT INTO evaluation_criteria (offering_id, seq_no, name_th, name_en, weight) VALUES
 (1, 1, 'ที่มาและความสำคัญ',                    'Background and Significance',            15.00),
 (1, 2, 'การกำหนดปัญหาและวัตถุประสงค์',          'Problem Definition and Objectives',      15.00),
 (1, 3, 'การทบทวนวรรณกรรมและงานที่เกี่ยวข้อง',     'Literature Review and Related Work',     15.00),
 (1, 4, 'วิธีดำเนินงานและความเป็นไปได้ทางเทคนิค',  'Methodology and Technical Feasibility',  25.00),
 (1, 5, 'ขอบเขตโครงงานและผลที่คาดว่าจะได้รับ',     'Project Scope and Expected Outcomes',    15.00),
 (1, 6, 'การวางแผนโครงงานและการนำเสนอ',          'Project Planning and Presentation',      15.00);

-- System settings (T32): proposed defaults from Phase 1 section 12 -------------
INSERT INTO system_settings (setting_key, setting_value, value_type, description) VALUES
 ('group.min_members',               '2',     'int',    'OQ-01 จำนวนสมาชิกขั้นต่ำต่อกลุ่ม (รวม Leader)'),
 ('group.max_members',               '3',     'int',    'OQ-01 จำนวนสมาชิกสูงสุดต่อกลุ่ม (รวม Leader)'),
 ('group.invite_requires_accept',    'true',  'bool',   'OQ-02 สมาชิกต้องตอบรับคำเชิญ'),
 ('advisor.requires_accept',         'false', 'bool',   'OQ-03 Advisor ต้องตอบรับก่อน Admin มอบหมาย'),
 ('deadline.allow_late_default',     'true',  'bool',   'OQ-05 รับงานหลังกำหนดและทำเครื่องหมาย late'),
 ('decision.block_conflicted_admin', 'true',  'bool',   'OQ-08 ห้าม Admin ที่เป็น Advisor/Committee ตัดสินขั้นสุดท้ายเมื่อมี Admin คนอื่น'),
 ('topic.admin_revision_route',      'advisor','string','OQ-10 advisor = ผ่าน Advisor อีกรอบ, admin = ส่งตรง Admin'),
 ('evaluation.peer_visible_after_both','true','bool',   'OQ-11 Committee เห็นผลของอีกคนหลังส่งครบ'),
 ('evaluation.release_on_decision',  'true',  'bool',   'OQ-12 Release ผลให้นิสิตเมื่อบันทึก Final Decision'),
 ('evaluation.show_committee_names', 'false', 'bool',   'OQ-12 แสดงชื่อกรรมการให้นิสิตเห็น'),
 ('decision.minor_requires_revision','true',  'bool',   'OQ-14 Approved with Minor Revisions ต้องส่งฉบับแก้'),
 ('decision.justify_when',           'any',   'string', 'OQ-15 any = ต่างจาก recommendation อย่างน้อย 1 คน, both = ต่างจากทั้งสองคน'),
 ('evaluation.score_gap_flag',       '0',     'int',    'OQ-16 flag เมื่อ |S1-S2| >= ค่านี้ (0 = ปิด)'),
 ('revision.max_rounds',             '0',     'int',    'OQ-19 จำนวนรอบแก้ไขสูงสุด (0 = ไม่จำกัด)'),
 ('upload.proposal_max_mb',          '20',    'int',    'OQ-20 ขนาด Proposal PDF สูงสุด (MB)'),
 ('upload.response_max_mb',          '10',    'int',    'OQ-20 ขนาด Response-to-comments สูงสุด (MB)'),
 ('upload.topic_max_mb',             '10',    'int',    'OQ-20 ขนาดเอกสารประกอบหัวข้อสูงสุด (MB)'),
 ('upload.topic_allowed_ext',        '["pdf","docx","png","jpg","jpeg"]', 'json', 'OQ-20 นามสกุลเอกสารประกอบหัวข้อ'),
 ('session.timeout_min',             '60',    'int',    'หมดอายุ session เมื่อไม่ใช้งาน (นาที)'),
 ('email.enabled',                   'false', 'bool',   'เปิดส่งอีเมลผ่าน email extension');
