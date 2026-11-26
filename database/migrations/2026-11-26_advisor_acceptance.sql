-- =============================================================================
-- PPMS migration 2026-11-26: อาจารย์ที่นิสิตขอต้องตอบรับก่อน แล้วผู้ประสานงานรายวิชาอนุมัติลำดับสุดท้าย
-- phpMyAdmin: เลือกฐานข้อมูล PPMS -> Import ไฟล์นี้ -> Go  (ไม่ลบข้อมูลเดิม, รันซ้ำได้)
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE project_advisors
    MODIFY status ENUM('requested','accepted','assigned','declined','replaced') NOT NULL DEFAULT 'requested';
