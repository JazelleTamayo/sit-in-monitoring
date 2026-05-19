<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Lab Management - CCS Sit-in System";
$basePath  = "../";

$success_message = '';
$error_message   = '';

// ── Flash messages from redirect ──────────────────────────────────────────
if (isset($_SESSION['flash'])) {
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    if ($flash_type === 'success') $success_message = $_SESSION['flash'];
    else $error_message = $_SESSION['flash'];
    unset($_SESSION['flash'], $_SESSION['flash_type']);
}

$laboratories = ['524', '526', '528', '530', '517'];

// ── Handle POST actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add new PC
    if ($action === 'add_pc') {
        $lab       = trim($_POST['lab']       ?? '');
        $pc_number = trim($_POST['pc_number'] ?? '');
        $status    = trim($_POST['status']    ?? 'available');
        $notes     = trim($_POST['notes']     ?? '');

        if (!in_array($lab, $laboratories) || empty($pc_number)) {
            $error_message = "Invalid lab or PC number.";
        } elseif (!in_array($status, ['available', 'broken'])) {
            $error_message = "Invalid status.";
        } else {
            // Check duplicate
            $check = $pdo->prepare("SELECT id FROM pcs WHERE lab = ? AND pc_number = ?");
            $check->execute([$lab, $pc_number]);
            if ($check->fetch()) {
                $error_message = "PC $pc_number already exists in Lab $lab.";
            } else {
                $pdo->prepare("INSERT INTO pcs (lab, pc_number, status, notes) VALUES (?, ?, ?, ?)")
                    ->execute([$lab, $pc_number, $status, $notes ?: null]);
                $success_message = "PC $pc_number added to Lab $lab successfully.";
            }
        }
    }

    // Update PC status / notes
    if ($action === 'update_pc') {
        $id     = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $notes  = trim($_POST['notes']  ?? '');

        if (!$id || !in_array($status, ['available', 'broken'])) {
            $error_message = "Invalid update data.";
        } else {
            // If marking available, make sure it's not in an active sit-in
            if ($status === 'available') {
                $row = $pdo->prepare("SELECT lab, pc_number FROM pcs WHERE id = ?");
                $row->execute([$id]);
                $pc = $row->fetch();
                if ($pc) {
                    $active = $pdo->prepare("SELECT id FROM sit_in WHERE laboratory = ? AND pc_number = ? AND status = 'active'");
                    $active->execute([$pc['lab'], $pc['pc_number']]);
                    if ($active->fetch()) {
                        $error_message = "Cannot mark PC as available — it has an active sit-in session.";
                    }
                }
            }
            if (!$error_message) {
                $pdo->prepare("UPDATE pcs SET status = ?, notes = ? WHERE id = ?")
                    ->execute([$status, $notes ?: null, $id]);
                $success_message = "PC updated successfully.";
            }
        }
    }

    // Delete PC
    if ($action === 'delete_pc') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            $error_message = "Invalid PC.";
        } else {
            // Check for active sit-in
            $row = $pdo->prepare("SELECT lab, pc_number FROM pcs WHERE id = ?");
            $row->execute([$id]);
            $pc = $row->fetch();
            if ($pc) {
                $active = $pdo->prepare("SELECT id FROM sit_in WHERE laboratory = ? AND pc_number = ? AND status = 'active'");
                $active->execute([$pc['lab'], $pc['pc_number']]);
                if ($active->fetch()) {
                    $error_message = "Cannot delete PC — it has an active sit-in session.";
                } else {
                    $pdo->prepare("DELETE FROM pcs WHERE id = ?")->execute([$id]);
                    $success_message = "PC deleted successfully.";
                }
            } else {
                $error_message = "PC not found.";
            }
        }
    }

    // Bulk status update for a whole lab
    if ($action === 'bulk_update') {
        $lab        = trim($_POST['lab']    ?? '');
        $new_status = trim($_POST['bulk_status'] ?? '');
        if (!in_array($lab, $laboratories) || !in_array($new_status, ['available', 'broken'])) {
            $error_message = "Invalid bulk update parameters.";
        } else {
            // Skip PCs that are in active sit-ins
            $pdo->prepare("
                UPDATE pcs SET status = ?
                WHERE lab = ?
                AND pc_number NOT IN (
                    SELECT pc_number FROM sit_in WHERE laboratory = ? AND status = 'active' AND pc_number IS NOT NULL
                )
            ")->execute([$new_status, $lab, $lab]);
            $success_message = "Bulk update applied to Lab $lab (active sit-in PCs were skipped).";
        }
    }
}

