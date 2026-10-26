# PROJECT_CONTEXT.md - CTU Registrar Digital Queue & Document Processing System

## Project Overview
This is a PHP + MySQL web-based system for Cebu Technological University (CTU) Daanbantayan Campus Registrar Office to manage:
- Student account registration and authentication
- Document request submission and tracking (with tracking numbers)
- Daily queue ticketing system for on-campus visits
- Status updates with automatic Gmail/SMTP email notifications via PHPMailer
- In-app notifications for students
- Staff-side management of document requests

**Project Location:** C:\xampp\htdocs\Registrar\Registrar-ike  
**Workspace Root:** C:\xampp\htdocs\Registrar\Registrar-ike  
**Database:** ctu_registrar_system (MySQL/MariaDB)  
**Server Environment:** XAMPP on Windows (Apache + MySQL + PHP)

## Technology Stack (Verified)
- **Backend:** PHP (procedural style with includes/functions)
- **Database:** MySQL/MariaDB with MySQLi
- **Frontend:** Bootstrap 5 (CDN), Bootstrap Icons, custom CSS (assets/css/style.css)
- **Email:** PHPMailer 6.9+ (vendor/PHPMailer/) with Gmail SMTP
- **Authentication:** PHP sessions, bcrypt password hashing (password_hash/verify), CSRF tokens
- **Security:** htmlspecialchars/sanitize, prepared statements, session regeneration on login

## Folder and File Structure (Verified)
`
Registrar-ike/
├── .env                    # SMTP credentials (empty username/password)
├── .env.example            # Template for SMTP config
├── index.php               # Entry point/redirect
├── login.php               # Login page (role-based redirect)
├── logout.php              # Session destroy
├── register.php            # Student registration form
├── system-development-plan.pdf
├── assets/
│   ├── css/style.css       # Main stylesheet (~25KB)
│   ├── images/             # CTU logo, favicons
│   └── js/script.js        # Minimal JS
├── config/
│   ├── database.php        # MySQLi connection (localhost/root/empty/ctu_registrar_system)
│   └── mail.php            # Loads SMTP config from .env
├── database/
│   ├── ctu_registrar_system.sql        # Schema + sample data
│   ├── backup_pre_gmail.sql            # Backup
│   └── migration_personal_gmail.sql    # Migration adding personal_gmail and last_notified_status
├── includes/
│   ├── auth.php            # Session/auth helpers, role checks, CSRF, flash messages, BASE_URL/url()
│   ├── footer.php          # Footer partial
│   ├── header.php          # Header/nav partial
│   ├── mailer.php          # sendDocumentStatusEmail() using PHPMailer
│   └── request_status.php  # updateDocumentRequestStatus() - updates status, creates notification, sends email
├── staff/
│   └── manage_requests.php # Staff UI to manage document requests and send status updates
├── student/
│   ├── dashboard.php       # Student dashboard with queue + recent requests
│   ├── get_queue.php       # Generate daily queue ticket
│   ├── my_queue.php        # View live queue status
│   ├── my_requests.php     # View all document requests
│   ├── notifications.php   # In-app notifications
│   └── request_document.php # Submit document request
└── vendor/PHPMailer/       # PHPMailer library files
`

## Database Schema (Verified from SQL)
Tables:
1. **users** (id, student_id (nullable, unique), full_name, email, **personal_gmail** added via migration, password (bcrypt), role ENUM('student','staff','admin'), created_at)
2. **document_types** (id, document_name, description, is_active TINYINT)
3. **document_requests** (id, user_id FK, document_type_id FK, tracking_number (unique), purpose, status ENUM('Pending','Processing','Ready for Pickup','Completed','Rejected') default Pending, remarks, **last_notified_status** added via migration, requested_at, updated_at ON UPDATE)
4. **queues** (id, user_id FK, service_type, queue_number, queue_date (DATE), status ENUM('Waiting','Serving','Completed','Skipped') default Waiting, created_at, called_at, completed_at; indexes on queue_date, status)
5. **notifications** (id, user_id FK, message, is_read TINYINT default 0, created_at; index on user_id,is_read)

Sample data: admin@ctu.edu.ph, staff@ctu.edu.ph, student juan@student.ctu.edu.ph (2024-0001) with known bcrypt hashes.

## Features Verified as Implemented
### Authentication & Registration
- Student registration (register.php) - creates user with role 'student', student_id, full_name, email, password (hashed). Has form validation.
- Login (login.php) - email/password auth with password_verify, session regeneration, role-based redirects. If staff/admin dashboard missing, shows flash message and logs out (redirects to logout.php with info).
- Logout (logout.php) - destroys session
- CSRF protection via generateCSRFToken/validateCSRFToken
- Role-based access control via requireLogin/requireRole

### Student Features
- Dashboard shows: active queue ticket for today (status Waiting/Serving), currently serving queue number, recent 5 document requests with status badges
- Request document (request_document.php) - submit document request with document type selection, purpose, generates tracking number (format appears to be system-generated; need to inspect implementation)
- My requests (my_requests.php) - view all requests with details/status
- Get queue (get_queue.php) - generate daily queue ticket
- My queue (my_queue.php) - live queue view
- Notifications (student/notifications.php) - in-app notifications

### Staff Features
- Manage requests (staff/manage_requests.php) - view all requests with student info, CTU email, personal_gmail, document, purpose, status; update status via modal with remarks. Triggers email on status change to Approved/Ready for Pickup/Rejected (and code includes Completed in trigger list). Creates in-app notification. Prevents duplicate emails using last_notified_status. Shows email sent status/notes.

