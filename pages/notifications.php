<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$pageTitle = "Notifications - CCS Sit-in System";
$extraCSS = "dashboard";
$basePath = "../";

require_once __DIR__ . '/../includes/get_announcements.php';

$announcements = getLatestAnnouncements(50);
$notifications = getUserNotifications($_SESSION['user_id'], 50);

$unreadAnnouncementCount = getUnreadAnnouncementCount($_SESSION['user_id']);
$unreadNotificationCount = getUnreadNotificationCount($_SESSION['user_id']);
$totalUnreadCount = $unreadAnnouncementCount + $unreadNotificationCount;

if (isset($_POST['mark_announcement_read']) && isset($_POST['announcement_id'])) {
    markAnnouncementAsRead($_SESSION['user_id'], $_POST['announcement_id']);
    header("Location: notifications.php");
    exit();
}
if (isset($_POST['mark_notification_read']) && isset($_POST['notification_id'])) {
    markNotificationAsRead($_POST['notification_id'], $_SESSION['user_id']);
    header("Location: notifications.php");
    exit();
}
if (isset($_POST['mark_all_read'])) {
    markAllNotificationsAsRead($_SESSION['user_id']);
    foreach ($announcements as $ann) {
        markAnnouncementAsRead($_SESSION['user_id'], $ann['id']);
    }
    header("Location: notifications.php");
    exit();
}
if (isset($_POST['delete']) && isset($_POST['notification_id'])) {
    deleteNotification($_POST['notification_id'], $_SESSION['user_id']);
    header("Location: notifications.php");
    exit();
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/user_navigation.php'; ?>

<style>
    /* Dark Theme - Matching reservation.php */
    .notif-wrap {
        min-height: 100vh;
        padding: 90px 24px 48px;
    }

    .notif-inner {
        max-width: 900px;
        margin: 0 auto;
    }

    /* Header */
    .notif-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .notif-header h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: #f1f5f9;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .notif-header h1 i {
        color: #facc15;
    }

    .notif-date-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 18px;
        background: rgba(10, 18, 40, 0.70);
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 999px;
        color: #cbd5e1;
        font-size: 0.875rem;
        backdrop-filter: blur(12px);
    }

    .notif-date-pill i {
        color: #0ea5e9;
    }

    /* Mark All Button */
    .mark-all-container {
        text-align: right;
        margin-bottom: 24px;
    }

    .mark-all-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.10);
        color: #cbd5e1;
        padding: 10px 24px;
        border-radius: 999px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .mark-all-btn:hover {
        background: rgba(255, 255, 255, 0.12);
        color: #f1f5f9;
        border-color: rgba(255, 255, 255, 0.20);
    }

    /* Section */
    .notif-section {
        margin-bottom: 40px;
    }

    .notif-section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .notif-section-header i {
        font-size: 1.2rem;
    }

    .announce-header-icon {
        color: #facc15;
    }

    .personal-header-icon {
        color: #60a5fa;
    }

    .notif-section-header h2 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #f1f5f9;
    }

    .section-badge {
        background: rgba(250, 204, 21, 0.15);
        border: 1px solid rgba(250, 204, 21, 0.25);
        color: #fde68a;
        padding: 3px 12px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .new-badge {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.25);
        color: #fca5a5;
        padding: 3px 12px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .section-line {
        flex: 1;
        height: 1px;
        background: rgba(255, 255, 255, 0.05);
    }

    /* Notification Cards - Glassmorphism */
    .notif-card {
        background: rgba(10, 18, 40, 0.82);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 12px;
        display: flex;
        gap: 16px;
        align-items: flex-start;
        transition: all 0.25s ease;
        backdrop-filter: blur(12px);
    }

    .notif-card.unread {
        background: rgba(37, 99, 235, 0.12);
        border-left: 4px solid #0ea5e9;
    }

    .notif-card:hover {
        transform: translateX(4px);
        background: rgba(10, 18, 40, 0.92);
        border-color: rgba(255, 255, 255, 0.15);
    }

    /* Card Icons */
    .notif-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .announce-icon {
        background: rgba(250, 204, 21, 0.12);
        color: #facc15;
    }

    .personal-icon {
        background: rgba(96, 165, 250, 0.12);
        color: #60a5fa;
    }

    /* Card Content */
    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .notif-top h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #f1f5f9;
    }

    .notif-date {
        font-size: 0.7rem;
        color: #64748b;
        background: rgba(255, 255, 255, 0.05);
        padding: 4px 10px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .notif-message {
        font-size: 0.85rem;
        color: #94a3b8;
        line-height: 1.5;
        margin: 0 0 10px;
    }

    .notif-meta {
        display: flex;
        gap: 16px;
        font-size: 0.7rem;
        color: #64748b;
    }

    .notif-meta i {
        margin-right: 4px;
    }

    /* Action Buttons */
    .notif-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex-shrink: 0;
    }

    .btn-mark-read {
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.25);
        color: #6ee7b7;
        padding: 8px 16px;
        border-radius: 999px;
        cursor: pointer;
        font-size: 0.7rem;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .btn-mark-read:hover {
        background: rgba(16, 185, 129, 0.25);
        color: #ffffff;
        border-color: rgba(16, 185, 129, 0.5);
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.25);
        color: #fca5a5;
        padding: 8px 16px;
        border-radius: 999px;
        cursor: pointer;
        font-size: 0.7rem;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .btn-delete:hover {
        background: rgba(239, 68, 68, 0.25);
        color: #ffffff;
        border-color: rgba(239, 68, 68, 0.5);
    }

    /* Empty State */
    .notif-empty {
        background: rgba(10, 18, 40, 0.60);
        border: 2px dashed rgba(255, 255, 255, 0.10);
        border-radius: 20px;
        padding: 60px 24px;
        text-align: center;
        backdrop-filter: blur(12px);
    }

    .empty-icon {
        width: 70px;
        height: 70px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin: 0 auto 18px;
    }

    .empty-announce-icon {
        color: #facc15;
    }

    .empty-personal-icon {
        color: #60a5fa;
    }

    .notif-empty h3 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #f1f5f9;
        margin: 0 0 10px;
    }

    .notif-empty p {
        font-size: 0.85rem;
        color: #94a3b8;
        max-width: 350px;
        margin: 0 auto;
        line-height: 1.5;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .notif-wrap {
            padding: 80px 16px 32px;
        }

        .notif-card {
            flex-direction: column;
        }

        .notif-actions {
            flex-direction: row;
            align-self: flex-end;
        }

        .notif-top {
            flex-direction: column;
            align-items: flex-start;
        }

        .notif-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .mark-all-container {
            text-align: center;
        }

        .notif-section-header {
            flex-wrap: wrap;
        }
    }
