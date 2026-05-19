<?php
session_start();

if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php?error=" . urlencode("Unauthorized access"));
    exit();
}

require_once __DIR__ . '/../config/database.php';

// ── STATISTICS ─────────────────────────────────────────────────────────────
$total_students       = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$current_sitin        = $pdo->query("SELECT COUNT(*) FROM sit_in WHERE status = 'active'")->fetchColumn();
$total_sitin          = $pdo->query("SELECT COUNT(*) FROM sit_in")->fetchColumn();
$today_sitins         = $pdo->query("SELECT COUNT(*) FROM sit_in WHERE login_date = CURDATE()")->fetchColumn();
$pending_reservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();

// ── ANALYTICS ──────────────────────────────────────────────────────────────
$total_feedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$avg_rating     = $pdo->query("SELECT AVG(rating) FROM feedback")->fetchColumn();
$popular_lab    = $pdo->query("SELECT laboratory, COUNT(*) as cnt FROM sit_in GROUP BY laboratory ORDER BY cnt DESC LIMIT 1")->fetch();
$popular_purpose= $pdo->query("SELECT purpose, COUNT(*) as cnt FROM sit_in GROUP BY purpose ORDER BY cnt DESC LIMIT 1")->fetch();

// Weekly trend (last 7 days)
$weekly_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$weekly_data   = array_fill(0, 7, 0);
$weekly_result = $pdo->query("
    SELECT DAYOFWEEK(login_date) as day_num, COUNT(*) as count
    FROM sit_in
    WHERE login_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DAYOFWEEK(login_date)
")->fetchAll();
foreach ($weekly_result as $row) {
    $idx = $row['day_num'] - 2;
    if ($idx < 0) $idx = 6;
    if ($idx >= 0 && $idx < 7) $weekly_data[$idx] = (int)$row['count'];
}

// Lab usage
$lab_usage = $pdo->query("
    SELECT laboratory, COUNT(*) as count
    FROM sit_in
    GROUP BY laboratory
    ORDER BY count DESC
")->fetchAll();

// Purpose breakdown
$purpose_rows = $pdo->query("SELECT purpose, COUNT(*) as cnt FROM sit_in GROUP BY purpose ORDER BY cnt DESC")->fetchAll();

// Top students
$top_students = $pdo->query("
    SELECT s.name, COUNT(*) as total_sitins
    FROM sit_in s
    GROUP BY s.user_id
    ORDER BY total_sitins DESC
    LIMIT 5
")->fetchAll();

// ── GENERAL PERFORMANCE ────────────────────────────────────────────────────
$total_reward_points = $pdo->query("SELECT SUM(reward_points) FROM users")->fetchColumn();
$total_completed     = $pdo->query("SELECT COUNT(*) FROM sit_in WHERE status = 'completed'")->fetchColumn();
$total_active        = $pdo->query("SELECT COUNT(*) FROM sit_in WHERE status = 'active'")->fetchColumn();

$reward_percentage     = $total_reward_points > 0 ? min(100, round(($total_reward_points / ($total_students * 10)) * 100, 1)) : 0;
$completion_percentage = $total_sitin > 0 ? round(($total_completed / $total_sitin) * 100, 1) : 0;
$active_percentage     = $total_sitin > 0 ? round(($total_active  / $total_sitin) * 100, 1) : 0;

// ── STUDENT LEADERBOARD — weighted score: 60% lifetime points, 20% hours, 20% sessions ──
$student_performance = $pdo->query("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.reward_points,
        COUNT(s.id) as total_sessions,
        COALESCE(SUM(s.reward_points_given), 0) as total_points_earned,
        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.login_time, s.logout_time)), 0) as total_minutes
    FROM users u
    LEFT JOIN sit_in s ON u.id = s.user_id AND s.status = 'completed'
    GROUP BY u.id
    ORDER BY (
        (COALESCE(SUM(s.reward_points_given), 0) * 0.60) +
        (COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.login_time, s.logout_time)), 0) / 60 * 0.20) +
        (COUNT(s.id) * 0.20)
    ) DESC
    LIMIT 3
