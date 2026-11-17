<?php
/**
 * Request Status Update Function
 * Updates the document request status and automatically sends PHPMailer email notification.
 * Safe, idempotent (prevents duplicate emails), and creates in-app notifications.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mailer.php';

/**
 * Update request status, send student email if status changed to trigger statuses.
 *
 * @param int $request_id
 * @param string $new_status ('Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Completed', 'Rejected')
 * @param string $remarks Optional registrar remarks
 * @return array ['success' => bool, 'message' => string, 'email_sent' => bool, 'email_error' => string]
 */
function updateDocumentRequestStatus($request_id, $new_status, $remarks = '') {
    global $conn;

    // Fetch existing request and student details including fee and requirements
    $sql = "SELECT dr.id, dr.user_id, dr.tracking_number, dr.status, dr.remarks, dr.last_notified_status,
                   u.full_name, u.email, u.personal_gmail, 
                   dt.document_name, dt.fee, dt.requirements
            FROM document_requests dr
            JOIN users u ON dr.user_id = u.id
            JOIN document_types dt ON dr.document_type_id = dt.id
            WHERE dr.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        return ['success' => false, 'message' => 'Document request not found.', 'email_sent' => false, 'email_error' => ''];
    }

    $old_status = $request['status'];
    $last_notified = $request['last_notified_status'];

    // Update database status and remarks
    $update_stmt = $conn->prepare("UPDATE document_requests SET status = ?, remarks = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("ssi", $new_status, $remarks, $request_id);
    $saved = $update_stmt->execute();
    $update_stmt->close();

    if (!$saved) {
        return ['success' => false, 'message' => 'Failed to update document status in database.', 'email_sent' => false, 'email_error' => ''];
    }

    // Build specific in-app notification message
    if ($new_status === 'Ready for Pickup') {
        $notif_msg = "Your requested document ({$request['document_name']} - {$request['tracking_number']}) is ready for pickup. Please proceed to the school cashier to pay the applicable document fee (₱" . number_format($request['fee'] ?? 0, 2) . "). Bring your original official receipt and all required physical documents when claiming your document at the Registrar Office.";
    } elseif ($new_status === 'Completed') {
        $notif_msg = "Your document ({$request['document_name']} - {$request['tracking_number']}) has been successfully released and claimed. Thank you!";
    } else {
        $notif_msg = "Your document request (" . $request['document_name'] . " - " . $request['tracking_number'] . ") status changed to: " . $new_status . ".";
    }

    if (!empty($remarks) && $new_status !== 'Ready for Pickup') {
        $notif_msg .= " Remarks: " . $remarks;
    }

    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notif_stmt->bind_param("is", $request['user_id'], $notif_msg);
    $notif_stmt->execute();
    $notif_stmt->close();

    // Trigger statuses for email notifications: Approved, Rejected, Ready for Pickup, Completed
    $email_trigger_statuses = ['Approved', 'Ready for Pickup', 'Rejected', 'Completed'];

    $email_sent = false;
    $email_error = '';

    // Only send email if:
    // 1. Status is in trigger list
    // 2. Status has changed from previous AND hasn't already been notified
    $should_notify = in_array($new_status, $email_trigger_statuses) && ($new_status !== $last_notified);

    if ($should_notify) {
        $recipient = !empty($request['personal_gmail']) ? $request['personal_gmail'] : $request['email'];
        
        $mail_result = sendDocumentStatusEmail(
            $recipient,
            $request['full_name'],
            $request['tracking_number'],
            $request['document_name'],
            $new_status,
            $remarks,
            $request['fee'] ?? 0.00,
            $request['requirements'] ?? ''
        );

        if ($mail_result['success']) {
            $email_sent = true;
            // Record last notified status to prevent duplicate emails
            $track_stmt = $conn->prepare("UPDATE document_requests SET last_notified_status = ? WHERE id = ?");
            $track_stmt->bind_param("si", $new_status, $request_id);
            $track_stmt->execute();
            $track_stmt->close();
        } else {
            $email_error = $mail_result['message'];
        }
    }

    return [
        'success' => true,
        'message' => 'Status updated successfully.',
        'email_sent' => $email_sent,
        'email_error' => $email_error
    ];
}
