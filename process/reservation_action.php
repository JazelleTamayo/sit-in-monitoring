<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$redirect = '../pages/admin_reservation.php';

if (!$id) {
    header("Location: $redirect?error=Invalid+request");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch();
if (!$res) {
    header("Location: $redirect?error=Reservation+not+found");
    exit();
}

// Helper: insert notification
function sendNotification($pdo, $user_id, $title, $message, $type = 'reservation') {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$user_id, $title, $message, $type]);
}

// Helper: restore one session if the reservation was pending or approved-without-active-sitin (when rejecting or deleting)
function restoreSession($pdo, $res) {
    if ($res['status'] === 'pending') {
        // Reservation was pending — session was deducted at reservation time, restore it
        $stmt = $pdo->prepare("UPDATE users SET sessions = sessions + 1 WHERE id = ?");
        $stmt->execute([$res['user_id']]);
    } elseif ($res['status'] === 'approved') {
        // Approved reservations trigger auto sit-in which deducts a session.
        // Only restore if there is no active sit-in linked to this reservation (i.e. auto sit-in was skipped)
        $check = $pdo->prepare("SELECT id FROM sit_in WHERE reservation_id = ? AND status = 'active'");
        $check->execute([$res['id']]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("UPDATE users SET sessions = sessions + 1 WHERE id = ?");
            $stmt->execute([$res['user_id']]);
        }
    }
}

// ⭐ NEW HELPER: Auto sit-in the student when reservation is approved
function autoSitIn($pdo, $res, $new_pc = null) {
    $pc_number = $new_pc ?? $res['pc_number'];
    
    // Check if student already has an active sit-in
    $checkStmt = $pdo->prepare("
        SELECT id FROM sit_in 
        WHERE user_id = ? AND status = 'active'
    ");
    $checkStmt->execute([$res['user_id']]);
    if ($checkStmt->fetch()) {
        // Student already has active sit-in, don't create duplicate
        return false;
    }
    
    // Reduce student's session count by 1 — abort if they have none left
    $sessionStmt = $pdo->prepare("UPDATE users SET sessions = sessions - 1 WHERE id = ? AND sessions > 0");
    $sessionStmt->execute([$res['user_id']]);
    if ($sessionStmt->rowCount() === 0) {
        // Student had 0 sessions — do not create a free sit-in
        return false;
    }

    // Create sit-in record
    $sitStmt = $pdo->prepare("
        INSERT INTO sit_in (user_id, id_number, name, laboratory, pc_number, purpose, login_time, login_date, status, reservation_id)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), CURDATE(), 'active', ?)
    ");
    $sitStmt->execute([
        $res['user_id'],
        $res['id_number'],
        $res['name'],
        $res['laboratory'],
        $pc_number,
        $res['purpose'],
        $res['id']
    ]);
    
    return true;
}

$success = '';
$error = '';

switch ($action) {
    case 'approve':
        // Update reservation status
        $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?")->execute([$id]);
        
        // ⭐ AUTO SIT-IN THE STUDENT
        autoSitIn($pdo, $res);
        
        // Send notification (updated message)
        $title = "Reservation Approved & Auto Checked-in";
        $message = "Your reservation for Lab {$res['laboratory']} on {$res['reservation_date']} (PC {$res['pc_number']}) has been APPROVED. You have been automatically checked in. Proceed to your assigned PC.";
        sendNotification($pdo, $res['user_id'], $title, $message);
        $success = "Reservation approved and student automatically checked in.";
        break;

    case 'reassign_approve':
        $new_pc = $_POST['new_pc'] ?? '';
        $reason = $_POST['reason'] ?? 'The original PC was unavailable';
        if (!$new_pc) {
            $error = "No PC selected for reassignment.";
            break;
        }
        
        // Update reservation with new PC and approved status
        $update = $pdo->prepare("UPDATE reservations SET pc_number = ?, status = 'approved', alt_pc_suggestion = ? WHERE id = ?");
        $update->execute([$new_pc, $reason, $id]);
        
        // ⭐ AUTO SIT-IN THE STUDENT with the NEW PC
        autoSitIn($pdo, $res, $new_pc);
        
        // Send notification (updated message)
        $title = "Reservation Approved & Auto Checked-in (PC Changed)";
        $message = "Your reservation for Lab {$res['laboratory']} has been APPROVED. PC changed from {$res['pc_number']} to {$new_pc} because: {$reason}. You have been automatically checked in.";
        sendNotification($pdo, $res['user_id'], $title, $message);
        $success = "Reservation reassigned, approved, and student automatically checked in.";
        break;

    case 'reject':
        $reason = trim($_POST['reject_reason'] ?? 'No reason provided');
        restoreSession($pdo, $res);
        $pdo->prepare("UPDATE reservations SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $id]);
        $title = "Reservation Rejected";
        $message = "Your reservation for Lab {$res['laboratory']} on {$res['reservation_date']} has been REJECTED. Reason: {$reason}";
        sendNotification($pdo, $res['user_id'], $title, $message);
        $success = "Reservation rejected.";
        break;

    case 'delete':
        restoreSession($pdo, $res);
        $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
        $title = "Reservation Deleted by Admin";
        $message = "Your reservation for Lab {$res['laboratory']} on {$res['reservation_date']} has been deleted by admin.";
        sendNotification($pdo, $res['user_id'], $title, $message);
        $success = "Reservation deleted.";
        break;

    default:
        $error = "Invalid action.";
}

if ($error) {
    header("Location: $redirect?error=" . urlencode($error));
} else {
    header("Location: $redirect?success=" . urlencode($success));
}
exit();
