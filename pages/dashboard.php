<?php
// ─── sit-in-monitoring/pages/dashboard.php ───────────────────────────────────
session_start();

// Auth guard — redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please login first"));
    exit();
}

$pageTitle = "Dashboard - CCS Sit-in System";
$extraCSS = "dashboard";
$basePath = "../";

require_once __DIR__ . '/../config/database.php'; // ADD THIS for database connection

// Include announcements functions
require_once __DIR__ . '/../includes/get_announcements.php';

// Get latest announcements and unread count
$latestAnnouncements = getLatestAnnouncements(3);
$unreadCount = getUnreadAnnouncementCount($_SESSION['user_id']);

// Get fresh user data from database for sessions and reward points
$userStmt = $pdo->prepare("SELECT sessions, reward_points FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$userData = $userStmt->fetch();
$currentSessions = $userData['sessions'] ?? 30;
$rewardPoints = $userData['reward_points'] ?? 0;
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/user_navigation.php'; ?>

<!-- ── SUCCESS MODAL ──────────────────────────────────────────────────────── -->
<?php if (isset($_GET['success'])): ?>
    <div class="modal-overlay" id="successModal">
        <div class="modal-container">
            <div class="modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="modal-title">Login Successful!</h2>
            <p class="modal-message"><?php echo htmlspecialchars($_GET['success']); ?></p>

            <div class="modal-user-info">
                <div class="modal-info-item">
                    <i class="fas fa-id-card"></i>
                    <span><?php echo htmlspecialchars($_SESSION['id_number']); ?></span>
                </div>
                <div class="modal-info-item">
                    <i class="fas fa-user"></i>
                    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </div>
                <div class="modal-info-item">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                </div>
            </div>

            <button class="modal-btn" onclick="closeModal()">
                <i class="fas fa-check"></i> OK, Got It!
            </button>
        </div>
    </div>

    <script>
        function closeModal() {
            document.getElementById('successModal').style.display = 'none';
            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url.toString());
        }
        window.addEventListener('click', function (e) {
            const modal = document.getElementById('successModal');
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
<?php endif; ?>

<!-- ── MAIN DASHBOARD ──────────────────────────────────────────────────────── -->
<div class="dashboard-container">
    <div class="dashboard-main">

        <!-- Page header -->
        <div class="dashboard-header">
            <h1>Student Portal</h1>
            <div class="date-badge">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <!-- Two-column grid -->
        <div class="dashboard-grid">

            <!-- ── LEFT COLUMN: Student Info Card ─────────────────────────── -->
            <div class="grid-left">
                <div class="card student-card">

                    <!-- Profile header - VIEW ONLY (no camera overlay) -->
                    <div class="profile-header">
                        <div class="profile-image-wrapper">
                            <div class="profile-image">
                                <?php
                                $upload_dir = __DIR__ . '/../assets/uploads/';
                                $upload_url = '../assets/uploads/';
                                $user_id = $_SESSION['user_id'];
                                $image_found = false;

                                if (!file_exists($upload_dir)) {
                                    mkdir($upload_dir, 0777, true);
                                }

                                foreach (['jpg', 'png', 'jpeg'] as $ext) {
                                    $file = "profile_{$user_id}.{$ext}";
                                    if (file_exists($upload_dir . $file)) {
                                        echo '<img src="' . $upload_url . $file . '?v=' . time() . '" alt="Profile" id="profileImage">';
                                        $image_found = true;
                                        break;
                                    }
                                }

                                if (!$image_found) {
                                    echo '<i class="fas fa-user-circle"></i>';
                                }
                                ?>
                            </div>
                            <!-- NO camera overlay - view only -->
                        </div>

                        <div class="profile-info">
                            <h3 class="profile-name">
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </h3>
                            <p class="profile-course">
                                <?php echo isset($_SESSION['course'])
                                    ? htmlspecialchars($_SESSION['course'])
                                    : 'BSIT'; ?>
                                &ndash; Year
                                <?php echo isset($_SESSION['year_level'])
                                    ? htmlspecialchars($_SESSION['year_level'])
                                    : '3'; ?>
                            </p>
                            <p class="profile-id">
                                ID: <?php echo htmlspecialchars($_SESSION['id_number']); ?>
                            </p>
                        </div>
                    </div><!-- /.profile-header -->

                    <!-- Card header -->
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-user-graduate"></i>
                            <h2>Student Information</h2>
                        </div>
                        <span class="card-date"><?php echo date('Y-M-d'); ?></span>
                    </div>

                    <!-- Card body -->
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Course</span>
                            <span class="info-value">
                                <?php echo isset($_SESSION['course'])
                                    ? htmlspecialchars($_SESSION['course'])
                                    : 'BSIT'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Year</span>
                            <span class="info-value">
                                <?php echo isset($_SESSION['year_level'])
                                    ? htmlspecialchars($_SESSION['year_level'])
                                    : '3'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address</span>
                            <span class="info-value">
                                <?php echo isset($_SESSION['address'])
                                    ? htmlspecialchars($_SESSION['address'])
                                    : '—'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Sessions</span>
                            <span class="info-value">
                                <?php echo $currentSessions; ?>
                            </span>
                        </div>
                        <!-- REWARD POINTS ROW -->
                        <div class="info-row">
                            <span class="info-label">Reward Points</span>
                            <span class="info-value">
                                <?php echo $rewardPoints; ?>
                                <span style="color: #facc15; font-size: 12px;">⭐</span>
                                <small style="color: #64748b; font-size: 10px;">(3 points = +1 session)</small>
                            </span>
                        </div>
                    </div><!-- /.card-body -->

                    <div class="card-footer">
                        <i class="fas fa-user-shield"></i>
                        <span>CCS Admin &mdash; <?php echo date('Y-M-d'); ?></span>
                    </div>

                </div><!-- /.student-card -->
            </div><!-- /.grid-left -->

            <!-- ── RIGHT COLUMN: Announcement + Rules ─────────────────────── -->
            <div class="grid-right">

                <!-- Announcement Card with Notifications -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-bullhorn"></i>
                            <h2>Announcements</h2>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge"><?php echo $unreadCount; ?> new</span>
                            <?php endif; ?>
                        </div>
                        <a href="notifications.php" class="view-all-link">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body announcements-container">
                        <?php if (empty($latestAnnouncements)): ?>
                            <p class="no-announcements">No announcements at this time.</p>
                        <?php else: ?>
                            <?php foreach ($latestAnnouncements as $announcement): ?>
                                <div class="announcement-item" data-id="<?php echo $announcement['id']; ?>">
                                    <div class="announcement-meta">
                                        <span class="author">
                                            <i class="fas fa-user-shield"></i>
                                            <?php echo htmlspecialchars($announcement['author']); ?>
                                        </span>
                                        <span class="date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php echo date('F j, Y', strtotime($announcement['created_at'])); ?>
                                        </span>
                                    </div>
                                    <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                    <p class="announcement-content">
                                        <?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 150))); ?>
                                        <?php if (strlen($announcement['content']) > 150): ?>
                                            <a href="view_announcements.php?id=<?php echo $announcement['id']; ?>" class="read-more">Read more</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div><!-- /.card (announcement) -->

                <!-- Rules Card -->
                <div class="card rules-card" style="margin-top: 20px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-gavel"></i>
                            <h2>Rules and Regulation</h2>
                        </div>
                    </div>
                    <div class="card-body rules-scroll">

                        <h3 class="university-name">University of Cebu</h3>
                        <h4 class="college-name">COLLEGE OF INFORMATION &amp; COMPUTER STUDIES</h4>
                        <h5 class="rules-subtitle">LABORATORY RULES AND REGULATIONS</h5>

                        <p class="rules-intro">
                            To avoid embarrassment and maintain camaraderie with your friends and
                            superiors at our laboratories, please observe the following:
                        </p>

                        <ol class="rules-list">
                            <li>Maintain silence, proper decorum, and discipline inside the laboratory.
                                Mobile phones, walkmans and other personal pieces of equipment must be
                                switched off.</li>
                            <li>Games are not allowed inside the lab. This includes computer-related
                                games, card games and other games that may disturb the operation of
                                the lab.</li>
                            <li>Surfing the Internet is allowed only with the permission of the
                                instructor. Downloading and installing of software are strictly
                                prohibited.</li>
                            <li>Getting access to other websites not related to the course (especially
                                pornographic and illicit sites) is strictly prohibited.</li>
                            <li>Deleting computer files and changing the set-up of the computer is a
                                major offense.</li>
                            <li>Observe computer time usage carefully. A fifteen-minute allowance is
                                given for each use. Otherwise, the unit will be given to those who
                                wish to "sit-in".</li>
                            <li>Observe proper decorum while inside the laboratory.
                                <ul class="sublist">
                                    <li>Do not get inside the lab unless the instructor is present.</li>
                                    <li>All bags, knapsacks, and the likes must be deposited at the counter.</li>
                                    <li>Follow the seating arrangement of your instructor.</li>
                                    <li>At the end of class, all software programs must be closed.</li>
                                    <li>Return all chairs to their proper places after using.</li>
                                </ul>
                            </li>
                            <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism
                                are prohibited inside the lab.</li>
                            <li>Anyone causing a continual disturbance will be asked to leave the lab.
                                Acts or gestures offensive to the members of the community, including
                                public display of physical intimacy, are not tolerated.</li>
                            <li>Persons exhibiting hostile or threatening behavior such as yelling,
                                swearing, or disregarding requests made by lab personnel will be
                                asked to leave the lab.</li>
                            <li>For serious offense, the lab personnel may call the Civil Security
                                Office (CSU) for assistance.</li>
                            <li>Any technical problem or difficulty must be addressed to the laboratory
                                supervisor, student assistant or instructor immediately.</li>
                        </ol>

                        <div class="disciplinary-section">
                            <h5 class="disciplinary-title">Disciplinary Action</h5>
                            <ul class="disciplinary-list">
                                <li><strong>First Offense</strong> &mdash; The Head or the Dean or OIC
                                    recommends to the Guidance Center for a suspension from classes
                                    for each offender.</li>
                                <li><strong>Second and Subsequent Offenses</strong> &mdash; A
                                    recommendation for a heavier sanction will be endorsed to the
                                    Guidance Center.</li>
                            </ul>
                        </div>

                    </div><!-- /.rules-scroll -->
                </div><!-- /.rules-card -->

            </div><!-- /.grid-right -->
        </div><!-- /.dashboard-grid -->
    </div><!-- /.dashboard-main -->
