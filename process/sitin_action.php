<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: ../pages/login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/admin_sitin_records.php");
    exit();
}

$action   = $_POST['action']   ?? '';
$redirect = $_POST['redirect'] ?? 'admin_sitin_records.php';
$back     = "../pages/" . basename($redirect);  // safety: strip any path traversal

// ── Helper: set flash and redirect ────────────────────────────────────────
function flash_redirect($msg, $type, $url) {
    $_SESSION['flash']      = $msg;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

// ════ TIME OUT ════
if ($action === 'timeout') {
    $id = intval($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id FROM sit_in WHERE id = ? AND status = 'active'");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        flash_redirect('Record not found or already completed.', 'warning', $back);
    }

    $pdo->prepare("
        UPDATE sit_in
        SET    status = 'completed',
               logout_time = NOW()
        WHERE  id = ? AND status = 'active'
    ")->execute([$id]);

    flash_redirect('Sit-in marked as completed.', 'success', $back);
}

// ════ CANCEL ════
if ($action === 'cancel') {
    $id = intval($_POST['id'] ?? 0);

    // Get user_id so we can restore the session
    $stmt = $pdo->prepare("SELECT user_id FROM sit_in WHERE id = ? AND status = 'active'");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        flash_redirect('Record not found or already closed.', 'warning', $back);
    }

    // Mark cancelled + set logout time
    $pdo->prepare("
        UPDATE sit_in
        SET    status = 'cancelled',
               logout_time = NOW()
        WHERE  id = ? AND status = 'active'
    ")->execute([$id]);

    // Restore one session to the student
    $pdo->prepare("
        UPDATE users
        SET    sessions = sessions + 1
        WHERE  id = ?
    ")->execute([$row['user_id']]);

    flash_redirect('Sit-in cancelled and student session restored.', 'success', $back);
}

// ════ DELETE ════
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);

    // Fetch first so we can restore the session if the sit-in was still active
    $row = $pdo->prepare("SELECT user_id, status FROM sit_in WHERE id = ?");
    $row->execute([$id]);
    $record = $row->fetch();

    if ($record) {
        if ($record['status'] === 'active') {
            $pdo->prepare("UPDATE users SET sessions = sessions + 1 WHERE id = ?")
                ->execute([$record['user_id']]);
        }
        $pdo->prepare("DELETE FROM sit_in WHERE id = ?")->execute([$id]);
    }

    flash_redirect('Record permanently deleted.', 'danger', $back);
}

// ════ CREATE ════
if ($action === 'create') {
    $user_id    = intval($_POST['user_id']    ?? 0);
    $purpose    = trim($_POST['purpose']      ?? '');
    $laboratory = trim($_POST['laboratory']   ?? '');
    $login_time = trim($_POST['login_time']   ?? date('H:i'));

    if (!$user_id || !$purpose || !$laboratory) {
        flash_redirect('Please fill in all required fields.', 'danger', $back);
    }

    // Check sessions remaining
    $stmt = $pdo->prepare("SELECT id_number, CONCAT(first_name,' ',last_name) AS full_name, sessions FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();

    if (!$student) {
        flash_redirect('Student not found.', 'danger', $back);
    }

    if ($student['sessions'] <= 0) {
        flash_redirect('Student has no remaining sessions.', 'warning', $back);
    }

    // Check if student already has an active sit-in
    $stmt = $pdo->prepare("SELECT id FROM sit_in WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        flash_redirect('This student already has an active sit-in session.', 'warning', $back);
    }

    // Insert sit-in record
    $pdo->prepare("
        INSERT INTO sit_in (user_id, id_number, name, purpose, laboratory, login_time, login_date, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'active', NOW())
    ")->execute([
        $user_id,
        $student['id_number'],
        $student['full_name'],
        $purpose,
        $laboratory,
        $login_time,
    ]);

    // Deduct one session
    $pdo->prepare("UPDATE users SET sessions = sessions - 1 WHERE id = ?")->execute([$user_id]);

    flash_redirect(
        'Sit-in created for ' . $student['full_name'] . '.',
        'success',
        $back
    );
}

// Fallback
flash_redirect('Unknown action.', 'danger', $back);