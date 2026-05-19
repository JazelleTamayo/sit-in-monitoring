<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$flash      = $_SESSION['flash']      ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

$filter_lab  = $_GET['lab']  ?? '';
$filter_date = $_GET['date'] ?? '';

$where  = ["s.status = 'completed'"];
$params = [];

if ($filter_lab) {
    $where[]  = 's.laboratory = ?';
    $params[] = $filter_lab;
}
if ($filter_date) {
    $where[]  = 's.login_date = ?';
    $params[] = $filter_date;
}
$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT s.id,
           s.id_number,
           s.name,
           s.purpose,
           s.laboratory,
           s.pc_number,
           s.login_time,
           s.logout_time,
           s.login_date,
           s.reward_points_given,
           u.course,
           u.year_level
    FROM   sit_in s
    JOIN   users  u ON u.id = s.user_id
    WHERE  $where_sql
    ORDER  BY s.login_date DESC, s.login_time DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

$labs = $pdo->query("SELECT DISTINCT laboratory FROM sit_in ORDER BY laboratory")
            ->fetchAll(PDO::FETCH_COLUMN);

// Delete — completed records only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['id'] ?? 0);
    if ($del_id) {
        $check = $pdo->prepare("SELECT id FROM sit_in WHERE id = ? AND status = 'completed'");
        $check->execute([$del_id]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM sit_in WHERE id = ?")->execute([$del_id]);
            $_SESSION['flash']      = 'Record deleted.';
            $_SESSION['flash_type'] = 'success';
        }
    }
    header("Location: admin_sitin_records.php?" . http_build_query(array_filter(['lab'=>$filter_lab,'date'=>$filter_date])));
    exit();
}

$pageTitle = "Sit-in Records - CCS Admin";
$basePath  = "../";
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<style>
:root {
    --primary: #2563eb;
    --accent: #0ea5e9;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --text-label: #7dd3fc;
    --border-light: rgba(255,255,255,0.10);
    --card-bg: rgba(10,18,40,0.82);
    --card-border: rgba(255,255,255,0.10);
    --card-border-hover: rgba(14,165,233,0.45);
    --radius-md: 16px;
    --radius-sm: 10px;
    --transition: all 0.25s ease;
}

.records-page { min-height:100vh; padding:1.5rem 32px 48px; position:relative; }
.records-page::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:-1;
    background:
        radial-gradient(ellipse at 5% 0%,   rgba(37,99,235,0.35) 0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%);
}
.page-inner { max-width:1300px; margin:0 auto; }

.page-hdr { display:flex; align-items:center; margin-bottom:24px; padding-bottom:20px; border-bottom:1px solid var(--border-light); }
.page-hdr h1 { font-size:1.75rem; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:.6rem; margin:0; }

.card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:var(--radius-md); backdrop-filter:blur(24px); overflow:hidden; transition:var(--transition); }
.card:hover { border-color:var(--card-border-hover); }
.card-hdr { display:flex; align-items:center; gap:.6rem; padding:1rem 1.4rem; background:rgba(37,99,235,0.20); border-bottom:1px solid var(--border-light); }
.card-hdr h3 { color:var(--text-primary); font-size:1rem; font-weight:600; margin:0; }
.card-hdr i { color:var(--accent); }
.card-hdr .rec-count { margin-left:auto; font-size:0.75rem; color:var(--text-muted); }

