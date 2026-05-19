<?php
session_start();

// Auth guard - redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please login first"));
    exit();
}

// Redirect admin to admin dashboard
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: admin_dashboard.php");
    exit();
}

$pageTitle = "Reservation - CCS Sit-in System";
$extraCSS  = "dashboard";
$basePath  = "../";

require_once __DIR__ . '/../config/database.php';

// ── Get fresh user data from DB ───────────────────────────────────────────
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$remaining_sessions = (int)($user['sessions'] ?? 0);

// ── Helper function to check if time is within 8 AM - 4 PM ─────────────────
function isTimeValid($time) {
    $timestamp = strtotime($time);
    $hour = (int)date('H', $timestamp);
    $minute = (int)date('i', $timestamp);
    if ($hour < 8 || $hour > 16) return false;
    if ($hour == 16 && $minute > 0) return false;
    return true;
}

// ── Handle form submission ────────────────────────────────────────────────
$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $purpose   = trim($_POST['purpose']  ?? '');
    $lab       = trim($_POST['lab']      ?? '');
    $time_in   = trim($_POST['time_in']  ?? '');
    $date      = trim($_POST['date']     ?? '');
    $pc_number = trim($_POST['pc_number'] ?? '');

    $errors = [];

    if (empty($purpose))   $errors[] = "Purpose is required";
    if (empty($lab))       $errors[] = "Laboratory is required";
    if (empty($time_in))   $errors[] = "Time in is required";
    if (empty($date))      $errors[] = "Date is required";
    if (empty($pc_number)) $errors[] = "Please select a PC";

    // Validate date
    $formatted_date = '';
    if (!empty($date)) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $formatted_date = $date;
            if ($formatted_date < date('Y-m-d')) {
                $errors[] = "You cannot reserve for a past date";
            }
        } else {
            $errors[] = "Invalid date format";
        }
    }

    // Validate time
    if (!empty($time_in) && !isTimeValid($time_in)) {
        $errors[] = "Reservation time must be between 8:00 AM and 4:00 PM";
    }

    // Check remaining sessions
    if ($remaining_sessions <= 0) {
        $errors[] = "You have no remaining sessions left";
    }

    // Check if user already has a pending reservation
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status = 'pending'");
    $pendingStmt->execute([$user_id]);
    if ($pendingStmt->fetchColumn() >= 1) {
        $errors[] = "You already have a pending reservation. Please wait for it to be approved or rejected before making a new one.";
    }

    // ── PC availability check: block pending/approved reservations and active sit‑ins ──
    if (empty($errors)) {
        // 1. Check if any other reservation (pending or approved) exists for this PC on the same date
        $checkRes = $pdo->prepare("
            SELECT COUNT(*) FROM reservations 
            WHERE laboratory = ? AND reservation_date = ? AND pc_number = ? AND status IN ('approved', 'pending')
        ");
        $checkRes->execute([$lab, $formatted_date, $pc_number]);
        if ($checkRes->fetchColumn() > 0) {
            $errors[] = "This PC already has a pending or approved reservation for that date. Please choose another PC.";
        } else {
            // 2. Check if there is an active sit‑in on this PC for the same date
            $checkSit = $pdo->prepare("
                SELECT COUNT(*) FROM sit_in 
                WHERE laboratory = ? AND login_date = ? AND pc_number = ? AND status = 'active'
            ");
            $checkSit->execute([$lab, $formatted_date, $pc_number]);
            if ($checkSit->fetchColumn() > 0) {
                $errors[] = "This PC is currently in use (active sit‑in). Please choose another PC.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $insert_stmt = $pdo->prepare("
                INSERT INTO reservations (user_id, id_number, name, purpose, laboratory, pc_number, time_in, reservation_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $insert_stmt->execute([
                $user_id,
                $user['id_number'],
                trim($user['first_name'] . ' ' . $user['last_name']),
                $purpose,
                $lab,
                $pc_number,
                $time_in,
                $formatted_date
            ]);

            $success_message = "Reservation submitted successfully! Please wait for admin approval.";

            // Refresh sessions
            $stmt = $pdo->prepare("SELECT sessions FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $remaining_sessions = (int)($stmt->fetchColumn() ?? 0);

        } catch (PDOException $e) {
            error_log("Reservation error: " . $e->getMessage());
            $error_message = "Failed to submit reservation. Please try again.";
        }
    } else {
        $error_message = implode(", ", $errors);
    }
}

// ── Fetch this student's existing reservations (for display) ──────────────
$my_reservations = $pdo->prepare("
    SELECT * FROM reservations
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$my_reservations->execute([$user_id]);
$my_reservations = $my_reservations->fetchAll();

$laboratories = ['524', '526', '528', '530', '517'];
$purposes     = ['C Programming','Java','PHP','ASP.Net','C#','Python','Research','Thesis','Capstone','Other'];
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/user_navigation.php'; ?>

<style>
.res-wrap { min-height: 100vh; padding: 1.5rem 24px 48px; }
.res-inner { max-width: 820px; margin: 0 auto; }
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
.dashboard-header h1 { font-size: 1.75rem; font-weight: 700; color: #f1f5f9; }
.date-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: rgba(10,18,40,0.70); border: 1px solid rgba(255,255,255,0.10); border-radius: 999px; color: #cbd5e1; font-size: 0.875rem; backdrop-filter: blur(12px); }
.date-badge i { color: #0ea5e9; }
.res-alert { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; }
.res-alert.success { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }
.res-alert.error { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; }
.res-card { background: rgba(10,18,40,0.82); border: 1px solid rgba(255,255,255,0.10); border-radius: 20px; backdrop-filter: blur(24px); padding: 32px; margin-bottom: 20px; }
.res-card-title { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; padding-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.07); }
.res-card-title i { color: #0ea5e9; font-size: 1.1rem; }
.res-card-title h2 { color: #f1f5f9; font-size: 1.15rem; font-weight: 700; margin: 0; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; color: #64748b; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px; }
.form-label i { margin-right: 5px; color: #60a5fa; }
.form-control { width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #f1f5f9; font-size: 0.92rem; font-family: inherit; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; box-sizing: border-box; appearance: none; }
.form-control:focus { outline: none; border-color: rgba(96,165,250,0.45); background: rgba(255,255,255,0.08); box-shadow: 0 0 0 3px rgba(96,165,250,0.12); }
.form-control::placeholder { color: #475569; }
.form-control[readonly], .form-control[disabled] { background: rgba(255,255,255,0.03); color: #64748b; cursor: not-allowed; border-color: rgba(255,255,255,0.05); }
.sessions-display { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 13px 16px; display: flex; align-items: center; gap: 10px; }
.btn-row { display: flex; gap: 15px; justify-content: center; align-items: center; margin-top: 28px; }
.btn-reserve { background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; border: none; padding: 14px 48px; border-radius: 999px; font-size: 1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 20px rgba(37,99,235,0.4); transition: all 0.25s ease; font-family: inherit; }
.btn-reserve:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(37,99,235,0.55); }
.btn-reserve:disabled { background: rgba(100,116,139,0.3); color: #64748b; cursor: not-allowed; box-shadow: none; transform: none; }
.btn-cancel { background: rgba(255,255,255,0.07); color: #94a3b8; text-decoration: none; padding: 14px 32px; border-radius: 999px; font-size: 1rem; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.08); transition: all 0.25s ease; }
.btn-cancel:hover { background: rgba(255,255,255,0.12); color: #f1f5f9; }
.info-card { background: rgba(37,99,235,0.06); border: 1px solid rgba(37,99,235,0.18); border-radius: 16px; padding: 20px; display: flex; align-items: flex-start; gap: 15px; margin-bottom: 28px; }
.info-card i { color: #60a5fa; font-size: 1.4rem; flex-shrink: 0; margin-top: 2px; }
.info-card h4 { color: #f1f5f9; margin-bottom: 6px; font-size: 0.95rem; }
.info-card ul { color: #94a3b8; font-size: 0.85rem; margin-left: 18px; line-height: 1.7; }
.recent-card { background: rgba(10,18,40,0.82); border: 1px solid rgba(255,255,255,0.10); border-radius: 20px; backdrop-filter: blur(24px); overflow: hidden; }
.recent-header { display: flex; align-items: center; gap: 8px; padding: 16px 24px; background: rgba(37,99,235,0.20); border-bottom: 1px solid rgba(255,255,255,0.08); }
.recent-header i { color: #0ea5e9; }
.recent-header h3 { color: #f1f5f9; font-size: 0.95rem; font-weight: 600; margin: 0; }
.recent-table { width: 100%; border-collapse: collapse; }
.recent-table th { padding: 11px 16px; text-align: left; color: #64748b; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; background: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.06); }
.recent-table td { padding: 11px 16px; color: #cbd5e1; font-size: 0.85rem; border-bottom: 1px solid rgba(255,255,255,0.04); }
.recent-table tr:last-child td { border-bottom: none; }
.recent-table tr:hover td { background: rgba(255,255,255,0.03); }
.badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.badge-pending { background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.30); color: #fcd34d; }
.badge-approved { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }
.badge-rejected { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; }
.no-recent { padding: 30px; text-align: center; color: #475569; font-size: 0.875rem; }
#pcGrid { display: flex; flex-wrap: wrap; gap: 8px; max-height: 280px; overflow-y: auto; padding: 12px; background: rgba(255,255,255,0.02); border-radius: 16px; margin-top: 8px; }
.pc-tile { width: 70px; text-align: center; padding: 6px 0; border-radius: 12px; font-size: 0.8rem; font-weight: 600; transition: all 0.2s ease; cursor: pointer; border: 1px solid transparent; }
.pc-tile.available { background: rgba(16,185,129,0.2); border-color: rgba(16,185,129,0.4); color: #6ee7b7; }
.pc-tile.available:hover { background: rgba(16,185,129,0.4); transform: scale(1.02); }
.pc-tile.reserved { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #fca5a5; cursor: not-allowed; }
.pc-tile.selected { background: #2563eb; color: white; border-color: white; box-shadow: 0 0 0 2px rgba(37,99,235,0.5); }
.pc-legend { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 8px; font-size: 0.75rem; color: #94a3b8; }
.legend-item { display: inline-flex; align-items: center; gap: 6px; }
.legend-circle { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
.legend-circle.available { background: #10b981; box-shadow: 0 0 0 1px rgba(16,185,129,0.5); }
.legend-circle.reserved { background: #ef4444; }
</style>

<div class="res-wrap">
    <div class="res-inner">

        <div class="dashboard-header">
            <h1>Lab Reservation</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="res-alert success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
        <div class="res-alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="res-card">
            <div class="res-card-title">
                <i class="fas fa-calendar-check"></i>
                <h2>Reserve a Laboratory Slot</h2>
            </div>

            <form method="POST" action="" id="reservationForm">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-id-card"></i> ID Number</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['id_number'] ?? $_SESSION['id_number']); ?>" readonly disabled>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user"></i> Student Name</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?>" readonly disabled>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tasks"></i> Purpose <span style="color:#f87171;">*</span></label>
                    <select name="purpose" class="form-control" required>
                        <option value="" style="background:#1e293b;">Select Purpose</option>
                        <?php foreach ($purposes as $p): ?>
                        <option value="<?php echo $p; ?>" style="background:#1e293b;" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-flask"></i> Laboratory <span style="color:#f87171;">*</span></label>
                    <select name="lab" id="labSelect" class="form-control" required>
                        <option value="" style="background:#1e293b;">Select Laboratory</option>
                        <?php foreach ($laboratories as $l): ?>
                        <option value="<?php echo $l; ?>" style="background:#1e293b;" <?php echo (isset($_POST['lab']) && $_POST['lab'] === $l) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-clock"></i> Time In <span style="color:#f87171;">*</span></label>
                    <input type="time" name="time_in" class="form-control" min="08:00" max="16:00" value="<?php echo $_POST['time_in'] ?? '08:00'; ?>" required>
                    <small style="color:#475569;font-size:0.75rem;margin-top:5px;display:block;">⏰ Available hours: 8:00 AM – 4:00 PM</small>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Date <span style="color:#f87171;">*</span></label>
                    <input type="date" name="date" id="datePicker" class="form-control" value="<?php echo isset($_POST['date']) ? $_POST['date'] : date('Y-m-d'); ?>" required>
                    <small style="color:#475569;font-size:0.75rem;margin-top:5px;display:block;">📅 Select a date (future dates only)</small>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-desktop"></i> Select PC <span style="color:#f87171;">*</span></label>
                    <div id="pcGrid"><div class="text-muted">Select a laboratory and date first.</div></div>
                    <input type="hidden" name="pc_number" id="selectedPC" required>
                </div>
                <div class="pc-legend">
                    <span class="legend-item"><span class="legend-circle available"></span> Available</span>
                    <span class="legend-item"><span class="legend-circle reserved"></span> Unavailable</span>
                </div>
                <br>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-hourglass-half"></i> Remaining Sessions</label>
                    <div class="sessions-display">
                        <span style="color: <?php echo $remaining_sessions > 10 ? '#6ee7b7' : ($remaining_sessions > 5 ? '#fcd34d' : '#fca5a5'); ?>; font-size: 1.2rem; font-weight: 700;"><?php echo $remaining_sessions; ?></span>
                        <span style="color:#94a3b8;font-size:0.9rem;">sessions left</span>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" name="reserve" class="btn-reserve" <?php echo $remaining_sessions <= 0 ? 'disabled' : ''; ?>><i class="fas fa-calendar-check"></i> Reserve</button>
                    <a href="dashboard.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>

        <div class="info-card">
            <i class="fas fa-info-circle"></i>
            <div>
                <h4>Reservation Guidelines</h4>
                <ul>
                    <li>Reservations are subject to admin approval</li>
                    <li>Please arrive on time for your reservation</li>
                    <li>Cancellations must be made at least 1 hour before the scheduled time</li>
                    <li>Only one pending reservation allowed at a time</li>
                    <li>Reservations only allowed between 8:00 AM and 4:00 PM</li>
                    <li><strong>Select a specific PC when reserving.</strong></li>
                </ul>
            </div>
        </div>

        <?php if (!empty($my_reservations)): ?>
        <div class="recent-card">
            <div class="recent-header"><i class="fas fa-history"></i><h3>My Recent Reservations</h3></div>
            <table class="recent-table">
                <thead><tr><th>Purpose</th><th>Laboratory</th><th>PC</th><th>Date</th><th>Time In</th><th>Status</th></td></thead>
                <tbody>
                    <?php foreach ($my_reservations as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($r['laboratory']); ?></td>
                        <td><?php echo htmlspecialchars($r['pc_number'] ?? '—'); ?></td>
                        <td style="color:#94a3b8;"><?php echo $r['reservation_date'] ? date('M j, Y', strtotime($r['reservation_date'])) : '—'; ?></td>
                        <td><?php echo $r['time_in'] ? date('h:i A', strtotime($r['time_in'])) : '—'; ?></td>
                        <td><?php $s = $r['status'] ?? 'pending'; $cls = match($s) { 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', default => 'badge-pending' }; ?><span class="badge <?php echo $cls; ?>"><?php echo ucfirst($s); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
const labSelect = document.getElementById('labSelect');
const datePicker = document.getElementById('datePicker');
const pcGrid = document.getElementById('pcGrid');
const selectedPcInput = document.getElementById('selectedPC');

function loadPCs() {
    const lab = labSelect.value;
    const date = datePicker.value;
    if (!lab || !date) {
        pcGrid.innerHTML = '<div class="text-muted">Select a laboratory and date first.</div>';
        selectedPcInput.value = '';
        return;
    }
    pcGrid.innerHTML = '<div class="text-muted">Loading PCs...</div>';
    fetch(`../process/get_pcs.php?lab=${encodeURIComponent(lab)}&date=${encodeURIComponent(date)}`)
        .then(res => res.json())
        .then(data => {
            pcGrid.innerHTML = '';
            if (!data.length) {
                pcGrid.innerHTML = '<div class="text-muted">No PCs found for this lab.</div>';
                return;
            }
            data.forEach(pc => {
                const tile = document.createElement('div');
                // Map 'broken' to 'reserved' for consistency (non‑clickable)
                let statusClass = pc.status;
                if (statusClass === 'broken') statusClass = 'reserved';
                tile.className = `pc-tile ${statusClass}`;
                tile.textContent = pc.pc_number;
                if (pc.status === 'available') {
                    tile.addEventListener('click', () => {
                        document.querySelectorAll('#pcGrid .pc-tile').forEach(t => t.classList.remove('selected'));
                        tile.classList.add('selected');
                        selectedPcInput.value = pc.pc_number;
                    });
                } else {
                    tile.style.cursor = 'not-allowed';
                }
                pcGrid.appendChild(tile);
            });
        })
        .catch(err => {
            console.error(err);
            pcGrid.innerHTML = '<div class="text-danger">Error loading PCs.</div>';
        });
}

labSelect.addEventListener('change', loadPCs);
datePicker.addEventListener('change', loadPCs);
if (labSelect.value && datePicker.value) loadPCs();
</script>

<?php include '../includes/footer.php'; ?>
