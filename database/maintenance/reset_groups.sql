-- =============================================================================
-- PPMS: ลบข้อมูลกลุ่มโครงงานทั้งหมด (เริ่มสร้างกลุ่มใหม่)
--
-- ลบ:   กลุ่ม สมาชิก คำขออาจารย์ที่ปรึกษา โครงงาน หัวข้อ Proposal (ร่าง/ทุก version)
--       การตรวจทีละบท กรรมการ ผลประเมิน คำตัดสิน การแก้ไข บันทึกการเข้าพบ
--       แบบอนุมัติขอบเขต ข้อมูลไฟล์แนบ ประวัติสถานะ และการแจ้งเตือนที่เกี่ยวข้อง
-- คงไว้: บัญชีผู้ใช้ (นิสิต อาจารย์ admin) บทบาท ปีการศึกษา/รายวิชา กำหนดการ
--       เกณฑ์การประเมิน ค่าตั้งระบบ และ audit log
--
-- !!! ลบแล้วกู้คืนไม่ได้ ให้ Export ฐานข้อมูลเก็บไว้ก่อน (phpMyAdmin > Export > Go)
-- วิธีใช้: phpMyAdmin > เลือกฐานข้อมูล PPMS > แท็บ Import (หรือ SQL) > ไฟล์นี้ > Go
-- รหัสกลุ่มจะเริ่มนับใหม่ที่ ...-001
-- =============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- การแจ้งเตือนที่อ้างถึงกลุ่ม/โครงงาน (เก็บการแจ้งเตือนอื่นไว้)
DELETE FROM notifications
 WHERE related_type IN ('group','topic','proposal','assignment','evaluation','progress_report','scope','proposal_chapter');

-- ขั้นตอนท้าย -> ต้น (TRUNCATE ลบทั้งตารางและเริ่มเลข id ใหม่)
TRUNCATE TABLE scope_approvals;
TRUNCATE TABLE scope_submissions;
TRUNCATE TABLE progress_reports;
TRUNCATE TABLE presentation_evaluations;
TRUNCATE TABLE evaluation_comments;
TRUNCATE TABLE evaluation_scores;
TRUNCATE TABLE committee_evaluations;
TRUNCATE TABLE revision_responses;
TRUNCATE TABLE revision_requests;
TRUNCATE TABLE approval_decisions;
TRUNCATE TABLE committee_assignments;
TRUNCATE TABLE proposal_chapter_reviews;
TRUNCATE TABLE proposal_drafts;
TRUNCATE TABLE proposal_versions;
TRUNCATE TABLE proposal_submissions;
TRUNCATE TABLE topic_approvals;
TRUNCATE TABLE topic_submissions;
TRUNCATE TABLE project_topics;
TRUNCATE TABLE projects;
TRUNCATE TABLE project_advisors;
TRUNCATE TABLE group_member_histories;
TRUNCATE TABLE project_members;
TRUNCATE TABLE project_groups;
TRUNCATE TABLE document_files;
TRUNCATE TABLE status_histories;

-- เกณฑ์การประเมินที่ถูกล็อกเพราะมีผลประเมิน: ปลดล็อก
UPDATE course_offerings SET rubric_locked_at = NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ตรวจผล: ทุกค่าควรเป็น 0 และ users ยังมีครบ
SELECT (SELECT COUNT(*) FROM project_groups) AS groups_left,
       (SELECT COUNT(*) FROM projects) AS projects_left,
       (SELECT COUNT(*) FROM proposal_versions) AS proposals_left,
       (SELECT COUNT(*) FROM users) AS users_kept,
       (SELECT COUNT(*) FROM students) AS students_kept;
