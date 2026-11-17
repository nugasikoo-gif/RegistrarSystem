-- Safe schema update to support 'Approved' status
-- Run this if your existing database was created with the initial schema

USE `ctu_registrar_system`;

ALTER TABLE `document_requests` 
  MODIFY COLUMN `status` ENUM('Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Completed', 'Rejected') NOT NULL DEFAULT 'Pending';