.log-note { display:flex; align-items:center; gap:8px; padding:10px 18px; background:rgba(14,165,233,0.06); border-bottom:1px solid rgba(14,165,233,0.12); font-size:0.8rem; color:#7dd3fc; }

.filter-bar { display:flex; align-items:flex-end; gap:10px; padding:14px 18px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border-light); flex-wrap:wrap; }
.fg { display:flex; flex-direction:column; gap:4px; }
.fg label { font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); }
.fg select, .fg input[type="date"] { padding:8px 12px; background:rgba(255,255,255,0.05); border:1px solid var(--border-light); border-radius:var(--radius-sm); color:var(--text-primary); font-size:0.84rem; font-family:inherit; }
.fg select option { background:#0d1525; }

.btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:var(--radius-sm); font-size:0.84rem; font-weight:600; cursor:pointer; border:none; transition:var(--transition); text-decoration:none; font-family:inherit; }
.btn-primary { background:linear-gradient(135deg,var(--primary),#1d4ed8); color:#fff; }
.btn-primary:hover { transform:translateY(-1px); color:#fff; }
.btn-ghost { background:rgba(255,255,255,0.05); color:var(--text-secondary); border:1px solid var(--border-light); }
.btn-danger { background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; }
.btn-sm { padding:5px 10px; font-size:0.75rem; }

.table-wrap { padding:16px; overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
thead th { background:rgba(37,99,235,0.22); color:var(--text-label); padding:11px 13px; font-size:0.74rem; text-transform:uppercase; border-bottom:1px solid var(--border-light); text-align:left; white-space:nowrap; }
tbody td { padding:11px 13px; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.83rem; color:var(--text-secondary); }
tbody tr:hover { background:rgba(255,255,255,0.02); }

.pts-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700; background:rgba(245,158,11,0.15); color:#fcd34d; border:1px solid rgba(245,158,11,0.25); }
.pts-none { color:#334155; }

.flash { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:0.875rem; font-weight:600; border-left:3px solid; }
.flash-success { background:rgba(16,185,129,0.08); border-color:#10b981; color:#6ee7b7; }
.flash-danger  { background:rgba(239,68,68,0.08);  border-color:#ef4444; color:#fca5a5; }

.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select { background:rgba(255,255,255,0.05); border:1px solid var(--border-light); color:var(--text-primary); border-radius:var(--radius-sm); }
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate { color:var(--text-muted); font-size:0.8rem; }
.dataTables_wrapper .dataTables_paginate .paginate_button { color:var(--text-muted) !important; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current { background:rgba(37,99,235,0.25) !important; color:var(--text-primary) !important; border-color:rgba(37,99,235,0.4) !important; border-radius:6px; }
.dt-bar { display:flex; justify-content:space-between; align-items:center; padding:14px 18px; }

@media(max-width:640px){ .records-page{ padding:1.5rem 16px 32px; } }
</style>

<div class="records-page">
<div class="page-inner">

    <div class="page-hdr">
        <h1><i class="fas fa-history" style="color:#0ea5e9;"></i> Sit-in Records</h1>
    </div>

    <?php if ($flash): ?>
    <div class="flash flash-<?= htmlspecialchars($flash_type) ?>">
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-hdr">
            <i class="fas fa-table"></i>
            <h3>Completed Sessions</h3>
            <span class="rec-count"><?= count($records) ?> records</span>
        </div>

        <div class="log-note">
            <i class="fas fa-info-circle"></i>
            Read-only log of completed sit-in sessions. To time out active sessions, use the
            <a href="admin_sitin.php" style="color:#7dd3fc;font-weight:600;margin-left:2px;">Sit-in page</a>.
        </div>

        <form method="GET" class="filter-bar">
            <div class="fg">
                <label>Laboratory</label>
                <select name="lab">
                    <option value="">All Labs</option>
                    <?php foreach ($labs as $lab): ?>
                    <option value="<?= htmlspecialchars($lab) ?>" <?= $filter_lab === (string)$lab ? 'selected' : '' ?>>
                        Lab <?= htmlspecialchars($lab) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="admin_sitin_records.php" class="btn btn-ghost"><i class="fas fa-times"></i> Reset</a>
        </form>

        <div class="table-wrap">
            <table id="recordsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID Number</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Purpose</th>
                        <th>Lab</th>
                        <th>PC</th>
                        <th>Date</th>
                        <th>Login</th>
                        <th>Logout</th>
                        <th>Pts Given</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong style="color:var(--text-primary);"><?= htmlspecialchars($r['id_number']) ?></strong></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td style="font-size:0.78rem;"><?= htmlspecialchars($r['course'] . ' ' . $r['year_level']) ?></td>
                        <td><?= htmlspecialchars($r['purpose']) ?></td>
                        <td>Lab <?= htmlspecialchars($r['laboratory']) ?></td>
                        <td><?= $r['pc_number'] ? htmlspecialchars($r['pc_number']) : '<span class="pts-none">—</span>' ?></td>
                        <td><?= date('M d, Y', strtotime($r['login_date'])) ?></td>
                        <td><?= htmlspecialchars($r['login_time']) ?></td>
                        <td><?= $r['logout_time'] ? htmlspecialchars($r['logout_time']) : '<span class="pts-none">—</span>' ?></td>
                        <td>
                            <?php if ((int)$r['reward_points_given'] > 0): ?>
                                <span class="pts-badge">+<?= (int)$r['reward_points_given'] ?></span>
                            <?php else: ?>
                                <span class="pts-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete this record?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(function(){
    $('#recordsTable').DataTable({
        responsive: true,
        order: [[7,'desc']],
        dom: '<"dt-bar"lf>rtip',
        columnDefs: [{ orderable: false, targets: [11] }]
    });
});
</script>

<?php include '../includes/footer.php'; ?>