### Email/Notifications
- PHPMailer integration configured via config/mail.php reading .env
- sendDocumentStatusEmail() builds HTML email with CTU branding, status badge colors, remarks, pickup instructions for Ready for Pickup
- updateDocumentRequestStatus() updates DB, creates notification entry, sends email conditionally
- Migration adds personal_gmail to users and last_notified_status to document_requests

## Partially Implemented / Unfinished Features (Verified)
- **Staff dashboard:** auth.php checks for staff/dashboard.php; if missing, redirects to logout with message "Staff module is currently under development (Phase 4)." Staff can still access manage_requests.php directly (it has requireRole('staff')) - need to confirm if staff/dashboard.php exists.
- **Admin dashboard:** Similar - if missing, redirects to logout with "Admin module is currently under development (Phase 5)." No admin/ folder visible in listing.
- **Password reset:** login.php has "Forgot Password?" link that just alerts "For password resets, please contact the campus MIS office." - not implemented.
- **SMTP credentials:** .env has empty SMTP_USERNAME/PASSWORD - emails will be skipped gracefully per mailer.php logic.

## Exact Unfinished Task and Next Steps (Based on Codebase State)
From auth.php messages and missing modules:
1. **Phase 4 - Staff Dashboard:** Create staff/dashboard.php with staff-specific overview (pending requests count, today's queue, etc.). Currently staff land on logout if dashboard missing.
2. **Phase 5 - Admin Module:** Create admin/ directory with dashboard.php and likely user management, document type management, reports, etc.
3. **Email Configuration:** Configure SMTP credentials in .env for actual email delivery (or document setup process).
4. **Password Reset Flow:** Implement proper password reset (token-based) if required, or confirm it's intentionally manual.

**To confirm from conversation context:** The user says "I am continuing an existing PHP + MySQL registrar office queue and document processing system from another AI coding tool" and provided "the previous Antigravity conversation I provide as additional context" - but the conversation text was not included in the message. I need to note this limitation.

## Database Tables and Relationships (Verified)
- users 1:N document_requests, 1:N queues, 1:N notifications
- document_types 1:N document_requests
- All foreign keys with ON DELETE CASCADE

## Registration, Authentication, and Notification Requirements (Verified)
**Registration:** Student self-registration via register.php creates account with role 'student'. Staff/admin accounts appear to be created directly in DB (sample data shows them).
**Authentication:** Session-based, bcrypt passwords, CSRF tokens on forms, role-based access control, session regeneration on login.
**Notifications:** 
- In-app: notifications table, created by updateDocumentRequestStatus() on status change
- Email: PHPMailer via Gmail SMTP, sent on status change to Approved/Ready for Pickup/Rejected (Completed also in trigger list in code), only if status changed and not already notified (last_notified_status), recipient is personal_gmail if set else CTU email, skipped gracefully if SMTP not configured.

## Important Bugs, Previous Fixes, and Constraints (Observed)
- **BASE_URL handling:** auth.php has dynamic BASE_URL detection for /Registrar-ike or /Registrar paths - good for XAMPP subdirectory deployments
- **Graceful degradation:** Email failures don't block status updates; staff module/admin module missing don't crash, redirect with messages
- **Duplicate email prevention:** last_notified_status prevents resending same status notification
- **CSRF protection:** Implemented on forms (manage_requests uses it)
- **SQL injection protection:** Prepared statements used throughout
- **XSS protection:** sanitize() uses htmlspecialchars

## Testing Checklist and Acceptance Criteria (Suggested based on verified features)
- [ ] Database imported successfully (ctu_registrar_system)
- [ ] Can login as admin/staff/student with sample credentials
- [ ] Student can register new account
- [ ] Student can request document and get tracking number
- [ ] Student can generate queue ticket for today
- [ ] Staff can view/manage requests and update status
- [ ] Email notifications trigger on Approved/Ready for Pickup/Rejected (with SMTP configured)
- [ ] In-app notifications created on status changes
- [ ] Role-based redirects work correctly
- [ ] No duplicate emails on same status update

## Instructions for the Next AI Coding Assistant
1. **Do not rebuild** - extend existing structure, follow existing patterns (procedural PHP, includes, MySQLi prepared statements)
2. **Follow conventions:** Use existing auth helpers (requireRole, sanitize, setFlashMessage), BASE_URL/url() for links, CSRF tokens on forms
3. **Email system:** Uses personal_gmail from users table (added by migration) with fallback to email; last_notified_status prevents duplicates
4. **Status flow:** Allowed statuses are Pending, Processing, Approved, Ready for Pickup, Completed, Rejected (see manage_requests.php)
5. **Check before creating:** staff/dashboard.php and admin/ don't exist - may need to create per phases
6. **Preserve data:** Never modify DB schema without migration scripts; be careful not to reset data
7. **SMTP:** .env currently has empty credentials - emails will be skipped; configure for production/testing
8. **Testing:** Test in XAMPP environment at http://localhost/Registrar/Registrar-ike/

## Notes About Conversation Context
**Limitation:** The previous "Antigravity conversation" mentioned in the user message was not provided as text in the prompt. This document is based solely on verified source code inspection (files, SQL schema, migrations). Any specific requirements, bug reports, or unfinished tasks mentioned in that conversation that aren't evident from the codebase may be missing. Please obtain and merge that conversation context if available.
