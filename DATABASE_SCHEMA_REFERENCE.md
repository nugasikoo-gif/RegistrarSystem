# DATABASE SCHEMA REFERENCE
## CTU Registrar Digital Queue & Document Processing System

**Database Name:** ctu_registrar_system  
**Character Set:** utf8mb4  
**Collation:** utf8mb4_general_ci  
**Engine:** InnoDB  
**Generated:** 2026-10-31

---

## Table of Contents
1. [Overview](#1-overview)
2. [Schema Sources](#2-schema-sources)
3. [Tables](#3-tables)
4. [Table Relationships](#4-table-relationships)
5. [ENUM Values](#5-enum-values)
6. [Indexes and Constraints](#6-indexes-and-constraints)
7. [Migrations and Schema Changes](#7-migrations-and-schema-changes)
8. [Table Usage in Application](#8-table-usage-in-application)
9. [Structures Not Confirmable From Available Files](#9-structures-not-confirmable-from-available-files)

---

## 1. Overview

This document is a focused technical reference derived from actual SQL files and database-related PHP code in the project. It documents the database structure as implemented. No real student records, credentials, or sensitive data are included.

---

## 2. Schema Sources

| Source File | Purpose |
|-------------|---------|
| database/ctu_registrar_system.sql | Primary schema and sample data (base tables, ENUMs, foreign keys) |
| database/migration_personal_gmail.sql | Adds users.personal_gmail and document_requests.last_notified_status |
| database/migration_document_requirements_fee.sql | Adds document_types.fee, processing_time, equirements, instructions |
| database/migration_add_approved_status.sql | References/ensures Approved status in workflow |
| database/backup_pre_gmail.sql | Backup file (inspected for comparison) |
| config/database.php | Database connection details |
| includes/request_status.php, includes/mailer.php, student/request_document.php, staff/manage_requests.php, dmin/manage_documents.php | Usage confirmation |

---

## 3. Tables

### 3.1 users (Source: database/ctu_registrar_system.sql, modified by database/migration_personal_gmail.sql)

`sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(20) DEFAULT NULL,
  ull_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  personal_gmail VARCHAR(255) NULL,
  password VARCHAR(255) NOT NULL,
  ole ENUM('student', 'staff', 'admin') NOT NULL DEFAULT 'student',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_email (email),
  UNIQUE KEY uk_student_id (student_id)
) ENGINE=InnoDB;
`

| Column | Data Type | NULL/NOT NULL | Key/Constraints | Default | Notes |
|--------|-----------|---------------|-----------------|---------|-------|
| id | INT | NOT NULL | PRIMARY KEY, AUTO_INCREMENT | - | User identifier |
| student_id | VARCHAR(20) | NULL | UNIQUE uk_student_id | NULL | NULL for staff/admin |
| ull_name | VARCHAR(100) | NOT NULL | - | - | Full name |
| email | VARCHAR(100) | NOT NULL | UNIQUE uk_email | - | Primary email |
| personal_gmail | VARCHAR(255) | NULL | - | NULL | Added by migration; preferred for notifications |
| password | VARCHAR(255) | NOT NULL | - | - | Bcrypt hashed password |
| ole | ENUM('student','staff','admin') | NOT NULL | - | 'student' | Access control |
| created_at | DATETIME | NOT NULL | - | CURRENT_TIMESTAMP | Creation time |

### 3.2 document_types (Source: database/ctu_registrar_system.sql, modified by database/migration_document_requirements_fee.sql)

`sql
CREATE TABLE document_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_name VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL,
  ee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  processing_time VARCHAR(100) NULL DEFAULT '2-3 Business Days',
  equirements TEXT NULL,
  instructions TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
`

| Column | Data Type | NULL/NOT NULL | Key/Constraints | Default | Notes |
|--------|-----------|---------------|-----------------|---------|-------|
| id | INT | NOT NULL | PRIMARY KEY, AUTO_INCREMENT | - | Identifier |
| document_name | VARCHAR(100) | NOT NULL | - | - | Document name |
| description | TEXT | NULL | - | NULL | Description |
| ee | DECIMAL(10,2) | NOT NULL | - | 0.00 | Cashier fee |
| processing_time | VARCHAR(100) | NULL | - | '2-3 Business Days' | Turnaround time |
| equirements | TEXT | NULL | - | NULL | Physical requirements to present on claim |
| instructions | TEXT | NULL | - | NULL | Additional instructions |
| is_active | TINYINT(1) | NOT NULL | - | 1 | 1=active, 0=inactive |

### 3.3 document_requests (Source: database/ctu_registrar_system.sql, modified by database/migration_personal_gmail.sql)

`sql
CREATE TABLE document_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  document_type_id INT NOT NULL,
  	racking_number VARCHAR(20) NOT NULL,
  purpose TEXT NOT NULL,
  status ENUM('Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Completed', 'Rejected') NOT NULL DEFAULT 'Pending',
  emarks TEXT DEFAULT NULL,
  last_notified_status VARCHAR(50) NULL DEFAULT NULL,
  equested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tracking (	racking_number),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;
`

| Column | Data Type | NULL/NOT NULL | Key/Constraints | Default | Notes |
|--------|-----------|---------------|-----------------|---------|-------|
| id | INT | NOT NULL | PRIMARY KEY, AUTO_INCREMENT | - | Request ID |
| user_id | INT | NOT NULL | FK → users(id) ON DELETE CASCADE | - | Student |
| document_type_id | INT | NOT NULL | FK → document_types(id) ON DELETE CASCADE | - | Document type |
| 	racking_number | VARCHAR(20) | NOT NULL | UNIQUE uk_tracking | - | REQ-YYYYMMDD-XXX |
| purpose | TEXT | NOT NULL | - | - | Purpose |
| status | ENUM('Pending','Processing','Approved','Ready for Pickup','Completed','Rejected') | NOT NULL | - | 'Pending' | EXACT ENUM - PRESERVE |
| emarks | TEXT | NULL | - | NULL | Staff remarks |
| last_notified_status | VARCHAR(50) | NULL | - | NULL | Added by migration |
| equested_at | DATETIME | NOT NULL | - | CURRENT_TIMESTAMP | Submitted |
| updated_at | DATETIME | NULL | ON UPDATE CURRENT_TIMESTAMP | NULL | Last updated |

### 3.4 queues (Source: database/ctu_registrar_system.sql)

`sql
CREATE TABLE queues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  service_type VARCHAR(50) NOT NULL,
  queue_number VARCHAR(10) NOT NULL,
  queue_date DATE NOT NULL,
  status ENUM('Waiting', 'Serving', 'Completed', 'Skipped') NOT NULL DEFAULT 'Waiting',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  called_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_queue_date (queue_date),
  INDEX idx_status (status)
) ENGINE=InnoDB;
`

| Column | Data Type | NULL/NOT NULL | Key/Constraints | Default | Notes |
|--------|-----------|---------------|-----------------|---------|-------|
| id | INT | NOT NULL | PRIMARY KEY, AUTO_INCREMENT | - | Queue ID |
| user_id | INT | NOT NULL | FK → users(id) ON DELETE CASCADE | - | Student |
| service_type | VARCHAR(50) | NOT NULL | - | - | Service type |
| queue_number | VARCHAR(10) | NOT NULL | - | - | Queue number |
| queue_date | DATE | NOT NULL | INDEX idx_queue_date | - | Date |
| status | ENUM('Waiting','Serving','Completed','Skipped') | NOT NULL | INDEX idx_status | 'Waiting' | Status |
| created_at | DATETIME | NOT NULL | - | CURRENT_TIMESTAMP | Created |
| called_at | DATETIME | NULL | - | NULL | Called time |
| completed_at | DATETIME | NULL | - | NULL | Completed time |

### 3.5 
otifications (Source: database/ctu_registrar_system.sql)

`sql
CREATE TABLE 
otifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;
`

| Column | Data Type | NULL/NOT NULL | Key/Constraints | Default | Notes |
|--------|-----------|---------------|-----------------|---------|-------|
| id | INT | NOT NULL | PRIMARY KEY, AUTO_INCREMENT | - | Notification ID |
| user_id | INT | NOT NULL | FK → users(id) ON DELETE CASCADE, INDEX idx_user_read | - | Recipient |
| message | TEXT | NOT NULL | - | - | Message |
| is_read | TINYINT(1) | NOT NULL | - | 0 | 0=unread, 1=read |
| created_at | DATETIME | NOT NULL | - | CURRENT_TIMESTAMP | Created |

---

## 4. Table Relationships

`mermaid
erDiagram
    USERS ||--o{ DOCUMENT_REQUESTS : submits
    USERS ||--o{ QUEUES : joins
    USERS ||--o{ NOTIFICATIONS : receives
    DOCUMENT_TYPES ||--o{ DOCUMENT_REQUESTS : requested_as
`

| Relationship | Type | FK | On Delete | Description |
|--------------|------|----|-----------|-------------|
| users-document_requests | 1:N | document_requests.user_id | CASCADE | User submits requests |
| users-queues | 1:N | queues.user_id | CASCADE | User joins queues |
| users-
otifications | 1:N | 
otifications.user_id | CASCADE | User receives notifications |
| document_types-document_requests | 1:N | document_requests.document_type_id | CASCADE | Document type requested multiple times |

---

## 5. ENUM Values

| Table.Column | ENUM Definition | Values | Notes |
|--------------|-----------------|--------|-------|
| users.role | ENUM('student','staff','admin') | student, staff, admin | Access control |
| document_requests.status | ENUM('Pending','Processing','Approved','Ready for Pickup','Completed','Rejected') | Pending, Processing, Approved, Ready for Pickup, Completed, Rejected | **EXACT as in schema - preserve** |
| queues.status | ENUM('Waiting','Serving','Completed','Skipped') | Waiting, Serving, Completed, Skipped | Queue workflow |

---

## 6. Indexes and Constraints

| Type | Table | Name | Columns | Source |
|------|-------|------|---------|--------|
| PRIMARY KEY | all | (id) | id | SQL |
| UNIQUE | users | uk_email | email | SQL |
| UNIQUE | users | uk_student_id | student_id | SQL |
| UNIQUE | document_requests | uk_tracking | tracking_number | SQL |
| INDEX | queues | idx_queue_date | queue_date | SQL |
| INDEX | queues | idx_status | status | SQL |
| COMPOSITE INDEX | notifications | idx_user_read | user_id, is_read | SQL |
| FOREIGN KEY | document_requests | - | user_id → users(id) | SQL |
| FOREIGN KEY | document_requests | - | document_type_id → document_types(id) | SQL |
| FOREIGN KEY | queues | - | user_id → users(id) | SQL |
| FOREIGN KEY | notifications | - | user_id → users(id) | SQL |

---

## 7. Migrations and Schema Changes

| File | Change | Notes |
|------|--------|-------|
| database/migration_personal_gmail.sql | Add users.personal_gmail, document_requests.last_notified_status | Prevents duplicate emails; personal Gmail support |
| database/migration_document_requirements_fee.sql | Add document_types.fee, processing_time, equirements, instructions + populate defaults | No change to document_requests.status ENUM |
| database/migration_add_approved_status.sql | Referenced in workflow | Related to Approved status |
| database/run_enhancement_migration.php | Migration runner | PHP automation |

---

## 8. Table Usage in Application

| Table | Primary Usage Files | Purpose |
|-------|---------------------|---------|
| users | login.php, egister.php, includes/auth.php, dmin/manage_users.php | Auth/roles |
| document_types | student/request_document.php, dmin/manage_documents.php, student/my_requests.php | Catalog/fees/requirements |
| document_requests | student/request_document.php, student/my_requests.php, staff/manage_requests.php, includes/request_status.php | Request workflow |
| queues | staff/manage_queue.php, student/my_queue.php, student/get_queue.php | Queue system |
| 
otifications | includes/auth.php, includes/request_status.php, student/notifications.php | Notifications |

---

## 9. Structures Not Confirmable From Available Files

- Triggers, views, stored procedures: not present in inspected SQL files.
- Additional tables beyond the 5 documented: none found.
- Live schema differences: no full DB export available; schema reconstructed from SQL files + migrations.

**All tables documented above are supported by actual SQL sources in database/.**
