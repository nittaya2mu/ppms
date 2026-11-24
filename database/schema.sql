-- =============================================================================
-- PPMS - Project Preparation Management System
-- Database schema (MySQL 8.0+ / MariaDB 10.6+)
-- Source of truth: Phase 1 design doc, section 9 (Data Dictionary, T01-T32)
--
-- Import with phpMyAdmin (Import tab) or:
--   mysql -u root -p < database/schema.sql
-- The script drops and recreates the `ppms` database.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

DROP DATABASE IF EXISTS ppms;
CREATE DATABASE ppms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ppms;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 9.1 Users and academic structure
-- -----------------------------------------------------------------------------

-- T01
CREATE TABLE roles (
    id          TINYINT UNSIGNED NOT NULL,
    code        VARCHAR(30)  NOT NULL,
    name_th     VARCHAR(100) NOT NULL,
    name_en     VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB;

-- T02
CREATE TABLE users (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username             VARCHAR(50)  NOT NULL,
    email                VARCHAR(150) NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    title_th             VARCHAR(50)  NULL,
    first_name_th        VARCHAR(100) NOT NULL,
    last_name_th         VARCHAR(100) NOT NULL,
    first_name_en        VARCHAR(100) NULL,
    last_name_en         VARCHAR(100) NULL,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 1,
    failed_login_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until         DATETIME     NULL,
    last_login_at        DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at           DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_active (is_active, deleted_at)
) ENGINE=InnoDB;

-- T03
CREATE TABLE user_roles (
    user_id    BIGINT UNSIGNED  NOT NULL,
    role_id    TINYINT UNSIGNED NOT NULL,
    is_active  TINYINT(1)       NOT NULL DEFAULT 1,
    granted_by BIGINT UNSIGNED  NULL,
    granted_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    KEY idx_user_roles_role (role_id, is_active),
    CONSTRAINT fk_user_roles_user    FOREIGN KEY (user_id)    REFERENCES users (id),
    CONSTRAINT fk_user_roles_role    FOREIGN KEY (role_id)    REFERENCES roles (id),
    CONSTRAINT fk_user_roles_granter FOREIGN KEY (granted_by) REFERENCES users (id)
) ENGINE=InnoDB;

-- T04
CREATE TABLE students (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,
    student_code  VARCHAR(20)  NOT NULL,
    program       VARCHAR(150) NOT NULL,
    department    VARCHAR(150) NOT NULL,
    entry_year_be SMALLINT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_user (user_id),
    UNIQUE KEY uq_students_code (student_code),
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- T05
CREATE TABLE academic_years (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    year_be    SMALLINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date   DATE NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_years_year (year_be),
    CONSTRAINT chk_academic_years_dates CHECK (start_date < end_date)
) ENGINE=InnoDB;

-- T06
CREATE TABLE semesters (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    term_no          TINYINT UNSIGNED NOT NULL,
    start_date       DATE NOT NULL,
    end_date         DATE NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_semesters_year_term (academic_year_id, term_no),
    CONSTRAINT fk_semesters_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
    CONSTRAINT chk_semesters_term  CHECK (term_no BETWEEN 1 AND 3),
    CONSTRAINT chk_semesters_dates CHECK (start_date < end_date)
) ENGINE=InnoDB;

-- T07
CREATE TABLE courses (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_code VARCHAR(20)  NOT NULL,
    name_th     VARCHAR(200) NOT NULL,
    name_en     VARCHAR(200) NOT NULL,
    credits     VARCHAR(20)  NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courses_code (course_code)
) ENGINE=InnoDB;

-- T08
CREATE TABLE course_offerings (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id        BIGINT UNSIGNED NOT NULL,
    semester_id      BIGINT UNSIGNED NOT NULL,
    coordinator_id   BIGINT UNSIGNED NOT NULL,
    status           ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
    rubric_locked_at DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_offerings_course_semester (course_id, semester_id),
    CONSTRAINT fk_offerings_course      FOREIGN KEY (course_id)      REFERENCES courses (id),
    CONSTRAINT fk_offerings_semester    FOREIGN KEY (semester_id)    REFERENCES semesters (id),
    CONSTRAINT fk_offerings_coordinator FOREIGN KEY (coordinator_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- T09
CREATE TABLE deadlines (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    offering_id   BIGINT UNSIGNED NOT NULL,
    deadline_type ENUM('group_registration','topic_submission','proposal_submission',
                       'committee_evaluation','revision_submission') NOT NULL,
    due_at        DATETIME NOT NULL,
    allow_late    TINYINT(1) NOT NULL DEFAULT 1,      -- OQ-05 default: accept and flag "late"
    reminder_days TINYINT UNSIGNED NOT NULL DEFAULT 2,
    note          VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_deadlines_offering_type (offering_id, deadline_type),
    CONSTRAINT fk_deadlines_offering FOREIGN KEY (offering_id) REFERENCES course_offerings (id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 9.2 Project groups and topics
-- -----------------------------------------------------------------------------

-- T10
CREATE TABLE project_groups (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    offering_id       BIGINT UNSIGNED NOT NULL,
    group_code        VARCHAR(20)  NOT NULL,
    group_name        VARCHAR(200) NOT NULL,
    leader_student_id BIGINT UNSIGNED NOT NULL,
    status            ENUM('draft','submitted','verified','active','inactive') NOT NULL DEFAULT 'draft',
    registered_at     DATETIME NULL,
    verified_by       BIGINT UNSIGNED NULL,
    verified_at       DATETIME NULL,
    reject_reason     TEXT NULL,
    lock_version      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_groups_offering_code (offering_id, group_code),
    KEY idx_groups_status (offering_id, status),
    CONSTRAINT fk_groups_offering FOREIGN KEY (offering_id)       REFERENCES course_offerings (id),
    CONSTRAINT fk_groups_leader   FOREIGN KEY (leader_student_id) REFERENCES students (id),
    CONSTRAINT fk_groups_verifier FOREIGN KEY (verified_by)       REFERENCES users (id)
) ENGINE=InnoDB;

-- T11
CREATE TABLE project_members (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id    BIGINT UNSIGNED NOT NULL,
    student_id  BIGINT UNSIGNED NOT NULL,
    offering_id BIGINT UNSIGNED NOT NULL,
    member_role ENUM('leader','member') NOT NULL DEFAULT 'member',
    status      ENUM('invited','active','declined','removed') NOT NULL DEFAULT 'invited',
    active_key  TINYINT GENERATED ALWAYS AS (IF(status = 'active', 1, NULL)) STORED,
    leader_key  TINYINT GENERATED ALWAYS AS (IF(status = 'active' AND member_role = 'leader', 1, NULL)) STORED,
    joined_at   DATETIME NULL,
    left_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_member_one_active_group (offering_id, student_id, active_key),  -- BR-2
    UNIQUE KEY uq_group_one_leader (group_id, leader_key),                        -- BR-1
    KEY idx_members_group (group_id, status),
    CONSTRAINT fk_members_group    FOREIGN KEY (group_id)    REFERENCES project_groups (id),
    CONSTRAINT fk_members_student  FOREIGN KEY (student_id)  REFERENCES students (id),
    CONSTRAINT fk_members_offering FOREIGN KEY (offering_id) REFERENCES course_offerings (id)
) ENGINE=InnoDB;

-- T12 (append-only)
CREATE TABLE group_member_histories (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id     BIGINT UNSIGNED NOT NULL,
    student_id   BIGINT UNSIGNED NOT NULL,
    action       ENUM('invited','joined','declined','added','removed','leader_changed') NOT NULL,
    reason       TEXT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    approved_by  BIGINT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gmh_group (group_id, created_at),
    CONSTRAINT fk_gmh_group     FOREIGN KEY (group_id)     REFERENCES project_groups (id),
    CONSTRAINT fk_gmh_student   FOREIGN KEY (student_id)   REFERENCES students (id),
    CONSTRAINT fk_gmh_requester FOREIGN KEY (requested_by) REFERENCES users (id),
    CONSTRAINT fk_gmh_approver  FOREIGN KEY (approved_by)  REFERENCES users (id)
) ENGINE=InnoDB;

-- T13
CREATE TABLE project_advisors (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id     BIGINT UNSIGNED NOT NULL,
    advisor_id   BIGINT UNSIGNED NOT NULL,
    status       ENUM('requested','assigned','declined','replaced') NOT NULL DEFAULT 'requested',
    active_key   TINYINT GENERATED ALWAYS AS (IF(status = 'assigned', 1, NULL)) STORED,
    requested_by BIGINT UNSIGNED NULL,
    assigned_by  BIGINT UNSIGNED NULL,
    assigned_at  DATETIME NULL,
    ended_at     DATETIME NULL,
    note         TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_group_one_advisor (group_id, active_key),
    KEY idx_advisors_advisor (advisor_id, status),
    CONSTRAINT fk_advisors_group     FOREIGN KEY (group_id)     REFERENCES project_groups (id),
    CONSTRAINT fk_advisors_user      FOREIGN KEY (advisor_id)   REFERENCES users (id),
    CONSTRAINT fk_advisors_requester FOREIGN KEY (requested_by) REFERENCES users (id),
    CONSTRAINT fk_advisors_assigner  FOREIGN KEY (assigned_by)  REFERENCES users (id)
) ENGINE=InnoDB;

-- T14
CREATE TABLE projects (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id          BIGINT UNSIGNED NOT NULL,
    offering_id       BIGINT UNSIGNED NOT NULL,
    project_code      VARCHAR(30)  NOT NULL,
    title_th          VARCHAR(500) NULL,
    title_en          VARCHAR(500) NULL,
    stage             ENUM('topic','proposal','approved','closed') NOT NULL DEFAULT 'topic',
    final_approved_at DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_group (group_id),
    UNIQUE KEY uq_projects_code (project_code),
    KEY idx_projects_offering (offering_id, stage),
    CONSTRAINT fk_projects_group    FOREIGN KEY (group_id)    REFERENCES project_groups (id),
    CONSTRAINT fk_projects_offering FOREIGN KEY (offering_id) REFERENCES course_offerings (id)
) ENGINE=InnoDB;

-- T15
CREATE TABLE project_topics (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id             BIGINT UNSIGNED NOT NULL,
    status                 ENUM('draft','submitted','advisor_review','advisor_approved','revision_required',
                                'admin_review','topic_approved','topic_rejected') NOT NULL DEFAULT 'draft',
    current_key            TINYINT GENERATED ALWAYS AS (IF(status <> 'topic_rejected', 1, NULL)) STORED,
    current_submission_id  BIGINT UNSIGNED NULL,
    approved_submission_id BIGINT UNSIGNED NULL,
    approved_at            DATETIME NULL,
    returned_from          ENUM('advisor','admin') NULL,
    lock_version           INT UNSIGNED NOT NULL DEFAULT 0,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_topics_one_current (project_id, current_key),
    KEY idx_topic_status (status),
    CONSTRAINT fk_topics_project FOREIGN KEY (project_id) REFERENCES projects (id)
    -- fk to topic_submissions added below (circular reference)
) ENGINE=InnoDB;

-- T16
CREATE TABLE topic_submissions (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    topic_id          BIGINT UNSIGNED NOT NULL,
    version_no        SMALLINT UNSIGNED NOT NULL,
    title_th          VARCHAR(500) NOT NULL,
    title_en          VARCHAR(500) NOT NULL,
    background        TEXT NULL,
    problem_statement TEXT NULL,
    objectives        TEXT NULL,
    scope             TEXT NULL,
    expected_outcomes TEXT NULL,
    methodology       TEXT NULL,
    expected_benefits TEXT NULL,
    related_work      TEXT NULL,
    references_text   TEXT NULL,
    revision_note     TEXT NULL,
    is_draft          TINYINT(1) NOT NULL DEFAULT 1,
    is_late           TINYINT(1) NOT NULL DEFAULT 0,
    submitted_by      BIGINT UNSIGNED NULL,
    submitted_at      DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_topic_submissions_version (topic_id, version_no),
    CONSTRAINT fk_topic_submissions_topic     FOREIGN KEY (topic_id)     REFERENCES project_topics (id),
    CONSTRAINT fk_topic_submissions_submitter FOREIGN KEY (submitted_by) REFERENCES users (id)
) ENGINE=InnoDB;

ALTER TABLE project_topics
    ADD CONSTRAINT fk_topics_current_submission  FOREIGN KEY (current_submission_id)  REFERENCES topic_submissions (id),
    ADD CONSTRAINT fk_topics_approved_submission FOREIGN KEY (approved_submission_id) REFERENCES topic_submissions (id);

-- T17 (append-only)
CREATE TABLE topic_approvals (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    topic_id      BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    level         ENUM('advisor','admin') NOT NULL,
    decision      ENUM('approved','revision_required','rejected') NOT NULL,
    comments      TEXT NULL,
    decided_by    BIGINT UNSIGNED NOT NULL,
    decided_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_topic_approvals_once (submission_id, level),
    KEY idx_topic_approvals_topic (topic_id, decided_at),
    CONSTRAINT fk_topic_approvals_topic      FOREIGN KEY (topic_id)      REFERENCES project_topics (id),
    CONSTRAINT fk_topic_approvals_submission FOREIGN KEY (submission_id) REFERENCES topic_submissions (id),
    CONSTRAINT fk_topic_approvals_user       FOREIGN KEY (decided_by)    REFERENCES users (id),
    CONSTRAINT chk_topic_approvals_comment CHECK (decision = 'approved' OR CHAR_LENGTH(TRIM(COALESCE(comments, ''))) > 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 9.3 Proposal, committee and evaluation
-- -----------------------------------------------------------------------------

-- T28 (created early: referenced by proposal_versions and revision_responses)
CREATE TABLE document_files (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_type    ENUM('topic_submission','proposal_version','revision_response','other') NOT NULL,
    owner_id      BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   CHAR(40)     NOT NULL,
    storage_path  VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(100) NOT NULL,
    file_size     INT UNSIGNED NOT NULL,
    sha256        CHAR(64)     NOT NULL,
    uploaded_by   BIGINT UNSIGNED NOT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_files_stored (stored_name),
    KEY idx_document_files_owner (owner_type, owner_id),
    KEY idx_document_files_sha (sha256),
    CONSTRAINT fk_document_files_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB;

-- T18
CREATE TABLE proposal_submissions (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id            BIGINT UNSIGNED NOT NULL,
    status                ENUM('draft','submitted','advisor_review','advisor_approved','committee_assignment',
                               'committee_evaluation','evaluation_completed','revision_required','re_evaluation',
                               'final_review','approved','approved_minor','rejected') NOT NULL DEFAULT 'draft',
    current_version_id    BIGINT UNSIGNED NULL,
    current_round_no      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    needs_admin_attention TINYINT(1) NOT NULL DEFAULT 0,
    released_at           DATETIME NULL,
    lock_version          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proposals_project (project_id),
    KEY idx_proposal_status (status),
    CONSTRAINT fk_proposals_project FOREIGN KEY (project_id) REFERENCES projects (id)
) ENGINE=InnoDB;

-- T19
CREATE TABLE proposal_versions (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proposal_id        BIGINT UNSIGNED NOT NULL,
    version_no         SMALLINT UNSIGNED NOT NULL,
    file_id            BIGINT UNSIGNED NOT NULL,
    title              VARCHAR(500) NOT NULL,
    sections_checklist JSON NULL,
    revision_notes     TEXT NULL,
    version_status     ENUM('submitted','under_advisor_review','returned','accepted_for_evaluation',
                            'evaluated','superseded') NOT NULL DEFAULT 'submitted',
    is_late            TINYINT(1) NOT NULL DEFAULT 0,
    submitted_by       BIGINT UNSIGNED NOT NULL,
    submitted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proposal_versions_no (proposal_id, version_no),
    CONSTRAINT fk_pv_proposal  FOREIGN KEY (proposal_id)  REFERENCES proposal_submissions (id),
    CONSTRAINT fk_pv_file      FOREIGN KEY (file_id)      REFERENCES document_files (id),
    CONSTRAINT fk_pv_submitter FOREIGN KEY (submitted_by) REFERENCES users (id)
) ENGINE=InnoDB;

ALTER TABLE proposal_submissions
    ADD CONSTRAINT fk_proposals_current_version FOREIGN KEY (current_version_id) REFERENCES proposal_versions (id);

-- T20
CREATE TABLE committee_assignments (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proposal_id            BIGINT UNSIGNED NOT NULL,
    proposal_version_id    BIGINT UNSIGNED NOT NULL,
    round_no               TINYINT UNSIGNED NOT NULL,
    advisor_id             BIGINT UNSIGNED NOT NULL,
    member1_id             BIGINT UNSIGNED NOT NULL,
    member2_id             BIGINT UNSIGNED NOT NULL,
    assigned_by            BIGINT UNSIGNED NOT NULL,
    assigned_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    evaluation_deadline    DATETIME NOT NULL,
    status                 ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    completed_at           DATETIME NULL,
    average_score          DECIMAL(5,2) NULL,
    recommendations_differ TINYINT(1) NULL,
    updated_at             DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ca_round (proposal_id, round_no),
    KEY idx_ca_member1 (member1_id, status),
    KEY idx_ca_member2 (member2_id, status),
    CONSTRAINT fk_ca_proposal FOREIGN KEY (proposal_id)         REFERENCES proposal_submissions (id),
    CONSTRAINT fk_ca_version  FOREIGN KEY (proposal_version_id) REFERENCES proposal_versions (id),
    CONSTRAINT fk_ca_advisor  FOREIGN KEY (advisor_id)          REFERENCES users (id),
    CONSTRAINT fk_ca_member1  FOREIGN KEY (member1_id)          REFERENCES users (id),
    CONSTRAINT fk_ca_member2  FOREIGN KEY (member2_id)          REFERENCES users (id),
    CONSTRAINT fk_ca_assigner FOREIGN KEY (assigned_by)         REFERENCES users (id),
    CONSTRAINT chk_ca_members_distinct CHECK (member1_id <> member2_id),
    CONSTRAINT chk_ca_average CHECK (average_score IS NULL OR average_score BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- T21
CREATE TABLE evaluation_criteria (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    offering_id BIGINT UNSIGNED NOT NULL,
    seq_no      TINYINT UNSIGNED NOT NULL,
    name_th     VARCHAR(200) NOT NULL,
    name_en     VARCHAR(200) NOT NULL,
    description TEXT NULL,
    weight      DECIMAL(5,2) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_criteria_seq (offering_id, seq_no),
    CONSTRAINT fk_criteria_offering FOREIGN KEY (offering_id) REFERENCES course_offerings (id),
    CONSTRAINT chk_criteria_weight CHECK (weight > 0 AND weight <= 100)
) ENGINE=InnoDB;

-- T22
CREATE TABLE committee_evaluations (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id  BIGINT UNSIGNED NOT NULL,
    evaluator_id   BIGINT UNSIGNED NOT NULL,
    member_slot    TINYINT UNSIGNED NOT NULL,
    status         ENUM('not_started','draft','submitted','reopened') NOT NULL DEFAULT 'not_started',
    total_score    DECIMAL(5,2) NULL,
    recommendation ENUM('approved','approved_minor','major_revisions','not_approved') NULL,
    submitted_at   DATETIME NULL,
    reopened_by    BIGINT UNSIGNED NULL,
    reopened_at    DATETIME NULL,
    reopen_reason  TEXT NULL,
    lock_version   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ce_evaluator (assignment_id, evaluator_id),
    UNIQUE KEY uq_ce_slot (assignment_id, member_slot),
    KEY idx_ce_evaluator (evaluator_id, status),
    CONSTRAINT fk_ce_assignment FOREIGN KEY (assignment_id) REFERENCES committee_assignments (id),
    CONSTRAINT fk_ce_evaluator  FOREIGN KEY (evaluator_id)  REFERENCES users (id),
    CONSTRAINT fk_ce_reopener   FOREIGN KEY (reopened_by)   REFERENCES users (id),
    CONSTRAINT chk_ce_slot  CHECK (member_slot IN (1, 2)),
    CONSTRAINT chk_ce_score CHECK (total_score IS NULL OR total_score BETWEEN 0 AND 100),
    CONSTRAINT chk_ce_submitted CHECK (status <> 'submitted' OR (total_score IS NOT NULL AND recommendation IS NOT NULL AND submitted_at IS NOT NULL))
) ENGINE=InnoDB;

-- T23
CREATE TABLE evaluation_scores (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evaluation_id   BIGINT UNSIGNED NOT NULL,
    criterion_id    BIGINT UNSIGNED NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    weight_snapshot DECIMAL(5,2) NOT NULL,
    weighted_score  DECIMAL(5,2) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scores_criterion (evaluation_id, criterion_id),
    CONSTRAINT fk_scores_evaluation FOREIGN KEY (evaluation_id) REFERENCES committee_evaluations (id) ON DELETE CASCADE,
    CONSTRAINT fk_scores_criterion  FOREIGN KEY (criterion_id)  REFERENCES evaluation_criteria (id),
    CONSTRAINT chk_scores_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- T24
CREATE TABLE evaluation_comments (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    comment_type  ENUM('strengths','weaknesses','technical','improvements','overall') NOT NULL,
    content       TEXT NOT NULL,
    is_critical   TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comments_type (evaluation_id, comment_type),
    CONSTRAINT fk_comments_evaluation FOREIGN KEY (evaluation_id) REFERENCES committee_evaluations (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- T25 (append-only)
CREATE TABLE approval_decisions (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proposal_id            BIGINT UNSIGNED NOT NULL,
    proposal_version_id    BIGINT UNSIGNED NOT NULL,
    assignment_id          BIGINT UNSIGNED NULL,
    decision_level         ENUM('advisor_review','final_decision') NOT NULL,
    decision               ENUM('ready_for_committee','revision_required','not_approved',
                                'approved','approved_minor','major_revisions') NOT NULL,
    comments               TEXT NULL,
    supporting_reasons     TEXT NULL,
    justification          TEXT NULL,
    differs_from_committee TINYINT(1) NOT NULL DEFAULT 0,
    average_score_snapshot DECIMAL(5,2) NULL,
    decided_by             BIGINT UNSIGNED NOT NULL,
    decided_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_decisions_proposal (proposal_id, decided_at),
    CONSTRAINT fk_decisions_proposal   FOREIGN KEY (proposal_id)         REFERENCES proposal_submissions (id),
    CONSTRAINT fk_decisions_version    FOREIGN KEY (proposal_version_id) REFERENCES proposal_versions (id),
    CONSTRAINT fk_decisions_assignment FOREIGN KEY (assignment_id)       REFERENCES committee_assignments (id),
    CONSTRAINT fk_decisions_user       FOREIGN KEY (decided_by)          REFERENCES users (id),
    CONSTRAINT chk_decisions_level CHECK (
        (decision_level = 'advisor_review' AND decision IN ('ready_for_committee','revision_required','not_approved'))
        OR
        (decision_level = 'final_decision' AND decision IN ('approved','approved_minor','major_revisions','not_approved'))
    ),
    CONSTRAINT chk_decisions_justification CHECK (
        differs_from_committee = 0 OR CHAR_LENGTH(TRIM(COALESCE(justification, ''))) > 0
    ),
    CONSTRAINT chk_decisions_advisor_comment CHECK (
        decision_level <> 'advisor_review' OR decision = 'ready_for_committee'
        OR CHAR_LENGTH(TRIM(COALESCE(comments, ''))) > 0
    )
) ENGINE=InnoDB;

-- T26
CREATE TABLE revision_requests (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proposal_id        BIGINT UNSIGNED NOT NULL,
    decision_id        BIGINT UNSIGNED NOT NULL,
    source_version_id  BIGINT UNSIGNED NOT NULL,
    requested_by_level ENUM('advisor','admin') NOT NULL,
    revision_mode      ENUM('advisor_only','re_evaluation') NULL,
    mode_selected_by   BIGINT UNSIGNED NULL,
    mode_selected_at   DATETIME NULL,
    due_at             DATETIME NULL,
    status             ENUM('open','submitted','closed') NOT NULL DEFAULT 'open',
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    closed_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_revision_requests_decision (decision_id),
    KEY idx_revision_requests_proposal (proposal_id, status),
    CONSTRAINT fk_rr_proposal FOREIGN KEY (proposal_id)       REFERENCES proposal_submissions (id),
    CONSTRAINT fk_rr_decision FOREIGN KEY (decision_id)       REFERENCES approval_decisions (id),
    CONSTRAINT fk_rr_version  FOREIGN KEY (source_version_id) REFERENCES proposal_versions (id),
    CONSTRAINT fk_rr_selector FOREIGN KEY (mode_selected_by)  REFERENCES users (id)
) ENGINE=InnoDB;

-- T27
CREATE TABLE revision_responses (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    revision_request_id BIGINT UNSIGNED NOT NULL,
    new_version_id      BIGINT UNSIGNED NOT NULL,
    response_file_id    BIGINT UNSIGNED NOT NULL,
    response_note       TEXT NULL,
    submitted_by        BIGINT UNSIGNED NOT NULL,
    submitted_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_revision_responses_version (new_version_id),
    KEY idx_revision_responses_request (revision_request_id),
    CONSTRAINT fk_rresp_request   FOREIGN KEY (revision_request_id) REFERENCES revision_requests (id),
    CONSTRAINT fk_rresp_version   FOREIGN KEY (new_version_id)      REFERENCES proposal_versions (id),
    CONSTRAINT fk_rresp_file      FOREIGN KEY (response_file_id)    REFERENCES document_files (id),
    CONSTRAINT fk_rresp_submitter FOREIGN KEY (submitted_by)        REFERENCES users (id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 9.4 Notifications, audit and settings
-- -----------------------------------------------------------------------------

-- T29
CREATE TABLE notifications (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    event_type   VARCHAR(50)  NOT NULL,
    title        VARCHAR(255) NOT NULL,
    message      TEXT NOT NULL,
    link_url     VARCHAR(255) NULL,
    related_type VARCHAR(40)  NULL,
    related_id   BIGINT UNSIGNED NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    read_at      DATETIME NULL,
    email_status ENUM('none','queued','sent','failed') NOT NULL DEFAULT 'none',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB;

-- T30 (append-only)
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    action      VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id   BIGINT UNSIGNED NULL,
    old_values  JSON NULL,
    new_values  JSON NULL,
    reason      TEXT NULL,
    ip_address  VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB;

-- T31 (append-only)
CREATE TABLE status_histories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('group','topic','proposal','assignment','evaluation') NOT NULL,
    entity_id   BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status   VARCHAR(40) NOT NULL,
    reason      TEXT NULL,
    changed_by  BIGINT UNSIGNED NOT NULL,
    changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_histories_entity (entity_type, entity_id, changed_at),
    CONSTRAINT fk_status_histories_user FOREIGN KEY (changed_by) REFERENCES users (id)
) ENGINE=InnoDB;

-- T32
CREATE TABLE system_settings (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(80) NOT NULL,
    setting_value TEXT NOT NULL,
    value_type    ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    description   VARCHAR(255) NULL,
    updated_by    BIGINT UNSIGNED NULL,
    updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 9.5 Triggers
-- -----------------------------------------------------------------------------
DELIMITER $$

-- DD-11: append-only tables -------------------------------------------------
CREATE TRIGGER trg_topic_approvals_no_update BEFORE UPDATE ON topic_approvals FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'topic_approvals is append-only'; END$$
CREATE TRIGGER trg_topic_approvals_no_delete BEFORE DELETE ON topic_approvals FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'topic_approvals is append-only'; END$$

CREATE TRIGGER trg_approval_decisions_no_update BEFORE UPDATE ON approval_decisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'approval_decisions is append-only'; END$$
CREATE TRIGGER trg_approval_decisions_no_delete BEFORE DELETE ON approval_decisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'approval_decisions is append-only'; END$$

CREATE TRIGGER trg_status_histories_no_update BEFORE UPDATE ON status_histories FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'status_histories is append-only'; END$$
CREATE TRIGGER trg_status_histories_no_delete BEFORE DELETE ON status_histories FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'status_histories is append-only'; END$$

CREATE TRIGGER trg_audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only'; END$$
CREATE TRIGGER trg_audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only'; END$$

CREATE TRIGGER trg_gmh_no_update BEFORE UPDATE ON group_member_histories FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'group_member_histories is append-only'; END$$
CREATE TRIGGER trg_gmh_no_delete BEFORE DELETE ON group_member_histories FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'group_member_histories is append-only'; END$$

-- Section 8: committee validation ------------------------------------------
CREATE TRIGGER trg_ca_before_insert BEFORE INSERT ON committee_assignments FOR EACH ROW
BEGIN
    IF NEW.member1_id = NEW.advisor_id OR NEW.member2_id = NEW.advisor_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A committee member cannot be the project advisor';
    END IF;
    IF (SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
         WHERE r.code = 'committee' AND ur.is_active = 1
           AND ur.user_id IN (NEW.member1_id, NEW.member2_id)) < 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Both committee members must have an active committee role';
    END IF;
END$$

CREATE TRIGGER trg_ca_before_update BEFORE UPDATE ON committee_assignments FOR EACH ROW
BEGIN
    IF NEW.member1_id <> OLD.member1_id OR NEW.member2_id <> OLD.member2_id OR NEW.advisor_id <> OLD.advisor_id THEN
        IF NEW.member1_id = NEW.advisor_id OR NEW.member2_id = NEW.advisor_id THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A committee member cannot be the project advisor';
        END IF;
        IF (SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'committee' AND ur.is_active = 1
               AND ur.user_id IN (NEW.member1_id, NEW.member2_id)) < 2 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Both committee members must have an active committee role';
        END IF;
    END IF;
    IF NEW.proposal_version_id <> OLD.proposal_version_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assigned proposal version cannot change; create a new round';
    END IF;
END$$

-- Section 3.4: submitted evaluations are locked -----------------------------
CREATE TRIGGER trg_eval_locked BEFORE UPDATE ON committee_evaluations FOR EACH ROW
BEGIN
    IF OLD.status = 'submitted' AND NEW.status <> 'reopened' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evaluation is locked after submission';
    END IF;
    IF NEW.status = 'reopened' AND OLD.status <> 'submitted' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only a submitted evaluation can be reopened';
    END IF;
    IF NEW.status = 'reopened' AND (NEW.reopened_by IS NULL OR CHAR_LENGTH(TRIM(COALESCE(NEW.reopen_reason, ''))) = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reopening requires an administrator and a reason';
    END IF;
END$$

CREATE TRIGGER trg_scores_locked_ins BEFORE INSERT ON evaluation_scores FOR EACH ROW
BEGIN
    IF (SELECT status FROM committee_evaluations WHERE id = NEW.evaluation_id) = 'submitted' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evaluation is locked after submission';
    END IF;
END$$
CREATE TRIGGER trg_scores_locked_upd BEFORE UPDATE ON evaluation_scores FOR EACH ROW
BEGIN
    IF (SELECT status FROM committee_evaluations WHERE id = OLD.evaluation_id) = 'submitted' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evaluation is locked after submission';
    END IF;
END$$
CREATE TRIGGER trg_scores_locked_del BEFORE DELETE ON evaluation_scores FOR EACH ROW
BEGIN
    IF (SELECT status FROM committee_evaluations WHERE id = OLD.evaluation_id) = 'submitted' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evaluation is locked after submission';
    END IF;
END$$

CREATE TRIGGER trg_comments_locked_ins BEFORE INSERT ON evaluation_comments FOR EACH ROW
BEGIN
    IF (SELECT status FROM committee_evaluations WHERE id = NEW.evaluation_id) = 'submitted' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evaluation is locked after submission';
    END IF;
END$$
CREATE TRIGGER trg_comments_locked_upd BEFORE UPDATE ON evaluation_comments FOR EACH ROW
BEGIN
    IF (SELECT status FROM committee_evaluations WHERE id = OLD.evaluation_id) = 'submitted' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evaluation is locked after submission';
    END IF;
END$$
CREATE TRIGGER trg_comments_locked_del BEFORE DELETE ON evaluation_comments FOR EACH ROW
BEGIN
    IF (SELECT status FROM committee_evaluations WHERE id = OLD.evaluation_id) = 'submitted' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evaluation is locked after submission';
    END IF;
END$$

-- Section 19: version history is never overwritten ---------------------------
CREATE TRIGGER trg_pv_immutable BEFORE UPDATE ON proposal_versions FOR EACH ROW
BEGIN
    IF NEW.file_id <> OLD.file_id OR NEW.version_no <> OLD.version_no OR NEW.proposal_id <> OLD.proposal_id
       OR NEW.submitted_by <> OLD.submitted_by OR NEW.submitted_at <> OLD.submitted_at THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Proposal versions are immutable; upload a new version';
    END IF;
END$$
CREATE TRIGGER trg_pv_no_delete BEFORE DELETE ON proposal_versions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Proposal versions cannot be deleted'; END$$

CREATE TRIGGER trg_ts_immutable BEFORE UPDATE ON topic_submissions FOR EACH ROW
BEGIN
    IF OLD.is_draft = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Submitted topic versions are immutable; create a new version';
    END IF;
END$$
CREATE TRIGGER trg_ts_no_delete BEFORE DELETE ON topic_submissions FOR EACH ROW
BEGIN
    IF OLD.is_draft = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Submitted topic versions cannot be deleted';
    END IF;
END$$

DELIMITER ;