// ── Fetch PCs grouped by lab ───────────────────────────────────────────────
$all_pcs = $pdo->query("SELECT * FROM pcs ORDER BY lab, pc_number")->fetchAll();

// Group by lab
$pcs_by_lab = [];
foreach ($laboratories as $lab) $pcs_by_lab[$lab] = [];
foreach ($all_pcs as $pc) {
    if (isset($pcs_by_lab[$pc['lab']])) {
        $pcs_by_lab[$pc['lab']][] = $pc;
    }
}

// Active sit-ins to overlay on grid
$active_sitins = $pdo->query("
    SELECT laboratory, pc_number, name, id_number, login_time
    FROM sit_in
    WHERE status = 'active' AND pc_number IS NOT NULL
")->fetchAll();

$active_map = [];
foreach ($active_sitins as $s) {
    $active_map[$s['laboratory']][$s['pc_number']] = $s;
}

// Stats per lab
$lab_stats = [];
foreach ($laboratories as $lab) {
    $pcs = $pcs_by_lab[$lab];
    $total     = count($pcs);
    $available = count(array_filter($pcs, fn($p) => $p['status'] === 'available'));
    $broken    = count(array_filter($pcs, fn($p) => $p['status'] === 'broken'));
    $occupied  = isset($active_map[$lab]) ? count($active_map[$lab]) : 0;
    $lab_stats[$lab] = compact('total', 'available', 'broken', 'occupied');
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
:root {
    --primary:           #2563eb;
    --primary-light:     #3b82f6;
    --accent:            #0ea5e9;
    --text-primary:      #f1f5f9;
    --text-secondary:    #cbd5e1;
    --text-muted:        #94a3b8;
    --border-light:      rgba(255,255,255,0.10);
    --card-bg:           rgba(10,18,40,0.82);
    --card-bg-hover:     rgba(14,24,52,0.90);
    --card-border:       rgba(255,255,255,0.10);
    --card-border-hover: rgba(14,165,233,0.45);
    --radius-md:         16px;
    --radius-sm:         10px;
    --transition:        all 0.25s ease;

    /* Status — muted tones that don't scream */
    --c-available: #10b981;
    --c-occupied:  #ef4444;
    --c-broken:    #f59e0b;
}

.lab-page-container {
    min-height: 100vh;
    padding: 1.5rem 32px 48px;
    position: relative;
}
.lab-page-container::before {
    content: '';
    position: fixed; inset: 0;
    background:
        radial-gradient(ellipse at 5% 0%,   rgba(37,99,235,0.35)  0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%),
        radial-gradient(ellipse at 75% 15%,  rgba(124,58,237,0.18) 0%, transparent 38%),
        radial-gradient(ellipse at 25% 85%,  rgba(16,185,129,0.12) 0%, transparent 38%);
    pointer-events: none; z-index: -1;
}
.lab-main { max-width: 1300px; margin: 0 auto; position: relative; z-index: 2; }

/* ── Page header ─────────────────────────────────────────────── */
.page-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 28px; padding-bottom: 20px;
    border-bottom: 1px solid var(--border-light);
}
.page-header h1 { font-size: 1.75rem; font-weight: 700; color: var(--text-primary); letter-spacing: -0.02em; }
.date-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 18px; background: rgba(10,18,40,0.70);
    border: 1px solid var(--border-light); border-radius: 999px;
    color: var(--text-secondary); font-size: 0.875rem; backdrop-filter: blur(12px);
}
.date-badge i { color: var(--accent); }

