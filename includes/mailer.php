<?php
/**
 * Mailer Helper using PHPMailer
 * Sends automatic emails for document request status updates.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';

/**
 * Send an email notification when a document request status changes.
 * 
 * @param string $recipient_email The student's personal Gmail address
 * @param string $student_name Full name of the student
 * @param string $tracking_number Document tracking number
 * @param string $document_name Name of requested document
 * @param string $new_status New status (Approved, Rejected, Ready for Pickup, Completed, etc.)
 * @param string|null $remarks Optional remarks from registrar staff
 * @param float $fee Applicable document fee
 * @param string $requirements Physical requirements list
 * @return array ['success' => bool, 'message' => string]
 */
function sendDocumentStatusEmail($recipient_email, $student_name, $tracking_number, $document_name, $new_status, $remarks = '', $fee = 0.00, $requirements = '') {
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid or missing recipient email address.'];
    }

    $config = require __DIR__ . '/../config/mail.php';

    // If SMTP credentials are not yet configured in .env, gracefully skip without crashing
    if (empty($config['username']) || empty($config['password'])) {
        error_log("PHPMailer Notice: SMTP credentials not set in .env. Email skipped for request $tracking_number.");
        return ['success' => false, 'message' => 'SMTP credentials not configured. Email notification skipped.'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = ($config['secure'] === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';

        // Timeout settings
        $mail->Timeout    = 10;

        // Sender & Recipient
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($recipient_email, $student_name);
        $mail->addReplyTo($config['from_email'], $config['from_name']);

        // Color badge determination based on status
        $status_color = '#0d6efd'; // Blue
        if ($new_status === 'Approved' || $new_status === 'Completed') {
            $status_color = '#198754'; // Green
        } elseif ($new_status === 'Ready for Pickup') {
            $status_color = '#059669'; // Emerald Green
        } elseif ($new_status === 'Rejected') {
            $status_color = '#dc3545'; // Red
        }

        $subject = "Document Request Update: $tracking_number - $new_status";
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Remarks block
        $remarks_html = '';
        if (!empty($remarks)) {
            $clean_remarks = nl2br(htmlspecialchars($remarks, ENT_QUOTES, 'UTF-8'));
            $remarks_html = "
                <div style='margin-top: 20px; padding: 14px; background: #f8f9fa; border-left: 4px solid #f97316; border-radius: 4px;'>
                    <strong style='color: #334155; font-size: 13px; text-transform: uppercase;'>Registrar Staff Remarks:</strong>
                    <p style='margin: 6px 0 0; color: #475569; font-size: 14px;'>$clean_remarks</p>
                </div>
            ";
        }

        // Instructions block
        $safe_name = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
        $safe_tracking = htmlspecialchars($tracking_number, ENT_QUOTES, 'UTF-8');
        $safe_doc = htmlspecialchars($document_name, ENT_QUOTES, 'UTF-8');
        $safe_status = htmlspecialchars($new_status, ENT_QUOTES, 'UTF-8');
        $fee_formatted = number_format(floatval($fee), 2);

        $instructions_html = '';
        if ($new_status === 'Ready for Pickup') {
            $reqs_display = !empty($requirements) ? nl2br(htmlspecialchars($requirements, ENT_QUOTES, 'UTF-8')) : '• Valid Student ID or Government ID';
            $instructions_html = "
                <div style='margin-top: 24px; padding: 18px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px;'>
                    <strong style='color: #065f46; font-size: 14px; display: block; margin-bottom: 8px;'>
                        🔔 Document Claiming & Payment Instructions
                    </strong>
                    <p style='margin: 0 0 10px; color: #047857; font-size: 14px; line-height: 1.5;'>
                        Your requested document is ready for pickup. Please proceed to the school cashier to pay the applicable document fee (<strong>₱$fee_formatted</strong>). Bring your original official receipt and all required physical documents when claiming your document at the Registrar Office.
                    </p>
                    <div style='background: #ffffff; padding: 12px; border-radius: 6px; border: 1px solid #d1fae5; margin-top: 10px;'>
                        <strong style='color: #1e293b; font-size: 12px; text-transform: uppercase;'>Required Physical Documents to Present:</strong>
                        <div style='font-size: 13px; color: #334155; margin-top: 6px; line-height: 1.5;'>$reqs_display</div>
                    </div>
                </div>
            ";
        } else {
            $instructions_html = "
                <div style='margin-top: 24px; padding-top: 18px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; line-height: 1.5;'>
                    You can monitor real-time processing and counter updates anytime through the CTU Student Portal.
                </div>
            ";
        }

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; }
                .email-card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
                .email-header { background: linear-gradient(135deg, #0d47a1 0%, #1565c0 100%); padding: 28px 24px; text-align: center; color: #ffffff; }
                .email-header h1 { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px; }
                .email-header p { margin: 6px 0 0; font-size: 12px; opacity: 0.85; text-transform: uppercase; letter-spacing: 1px; }
                .email-body { padding: 32px 28px; }
                .status-badge { display: inline-block; padding: 8px 16px; border-radius: 50px; color: #ffffff; background-color: $status_color; font-weight: 700; font-size: 14px; margin: 12px 0; }
                .info-table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
                .info-table td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
                .info-table td.label { color: #64748b; width: 40%; font-weight: 500; }
                .info-table td.val { color: #0f172a; font-weight: 600; text-align: right; }
                .email-footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
            </style>
        </head>
        <body>
            <div class='email-card'>
                <div class='email-header'>
                    <h1>Cebu Technological University</h1>
                    <p>Office of the Campus Registrar &bull; Document Processing</p>
                </div>
                <div class='email-body'>
                    <p style='font-size: 15px; margin-top: 0;'>Hello, <strong>$safe_name</strong>,</p>
                    <p style='font-size: 14px; color: #475569; line-height: 1.5;'>
                        Your document request status has been updated by the campus registrar's office.
                    </p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <div style='font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 600;'>Current Status</div>
                        <div class='status-badge'>$safe_status</div>
                    </div>

                    <table class='info-table'>
                        <tr>
                            <td class='label'>Tracking Number</td>
                            <td class='val' style='font-family: monospace; font-size: 15px;'>$safe_tracking</td>
                        </tr>
                        <tr>
                            <td class='label'>Requested Document</td>
                            <td class='val'>$safe_doc</td>
                        </tr>
                        <tr>
                            <td class='label'>Applicable Cashier Fee</td>
                            <td class='val'>₱$fee_formatted</td>
                        </tr>
                        <tr>
                            <td class='label'>Notification Sent To</td>
                            <td class='val'>$recipient_email</td>
                        </tr>
                    </table>

                    $remarks_html
                    $instructions_html
                </div>
                <div class='email-footer'>
                    &copy; " . date('Y') . " Cebu Technological University - Daanbantayan Campus<br>
                    This is an automated notification. Please do not reply directly to this email.
                </div>
            </div>
        </body>
        </html>
        ";

        if ($new_status === 'Ready for Pickup') {
            $mail->AltBody = "Hello $student_name,\n\nYour requested document is ready for pickup. Please proceed to the school cashier to pay the applicable document fee (₱$fee_formatted). Bring your original official receipt and all required physical documents when claiming your document at the Registrar Office.\n\nTracking #: $tracking_number\nDocument: $document_name\n\nCTU Registrar Office";
        } else {
            $mail->AltBody = "Hello $student_name,\n\nYour document request ($tracking_number - $document_name) status has been updated to: $new_status.\n\n" . (!empty($remarks) ? "Remarks: $remarks\n\n" : "") . "Please log in to the student portal for details.\n\nCTU Registrar Office";
        }

        $mail->send();
        return ['success' => true, 'message' => 'Notification email sent successfully.'];
    } catch (Exception $e) {
        error_log("PHPMailer Exception: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo];
    }
}
