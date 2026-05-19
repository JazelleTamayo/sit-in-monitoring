<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pageTitle = "Sit-in Reports - CCS Sit-in System";
$basePath = "../";

// ── FILTER PARAMETERS ─────────────────────────────────────────────────────
$date_from  = $_GET['date_from']  ?? date('Y-m-01');
$date_to    = $_GET['date_to']    ?? date('Y-m-d');
$purpose    = $_GET['purpose']    ?? '';
$laboratory = $_GET['laboratory'] ?? '';
$status     = $_GET['status']     ?? '';

// ── BUILD WHERE CLAUSE ─────────────────────────────────────────────────────
$where  = [];
$params = [];

if (!empty($date_from) && !empty($date_to)) {
    $where[]  = "s.login_date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}
if (!empty($purpose))    { $where[] = "s.purpose = ?";    $params[] = $purpose; }
if (!empty($laboratory)) { $where[] = "s.laboratory = ?"; $params[] = $laboratory; }
if (!empty($status))     { $where[] = "s.status = ?";     $params[] = $status; }

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ── FETCH REPORT DATA ──────────────────────────────────────────────────────
$sql = "
    SELECT
        s.id, s.id_number, s.name, s.purpose, s.laboratory,
        s.login_time, s.logout_time, s.login_date, s.status,
        s.reward_points_given,
        u.course, u.year_level,
        TIMESTAMPDIFF(MINUTE, s.login_time, s.logout_time) AS duration_minutes
    FROM sit_in s
    JOIN users u ON s.user_id = u.id
    $whereSql
    ORDER BY s.login_date DESC, s.login_time DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// ── STATISTICS (still needed for the PDF export) ──────────────────────────
$total_records       = count($reports);
$total_duration      = 0;
$total_reward_points = 0;

foreach ($reports as $r) {
    $mins = (int) $r['duration_minutes'];
    if ($mins > 0) $total_duration += $mins;
    $total_reward_points += (int) $r['reward_points_given'];
}

$completed_count = count(array_filter($reports, fn($r) => $r['status'] === 'completed'));
$active_count    = count(array_filter($reports, fn($r) => $r['status'] === 'active'));

// ── GET UNIQUE VALUES FOR FILTERS ──────────────────────────────────────────
$purposes     = $pdo->query("SELECT DISTINCT purpose    FROM sit_in WHERE purpose    IS NOT NULL ORDER BY purpose")->fetchAll();
$laboratories = $pdo->query("SELECT DISTINCT laboratory FROM sit_in WHERE laboratory IS NOT NULL ORDER BY laboratory")->fetchAll();

// ── DURATION HELPER ────────────────────────────────────────────────────────
function formatDuration($minutes) {
    if ($minutes === null || (int)$minutes <= 0) return '-';
    $m = (int)$minutes;
    return floor($m / 60) . 'h ' . ($m % 60) . 'm';
}

// ── HANDLE CSV EXPORT ──────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sit_in_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','ID Number','Name','Course','Year','Purpose','Laboratory','Date','Time In','Time Out','Duration (min)','Reward Points','Status']);
    foreach ($reports as $row) {
        $mins = (int)$row['duration_minutes'];
        fputcsv($out, [
            $row['id'], $row['id_number'], $row['name'], $row['course'], $row['year_level'],
            $row['purpose'], $row['laboratory'], $row['login_date'], $row['login_time'],
            $row['logout_time'] ?? '', max(0, $mins),
            $row['reward_points_given'] ?? 0, $row['status']
        ]);
    }
    fclose($out);
    exit();
}