</style>

<div class="notif-wrap">
    <div class="notif-inner">

        <!-- Page Header -->
        <div class="notif-header">
            <h1><i class="fas fa-bell"></i> Notifications Center</h1>
            <div class="notif-date-pill">
                <i class="far fa-calendar-alt"></i>
                <?php echo date('F j, Y'); ?>
            </div>
        </div>

        <div class="notifications-content">

            <?php if ($totalUnreadCount > 0): ?>
                <div class="mark-all-container">
                    <form method="POST">
                        <button type="submit" name="mark_all_read" class="mark-all-btn">
                            <i class="fas fa-check-double"></i> Mark all as read
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ANNOUNCEMENTS SECTION -->
            <div class="notif-section">
                <div class="notif-section-header">
                    <i class="fas fa-bullhorn announce-header-icon"></i>
                    <h2>Announcements</h2>
                    <span class="section-badge">From Admin</span>
                    <?php if ($unreadAnnouncementCount > 0): ?>
                        <span class="new-badge"><?php echo $unreadAnnouncementCount; ?> new</span>
                    <?php endif; ?>
                    <div class="section-line"></div>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="notif-empty">
                        <div class="empty-icon empty-announce-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>No announcements yet</h3>
                        <p>Check back later for updates from the admin</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann):
                        $pdo = getDB();
                        $checkStmt = $pdo->prepare("SELECT 1 FROM announcement_reads WHERE user_id = :user_id AND announcement_id = :ann_id");
                        $checkStmt->bindParam(':user_id', $_SESSION['user_id']);
                        $checkStmt->bindParam(':ann_id', $ann['id']);
                        $checkStmt->execute();
                        $isRead = $checkStmt->fetch() ? true : false;
                        ?>
                        <div class="notif-card <?php echo !$isRead ? 'unread' : ''; ?>">
                            <div class="notif-icon announce-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-top">
                                    <h3><?php echo htmlspecialchars($ann['title']); ?></h3>
                                    <span class="notif-date"><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></span>
                                </div>
                                <p class="notif-message"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                <div class="notif-meta">
                                    <span><i class="fas fa-user-shield"></i>
                                        <?php echo htmlspecialchars($ann['author']); ?></span>
                                    <span><i class="far fa-clock"></i>
                                        <?php echo timeAgo(strtotime($ann['created_at'])); ?></span>
                                </div>
                            </div>
                            <?php if (!$isRead): ?>
                                <div class="notif-actions">
                                    <form method="POST">
                                        <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" name="mark_announcement_read" class="btn-mark-read">
                                            <i class="fas fa-check-circle"></i> Mark read
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- PERSONAL NOTIFICATIONS SECTION -->
            <div class="notif-section">
                <div class="notif-section-header">
                    <i class="fas fa-bell personal-header-icon"></i>
                    <h2>Personal Notifications</h2>
                    <?php if ($unreadNotificationCount > 0): ?>
                        <span class="new-badge"><?php echo $unreadNotificationCount; ?> new</span>
                    <?php endif; ?>
                    <div class="section-line"></div>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="notif-empty">
                        <div class="empty-icon empty-personal-icon">
                            <i class="fas fa-bell-slash"></i>
                        </div>
                        <h3>No personal notifications yet</h3>
                        <p>When you receive updates about reservations or sit-in sessions, they'll appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notif-card <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                            <div class="notif-icon personal-icon">
                                <?php
                                $iconClass = 'fa-info-circle';
                                switch ($notif['type']) {
                                    case 'reservation':
                                        $iconClass = 'fa-calendar-check';
                                        break;
                                    case 'sitin':
                                        $iconClass = 'fa-clock';
                                        break;
                                    case 'reminder':
                                        $iconClass = 'fa-bell';
                                        break;
                                    case 'welcome':
                                        $iconClass = 'fa-smile-wink';
                                        break;
                                    case 'feedback':
                                        $iconClass = 'fa-comment-dots';
                                        break;
                                    default:
                                        $iconClass = 'fa-info-circle';
                                }
                                ?>
                                <i class="fas <?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-top">
                                    <h3><?php echo htmlspecialchars($notif['title']); ?></h3>
                                    <span class="notif-date"><?php echo date('M d, Y', strtotime($notif['created_at'])); ?></span>
                                </div>
                                <p class="notif-message"><?php echo $notif['message']; ?></p>
                                <div class="notif-meta">
                                    <span><i class="far fa-clock"></i>
                                        <?php echo timeAgo(strtotime($notif['created_at'])); ?></span>
                                </div>
                                <!-- REMOVED "View Details" link -->
                            </div>
                            <div class="notif-actions">
                                <?php if (!$notif['is_read']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" name="mark_notification_read" class="btn-mark-read">
                                            <i class="fas fa-check-circle"></i> Mark read
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" name="delete" class="btn-delete"
                                        onclick="return confirm('Delete this notification?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>