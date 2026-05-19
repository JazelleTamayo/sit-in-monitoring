<?php
session_start();
$pageTitle = "Home - CCS Sit-in Monitoring System";
$basePath = "";
$extraCSS = "";

require_once 'config/database.php';

// Top 3 leaderboard — weighted score: 60% reward points earned, 20% hours, 20% sessions completed
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

$max_sessions     = !empty($student_performance) ? max(array_column($student_performance, 'total_sessions'))     : 1;
$max_minutes      = !empty($student_performance) ? max(array_column($student_performance, 'total_minutes'))      : 1;
$max_points       = !empty($student_performance) ? max(array_column($student_performance, 'total_points_earned')): 1;
if ($max_sessions == 0) $max_sessions = 1;
if ($max_minutes  == 0) $max_minutes  = 1;
if ($max_points   == 0) $max_points   = 1;
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navigation.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <div class="welcome-card">
            <div class="college-badge">
                <span class="est">EST. 1983</span>
                <h1>COLLEGE OF COMPUTER STUDIES</h1>
            </div>
            
            <h2 class="welcome-title">Sit-in Monitoring System</h2>
            
            <div class="motto">
                <p class="latin">INCEPTUM. INNOVATIO. NUMERIS.</p>
                <p class="english">Beginning. Innovation. Numbers.</p>
            </div>

            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="action-buttons">
                    <a href="pages/login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </a>
                    <a href="pages/register.php" class="btn-register">
                        <i class="fas fa-user-plus"></i>
                        Register
                    </a>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="pages/dashboard.php" class="btn-dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Go to Dashboard
                    </a>
                </div>
            <?php endif; ?>

            <div class="features-mini">
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Student Portal</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Sit-in Management</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>History Tracking</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Real-time Monitoring</span>
                </div>
            </div>
        </div>

        <!-- About Section -->
        <section id="about" class="about-section" style="scroll-margin-top: 80px;">
            <h2>About the System</h2>
            <div class="about-content">
                <p>The CCS Sit-in Monitoring System is designed to streamline the process of computer laboratory usage for students of the College of Computer Studies. It provides an efficient way to track and manage student sit-in sessions.</p>
            </div>
        </section>

        <!-- ── LEADERBOARD SECTION ─────────────────────────────────────── -->
        <section id="leaderboard" class="leaderboard-section" style="scroll-margin-top: 80px;">

            <div class="lb-section-header">
                <span class="lb-trophy-icon">🏆</span>
                <h2>Leaderboard</h2>
                <p class="lb-subtitle">Top 3 most active sit-in students</p>
            </div>

            <?php if (empty($student_performance)): ?>
                <p class="lb-empty">No leaderboard data yet. Be the first!</p>
            <?php else: ?>
                <div class="lb-podium-wrap">
                    <?php
                    $medals      = ['🥇','🥈','🥉'];
                    $rank_colors = ['#facc15','#94a3b8','#b45309'];

                    /*
                     * Visual podium order: index 1 (2nd place) LEFT,
                     *                      index 0 (1st place) CENTER,
                     *                      index 2 (3rd place) RIGHT.
                     *
                     * $orig_rank = true array index (0/1/2) → medal lookup
                     * $actual_rank = $orig_rank + 1          → CSS class
                     */
                    $podium_order = [1, 0, 2];

                    foreach ($podium_order as $orig_rank):
                        if (!isset($student_performance[$orig_rank])) continue;
                        $student     = $student_performance[$orig_rank];
                        $actual_rank = $orig_rank + 1;

                        $sessions_pct = ($student['total_sessions']     / $max_sessions) * 100;
                        $minutes_pct  = ($student['total_minutes']       / $max_minutes)  * 100;
                        $points_pct   = ($student['total_points_earned'] / $max_points)   * 100;

                        $total_mins   = (int)$student['total_minutes'];
                        $hours        = intdiv($total_mins, 60);
                        $mins         = $total_mins % 60;
                        $time_display = $hours > 0 ? $hours.'h '.$mins.'m' : $mins.'m';
                        $upload_url   = 'assets/uploads/';
                        $uid          = $student['id'];
                    ?>
                    <div class="lb-podium-item lb-podium-rank-<?php echo $actual_rank; ?>">

                        <div class="lb-podium-medal"><?php echo $medals[$orig_rank]; ?></div>

                        <div class="lb-podium-avatar">
                            <?php
                            $found = false;
                            foreach (['jpg','png','jpeg'] as $ext) {
                                if (file_exists($upload_url."profile_{$uid}.{$ext}")) {
                                    echo '<img src="'.$upload_url.'profile_'.$uid.'.'.$ext.'" alt="avatar">';
                                    $found = true; break;
                                }
                            }
                            if (!$found) echo '<i class="fas fa-user-circle"></i>';
                            ?>
                        </div>

                        <div class="lb-podium-name">
                            <?php echo htmlspecialchars($student['first_name'].' '.$student['last_name']); ?>
                        </div>

                        <div class="lb-podium-stats">
                            <div class="lb-stat-row">
                                <span class="lb-stat-icon">📚</span>
                                <span class="lb-stat-label">Sessions</span>
                                <strong class="lb-stat-val lb-val-blue"><?php echo $student['total_sessions']; ?></strong>
                            </div>
                            <div class="lb-stat-row">
                                <span class="lb-stat-icon">⏱</span>
                                <span class="lb-stat-label">Time</span>
                                <strong class="lb-stat-val lb-val-green"><?php echo $time_display; ?></strong>
                            </div>
                            <div class="lb-stat-row">
                                <span class="lb-stat-icon">⭐</span>
                                <span class="lb-stat-label">Points</span>
                                <strong class="lb-stat-val lb-val-gold"><?php echo $student['total_points_earned']; ?></strong>
                            </div>
                        </div>

                        <div class="lb-podium-bars">
                            <div class="lb-bar-bg">
                                <div class="lb-bar-fill" style="width:<?php echo $points_pct; ?>%;background:linear-gradient(90deg,#ca8a04,#facc15);"></div>
                            </div>
                            <div class="lb-bar-bg">
                                <div class="lb-bar-fill" style="width:<?php echo $minutes_pct; ?>%;background:linear-gradient(90deg,#059669,#34d399);"></div>
                            </div>
                            <div class="lb-bar-bg">
                                <div class="lb-bar-fill" style="width:<?php echo $sessions_pct; ?>%;background:linear-gradient(90deg,#2563eb,#60a5fa);"></div>
                            </div>
                        </div>

                        <div class="lb-podium-stand"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </section>

    </div>
