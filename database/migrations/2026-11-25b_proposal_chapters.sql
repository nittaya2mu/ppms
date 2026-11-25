-- =============================================================================
-- PPMS migration 2026-11-25 (ชุดที่ 2): ร่าง Proposal, บทที่ 2-3 บนเว็บ, ตรวจทีละบท
-- ต้อง import 2026-11-25_department_forms.sql ก่อน
-- phpMyAdmin: เลือกฐานข้อมูล PPMS -> Import ไฟล์นี้ -> Go  (ไม่ลบข้อมูลเดิม, รันซ้ำได้)
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE document_files
    MODIFY owner_type ENUM('topic_submission','proposal_version','revision_response','proposal_draft','other') NOT NULL;

ALTER TABLE proposal_versions
    ADD COLUMN IF NOT EXISTS ch2_json MEDIUMTEXT NULL AFTER ch3_file_id,
    ADD COLUMN IF NOT EXISTS ch3_json MEDIUMTEXT NULL AFTER ch2_json;

CREATE TABLE IF NOT EXISTS proposal_drafts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NOT NULL,
    content_json MEDIUMTEXT NOT NULL,
    ch1_file_id  BIGINT UNSIGNED NULL,
    ch2_file_id  BIGINT UNSIGNED NULL,
    ch3_file_id  BIGINT UNSIGNED NULL,
    updated_by   BIGINT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proposal_drafts_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_chapter_reviews (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id     BIGINT UNSIGNED NOT NULL,
    chapter        TINYINT UNSIGNED NOT NULL,
    target_version SMALLINT UNSIGNED NOT NULL,
    content_json   MEDIUMTEXT NOT NULL,
    file_id        BIGINT UNSIGNED NOT NULL,
    status         ENUM('submitted','approved','revision_required') NOT NULL DEFAULT 'submitted',
    submitted_by   BIGINT UNSIGNED NOT NULL,
    submitted_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by    BIGINT UNSIGNED NULL,
    reviewed_at    DATETIME NULL,
    comments       TEXT NULL,
    PRIMARY KEY (id),
    KEY idx_pcr_project (project_id, target_version, chapter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value, value_type, description) VALUES
 ('proposal.chapter_review_required', 'true', 'bool', 'ต้องให้อาจารย์ที่ปรึกษาอนุมัติบทที่ 1-3 ทีละบทก่อนส่ง Proposal');