</div><!-- /.dashboard-container -->

<style>
/* Announcement Styles - Matching Rules Card Hover Effect */
.notification-badge {
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: 8px;
    font-weight: normal;
}

.view-all-link {
    color: #60a5fa;
    text-decoration: none;
    font-size: 13px;
    transition: color 0.3s;
}

.view-all-link:hover {
    color: #93c5fd;
    text-decoration: underline;
}

.announcements-container {
    max-height: 180px;  /* Changed from 400px to show only ~1 announcement */
    overflow-y: auto;
    padding-right: 5px;
}

.announcement-item {
    padding: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.3s ease;
    cursor: pointer;
    background: transparent;
}

.announcement-item:last-child {
    border-bottom: none;
}

/* Hover effect matching rules card */
.announcement-item:hover {
    background: rgba(96, 165, 250, 0.08);
    transform: translateX(4px);
    border-radius: 8px;
}

.announcement-title {
    font-size: 16px;
    margin: 8px 0;
    color: #f1f5f9;
    font-weight: 600;
}

.announcement-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 8px;
}

.announcement-content {
    font-size: 14px;
    line-height: 1.5;
    color: #94a3b8;
    margin: 0;
}

.read-more {
    color: #60a5fa;
    text-decoration: none;
    font-size: 13px;
    margin-left: 5px;
}

.read-more:hover {
    color: #93c5fd;
    text-decoration: underline;
}

.no-announcements {
    text-align: center;
    color: #64748b;
    padding: 30px;
    margin: 0;
}

/* Custom scrollbar - matching dark theme */
.announcements-container::-webkit-scrollbar {
    width: 6px;
}

.announcements-container::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
}

.announcements-container::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 3px;
}

.announcements-container::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}
</style>

<!-- ── MARK ANNOUNCEMENT AS READ SCRIPT ONLY ───────────────────────────────── -->
<script>
    // Mark announcement as read when clicked
    document.querySelectorAll('.announcement-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't mark as read if clicking on read-more link
            if (e.target.classList.contains('read-more')) return;
            
            const announcementId = this.dataset.id;
            if (announcementId) {
                fetch('../process/mark_announcement_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'announcement_id=' + announcementId
                });
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>