// ── HANDLE EXCEL EXPORT ────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="sit_in_report_' . date('Y-m-d') . '.xls"');
    echo '<html><head><meta charset="UTF-8"><title>Sit-in Report</title>';
    echo '<style>th{background:#2563eb;color:white;padding:8px;}td{padding:6px;}</style></head><body>';
    echo '<h2>Sit-in Report</h2><p>Generated: ' . date('F j, Y g:i A') . '</p>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr style="background:#2563eb;color:white;"><th>ID</th><th>ID Number</th><th>Name</th><th>Course</th><th>Year</th><th>Purpose</th><th>Lab</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Points</th><th>Status</th></tr>';
    foreach ($reports as $row) {
        echo '<tr><td>'.$row['id'].'</td><td>'.htmlspecialchars($row['id_number']).'</td><td>'.htmlspecialchars($row['name']).'</td><td>'.htmlspecialchars($row['course']).'</td><td>'.htmlspecialchars($row['year_level']).'</td><td>'.htmlspecialchars($row['purpose']).'</td><td>'.htmlspecialchars($row['laboratory']).'</td><td>'.$row['login_date'].'</td><td>'.$row['login_time'].'</td><td>'.($row['logout_time']??'—').'</td><td>'.formatDuration($row['duration_minutes']).'</td><td>'.($row['reward_points_given']??0).'</td><td>'.ucfirst($row['status']).'</td></tr>';
    }
    echo '</table><p style="margin-top:20px;font-size:11px;color:#666;">Generated by CCS Sit-in System</p></body></html>';
    exit();
}

