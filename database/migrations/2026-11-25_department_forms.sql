-- =============================================================================
-- PPMS migration 2026-11-25: แบบฟอร์มของภาควิชา
--   แบบฟอร์ม 1 (ขออนุมัติหัวข้อ), บทที่ 1 + ไฟล์บทที่ 1-3, แบบฟอร์ม 2 (ประเมินการนำเสนอ),
--   แบบฟอร์ม 8 (บันทึกการเข้าพบอาจารย์ที่ปรึกษา), แบบฟอร์ม 7 (อนุมัติขอบเขต)
--
-- ใช้กับฐานข้อมูลที่ติดตั้งไว้ก่อนวันที่ 25 พ.ย. 2569 (InfinityFree หรือ localhost)
-- phpMyAdmin: เลือกฐานข้อมูล PPMS -> Import ไฟล์นี้ -> Go
-- ไม่ลบข้อมูลเดิม และรันซ้ำได้
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE status_histories
    MODIFY entity_type ENUM('group','topic','proposal','assignment','evaluation','scope') NOT NULL;

ALTER TABLE topic_submissions
    ADD COLUMN IF NOT EXISTS project_type       VARCHAR(30)  NULL AFTER references_text,
    ADD COLUMN IF NOT EXISTS project_type_other VARCHAR(200) NULL AFTER project_type,
    ADD COLUMN IF NOT EXISTS co_advisor_name    VARCHAR(200) NULL AFTER project_type_other;

ALTER TABLE proposal_versions
    ADD COLUMN IF NOT EXISTS ch1_background TEXT NULL AFTER sections_checklist,
    ADD COLUMN IF NOT EXISTS ch1_objectives TEXT NULL AFTER ch1_background,
    ADD COLUMN IF NOT EXISTS ch1_scope      TEXT NULL AFTER ch1_objectives,
    ADD COLUMN IF NOT EXISTS ch1_benefits   TEXT NULL AFTER ch1_scope,
    ADD COLUMN IF NOT EXISTS ch1_tools      TEXT NULL AFTER ch1_benefits,
    ADD COLUMN IF NOT EXISTS ch1_plan       TEXT NULL AFTER ch1_tools,
    ADD COLUMN IF NOT EXISTS ch2_file_id    BIGINT UNSIGNED NULL AFTER ch1_plan,
    ADD COLUMN IF NOT EXISTS ch3_file_id    BIGINT UNSIGNED NULL AFTER ch2_file_id;

CREATE TABLE IF NOT EXISTS presentation_evaluations (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id  BIGINT UNSIGNED NOT NULL,
    evaluator_id   BIGINT UNSIGNED NOT NULL,
    evaluator_role ENUM('advisor','committee1','committee2') NOT NULL,
    status         ENUM('not_started','draft','submitted','reopened') NOT NULL DEFAULT 'not_started',
    objectives_ok  TINYINT(1) NULL,
    scope_ok       TINYINT(1) NULL,
    methodology_ok TINYINT(1) NULL,
    literature_ok  TINYINT(1) NULL,
    grades_json    TEXT NULL,
    total_score    DECIMAL(5,2) NULL,
    letter_grade   VARCHAR(2) NULL,
    result         ENUM('pass','revise','fail') NULL,
    suggestions    TEXT NULL,
    submitted_at   DATETIME NULL,
    reopened_by    BIGINT UNSIGNED NULL,
    reopened_at    DATETIME NULL,
    reopen_reason  TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pe_role (assignment_id, evaluator_role),
    KEY idx_pe_evaluator (evaluator_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progress_reports (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NOT NULL,
    report_no    TINYINT UNSIGNED NOT NULL,
    meeting_date DATE NOT NULL,
    work_summary TEXT NOT NULL,
    status       ENUM('submitted','reviewed') NOT NULL DEFAULT 'submitted',
    submitted_by BIGINT UNSIGNED NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by  BIGINT UNSIGNED NULL,
    suggestions  TEXT NULL,
    score        DECIMAL(5,2) NULL,
    reviewed_at  DATETIME NULL,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_progress_no (project_id, report_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scope_submissions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NOT NULL,
    version_no   SMALLINT UNSIGNED NOT NULL,
    items_json   TEXT NOT NULL,
    status       ENUM('pending','approved','revision_required') NOT NULL DEFAULT 'pending',
    submitted_by BIGINT UNSIGNED NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scope_version (project_id, version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scope_approvals (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope_id      BIGINT UNSIGNED NOT NULL,
    approver_id   BIGINT UNSIGNED NOT NULL,
    approver_role ENUM('advisor','committee1','committee2') NOT NULL,
    decision      ENUM('approved','revision_required') NOT NULL,
    comments      TEXT NULL,
    decided_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scope_role (scope_id, approver_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value, value_type, description) VALUES
 ('progress.min_reports',            '3',     'int',    'จำนวนครั้งขั้นต่ำของบันทึกการเข้าพบอาจารย์ที่ปรึกษา (ต้องได้รับการประเมินครบก่อนส่งแบบอนุมัติขอบเขต)'),
 ('progress.max_reports',            '5',     'int',    'จำนวนครั้งสูงสุดของบันทึกการเข้าพบอาจารย์ที่ปรึกษา'),
 ('progress.rubric',                 '[{"score": 30, "label": "ทำงานได้ > 80% (งานออกมาตรงกับขอบเขต ฟังก์ชันหลักทำงานได้อยู่ในขั้นดีมาก)"}, {"score": 25, "label": "ทำงานได้ < 80% (งานออกมาตรงกับขอบเขต ฟังก์ชันหลักทำงานได้อยู่ในขั้นดี)"}, {"score": 20, "label": "ทำงานได้ < 70% (งานออกมาตรงกับขอบเขต ฟังก์ชันหลักทำงานได้ในระดับที่น่าพอใจ)"}, {"score": 15, "label": "ทำงานได้ < 60% (งานออกมาตรงกับขอบเขต แต่ไม่ใช่ฟังก์ชันหลัก)"}, {"score": 10, "label": "ทำงานได้ < 50% (งานออกมาไม่ดี ไม่ตรงกับขอบเขต)"}, {"score": 0, "label": "ไม่มีความก้าวหน้าของงานที่ได้รับมอบหมาย"}]', 'json', 'เกณฑ์คะแนนการรายงานความก้าวหน้า (แบบฟอร์ม 8) ต่อครั้ง'),
 ('upload.chapter_max_mb',           '20',    'int',    'ขนาดไฟล์เอกสารบทที่ 1-3 สูงสุด (MB) ต่อไฟล์'),
 ('upload.chapter_allowed_ext',      '["pdf","docx","doc"]', 'json', 'นามสกุลไฟล์เอกสารบทที่ 1-3');

-- Evaluation rounds already assigned before this update: create the form-2 records
-- (advisor + committee 1 + committee 2) so they can be evaluated with the new form.
INSERT IGNORE INTO presentation_evaluations (assignment_id, evaluator_id, evaluator_role)
    SELECT id, advisor_id, 'advisor' FROM committee_assignments WHERE status = 'active';
INSERT IGNORE INTO presentation_evaluations (assignment_id, evaluator_id, evaluator_role)
    SELECT id, member1_id, 'committee1' FROM committee_assignments WHERE status = 'active';
INSERT IGNORE INTO presentation_evaluations (assignment_id, evaluator_id, evaluator_role)
    SELECT id, member2_id, 'committee2' FROM committee_assignments WHERE status = 'active';