</div>

<style>
/* ── LEADERBOARD SECTION ─────────────────────────────────────────────────── */
.leaderboard-section {
    margin: 20px auto 40px;
    max-width: 860px;
    text-align: center;
    background: rgba(10,18,40,0.6);
    backdrop-filter: blur(28px);
    -webkit-backdrop-filter: blur(28px);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 28px;
    padding: 44px 48px 0;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.07);
    animation: fadeInUp 0.55s 0.15s cubic-bezier(0.22,0.61,0.36,1) both;
    transition: background 0.25s ease, border-color 0.25s ease;
    overflow: hidden;
}
.leaderboard-section:hover {
    background: rgba(14,24,52,0.7);
    border-color: rgba(255,255,255,0.13);
}

/* Header */
.lb-section-header { margin-bottom: 36px; }
.lb-trophy-icon { font-size: 3rem; display: block; margin-bottom: 10px; line-height: 1; }
.lb-section-header h2 {
    font-size: 2rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 8px;
    letter-spacing: -0.02em;
}
.lb-section-header h2::after {
    content: '';
    display: block;
    width: 44px;
    height: 3px;
    background: linear-gradient(90deg, #facc15, #f59e0b);
    margin: 10px auto 0;
    border-radius: 2px;
}
.lb-subtitle { color: #94a3b8; font-size: 0.88rem; margin: 0; }
.lb-empty { color: #64748b; font-size: 0.95rem; padding: 32px; }

/* Podium wrapper */
.lb-podium-wrap {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 16px;
}

/* Individual podium card */
.lb-podium-item {
    flex: 1;
    max-width: 260px;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 20px 20px 0 0;
    padding: 24px 16px 0;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    position: relative;
}
.lb-podium-item:hover {
    transform: translateY(-8px);
    border-color: rgba(14,165,233,0.35);
    box-shadow: 0 16px 40px rgba(0,0,0,0.3);
}

/* Rank glow */
.lb-podium-rank-1 {
    border-color: rgba(250,204,21,0.3);
    box-shadow: 0 0 36px rgba(250,204,21,0.10);
    background: rgba(250,204,21,0.04);
}
.lb-podium-rank-2 { opacity: 0.92; }
.lb-podium-rank-3 { opacity: 0.87; }

/* Medal */
.lb-podium-medal { font-size: 2.6rem; margin-bottom: 14px; line-height: 1; }

/* Avatar */
.lb-podium-avatar {
    width: 82px; height: 82px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid rgba(255,255,255,0.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.8rem; color: #64748b;
    margin-bottom: 14px;
    background: rgba(255,255,255,0.05);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.lb-podium-rank-1 .lb-podium-avatar {
    width: 98px; height: 98px;
    border-color: #facc15;
    box-shadow: 0 0 22px rgba(250,204,21,0.3);
}
.lb-podium-rank-2 .lb-podium-avatar { border-color: #94a3b8; }
.lb-podium-rank-3 .lb-podium-avatar { border-color: #b45309; }
.lb-podium-item:hover .lb-podium-avatar { transform: scale(1.07); }
.lb-podium-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* Name */
.lb-podium-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: #f1f5f9;
    text-align: center;
    margin-bottom: 14px;
    line-height: 1.3;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Stats rows */
.lb-podium-stats {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
}
.lb-stat-row {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 5px 10px;
    font-size: 0.72rem;
}
.lb-stat-icon { font-size: 0.75rem; flex-shrink: 0; }
.lb-stat-label { color: #64748b; flex: 1; text-align: left; }
.lb-stat-val { font-weight: 700; font-size: 0.78rem; }
.lb-val-blue  { color: #60a5fa; }
.lb-val-green { color: #34d399; }
.lb-val-gold  { color: #facc15; }

/* Progress bars — now 3 bars matching the 3 weighted factors */
.lb-podium-bars {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 20px;
}
.lb-bar-bg {
    height: 5px;
    background: rgba(255,255,255,0.07);
    border-radius: 3px;
    overflow: hidden;
}
.lb-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.9s cubic-bezier(0.22,0.61,0.36,1);
}

/* Podium stand */
.lb-podium-stand { width: 100%; }
.lb-podium-rank-1 .lb-podium-stand { height: 60px; background: linear-gradient(180deg,rgba(250,204,21,0.85),rgba(217,119,6,0.9)); }
.lb-podium-rank-2 .lb-podium-stand { height: 40px; background: linear-gradient(180deg,rgba(148,163,184,0.7),rgba(100,116,139,0.8)); }
.lb-podium-rank-3 .lb-podium-stand { height: 24px; background: linear-gradient(180deg,rgba(180,83,9,0.7),rgba(146,64,14,0.8)); }

/* Responsive */
@media (max-width: 640px) {
    .leaderboard-section { padding: 32px 20px 0; }
    .lb-podium-wrap { gap: 8px; }
    .lb-podium-item { padding: 16px 10px 0; max-width: 190px; }
    .lb-podium-avatar { width: 62px; height: 62px; font-size: 2rem; }
    .lb-podium-rank-1 .lb-podium-avatar { width: 74px; height: 74px; }
    .lb-podium-name { font-size: 0.78rem; }
    .lb-stat-row { padding: 4px 8px; font-size: 0.65rem; }
    .lb-stat-val { font-size: 0.7rem; }
}
@media (max-width: 420px) {
    .lb-podium-wrap { gap: 5px; }
    .lb-podium-item { max-width: 130px; }
    .lb-podium-medal { font-size: 1.8rem; }
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
// When arriving from another page via #hash, scroll so the section
// starts just below the fixed navbar (~72px tall).
if (window.location.hash) {
    // Wait for page paint, then scroll with offset
    setTimeout(() => {
        const target = document.querySelector(window.location.hash);
        if (!target) return;
        const navHeight = document.querySelector('.navbar')?.offsetHeight ?? 72;
        const top = target.getBoundingClientRect().top + window.scrollY - navHeight - 8;
        window.scrollTo({ top, behavior: 'smooth' });
    }, 150);
}

// Also fix in-page anchor clicks on the same page
document.querySelectorAll('a[href^="#"], a[href*="index.php#"]').forEach(link => {
    link.addEventListener('click', function(e) {
        const hash = this.href.split('#')[1];
        if (!hash) return;
        const target = document.getElementById(hash);
        if (!target) return;
        // Only intercept if we're already on index.php
        if (!window.location.pathname.endsWith('index.php') && window.location.pathname !== '/' && !window.location.pathname.endsWith('/')) return;
        e.preventDefault();
        const navHeight = document.querySelector('.navbar')?.offsetHeight ?? 72;
        const top = target.getBoundingClientRect().top + window.scrollY - navHeight - 8;
        window.scrollTo({ top, behavior: 'smooth' });
        history.pushState(null, '', '#' + hash);
    });
});
</script>