")->fetchAll();

$max_points   = !empty($student_performance) ? max(array_column($student_performance, 'total_points_earned')) : 1;
$max_sessions = !empty($student_performance) ? max(array_column($student_performance, 'total_sessions'))      : 1;
$max_minutes  = !empty($student_performance) ? max(array_column($student_performance, 'total_minutes'))       : 1;
if ($max_points   == 0) $max_points   = 1;
if ($max_sessions == 0) $max_sessions = 1;
if ($max_minutes  == 0) $max_minutes  = 1;

$pageTitle = "Admin Dashboard - CCS Sit-in System";
$basePath  = "../";
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin_navigation.php'; ?>

<style>
/* ── ROOT VARIABLES ────────────────────────────────────────────────────── */
:root {
    --primary:           #2563eb;
    --primary-light:     #3b82f6;
    --accent:            #0ea5e9;
    --text-primary:      #f1f5f9;
    --text-secondary:    #cbd5e1;
    --text-muted:        #94a3b8;
    --text-label:        #7dd3fc;
    --border-light:      rgba(255,255,255,0.10);
    --card-bg:           rgba(10,18,40,0.82);
    --card-bg-hover:     rgba(14,24,52,0.90);
    --card-border:       rgba(255,255,255,0.10);
    --card-border-hover: rgba(14,165,233,0.45);
    --shadow-lg:         0 20px 60px rgba(0,0,0,0.60);
    --radius-lg:         28px;
    --radius-md:         16px;
    --radius-sm:         10px;
    --transition:        all 0.25s ease;
}

/* ── PAGE WRAPPER ──────────────────────────────────────────────────────── */
.admin-dashboard-container {
    min-height: 100vh;
    padding: 1.5rem 32px 48px 32px;
    position: relative;
    box-sizing: border-box;
}
.admin-dashboard-container::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at 5%   0%,  rgba(37,99,235,0.35)  0%, transparent 45%),
        radial-gradient(ellipse at 95% 100%, rgba(14,165,233,0.25) 0%, transparent 45%);
    pointer-events: none;
    z-index: -1;
}

/* ── HEADER ────────────────────────────────────────────────────────────── */
.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-light);
}
.admin-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.date-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: rgba(10,18,40,0.70);
    border: 1px solid var(--border-light);
    border-radius: 999px;
    color: var(--text-secondary);
    font-size: 0.875rem;
    backdrop-filter: blur(12px);
    white-space: nowrap;
}
.date-badge i { color: var(--accent); }

.admin-main {
    max-width: 1300px;
    margin: 0 auto;
}

/* ── STATS ROW (5 columns) ─────────────────────────────────────────────── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    padding: 1rem 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: var(--transition);
}
.stat-card:hover {
    background: var(--card-bg-hover);
    border-color: var(--card-border-hover);
    transform: translateY(-3px);
}
.stat-icon {
    width: 42px; height: 42px;
    flex-shrink: 0;
    background: rgba(37,99,235,0.15);
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
}
.stat-icon i { font-size: 1.1rem; color: var(--accent); }
.stat-info h3 { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.stat-info p  { color: var(--text-muted); font-size: 0.7rem; margin: 0; }

/* ── DASHBOARD GRID (2 columns) ─────────────────────────────────────────── */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}
.grid-col {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ── DASH CARD ─────────────────────────────────────────────────────────── */
.dash-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-md);
    backdrop-filter: blur(24px);
    overflow: hidden;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}