// ── HANDLE PDF EXPORT (jsPDF-powered page) ─────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $reportJson = json_encode(array_map(function($row) {
        return [
            'id'          => $row['id'],
            'id_number'   => $row['id_number'],
            'name'        => $row['name'],
            'course'      => $row['course'],
            'year_level'  => $row['year_level'],
            'purpose'     => $row['purpose'],
            'laboratory'  => $row['laboratory'],
            'login_date'  => $row['login_date'],
            'login_time'  => $row['login_time'],
            'logout_time' => $row['logout_time'] ?? '-',
            'duration'    => formatDuration($row['duration_minutes']),
            'points'      => $row['reward_points_given'] ?? 0,
            'status'      => ucfirst($row['status']),
        ];
    }, $reports));

    $filterDate = date('M d, Y', strtotime($date_from)) . ' – ' . date('M d, Y', strtotime($date_to));
    $generated  = date('F j, Y g:i A');
    $filename   = 'Sit-in_Report_' . date('Y-m-d') . '.pdf';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generating PDF…</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #0f172a;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 100vh; color: #f1f5f9;
        }
        .card {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 20px;
            padding: 48px 56px;
            text-align: center;
            max-width: 420px;
            width: 90%;
        }
        .icon { font-size: 56px; margin-bottom: 20px; }
        h2   { font-size: 1.4rem; margin-bottom: 8px; }
        p    { color: #94a3b8; font-size: .9rem; margin-bottom: 28px; }
        .spinner {
            width: 44px; height: 44px;
            border: 4px solid rgba(255,255,255,.1);
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin .8s linear infinite;
            margin: 0 auto 28px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn-row { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            padding: 10px 24px; border-radius: 10px; border: none;
            font-size: .875rem; font-weight: 600; cursor: pointer;
            transition: all .2s; text-decoration: none;
        }
        .btn-download {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: white; display: none;
        }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37,99,235,.4); }
        .btn-back {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            color: #94a3b8;
        }
        .btn-back:hover { background: rgba(255,255,255,.14); color: #f1f5f9; }
        #status-text { font-size: .8rem; color: #64748b; margin-top: 12px; min-height: 20px; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">📄</div>
    <h2>Generating PDF Report</h2>
    <p>Please wait while your report is being prepared…</p>
    <div class="spinner" id="spinner"></div>
    <div class="btn-row">
        <button class="btn btn-download" id="btn-dl">💾 Download PDF</button>
        <a href="admin_sitin_reports.php" class="btn btn-back">← Back to Reports</a>
    </div>
    <div id="status-text"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
const REPORT_DATA = <?php echo $reportJson; ?>;
const FILTER_DATE = <?php echo json_encode($filterDate); ?>;
const GENERATED   = <?php echo json_encode($generated); ?>;
const FILENAME    = <?php echo json_encode($filename); ?>;
const STATS = {
    total:     <?php echo $total_records; ?>,
    completed: <?php echo $completed_count; ?>,
    active:    <?php echo $active_count; ?>,
    duration:  <?php echo json_encode($total_duration > 0 ? floor($total_duration/60).'h '.($total_duration%60).'m' : '0h 0m'); ?>
};
const FILTERS = {
    purpose:    <?php echo json_encode($purpose ?: 'All'); ?>,
    laboratory: <?php echo json_encode($laboratory ?: 'All'); ?>,
    status:     <?php echo json_encode($status ?: 'All'); ?>
};
window.addEventListener('load', function () {
    setStatus('Loading PDF engine…');
    setTimeout(buildPDF, 300);
});
function setStatus(msg) {
    document.getElementById('status-text').textContent = msg;
}
function buildPDF() {
    try {
        setStatus('Building document…');
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pageW = doc.internal.pageSize.getWidth();
        const pageH = doc.internal.pageSize.getHeight();
        const margin = 14;
        doc.setFillColor(30, 58, 95);
        doc.rect(0, 0, pageW, 38, 'F');
        doc.setFillColor(37, 99, 235);
        doc.rect(0, 38, pageW, 2, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(18);
        doc.setFont('helvetica', 'bold');
        doc.text('Sit-in Session Report', pageW / 2, 16, { align: 'center' });
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(180, 200, 230);
        doc.text('CCS Sit-in Monitoring System', pageW / 2, 23, { align: 'center' });
        doc.text('Generated: ' + GENERATED, pageW / 2, 30, { align: 'center' });
        let y = 46;
        doc.setFillColor(248, 250, 252);
        doc.roundedRect(margin, y, pageW - margin * 2, 18, 3, 3, 'F');
        doc.setFontSize(7.5);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(30, 58, 95);
        doc.text('APPLIED FILTERS', margin + 4, y + 6);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(51, 65, 85);
        const filterText = [
            'Date: ' + FILTER_DATE,
            'Purpose: ' + FILTERS.purpose,
            'Lab: ' + FILTERS.laboratory,
            'Status: ' + FILTERS.status,
            'Records: ' + STATS.total
        ].join('    |    ');
        doc.text(filterText, margin + 4, y + 13);
        y = 70;
        const statLabels  = ['Total Sessions', 'Completed', 'Active', 'Total Duration'];
        const statValues  = [STATS.total, STATS.completed, STATS.active, STATS.duration];
        const statColors  = [[37,99,235], [16,185,129], [245,158,11], [139,92,246]];
        const boxW = (pageW - margin * 2 - 9) / 4;
        statLabels.forEach((lbl, i) => {
            const bx = margin + i * (boxW + 3);
            const [r, g, b] = statColors[i];
            doc.setFillColor(220, 230, 245);
            doc.roundedRect(bx + 1, y + 1, boxW, 20, 3, 3, 'F');
            doc.setFillColor(255, 255, 255);
            doc.roundedRect(bx, y, boxW, 20, 3, 3, 'F');
            doc.setFillColor(r, g, b);
            doc.roundedRect(bx, y, 3, 20, 1.5, 1.5, 'F');
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(r, g, b);
            doc.text(String(statValues[i]), bx + boxW / 2 + 1.5, y + 11, { align: 'center' });
            doc.setFontSize(6.5);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(100, 116, 139);
            doc.text(lbl.toUpperCase(), bx + boxW / 2 + 1.5, y + 17, { align: 'center' });
        });
        setStatus('Building table…');
        y = 96;
        const columns = [
            { header: '#',          dataKey: 'id' },
            { header: 'ID Number',  dataKey: 'id_number' },
            { header: 'Name',       dataKey: 'name' },
            { header: 'Course',     dataKey: 'course' },
            { header: 'Yr',         dataKey: 'year_level' },
            { header: 'Purpose',    dataKey: 'purpose' },
            { header: 'Lab',        dataKey: 'laboratory' },
            { header: 'Date',       dataKey: 'login_date' },
            { header: 'Time In',    dataKey: 'login_time' },
            { header: 'Time Out',   dataKey: 'logout_time' },
            { header: 'Duration',   dataKey: 'duration' },
            { header: 'Pts',        dataKey: 'points' },
            { header: 'Status',     dataKey: 'status' },
        ];
        doc.autoTable({
            startY: y,
            margin: { left: margin, right: margin },
            columns: columns,
            body: REPORT_DATA,
            styles: { fontSize: 7, cellPadding: 3, lineColor: [226,232,240], lineWidth: 0.2, textColor: [51,65,85], font: 'helvetica', overflow: 'ellipsize', minCellHeight: 8 },
            headStyles: { fillColor: [30,58,95], textColor: [255,255,255], fontStyle: 'bold', fontSize: 7, cellPadding: 3.5, halign: 'left' },
            alternateRowStyles: { fillColor: [248,250,252] },
            columnStyles: {
                0:  { cellWidth: 10 }, 1:  { cellWidth: 24 }, 2:  { cellWidth: 40 },
                3:  { cellWidth: 24 }, 4:  { cellWidth: 8 },  5:  { cellWidth: 32 },
                6:  { cellWidth: 14 }, 7:  { cellWidth: 24 }, 8:  { cellWidth: 19 },
                9:  { cellWidth: 19 }, 10: { cellWidth: 17 }, 11: { cellWidth: 10 },
                12: { cellWidth: 28 }
            },
            tableWidth: 269,
            didDrawCell: function (data) {
                if (data.column.dataKey === 'status' && data.section === 'body') {
                    const val = data.cell.raw;
                    if (val === 'Completed') {
                        doc.setFillColor(209, 250, 229);
                        doc.setTextColor(6, 95, 70);
                    } else if (val === 'Active') {
                        doc.setFillColor(254, 215, 170);
                        doc.setTextColor(146, 64, 14);
                    }
                    const { x, y, width, height } = data.cell;
                    doc.roundedRect(x + 1, y + 1.5, width - 2, height - 3, 2, 2, 'F');
                    doc.setFontSize(6.5);
                    doc.setFont('helvetica', 'bold');
                    doc.text(val, x + width / 2, y + height / 2 + 1, { align: 'center' });
                }
            },
            didDrawPage: function (data) {
                const pg = doc.internal.getCurrentPageInfo().pageNumber;
                const total = doc.internal.getNumberOfPages();
                doc.setFillColor(248, 250, 252);
                doc.rect(0, pageH - 10, pageW, 10, 'F');
                doc.setDrawColor(226, 232, 240);
                doc.setLineWidth(0.3);
                doc.line(0, pageH - 10, pageW, pageH - 10);
                doc.setFontSize(6.5);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(148, 163, 184);
                doc.text('This is a system-generated report. © ' + new Date().getFullYear() + ' CCS Sit-in Monitoring System', margin, pageH - 4);
                doc.text('Page ' + pg + ' of ' + total, pageW - margin, pageH - 4, { align: 'right' });
            }
        });
        if (REPORT_DATA.length === 0) {
            const finalY = doc.lastAutoTable ? doc.lastAutoTable.finalY + 10 : y + 10;
            doc.setFontSize(11);
            doc.setTextColor(148, 163, 184);
            doc.text('No data available for the selected filters.', pageW / 2, finalY, { align: 'center' });
        }
        setStatus('PDF is ready!');
        setTimeout(function () {
            document.getElementById('spinner').style.display = 'none';
            const btn = document.getElementById('btn-dl');
            btn.style.display = 'inline-block';
            btn.onclick = function () { doc.save(FILENAME); };
            document.querySelector('p').textContent = 'Your PDF report is ready to download.';
            setStatus('');
        }, 200);
    } catch (err) {
        document.getElementById('spinner').style.display = 'none';
        setStatus('❌ Error: ' + err.message);
        console.error(err);
    }
}
</script>
</body>
</html>
<?php
    exit();
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
    .reports-container {
        min-height: 100vh;
        padding: 1.5rem 32px 48px 32px;   /* unified with other admin pages */
        position: relative;
    }
    .reports-container::before {
        content: '';
        position: fixed; inset: 0;
        background:
            radial-gradient(ellipse at 5% 0%, rgba(37,99,235,.35) 0%, transparent 45%),
            radial-gradient(ellipse at 95% 100%, rgba(14,165,233,.25) 0%, transparent 45%);
        pointer-events: none;
        z-index: -1;
    }
    .reports-main {
        max-width: 1300px;               /* unified max-width */
        margin: 0 auto;
        position: relative;
        z-index: 2;
    }

    .page-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 28px; padding-bottom: 20px;
        border-bottom: 1px solid rgba(255,255,255,.10);
    }
    .page-header h1 {
        font-size: 1.75rem; font-weight: 700; color: #f1f5f9;
        display: flex; align-items: center; gap: 8px;
    }
    .page-header h1 i { color: #0ea5e9; }

    .date-badge {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 18px;
        background: rgba(10,18,40,.70); border: 1px solid rgba(255,255,255,.10);
        border-radius: 999px; color: #cbd5e1; font-size: .875rem;
        backdrop-filter: blur(12px);
    }

    /* Filter Card */
    .filter-card {
        background: rgba(10,18,40,.82); border: 1px solid rgba(255,255,255,.10);
        border-radius: 16px; backdrop-filter: blur(24px);
        padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
    }
    .filter-title { display:flex; align-items:center; gap:.5rem; color:#f1f5f9; font-size:.9rem; font-weight:600; margin-bottom:1rem; }

    .filter-fields {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: .85rem;
        margin-bottom: 1rem;
    }
    .filter-group label {
        display: block; color: #64748b; font-size: .7rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem;
    }
    .filter-group input,
    .filter-group select {
        width: 100%; padding: .55rem .75rem;
        background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
        border-radius: 8px; color: #f1f5f9; font-size: .82rem; font-family: inherit;
        transition: border-color .2s; box-sizing: border-box;
    }
    .filter-group input:focus,
    .filter-group select:focus { outline: none; border-color: #0ea5e9; background: rgba(255,255,255,.08); }
    .filter-group select option { background: #1e293b; color: #f1f5f9; }

    .filter-actions { display: flex; justify-content: center; gap: .75rem; }

    .btn-filter {
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: white; border: none;
        padding: .55rem 1.8rem; border-radius: 8px;
        font-size: .85rem; font-weight: 600; cursor: pointer;
        display: inline-flex; align-items: center; gap: .4rem;
        transition: all .2s; white-space: nowrap;
    }
    .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(37,99,235,.4); }

    .btn-reset {
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.10);
        color: #94a3b8; padding: .55rem 1.8rem; border-radius: 8px;
        font-size: .85rem; font-weight: 600; cursor: pointer;
        display: inline-flex; align-items: center; gap: .4rem;
        transition: all .2s; text-decoration: none; white-space: nowrap;
    }
    .btn-reset:hover { background: rgba(255,255,255,.12); color: #f1f5f9; }

    /* Export Row */
    .export-row { display: flex; justify-content: flex-end; gap: .6rem; margin-bottom: 1rem; }
    .btn-export {
        padding: .45rem 1.1rem; border-radius: 8px; font-size: .78rem; font-weight: 600;
        text-decoration: none; display: inline-flex; align-items: center; gap: .4rem;
        transition: all .2s; white-space: nowrap; cursor: pointer; border: none;
    }
    .btn-csv, .btn-excel { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.25); color: #6ee7b7; }
    .btn-csv:hover, .btn-excel:hover { background: rgba(16,185,129,.22); }
    .btn-pdf { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.25); color: #fca5a5; }
    .btn-pdf:hover { background: rgba(239,68,68,.22); }

    /* Table */
    .table-wrapper { overflow-x: auto; }
    .reports-table { width: 100%; border-collapse: collapse; }
    .reports-table thead tr { background: rgba(255,255,255,.03); border-bottom: 1px solid rgba(255,255,255,.08); }
    .reports-table th { padding:.75rem; text-align:left; color:#64748b; font-weight:600; font-size:.7rem; text-transform:uppercase; white-space:nowrap; }
    .reports-table td { padding:.75rem; color:#cbd5e1; font-size:.8rem; border-bottom: 1px solid rgba(255,255,255,.04); }
    .reports-table tr:hover td { background: rgba(255,255,255,.03); }

    .badge-completed { background:rgba(14,165,233,.12); border:1px solid rgba(14,165,233,.25); color:#7dd3fc; padding:.2rem .6rem; border-radius:999px; font-size:.7rem; font-weight:600; }
    .badge-active    { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.25); color:#6ee7b7;  padding:.2rem .6rem; border-radius:999px; font-size:.7rem; font-weight:600; }

    .empty-state { text-align:center; padding:3rem; color:#64748b; }

    /* Responsive */
    @media (max-width: 1100px) { .filter-fields { grid-template-columns: repeat(3,1fr); } }
    @media (max-width: 768px)  {
        .reports-container { padding: 80px 16px 40px; }
        .filter-fields { grid-template-columns: repeat(2,1fr); }
        .export-row    { justify-content: center; flex-wrap: wrap; }
    }
    @media (max-width: 480px)  { .filter-fields { grid-template-columns: 1fr; } }
</style>

<div class="reports-container">
    <div class="reports-main">

        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Sit-in Reports</h1>
            <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('F j, Y'); ?></div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-title"><i class="fas fa-filter"></i> Filter Reports</div>
            <form method="GET" action="">
                <div class="filter-fields">
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Purpose</label>
                        <select name="purpose">
                            <option value="">All Purposes</option>
                            <?php foreach ($purposes as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['purpose']); ?>" <?php echo $purpose === $p['purpose'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['purpose']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Laboratory</label>
                        <select name="laboratory">
                            <option value="">All Labs</option>
                            <?php foreach ($laboratories as $lab): ?>
                                <option value="<?php echo htmlspecialchars($lab['laboratory']); ?>" <?php echo $laboratory === $lab['laboratory'] ? 'selected' : ''; ?>>
                                    Lab <?php echo htmlspecialchars($lab['laboratory']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="active"    <?php echo $status === 'active'    ? 'selected' : ''; ?>>Active</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <a href="admin_sitin_reports.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Generate</button>
                </div>
            </form>
        </div>

        <!-- Export Buttons -->
        <div class="export-row">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-export btn-csv">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'excel'])); ?>" class="btn-export btn-excel">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'pdf'])); ?>" class="btn-export btn-pdf" target="_blank">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>

        <!-- Data Table -->
        <div style="background:rgba(10,18,40,.82);border:1px solid rgba(255,255,255,.10);border-radius:16px;backdrop-filter:blur(24px);overflow:hidden;">
            <div class="table-wrapper">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>ID Number</th><th>Name</th><th>Course</th><th>Year</th>
                            <th>Purpose</th><th>Lab</th><th>Date</th><th>Time In</th><th>Time Out</th>
                            <th>Duration</th><th>Points</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr><td colspan="13">
                                <div class="empty-state">
                                    <i class="fas fa-database" style="font-size:2rem;margin-bottom:.75rem;display:block;opacity:.3;"></i>
                                    <h3 style="color:#94a3b8;margin-bottom:.25rem;">No data available</h3>
                                    <p style="font-size:.85rem;">Try adjusting your filters or date range.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($reports as $row): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course']); ?></td>
                                    <td>Year <?php echo htmlspecialchars($row['year_level']); ?></td>
                                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                    <td>Lab <?php echo htmlspecialchars($row['laboratory']); ?></td>
                                    <td><?php echo $row['login_date']; ?></td>
                                    <td><?php echo $row['login_time']; ?></td>
                                    <td><?php echo $row['logout_time'] ?? '—'; ?></td>
                                    <td><?php echo formatDuration($row['duration_minutes']); ?></td>
                                    <td><?php echo $row['reward_points_given'] ?? 0; ?></td>
                                    <td><span class="badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>