/* ── Alerts ──────────────────────────────────────────────────── */
.alert {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 20px; border-radius: var(--radius-sm);
    margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 500;
}
.alert-success { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.30); color: #6ee7b7; }
.alert-error   { background: rgba(239,68,68,0.12);  border: 1px solid rgba(239,68,68,0.30);  color: #fca5a5; }

/* ── Summary cards ───────────────────────────────────────────── */
.summary-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
    gap: 16px; margin-bottom: 28px;
}
.summary-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    padding: 20px; text-align: center;
    transition: var(--transition);
}
.summary-card:hover { background: var(--card-bg-hover); border-color: var(--card-border-hover); }
.summary-card .s-icon {
    width: 42px; height: 42px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; margin: 0 auto 12px;
}
.summary-card .s-val { font-size: 2rem; font-weight: 800; line-height: 1; color: var(--text-primary); }
.summary-card .s-lbl { font-size: 0.78rem; color: var(--text-muted); margin-top: 5px; text-transform: uppercase; letter-spacing: 0.04em; }

.card-total    .s-icon { background: rgba(37,99,235,0.18);  color: #93c5fd; }
.card-available .s-icon { background: rgba(16,185,129,0.18); color: #6ee7b7; }
.card-occupied  .s-icon { background: rgba(239,68,68,0.18);  color: #fca5a5; }
.card-broken    .s-icon { background: rgba(245,158,11,0.18); color: #fcd34d; }

/* ── Cards / forms ───────────────────────────────────────────── */
.dash-card {
    background: var(--card-bg); border: 1px solid var(--card-border);
    border-radius: var(--radius-md); backdrop-filter: blur(24px);
    overflow: hidden; margin-bottom: 1.5rem;
}
.dash-card-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem;
    background: rgba(37,99,235,0.20);
    border-bottom: 1px solid var(--border-light);
}
.dash-card-header h3 { color: var(--text-primary); font-size: 1rem; font-weight: 600; margin: 0; }
.dash-card-header i  { color: var(--accent); margin-right: 6px; }
.dash-card-body { padding: 1.25rem; }

.form-row { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.form-group label { color: var(--text-muted); font-size: 0.8rem; font-weight: 500; }
.form-group select,
.form-group input {
    background: rgba(255,255,255,0.06); border: 1px solid var(--border-light);
    color: var(--text-primary); padding: 9px 14px; border-radius: 8px; font-size: 0.9rem;
    transition: border-color 0.2s;
}
.form-group select:focus,
.form-group input:focus { outline: none; border-color: rgba(14,165,233,0.5); }
.form-group select option { background: #0f172a; color: var(--text-primary); }

/* ── Buttons ─────────────────────────────────────────────────── */
.btn { padding: 9px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; transition: var(--transition); display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: linear-gradient(135deg,#2563eb,#3b82f6); color: #fff; }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.4); }
.btn-danger  { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.30); }
.btn-danger:hover  { background: rgba(239,68,68,0.25); }
.btn-warning { background: rgba(245,158,11,0.15); color: #fcd34d; border: 1px solid rgba(245,158,11,0.30); }
.btn-warning:hover { background: rgba(245,158,11,0.25); }
.btn-sm { padding: 5px 12px; font-size: 0.78rem; }

/* ── Lab tabs ────────────────────────────────────────────────── */
.lab-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.lab-tab {
    padding: 7px 20px; border-radius: 999px; font-size: 0.85rem; font-weight: 600;
    cursor: pointer; border: 1px solid var(--border-light);
    background: rgba(255,255,255,0.04); color: var(--text-muted);
    transition: var(--transition);
}
.lab-tab:hover  { border-color: rgba(14,165,233,0.4); color: var(--text-secondary); }
.lab-tab.active { background: rgba(37,99,235,0.25); border-color: var(--primary-light); color: #7dd3fc; }

/* ── PC grid ─────────────────────────────────────────────────── */
.lab-section        { display: none; }
.lab-section.active { display: block; }

.lab-stat-bar {
    display: flex; gap: 20px; flex-wrap: wrap; align-items: center;
    margin-bottom: 20px; padding: 12px 18px;
    background: var(--card-bg); border: 1px solid var(--card-border);
    border-radius: var(--radius-sm); backdrop-filter: blur(24px);
}
.lab-stat-item { display: flex; align-items: center; gap: 7px; font-size: 0.82rem; color: var(--text-secondary); }
.stat-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.stat-dot.green { background: var(--c-available); }
.stat-dot.red   { background: var(--c-occupied); }
.stat-dot.amber { background: var(--c-broken); }

.bulk-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.bulk-actions label { color: var(--text-muted); font-size: 0.82rem; }

.pc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
    gap: 14px;
}

/* PC card */
.pc-card {
    border: 1px solid var(--card-border);
    border-left: 5px solid transparent;
    border-radius: var(--radius-md);
    padding: 16px; position: relative;
    transition: var(--transition); cursor: default;
}
.pc-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.4); filter: brightness(1.1); }

.pc-card.status-available {
    background: rgba(16,185,129,0.20);
    border-left-color: #10b981;
    border-color: rgba(16,185,129,0.40);
}
.pc-card.status-broken {
    background: rgba(245,158,11,0.20);
    border-left-color: #f59e0b;
    border-color: rgba(245,158,11,0.40);
}
.pc-card.status-occupied {
    background: rgba(239,68,68,0.20);
    border-left-color: #ef4444;
    border-color: rgba(239,68,68,0.40);
}

.pc-number { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 7px; }

.pc-status-badge {
    display: inline-block; padding: 2px 10px; border-radius: 999px;
    font-size: 0.7rem; font-weight: 600; margin-bottom: 10px;
    letter-spacing: 0.03em; text-transform: uppercase;
}
.badge-available { background: var(--c-available); color: #022c22; border: none; }
.badge-broken    { background: var(--c-broken);    color: #1c1000; border: none; }
.badge-occupied  { background: var(--c-occupied);  color: #fff;    border: none; }

.pc-notes  { font-size: 0.73rem; color: var(--text-muted); margin-bottom: 10px; font-style: italic; min-height: 16px; }
.pc-occupant { font-size: 0.73rem; color: #7dd3fc; margin-bottom: 10px; line-height: 1.5; }
.pc-occupant span { font-weight: 700; color: var(--text-primary); }

.pc-actions { display: flex; gap: 6px; flex-wrap: wrap; }

/* ── Modal ───────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.65);
    backdrop-filter: blur(4px); z-index: 9000;
    display: none; align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: rgba(10,18,40,0.98); border: 1px solid rgba(255,255,255,0.12);
    border-radius: var(--radius-md); padding: 28px; width: 420px; max-width: 95vw;
    box-shadow: 0 24px 60px rgba(0,0,0,0.6);
}
.modal-box h3 { color: var(--text-primary); margin: 0 0 20px; font-size: 1.05rem; font-weight: 700; }
.modal-box .form-group { margin-bottom: 14px; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
.btn-close-modal { background: rgba(255,255,255,0.07); color: var(--text-muted); border: 1px solid var(--border-light); }
.btn-close-modal:hover { background: rgba(255,255,255,0.12); }

@media (max-width: 640px) {
    .lab-page-container { padding: 1rem 16px 32px; }
    .pc-grid { grid-template-columns: repeat(auto-fill, minmax(145px,1fr)); gap: 10px; }
}
</style>

<div class="lab-page-container">
<div class="lab-main">

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-laptop" style="color:var(--accent);margin-right:10px;"></i>Lab Management</h1>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            <span id="currentDate"></span>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($success_message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- Overall Summary -->
    <?php
    $total_all  = array_sum(array_column($lab_stats, 'total'));
    $avail_all  = array_sum(array_column($lab_stats, 'available'));
    $broken_all = array_sum(array_column($lab_stats, 'broken'));
    $occ_all    = array_sum(array_column($lab_stats, 'occupied'));
    ?>
    <div class="summary-grid">
        <div class="summary-card card-total">
            <div class="s-icon"><i class="fas fa-desktop"></i></div>
            <div class="s-val"><?php echo $total_all; ?></div>
            <div class="s-lbl">Total PCs</div>
        </div>
        <div class="summary-card card-available">
            <div class="s-icon"><i class="fas fa-check-circle"></i></div>
            <div class="s-val"><?php echo $avail_all; ?></div>
            <div class="s-lbl">Available</div>
        </div>
        <div class="summary-card card-occupied">
            <div class="s-icon"><i class="fas fa-user"></i></div>
            <div class="s-val"><?php echo $occ_all; ?></div>
            <div class="s-lbl">Occupied Now</div>
        </div>
        <div class="summary-card card-broken">
            <div class="s-icon"><i class="fas fa-tools"></i></div>
            <div class="s-val"><?php echo $broken_all; ?></div>
            <div class="s-lbl">Broken / Out of Service</div>
        </div>
    </div>

    <!-- Add PC Form -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h3><i class="fas fa-plus-circle"></i> Add New PC to Lab</h3>
        </div>
        <div class="dash-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_pc">
                <div class="form-row">
                    <div class="form-group">
                        <label>Laboratory</label>
                        <select name="lab" required>
                            <option value="">— Select Lab —</option>
                            <?php foreach ($laboratories as $l): ?>
                            <option value="<?php echo $l; ?>">Lab <?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>PC Number</label>
                        <input type="text" name="pc_number" placeholder="e.g. PC-11" required>
                    </div>
                    <div class="form-group">
                        <label>Initial Status</label>
                        <select name="status">
                            <option value="available">Available</option>
                            <option value="broken">Broken</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:2;min-width:200px;">
                        <label>Notes (optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Missing keyboard">
                    </div>
                    <div class="form-group" style="flex:0;min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add PC</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lab Tabs -->
    <div class="lab-tabs">
        <?php foreach ($laboratories as $i => $lab): ?>
        <div class="lab-tab <?php echo $i === 0 ? 'active' : ''; ?>" onclick="showLab('<?php echo $lab; ?>')">
            Lab <?php echo $lab; ?>
            <span style="font-size:0.72rem;margin-left:4px;opacity:0.7;">(<?php echo $lab_stats[$lab]['total']; ?>)</span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Per-Lab Sections -->
    <?php foreach ($laboratories as $i => $lab): ?>
    <div class="lab-section <?php echo $i === 0 ? 'active' : ''; ?>" id="lab-<?php echo $lab; ?>">

        <!-- Lab Stats Bar -->
        <div class="lab-stat-bar">
            <div class="lab-stat-item">
                <span class="stat-dot green"></span>
                <span><?php echo $lab_stats[$lab]['available']; ?> Available</span>
            </div>
            <div class="lab-stat-item">
                <span class="stat-dot red"></span>
                <span><?php echo $lab_stats[$lab]['occupied']; ?> Occupied</span>
            </div>
            <div class="lab-stat-item">
                <span class="stat-dot amber"></span>
                <span><?php echo $lab_stats[$lab]['broken']; ?> Broken</span>
            </div>
            <span style="margin-left:auto;color:var(--text-muted);font-size:0.8rem;"><?php echo $lab_stats[$lab]['total']; ?> total PCs</span>
        </div>

        <!-- Bulk Actions -->
        <form method="POST" class="bulk-actions" onsubmit="return confirm('Apply bulk status to all non-occupied PCs in Lab <?php echo $lab; ?>?')">
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="lab" value="<?php echo $lab; ?>">
            <label>Bulk update Lab <?php echo $lab; ?>:</label>
            <select name="bulk_status" required style="background:#1a2744;border:1px solid var(--border-light);color:var(--text-primary);padding:6px 12px;border-radius:8px;font-size:0.85rem;">
                <option value="available">Mark All Available</option>
                <option value="broken">Mark All Broken</option>
            </select>
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-bolt"></i> Apply</button>
        </form>

        <!-- PC Grid -->
        <?php if (empty($pcs_by_lab[$lab])): ?>
            <p style="color:var(--text-muted);text-align:center;padding:40px 0;">No PCs found for Lab <?php echo $lab; ?>. Add one above.</p>
        <?php else: ?>
        <div class="pc-grid">
            <?php foreach ($pcs_by_lab[$lab] as $pc):
                $isOccupied = isset($active_map[$lab][$pc['pc_number']]);
                $displayStatus = $isOccupied ? 'occupied' : $pc['status'];
                $badgeClass    = 'badge-' . $displayStatus;
                $cardClass     = 'status-' . $displayStatus;
                $badgeLabel    = ucfirst($displayStatus);
                $occupant      = $isOccupied ? $active_map[$lab][$pc['pc_number']] : null;
            ?>
            <div class="pc-card <?php echo $cardClass; ?>">
                <div class="pc-number"><i class="fas fa-desktop" style="opacity:0.5;margin-right:5px;"></i><?php echo htmlspecialchars($pc['pc_number']); ?></div>
                <span class="pc-status-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                <div class="pc-notes"><?php echo $pc['notes'] ? htmlspecialchars($pc['notes']) : '&nbsp;'; ?></div>
                <?php if ($occupant): ?>
                <div class="pc-occupant"><i class="fas fa-user" style="opacity:0.6;"></i> <span><?php echo htmlspecialchars($occupant['name']); ?></span><br>
                    <span style="opacity:0.6;"><?php echo htmlspecialchars($occupant['id_number']); ?> — <?php echo htmlspecialchars(substr($occupant['login_time'],0,5)); ?></span>
                </div>
                <?php endif; ?>
                <div class="pc-actions">
                    <?php if (!$isOccupied): ?>
                    <button class="btn btn-warning btn-sm" onclick="openEditModal(<?php echo $pc['id']; ?>, '<?php echo addslashes($pc['pc_number']); ?>', '<?php echo $pc['status']; ?>', '<?php echo addslashes($pc['notes'] ?? ''); ?>')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $pc['id']; ?>, '<?php echo addslashes($pc['pc_number']); ?>', '<?php echo $lab; ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php else: ?>
                    <span style="font-size:0.72rem;color:#60a5fa;"><i class="fas fa-lock"></i> In Use</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

</div><!-- end lab-main -->
</div><!-- end lab-page-container -->

<!-- Edit PC Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fas fa-edit" style="color:var(--accent);margin-right:8px;"></i>Edit PC</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_pc">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label>PC Number</label>
                <input type="text" id="editPcNum" disabled style="opacity:0.5;">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editStatus">
                    <option value="available">Available</option>
                    <option value="broken">Broken</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" name="notes" id="editNotes" placeholder="e.g. Needs RAM replacement">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-close-modal" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirmation form (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete_pc">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
// Date display
document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

// Tab switching
function showLab(lab) {
    document.querySelectorAll('.lab-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.lab-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('lab-' + lab).classList.add('active');
    event.currentTarget.classList.add('active');
}

// Edit modal
function openEditModal(id, pcNum, status, notes) {
    document.getElementById('editId').value    = id;
    document.getElementById('editPcNum').value = pcNum;
    document.getElementById('editStatus').value = status;
    document.getElementById('editNotes').value  = notes;
    document.getElementById('editModal').classList.add('open');
}
function closeModal() {
    document.getElementById('editModal').classList.remove('open');
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Delete confirmation
function confirmDelete(id, pcNum, lab) {
    if (confirm('Delete ' + pcNum + ' from Lab ' + lab + '? This cannot be undone.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>