.dash-card:hover {
    background: var(--card-bg-hover);
    border-color: var(--card-border-hover);
    transform: translateY(-3px);
}
.dash-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.7rem 1rem;
    background: rgba(37,99,235,0.20);
    border-bottom: 1px solid var(--border-light);
    flex-shrink: 0;
}
.dash-card-header-left { display: flex; align-items: center; gap: 0.5rem; }
.dash-card-header i    { color: var(--accent); font-size: 0.85rem; }
.dash-card-header h3   { color: var(--text-primary); font-size: 0.82rem; font-weight: 600; margin: 0; }
.view-all-link         { color: #60a5fa; text-decoration: none; font-size: 0.65rem; }
.view-all-link:hover   { color: #93c5fd; text-decoration: underline; }
.dash-card-body { padding: 1rem; flex: 1; }

/* ── MINI STATS ─────────────────────────────────────────────────────────── */
.stat-row   { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.75rem; }
.stat-item  { color: var(--text-secondary); font-size: 0.78rem; }
.stat-item strong           { color: #ffffff; font-weight: 700; }
.stat-item .highlight-green { color: #6ee7b7; }
.stat-item .highlight-blue  { color: #93c5fd; }

/* ── CHART WRAPPERS ─────────────────────────────────────────────────────── */
.chart-wrapper {
    position: relative;
    width: 100%;
    height: 200px;
}
.chart-wrapper canvas { width: 100% !important; height: 100% !important; }
.trend-chart-wrapper {
    position: relative;
    width: 100%;
    height: 240px;
}
.trend-chart-wrapper canvas { width: 100% !important; height: 100% !important; }
.chart-no-data { text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.8rem; }

/* ── LEGEND ────────────────────────────────────────────────────────────── */
.chart-legend { display: flex; flex-wrap: wrap; gap: 0.3rem 0.75rem; margin-top: 0.5rem; }
.legend-item  { display: flex; align-items: center; gap: 0.3rem; font-size: 0.63rem; color: var(--text-secondary); }
.legend-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ── POPULAR ROW ─────────────────────────────────────────────────────────── */
.popular-stats {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-light);
}
.popular-item { flex: 1; text-align: center; }
.popular-item .label { color: var(--text-muted); font-size: 0.6rem; margin-bottom: 0.2rem; }
.popular-item .value { color: #facc15; font-size: 0.72rem; font-weight: 700; }

/* ── PERFORMANCE BARS ───────────────────────────────────────────────────── */
.overview-item { margin-bottom: 12px; }
.overview-label {
    display: flex; justify-content: space-between;
    font-size: 0.7rem; color: var(--text-muted); margin-bottom: 4px;
}
.overview-label span:first-child { color: var(--text-label); }
.overview-label span:last-child  { color: #facc15; font-weight: 600; }
.overview-bar-bg   { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; }
.overview-bar-fill { height: 100%; border-radius: 3px; }

/* ── LEADERBOARD ────────────────────────────────────────────────────────── */
.leaderboard-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-light);
}
.leaderboard-item:last-child { border-bottom: none; }
.leaderboard-rank { width: 28px; font-size: 0.78rem; font-weight: 700; color: var(--accent); flex-shrink: 0; }
.leaderboard-info { flex: 1; min-width: 0; }
.leaderboard-name { font-size: 0.78rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.leaderboard-stats { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 3px; }
.leaderboard-stat  { font-size: 0.62rem; color: var(--text-muted); }
.leaderboard-stat strong { color: #facc15; }
.leaderboard-stat.time strong { color: #6ee7b7; }
.leaderboard-bars { width: 90px; flex-shrink: 0; }
.leaderboard-bar-bg   { height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; overflow: hidden; margin-bottom: 3px; }
.leaderboard-bar-fill { height: 100%; border-radius: 2px; }
.empty-state { text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.8rem; }

/* ── MODAL ──────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(8,14,26,0.90);
    backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
}
.modal-container {
    background: #0d1829;
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: var(--radius-lg);
    padding: 40px 36px;
    max-width: 450px; width: 90%;
    text-align: center;
    box-shadow: var(--shadow-lg);
    position: relative; overflow: hidden;
}
.modal-container::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--accent), #7c3aed);
}
.modal-icon { width: 80px; height: 80px; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.modal-icon i { font-size: 2.5rem; color: #ffd700; }
.modal-title   { color: #fff; font-size: 1.8rem; font-weight: 700; margin-bottom: 10px; }
.modal-message { color: var(--text-secondary); font-size: 1rem; margin-bottom: 24px; }
.modal-user-info { background: rgba(255,255,255,0.03); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 24px; text-align: left; }
.modal-info-item { display: flex; align-items: center; gap: 12px; padding: 8px 0; color: #e2e8f0; border-bottom: 1px solid rgba(255,255,255,0.06); }
.modal-info-item:last-child { border-bottom: none; }
.modal-info-item i { width: 20px; color: var(--accent); }
.modal-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 32px; background: linear-gradient(135deg, var(--primary), #7c3aed); color: #fff; border: none; border-radius: 999px; font-family: inherit; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: var(--transition); width: 100%; box-shadow: 0 4px 16px rgba(37,99,235,0.40); }
.modal-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,99,235,0.55); }

/* ── RESPONSIVE ─────────────────────────────────────────────────────────── */
@media (max-width: 1200px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1024px) {
    .stats-row      { grid-template-columns: repeat(2, 1fr); }
    .dashboard-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .admin-dashboard-container { padding: 80px 16px 40px 16px; }
    .stats-row   { grid-template-columns: 1fr; }
    .admin-header { flex-direction: column; gap: 12px; text-align: center; }
}
</style>

<!-- SUCCESS MODAL -->
<?php if(isset($_GET['success'])): ?>
<div class="modal-overlay" id="successModal">
    <div class="modal-container">
        <div class="modal-icon"><i class="fas fa-crown"></i></div>
        <h2 class="modal-title">Welcome, Admin!</h2>
        <p class="modal-message"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <div class="modal-user-info">
            <div class="modal-info-item"><i class="fas fa-id-card"></i><span><?php echo htmlspecialchars($_SESSION['id_number']); ?></span></div>
            <div class="modal-info-item"><i class="fas fa-user-shield"></i><span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></div>
            <div class="modal-info-item"><i class="fas fa-envelope"></i><span><?php echo htmlspecialchars($_SESSION['user_email']); ?></span></div>
        </div>
        <button class="modal-btn" onclick="closeModal()">OK, Got It!</button>
    </div>
</div>
<script>
function closeModal() {
    document.getElementById('successModal').style.display = 'none';
    const url = new URL(window.location.href);
    url.searchParams.delete('success');
    window.history.replaceState({}, document.title, url.toString());
}
window.onclick = function(e) {
    if (e.target === document.getElementById('successModal')) closeModal();
};
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
<?php endif; ?>

<!-- ── ADMIN DASHBOARD ──────────────────────────────────────────────────── -->
<div class="admin-dashboard-container">
    <div class="admin-main">

        <!-- HEADER -->
        <div class="admin-header">
            <h1><i class="fas fa-chalkboard-user" style="color:#0ea5e9;margin-right:8px;"></i> Dashboard Overview</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- ── STATS ROW (5 cards) ───────────────────────────────────────── -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info"><h3><?php echo $total_students; ?></h3><p>Total Students</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-info"><h3><?php echo $current_sitin; ?></h3><p>Currently Sit-in</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-history"></i></div>
                <div class="stat-info"><h3><?php echo $total_sitin; ?></h3><p>Total Sessions</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-info"><h3><?php echo $total_feedback; ?></h3><p>Total Feedback</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info"><h3><?php echo $pending_reservations; ?></h3><p>Pending Reservations</p></div>
            </div>
        </div>

        <!-- ── DASHBOARD GRID ─────────────────────────────────────────────── -->
        <div class="dashboard-grid">

            <!-- LEFT COLUMN -->
            <div class="grid-col">
                <!-- Sit-in Statistics + Pie Chart -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div class="dash-card-header-left">
                            <i class="fas fa-chart-pie"></i>
                            <h3>Sit-in Statistics</h3>
                        </div>
                    </div>
                    <div class="dash-card-body">
                        <div class="stat-row">
                            <div class="stat-item">Students Registered: <strong><?php echo $total_students; ?></strong></div>
                            <div class="stat-item">Currently Sit-in: <strong class="highlight-green"><?php echo $current_sitin; ?></strong></div>
                            <div class="stat-item">Total Sit-in: <strong class="highlight-blue"><?php echo $total_sitin; ?></strong></div>
                        </div>
                        <div class="chart-wrapper">
                            <?php if (!empty($purpose_rows)): ?>
                                <canvas id="sitinPieChart"></canvas>
                            <?php else: ?>
                                <div class="chart-no-data">
                                    <i class="fas fa-chart-pie" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:0.5rem;"></i>
                                    No sit-in data yet
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="chart-legend" id="chartLegend"></div>
                        <div class="popular-stats">
                            <div class="popular-item">
                                <div class="label">🏆 Most Popular Lab</div>
                                <div class="value"><?php echo $popular_lab    ? htmlspecialchars($popular_lab['laboratory'])  : 'N/A'; ?></div>
                            </div>
                            <div class="popular-item">
                                <div class="label">🎯 Most Popular Purpose</div>
                                <div class="value"><?php echo $popular_purpose ? htmlspecialchars($popular_purpose['purpose']) : 'N/A'; ?></div>
                            </div>
                            <div class="popular-item">
                                <div class="label">⭐ Average Rating</div>
                                <div class="value"><?php echo number_format($avg_rating, 1); ?> / 5</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Performance Overview -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div class="dash-card-header-left">
                            <i class="fas fa-chart-bar"></i>
                            <h3>General Performance</h3>
                        </div>
                    </div>
                    <div class="dash-card-body">
                        <div class="overview-item">
                            <div class="overview-label">
                                <span>🏆 Reward Points Distribution</span>
                                <span><?php echo $reward_percentage; ?>%</span>
                            </div>
                            <div class="overview-bar-bg">
                                <div class="overview-bar-fill" style="width:<?php echo $reward_percentage; ?>%;background:#facc15;"></div>
                            </div>
                        </div>
                        <div class="overview-item">
                            <div class="overview-label">
                                <span>✅ Completed Sessions</span>
                                <span><?php echo $completion_percentage; ?>%</span>
                            </div>
                            <div class="overview-bar-bg">
                                <div class="overview-bar-fill" style="width:<?php echo $completion_percentage; ?>%;background:#10b981;"></div>
                            </div>
                        </div>
                        <div class="overview-item">
                            <div class="overview-label">
                                <span>🟢 Active Sessions</span>
                                <span><?php echo $active_percentage; ?>%</span>
                            </div>
                            <div class="overview-bar-bg">
                                <div class="overview-bar-fill" style="width:<?php echo $active_percentage; ?>%;background:#0ea5e9;"></div>
                            </div>
                        </div>
                        <div class="popular-stats" style="margin-top:0.75rem;">
                            <div class="popular-item">
                                <div class="label">Current Reward Points (unconverted)</div>
                                <div class="value"><?php echo number_format($total_reward_points); ?></div>
                            </div>
                            <div class="popular-item">
                                <div class="label">Completed Sessions</div>
                                <div class="value"><?php echo $total_completed; ?></div>
                            </div>
                            <div class="popular-item">
                                <div class="label">Active Sessions</div>
                                <div class="value"><?php echo $total_active; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="grid-col">
                <!-- Weekly Trend -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div class="dash-card-header-left">
                            <i class="fas fa-chart-line"></i>
                            <h3>Weekly Trend</h3>
                        </div>
                        <span style="color:var(--text-muted);font-size:0.62rem;">Last 7 days</span>
                    </div>
                    <div class="dash-card-body">
                        <div class="trend-chart-wrapper">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Student Performance Leaderboard -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div class="dash-card-header-left">
                            <i class="fas fa-trophy"></i>
                            <h3>Student Leaderboard</h3>
                        </div>
                        <a href="admin_students.php" class="view-all-link">View All →</a>
                    </div>
                    <div class="dash-card-body" style="padding-top:0.5rem;padding-bottom:0.5rem;">
                        <?php if (empty($student_performance)): ?>
                            <div class="empty-state">No data available</div>
                        <?php else: ?>
                            <?php $rank = 1; ?>
                            <?php foreach ($student_performance as $student):
                                $points_pct   = $max_points   > 0 ? ($student['total_points_earned'] / $max_points)   * 100 : 0;
                                $sessions_pct = $max_sessions  > 0 ? ($student['total_sessions']      / $max_sessions)  * 100 : 0;
                                $minutes_pct  = $max_minutes   > 0 ? ($student['total_minutes']        / $max_minutes)  * 100 : 0;

                                $total_mins = (int)$student['total_minutes'];
                                $hours      = intdiv($total_mins, 60);
                                $mins       = $total_mins % 60;
                                if ($hours > 0 && $mins > 0)  { $time_display = $hours . 'h ' . $mins . 'm'; }
                                elseif ($hours > 0)            { $time_display = $hours . 'h 0m'; }
                                else                           { $time_display = $mins . 'm'; }
                            ?>
                            <div class="leaderboard-item">
                                <div class="leaderboard-rank">#<?php echo $rank++; ?></div>
                                <div class="leaderboard-info">
                                    <div class="leaderboard-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                    <div class="leaderboard-stats">
                                        <span class="leaderboard-stat">⭐ <strong><?php echo $student['total_points_earned']; ?></strong> Total Pts Earned</span>
                                        <span class="leaderboard-stat">📚 <strong><?php echo $student['total_sessions']; ?></strong> sessions</span>
                                        <span class="leaderboard-stat time">⏱ <strong><?php echo $time_display; ?></strong></span>
                                    </div>
                                </div>
                                <div class="leaderboard-bars">
                                    <div class="leaderboard-bar-bg">
                                        <div class="leaderboard-bar-fill" style="width:<?php echo $points_pct; ?>%;background:#facc15;"></div>
                                    </div>
                                    <div class="leaderboard-bar-bg">
                                        <div class="leaderboard-bar-fill" style="width:<?php echo $minutes_pct; ?>%;background:#10b981;"></div>
                                    </div>
                                    <div class="leaderboard-bar-bg">
                                        <div class="leaderboard-bar-fill" style="width:<?php echo $sessions_pct; ?>%;background:#3b82f6;"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /.dashboard-grid -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-dashboard-container -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* ── PIE CHART ──────────────────────────────────────────────────────────── */
<?php if (!empty($purpose_rows)): ?>
(function () {
    const labels = <?php echo json_encode(array_column($purpose_rows, 'purpose')); ?>;
    const data   = <?php echo json_encode(array_column($purpose_rows, 'cnt')); ?>;
    const colors = ['#3b82f6','#ec4899','#f97316','#eab308','#8b5cf6','#10b981','#0ea5e9','#ef4444','#f59e0b','#6366f1'];

    const ctx = document.getElementById('sitinPieChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, labels.length),
                borderColor: 'rgba(10,18,40,0.8)',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    const legend = document.getElementById('chartLegend');
    labels.forEach((label, i) => {
        legend.innerHTML += `
            <div class="legend-item">
                <div class="legend-dot" style="background:${colors[i]}"></div>
                <span>${label} (${data[i]})</span>
            </div>`;
    });
})();
<?php endif; ?>

/* ── WEEKLY TREND CHART ─────────────────────────────────────────────────── */
(function () {
    const labels = <?php echo json_encode($weekly_labels); ?>;
    const data   = <?php echo json_encode($weekly_data); ?>;

    const ctx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sit-ins',
                data: data,
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14,165,233,0.10)',
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#0ea5e9',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 10, font: { size: 10 }, color: '#94a3b8' }
                }
            },
            layout: { padding: { top: 6, bottom: 6, left: 4, right: 4 } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#94a3b8', font: { size: 10 } },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                x: {
                    ticks: { color: '#94a3b8', font: { size: 10 } },
                    grid: { display: false }
                }
            }
        }
    });
})();
</script>

<?php include '../includes/footer.php'; ?>