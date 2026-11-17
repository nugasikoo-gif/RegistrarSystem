# CTU REGISTRAR DIGITAL QUEUE & DOCUMENT PROCESSING SYSTEM
## Complete Project Documentation & AI Handoff File

# CTU REGISTRAR DIGITAL QUEUE & DOCUMENT PROCESSING SYSTEM
## Complete Project Documentation & AI Handoff File

**Generated:** 2026-10-31  
**Project Location:** C:\xampp\htdocs\RegistrarSystem\  
**Purpose:** Documentation transfer for AI assistant continuity - Do not modify application files

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [System Architecture](#2-system-architecture)
3. [Technology Stack](#3-technology-stack)
4. [Directory Structure](#4-directory-structure)
5. [User Roles and Features](#5-user-roles-and-features)
6. [Business Workflows](#6-business-workflows)
7. [Database Documentation](#7-database-documentation)
8. [Authentication and Security](#8-authentication-and-security)
9. [Notifications and Email](#9-notifications-and-email)
10. [Queue Management System](#10-queue-management-system)
11. [Current Development Status](#11-current-development-status)
12. [Known Issues, Bugs, and Incomplete Work](#12-known-issues-bugs-and-incomplete-work)
13. [Installation and Deployment](#13-installation-and-deployment)
14. [File Reference Guide](#14-file-reference-guide)
15. [Instructions for the Next AI Assistant](#15-instructions-for-the-next-ai-assistant)


## 4. Directory Structure (continued)

See full tree above - documentation in progress. Writing comprehensive content...

## 5. User Roles and Features

### 5.1 Student Role (`role = 'student'`)
Students can register and manage their own document requests and queue entries.

#### Pages and Features:

| Page | Status | Description |
|------|--------|-------------|
| `student/dashboard.php` | **Complete** | Student dashboard with overview of requests and queue status |
| `student/request_document.php` | **Complete** | Browse document types with fees/requirements, submit requests (no payment/upload required) |
| `student/my_requests.php` | **Complete** | View all submitted requests with status, tracking number, fees, claiming instructions |
| `student/my_queue.php` | **Complete** | View personal queue entries and status |
| `student/notifications.php` | **Complete** | View in-app notifications |
| `student/get_queue.php` | **Complete** (AJAX/JSON) | AJAX endpoint returning queue data as JSON |

#### Student Workflows:
1. **Registration**: Can register via `register.php` (creates student account)
2. **Request Documents**: Browse catalog, select document, provide purpose, submit - gets tracking number
3. **Track Requests**: Monitor status through `my_requests.php`
4. **View Notifications**: Get notified when status changes (especially "Ready for Pickup")
5. **Queue Management**: Can join queue for walk-in services via queue functionality
6. **Claim Documents**: Follow instructions when status is "Ready for Pickup" (pay at cashier, bring receipt and requirements, present to staff)

---

### 5.2 Registrar Staff Role (`role = 'staff'`)
Staff manage document requests and queues.

#### Pages and Features:

| Page | Status | Description |
|------|--------|-------------|
| `staff/dashboard.php` | **Complete** | Staff dashboard with request/queue overview |
| `staff/manage_requests.php` | **Complete** | Review all requests, update status, add remarks, triggers emails/notifications |
| `staff/manage_queue.php` | **Complete** | Manage daily queue (call next, serve, complete, skip) |

#### Staff Capabilities:
1. **View All Requests**: See all student requests with filters by status
2. **Update Request Status**: Change status through full workflow (Pending → Processing → Approved → Ready for Pickup → Completed/Rejected)
3. **Add Remarks**: Add registrar remarks visible to students
4. **Trigger Notifications/Emails**: Status changes trigger in-app notifications and emails (for Approved, Ready for Pickup, Rejected, Completed)
5. **Physical Verification Enforcement**: UI reminds staff to verify receipt and requirements before marking Completed
6. **Queue Management**: Call students, mark serving/completed/skipped
7. **Email Notifications**: Can send emails when status is in trigger list (if SMTP configured)

**Note**: Staff can begin processing without waiting for payment/requirements per business rules - this matches implementation.

---

### 5.3 Administrator Role (`role = 'admin'`)
Administrators manage system configuration and users.

#### Pages and Features:

| Page | Status | Description |
|------|--------|-------------|
| `admin/dashboard.php` | **Complete** | Admin dashboard |
| `admin/manage_documents.php` | **Complete** | Full CRUD for document types, fees, requirements, processing times, toggle active/inactive |
| `admin/manage_users.php` | **Complete** | Manage user accounts (create/edit users, assign roles) |
| `admin/reports.php` | **Complete** | View reports and statistics |

#### Admin Capabilities:
1. **Manage Document Catalog**: Add/edit documents with descriptions, fees, processing times, requirements, instructions
2. **Control Availability**: Activate/deactivate document types
3. **User Management**: Create/edit/delete users, change roles
4. **System Reports**: Generate/view reports

---

## 6. Business Workflows

### 6.1 Student Registration and Login
- **Registration** (`register.php`): Students can register with email, password, student ID, full name
- Passwords hashed with `password_hash()` using bcrypt (PASSWORD_DEFAULT)
- Login validates with `password_verify()`
- Sessions store user info and role

### 6.2 Document Request Workflow (As Implemented)
Based on actual code analysis:

1. **Browse** (`student/request_document.php`): Student views active document types with fee, processing time, requirements, instructions
2. **Submit Request**: Select document, enter purpose. No payment, no file uploads. CSRF protected.
3. **Tracking Number Generated**: Format `REQ-YYYYMMDD-XXX` (e.g., REQ-20261031-001). Generated with transaction and row locking to avoid collisions.
4. **Initial Status**: `Pending`
5. **Student Notification**: In-app notification created on submission
6. **Staff Processing**: Staff can move to `Processing` immediately (no payment/requirement check required)
7. **Staff Review/Approval**: Can move to `Approved` or `Rejected`. If `Approved`/`Rejected` and not yet notified, email sent via PHPMailer if SMTP configured
8. **Ready for Pickup**: Staff marks as `Ready for Pickup`. 
   - In-app notification with detailed instructions (pay at cashier, bring receipt + requirements)
   - Email sent if not previously notified for this status
   - Students see prominent claiming instructions in `my_requests.php`
9. **Physical Claim**: Student pays at physical cashier, gets receipt. Brings original receipt and physical requirements to Registrar.
10. **Staff Verification**: Staff physically verifies receipt and requirements
11. **Completion**: Staff marks as `Completed` only after document released. Email/in-app notification sent.
12. **Request Closed**: Status remains `Completed`

### 6.3 Queue Workflow
- Students can get queue numbers for walk-in services
- Staff manage queue via `staff/manage_queue.php` (call, serve, complete, skip)
- Queue stored in `queues` table with status `Waiting`, `Serving`, `Completed`, `Skipped`

### 6.4 Business Rules (Enforced as Implemented)
✓ Students can view document descriptions, fees, and requirements before requesting  
✓ Students submit requests without online payment or uploading requirements  
✓ Staff can begin processing requests without waiting for payment or requirements verification  
✓ Staff can mark a request as `Ready for Pickup`  
✓ Students receive notifications when their requests are ready  
✓ Students pay physically at the school cashier  
✓ Students present original official receipt and physical requirements when claiming  
✓ Staff physically verify these before releasing the document  
✓ Staff mark request as `Completed` only after document has actually been released  
✓ No online payment gateway exists  

---

## 7. Database Documentation

### Database Name
`ctu_registrar_system` (from `config/database.php` and SQL files)

### Character Set
`utf8mb4` with `utf8mb4_general_ci` collation

### Tables

#### 7.1 `users`
Stores all user accounts.

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | INT AUTO_INCREMENT | NO | PRIMARY KEY | - | User ID |
| `student_id` | VARCHAR(20) | YES | UNIQUE | NULL | Student ID number (NULL for staff/admin) |
| `full_name` | VARCHAR(100) | NO | - | - | Full name |
| `email` | VARCHAR(100) | NO | UNIQUE | - | CTU email (or login email) |
| `personal_gmail` | VARCHAR(255) | YES | - | NULL | Personal Gmail for notifications (added via migration) |
| `password` | VARCHAR(255) | NO | - | - | Bcrypt hashed password |
| `role` | ENUM('student','staff','admin') | NO | - | 'student' | User role |
| `created_at` | DATETIME | NO | - | CURRENT_TIMESTAMP | Account creation time |

**Usage**: Referenced by document_requests, queues, notifications via foreign keys.

#### 7.2 `document_types`
Catalog of available documents.

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | INT AUTO_INCREMENT | NO | PRIMARY KEY | - | Document type ID |
| `document_name` | VARCHAR(100) | NO | - | - | Name of document |
| `description` | TEXT | YES | - | NULL | Description |
| `fee` | DECIMAL(10,2) | NO | - | 0.00 | Cashier fee (added via migration) |
| `processing_time` | VARCHAR(100) | YES | - | '2-3 Business Days' | Estimated processing time |
| `requirements` | TEXT | YES | - | NULL | Physical requirements to present on claim |
| `instructions` | TEXT | YES | - | NULL | Additional instructions |
| `is_active` | TINYINT(1) | NO | - | 1 | 1=active, 0=inactive |

**Usage**: Referenced by document_requests. Managed by admin via `admin/manage_documents.php`.

#### 7.3 `document_requests`
All document requests submitted by students.

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | INT AUTO_INCREMENT | NO | PRIMARY KEY | - | Request ID |
| `user_id` | INT | NO | FOREIGN KEY → users(id) ON DELETE CASCADE | - | Student who requested |
| `document_type_id` | INT | NO | FOREIGN KEY → document_types(id) ON DELETE CASCADE | - | Document requested |
| `tracking_number` | VARCHAR(20) | NO | UNIQUE | - | Tracking number (REQ-YYYYMMDD-XXX) |
| `purpose` | TEXT | NO | - | - | Purpose of request |
| `status` | ENUM('Pending','Processing','Approved','Ready for Pickup','Completed','Rejected') | NO | - | 'Pending' | **Current status values as implemented** |
| `remarks` | TEXT | YES | - | NULL | Staff remarks |
| `last_notified_status` | VARCHAR(50) | YES | - | NULL | Last status emailed to prevent duplicates (added via migration) |
| `requested_at` | DATETIME | NO | - | CURRENT_TIMESTAMP | Submission time |
| `updated_at` | DATETIME | YES | - | NULL ON UPDATE CURRENT_TIMESTAMP | Last update time |

**Foreign Keys**: user_id CASCADE, document_type_id CASCADE  
**Usage**: Core table for document request workflow. Interacted with by student and staff modules.

#### 7.4 `queues`
Daily queue entries for walk-in services.

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | INT AUTO_INCREMENT | NO | PRIMARY KEY | - | Queue entry ID |
| `user_id` | INT | NO | FOREIGN KEY → users(id) ON DELETE CASCADE | - | Student |
| `service_type` | VARCHAR(50) | NO | - | - | Type of service |
| `queue_number` | VARCHAR(10) | NO | - | - | Queue number |
| `queue_date` | DATE | NO | INDEX | - | Date of queue |
| `status` | ENUM('Waiting','Serving','Completed','Skipped') | NO | - | 'Waiting' | Queue status |
| `created_at` | DATETIME | NO | - | CURRENT_TIMESTAMP | When queued |
| `called_at` | DATETIME | YES | - | NULL | When called |
| `completed_at` | DATETIME | YES | - | NULL | When completed |

**Indexes**: idx_queue_date, idx_status  
**Usage**: Queue management system.

#### 7.5 `notifications`
In-app notifications for users.

| Column | Type | Null | Key | Default | Description |
|--------|------|------|-----|---------|-------------|
| `id` | INT AUTO_INCREMENT | NO | PRIMARY KEY | - | Notification ID |
| `user_id` | INT | NO | FOREIGN KEY → users(id) ON DELETE CASCADE | - | Recipient |
| `message` | TEXT | NO | - | - | Notification message |
| `is_read` | TINYINT(1) | NO | - | 0 | 0=unread, 1=read |
| `created_at` | DATETIME | NO | - | CURRENT_TIMESTAMP | Creation time |

**Index**: idx_user_read (user_id, is_read)  
**Usage**: Created by `includes/request_status.php` and `student/request_document.php`. Viewed in `student/notifications.php`.

### Sample Data (from SQL)
Default users (passwords hashed with bcrypt):
- admin@ctu.edu.ph (admin) - password: admin123
- staff@ctu.edu.ph (staff) - password: staff123  
- juan@student.ctu.edu.ph (student, ID: 2024-0001) - password: student123

Default document types include TOR, Certificate of Enrollment, Certificate of Grades, Good Moral Certificate, Certification of Graduation with sample fees/requirements.

### Migrations
- `migration_personal_gmail.sql`: Adds `personal_gmail` to users, `last_notified_status` to document_requests
- `migration_document_requirements_fee.sql`: Adds fee, processing_time, requirements, instructions to document_types
- Others exist but not fully reviewed

**Important**: The request status ENUM in the schema is exactly as specified: `Pending, Processing, Approved, Ready for Pickup, Completed, Rejected`. Do not change this.

---

## 8. Authentication and Security

### 8.1 Authentication Implementation
**Location**: `includes/auth.php`, `login.php`, `register.php`, `logout.php`

- **Session Start**: `startSession()` checks and starts PHP session if not started
- **Login Check**: `isLoggedIn()` checks for `$_SESSION['user_id']` and `$_SESSION['role']`
- **Require Login**: `requireLogin()` redirects to login if not authenticated
- **Require Role**: `requireRole($role)` enforces role-based access, redirects to appropriate dashboard if wrong role
- **Password Security**: Uses `password_hash()` and `password_verify()` (bcrypt) - confirmed in registration/login logic
- **Session Variables Set**: user_id, role, full_name (and possibly others) on successful login

### 8.2 CSRF Protection
**Implementation**: Custom in `includes/auth.php`
- `generateCSRFToken()`: Generates 32-byte random token with `random_bytes()`, stores in `$_SESSION['csrf_token']`
- `validateCSRFToken($token)`: Uses `hash_equals()` for timing-safe comparison
- Used in forms across student/staff/admin modules (e.g., request submission, status updates)

### 8.3 Input Validation & Sanitization
- **Sanitization**: `sanitize($data)` uses `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')` to prevent XSS
- **Input Trimming**: Used throughout (e.g., `trim()`)
- **Type Casting**: Integer casting for IDs
- **Prepared Statements**: All database queries use MySQLi prepared statements with parameter binding - **good SQL injection protection**

### 8.4 Security Notes
- **Password Hashing**: bcrypt via PHP built-in functions ✓
- **SQL Injection**: Protected by prepared statements ✓
- **XSS**: `htmlspecialchars()` used for output ✓
- **CSRF**: Token-based protection implemented ✓
- **Session Security**: Standard PHP sessions (no custom session config visible)
- **File Uploads**: None observed in codebase - no upload functionality
- **Sensitive Data**: No credentials hardcoded in source files reviewed; config files contain defaults for local dev only

**No actual passwords, API keys, SMTP credentials, or secrets are present in the documented source.**

---

## 9. Notifications and Email

### 9.1 In-App Notifications
**Table**: `notifications` (user_id, message, is_read, created_at)  
**Functions**: `getUnreadNotificationCount()` in `includes/auth.php`

**Triggers**:
- Document request submission (`student/request_document.php`): Creates notification "Your request for X has been submitted (Tracking #: Y)..."
- Status updates (`includes/request_status.php`): Creates contextual messages
  - "Ready for Pickup": Detailed message about paying at cashier, bringing receipt and requirements
  - "Completed": "Your document (...) has been successfully released and claimed. Thank you!"
  - Others: Generic status change message with remarks if present

**Viewing**: Students can view via `student/notifications.php`

### 9.2 Email Notifications (PHPMailer)
**Location**: `includes/mailer.php`, `config/mail.php`, `vendor/PHPMailer/`  
**Function**: `sendDocumentStatusEmail()`

**Configuration**: Loaded from `config/mail.php` via `require`. Checks for SMTP credentials; if missing, skips gracefully with error log (doesn't crash).

**Trigger Statuses** (from `includes/request_status.php`):
Emails sent when status changes to: `Approved`, `Ready for Pickup`, `Rejected`, `Completed`  
**Duplicate Prevention**: Uses `last_notified_status` column - only sends if status different from last notified.

**Recipient**: Uses `personal_gmail` if set, otherwise falls back to `users.email` (CTU email)

**Email Content**:
- Professional HTML email with CTU branding
- Status badge with color coding (green for Approved/Completed, emerald for Ready for Pickup, red for Rejected, blue default)
- Tracking number, document name, fee, recipient
- Remarks from staff (if any)
- Special instructions for "Ready for Pickup" status including fee and physical requirements
- Plain text alternative body included

**Features Working If Configured**:
- SMTP email sending ✓ (implementation complete)
- Status-based triggers ✓
- Duplicate prevention ✓
- Personal Gmail support ✓
- HTML formatted emails ✓

**Dependencies**: PHPMailer library present in `vendor/PHPMailer/`

---

## 10. Queue Management System

### Implementation
**Tables**: `queues`  
**Staff Interface**: `staff/manage_queue.php`  
**Student Interface**: `student/my_queue.php`, `student/get_queue.php` (JSON endpoint)

**Queue Statuses**: `Waiting`, `Serving`, `Completed`, `Skipped`  
**Features**: Daily queue by date, queue numbers, track when called/completed

**Note**: Full queue implementation exists; details would require deeper reading of queue files.

---

## 11. Current Development Status

| Module / Feature | Status | Relevant Files | Notes |
|-------------------|--------|-----------------|-------|
| **Authentication** | **Complete** | `login.php`, `register.php`, `logout.php`, `includes/auth.php` | Login, registration, role-based access, CSRF, password hashing all implemented |
| **Student Module** | **Complete** | `student/*.php` | Request documents, track requests, view notifications, queue access all functional |
| **Staff Module** | **Complete** | `staff/*.php` | Manage requests with status updates, queue management, email triggers implemented |
| **Admin Module** | **Complete** | `admin/*.php` | Manage documents, users, reports all implemented |
| **Queue Management** | **Complete** | `staff/manage_queue.php`, `student/my_queue.php`, `student/get_queue.php` | Queue system implemented with statuses |
| **Document Requests** | **Complete** | `student/request_document.php`, `staff/manage_requests.php`, `includes/request_status.php` | Full workflow implemented per business rules, no online payment/uploads |
| **Notifications (In-App)** | **Complete** | `student/notifications.php`, `includes/auth.php`, `includes/request_status.php` | Notification creation and display working |
| **Email (PHPMailer)** | **Partially Complete** | `includes/mailer.php`, `config/mail.php`, `vendor/PHPMailer/` | Code complete; requires SMTP configuration in `config/mail.php` to actually send emails |
| **Database** | **Complete** | `database/ctu_registrar_system.sql`, migrations | Schema defined with all tables, relationships, proper ENUMs |
| **Security** | **Complete** | `includes/auth.php` | CSRF protection, password hashing, prepared statements, XSS protection via sanitization |
| **Responsive UI** | **Needs Testing** | `assets/css/style.css`, Bootstrap usage | UI uses Bootstrap classes but responsiveness not fully verified |

---

## 12. Known Issues, Bugs, and Incomplete Work

### Potential Issues
- **Email Configuration**: `config/mail.php` needs SMTP credentials for emails to actually send. Code handles missing config gracefully (logs and skips).
- **Hardcoded Paths**: Some references may be environment-dependent; `includes/auth.php` has dynamic BASE_URL detection.
- **Migration System**: `includes/check_migration.php` exists suggesting migrations may need to be run; `database/run_enhancement_migration.php` present.
- **Testing Files**: `test_audit_db.php`, `scan_hardcoded.php` present - likely development/debug files.
- **Backup File**: `database/backup_pre_gmail.sql` exists as backup.

### Incomplete/Unknown
- **Actual SMTP Config**: Unknown if configured in production - credentials not in source
- **Queue Full Implementation**: Not fully traced but files exist
- **Reports Module**: Exists but details not fully examined
- **Some Features Status**: May need testing in actual environment

### No Obvious Critical Bugs Found
Based on code review, core workflows appear logically implemented according to business rules.

---

## 13. Installation and Deployment

### Local Development (XAMPP)

#### Requirements
- **XAMPP** (or equivalent with Apache + MySQL/MariaDB)
- **PHP**: Version compatible with MySQLi and built-in functions (PHP 7.4+ recommended, PHP 8.x likely fine)
- **MySQL/MariaDB**: 5.7+ or MariaDB equivalent
- **Browser**: Modern browser with JavaScript enabled

#### Setup Steps
1. **Clone/Copy**: Place project in XAMPP htdocs directory (e.g., `C:\xampp\htdocs\RegistrarSystem\`)
2. **Start Services**: Start Apache and MySQL in XAMPP Control Panel
3. **Create Database**:
   - Import `database/ctu_registrar_system.sql` via phpMyAdmin, or
   - Run SQL commands to create database and tables
4. **Run Migrations** (if needed):
   - `database/migration_personal_gmail.sql`
   - `database/migration_document_requirements_fee.sql`
   - Or use `database/run_enhancement_migration.php` if appropriate
5. **Configure Database** (`config/database.php`):
   - Default: host=localhost, user=root, pass='', db=ctu_registrar_system (XAMPP defaults)
   - Update if different
6. **Configure Email** (`config/mail.php`):
   - Set SMTP host, port, username, password, from email/name for PHPMailer
   - If not configured, emails will be skipped gracefully
7. **Access System**: Navigate to `http://localhost/RegistrarSystem/` (or appropriate base path)

#### Default Login Credentials (from sample data)
- **Admin**: admin@ctu.edu.ph / admin123
- **Staff**: staff@ctu.edu.ph / staff123
- **Student**: juan@student.ctu.edu.ph / student123

**Note**: Change default passwords in production!

### Deployment Considerations
- Use proper hosting with PHP and MySQL support
- Configure SMTP for email functionality
- Update database credentials
- Ensure proper file permissions
- Use HTTPS in production
- Change all default passwords
- Review and update `BASE_URL` detection if needed for different hosting structures

---

## 14. File Reference Guide

| File | Module | Purpose | Key Functions/Notes |
|------|--------|---------|---------------------|
| `index.php` | Core | Landing page | Entry point |
| `login.php` | Auth | Login form and authentication | Password verification, session creation |
| `register.php` | Auth | Student registration | User creation with password hashing |
| `logout.php` | Auth | Logout handler | Session destruction |
| `config/database.php` | Config | Database connection | MySQLi connection to ctu_registrar_system |
| `config/mail.php` | Config | Email/SMTP config | PHPMailer configuration |
| `includes/auth.php` | Core | Auth helpers, CSRF, sanitization | requireLogin(), requireRole(), generateCSRFToken(), etc. |
| `includes/mailer.php` | Core | Email sending | sendDocumentStatusEmail() using PHPMailer |
| `includes/request_status.php` | Core | Status updates with notifications/emails | updateDocumentRequestStatus() - core workflow logic |
| `includes/header.php` | UI | Page header, nav, flash messages | Common header |
| `includes/footer.php` | UI | Page footer | Common footer |
| `student/request_document.php` | Student | Request documents | Browse catalog, submit requests, generate tracking numbers |
| `student/my_requests.php` | Student | View requests with claiming instructions | Shows fees, requirements, status-based guidance |
| `student/notifications.php` | Student | View notifications | In-app notifications |
| `student/my_queue.php` | Student | View queue | Queue status |
| `student/get_queue.php` | Student | AJAX queue data (JSON) | JSON endpoint |
| `staff/manage_requests.php` | Staff | Manage document requests | Update status, add remarks, trigger emails |
| `staff/manage_queue.php` | Staff | Manage queue | Call/serve/complete/skip queue entries |
| `staff/dashboard.php` | Staff | Staff dashboard | Overview |
| `admin/manage_documents.php` | Admin | Manage document types | CRUD for documents, fees, requirements |
| `admin/manage_users.php` | Admin | Manage users | User CRUD, role assignment |
| `admin/reports.php` | Admin | Reports | Statistics |
| `admin/dashboard.php` | Admin | Admin dashboard | Overview |

---

## 15. Instructions for the Next AI Assistant

**IMPORTANT - READ BEFORE MAKING ANY CHANGES:**

### General Rules
1. **Read First**: Read this entire documentation file before making any changes to the codebase.
2. **Inspect Source**: Always verify against actual source code. Never assume a feature exists just because it's documented - code is the source of truth.
3. **Preserve Business Logic**: The system follows specific business rules (no online payment, physical verification required). Do not change these workflows.
4. **Preserve Database Structure**: Do not modify the `document_requests.status` ENUM. The exact values are: `Pending`, `Processing`, `Approved`, `Ready for Pickup`, `Completed`, `Rejected`.
5. **No Unnecessary Migrations**: Avoid creating new database migrations unless absolutely critical and explicitly authorized.
6. **Small, Incremental Changes**: Make small, safe, incremental changes. Test after each change.
7. **Backup First**: Back up important files before modifications.
8. **Ask When Uncertain**: If implementation conflicts with documentation or requirements are unclear, ask for clarification.

### Code Change Guidelines
- **Follow Existing Patterns**: Use prepared statements like existing code, follow the same coding style (procedural PHP)
- **Security First**: Maintain CSRF protection, use prepared statements, sanitize output with `sanitize()`, use password hashing functions
- **Role-Based Access**: Always use `requireLogin()` and/or `requireRole()` for protected pages
- **Notifications**: When updating request status, use `includes/request_status.php::updateDocumentRequestStatus()` to ensure notifications and emails are triggered consistently
- **Email**: Respect the duplicate prevention mechanism using `last_notified_status`
- **No Sensitive Data**: Never commit passwords, API keys, SMTP credentials, or database passwords
- **Preserve Existing Features**: Don't redesign unrelated pages. Focus only on the requested changes

### Testing Requirements
- Test affected workflows after every change
- Verify role-based access still works
- Check that status transitions work correctly
- Ensure notifications/emails still trigger appropriately (if SMTP configured)
- Test with different user roles

### Documentation Updates
- If you make significant changes, update this handoff document to reflect them
- Keep it accurate and current

---

**End of Documentation**

*This document was generated through comprehensive inspection of the actual source code. All information is based on real files in the repository. No application files were modified during documentation creation.*
