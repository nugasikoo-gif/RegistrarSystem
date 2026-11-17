-- Safe migration script to add document requirements and cashier fee information to document_types
-- Preserves existing tables, foreign keys, and existing student requests.
-- Does NOT touch or modify document_requests.status ENUM.

USE `ctu_registrar_system`;

-- 1. Add applicable fee column
ALTER TABLE `document_types` 
  ADD COLUMN IF NOT EXISTS `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `description`;

-- 2. Add estimated processing time
ALTER TABLE `document_types` 
  ADD COLUMN IF NOT EXISTS `processing_time` VARCHAR(100) NULL DEFAULT '2-3 Business Days' AFTER `fee`;

-- 3. Add physical requirements list
ALTER TABLE `document_types` 
  ADD COLUMN IF NOT EXISTS `requirements` TEXT NULL AFTER `processing_time`;

-- 4. Add additional claiming instructions
ALTER TABLE `document_types` 
  ADD COLUMN IF NOT EXISTS `instructions` TEXT NULL AFTER `requirements`;

-- Populate configurable default sample data for standard registrar credentials
UPDATE `document_types` 
SET `fee` = 150.00,
    `processing_time` = '3-5 Working Days',
    `requirements` = '• Valid Student ID or Government-Issued ID\n• 2x2 colored ID picture (formal attire, white background)\n• Duly accomplished Clearance Form (for graduates/transferees)',
    `instructions` = 'First request must present official clearance. Subsequent requests require only valid ID.'
WHERE `document_name` LIKE '%Transcript of Records%' AND (`requirements` IS NULL OR `requirements` = '');

UPDATE `document_types` 
SET `fee` = 50.00,
    `processing_time` = '1-2 Working Days',
    `requirements` = '• Valid Student ID\n• Current Semester Study Load / Certificate of Registration (COR)',
    `instructions` = 'Used for scholarship applications, SSS/GSIS dependent claims, or employment verification.'
WHERE `document_name` LIKE '%Enrollment%' AND (`requirements` IS NULL OR `requirements` = '');

UPDATE `document_types` 
SET `fee` = 75.00,
    `processing_time` = '2-3 Working Days',
    `requirements` = '• Valid Student ID\n• Copy of previous semester grades or student portal printout',
    `instructions` = 'Official grades copy authenticated by the campus registrar.'
WHERE `document_name` LIKE '%Grades%' AND (`requirements` IS NULL OR `requirements` = '');

UPDATE `document_types` 
SET `fee` = 50.00,
    `processing_time` = '1-2 Working Days',
    `requirements` = '• Valid Student ID\n• Clearance from Campus Guidance Office / Dean of Student Affairs',
    `instructions` = 'Certification of good student conduct during residency.'
WHERE `document_name` LIKE '%Good Moral%' AND (`requirements` IS NULL OR `requirements` = '');

UPDATE `document_types` 
SET `fee` = 100.00,
    `processing_time` = '2-3 Working Days',
    `requirements` = '• Valid Government-Issued ID\n• Certified True Copy of Special Order (SO) or Board Resolution',
    `instructions` = 'Issued to verified graduates of CTU degree programs.'
WHERE `document_name` LIKE '%Graduation%' AND (`requirements` IS NULL OR `requirements` = '');

