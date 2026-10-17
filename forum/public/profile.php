<?php
/**
 * =====================================================================
 * User Profile Page
 * =====================================================================
 * Displays a developer's profile:
 * - Bio, role, member since date
 * - Tabbed or sectioned list of user's threads and replies
 * =====================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$pdo = get_db_connection();
$currentUserId = current_user_id();

// Determine target profile user ID
$profileId = (int) ($_GET['id'] ?? $currentUserId ?? 0);

if ($profileId <= 0) {
    redirect('public/login.php');
}

$uStmt = $pdo->prepare("SELECT id, name, email, role, is_banned, created_at FROM users WHERE id = :id LIMIT 1");
$uStmt->execute([':id' => $profileId]);
$profileUser = $uStmt->fetch();

if (!$profileUser) {
    http_response_code(404);
    $pageTitle = 'User Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card empty-state"><h2>User Not Found</h2><a href="' . url('public/index.php') . '" class="btn btn-primary">Back to Home</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $profileUser['name'] . "'s Profile";
require_once __DIR__ . '/../includes/header.php';

// Fetch threads started by this user
$threadStmt = $pdo->prepare("SELECT t.*, c.name AS category_name,
                                    COUNT(DISTINCT r.id) AS reply_count,
                                    COALESCE(SUM(v.value), 0) AS vote_score
                             FROM threads t
                             INNER JOIN categories c ON t.category_id = c.id
                             LEFT JOIN replies r ON t.id = r.thread_id
                             LEFT JOIN votes v ON v.target_type = 'thread' AND v.target_id = t.id
                             WHERE t.user_id = :uid
                             GROUP BY t.id, c.id, c.name
                             ORDER BY t.created_at DESC");
$threadStmt->execute([':uid' => $profileId]);
$userThreads = $threadStmt->fetchAll();

// Fetch replies written by this user along with thread context
$replyStmt = $pdo->prepare("SELECT r.*, t.title AS thread_title, t.id AS parent_thread_id,
                                   COALESCE(SUM(v.value), 0) AS vote_score
                            FROM replies r
                            INNER JOIN threads t ON r.thread_id = t.id
                            LEFT JOIN votes v ON v.target_type = 'reply' AND v.target_id = r.id
                            WHERE r.user_id = :uid
                            GROUP BY r.id, t.id, t.title
                            ORDER BY r.created_at DESC");
$replyStmt->execute([':uid' => $profileId]);
$userReplies = $replyStmt->fetchAll();

// Active tab ('threads' or 'replies')
$tab = $_GET['tab'] ?? 'threads';
?>

<div style="max-width: 960px; margin: 0 auto;">
    <!-- Profile Card Header -->
    <div class="card" style="padding: 2rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
            <div style="width: 72px; height: 72px; border-radius: var(--radius-full); background: linear-gradient(135deg, var(--primary), #8b5cf6); color:#fff; display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:bold;">
                <?= strtoupper(mb_substr($profileUser['name'], 0, 1, 'UTF-8')) ?>
            </div>

            <div style="flex: 1; min-width: 220px;">
                <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                    <h1 style="font-size: 1.6rem; font-weight: 700;"><?= e($profileUser['name']) ?></h1>
                    <?php if ($profileUser['role'] === 'admin'): ?>
                        <span class="badge badge-role badge-role-admin">Administrator</span>
                    <?php elseif ($profileUser['role'] === 'moderator'): ?>
                        <span class="badge badge-role badge-role-mod">Moderator</span>
                    <?php else: ?>
                        <span class="badge badge-role badge-role-user">Member</span>
                    <?php endif; ?>

                    <?php if ((int) $profileUser['is_banned'] === 1): ?>
                        <span class="badge" style="background:#fee2e2; color:#ef4444;">Suspended</span>
                    <?php endif; ?>
                </div>

                <div style="color: var(--text-muted); font-size: 0.875rem;">
                    <span>Member since <?= date('M Y', strtotime($profileUser['created_at'])) ?></span>
                    <?php if ($currentUserId === (int) $profileUser['id'] || is_admin()): ?>
                        <span> • <?= e($profileUser['email']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Activity Counters -->
            <div style="display: flex; gap: 1.5rem;">
                <div style="text-align: center;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?= count($userThreads) ?></div>
                    <div style="font-size: 0.775rem; color: var(--text-muted); text-transform: uppercase;">Threads</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary);"><?= count($userReplies) ?></div>
                    <div style="font-size: 0.775rem; color: var(--text-muted); text-transform: uppercase;">Replies</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
        <a href="<?= url('public/profile.php?id=' . $profileId . '&tab=threads') ?>" class="btn btn-sm <?= $tab === 'threads' ? 'btn-primary' : 'btn-secondary' ?>">
            Threads (<?= count($userThreads) ?>)
        </a>
        <a href="<?= url('public/profile.php?id=' . $profileId . '&tab=replies') ?>" class="btn btn-sm <?= $tab === 'replies' ? 'btn-primary' : 'btn-secondary' ?>">
            Replies &amp; Answers (<?= count($userReplies) ?>)
        </a>
    </div>

    <!-- Tab 1: User's Threads -->
    <?php if ($tab === 'threads'): ?>
        <?php if (empty($userThreads)): ?>
            <div class="card empty-state">
                <div class="empty-state-icon">📝</div>
                <div class="empty-state-title">No threads created yet</div>
                <p style="font-size: 0.875rem;">This user has not created any technical discussions yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($userThreads as $t): ?>
                <article class="card thread-card" style="padding: 1rem;">
                    <div class="thread-content">
                        <div class="thread-meta">
                            <span class="badge badge-category"><?= e($t['category_name']) ?></span>
                            <?php if ($t['is_pinned']): ?><span class="badge badge-pinned">Pinned</span><?php endif; ?>
                            <?php if ($t['is_locked']): ?><span class="badge badge-locked">Locked</span><?php endif; ?>
                            <span>• <?= time_ago($t['created_at']) ?></span>
                        </div>

                        <h2 class="thread-title">
                            <a href="<?= url('public/thread.php?id=' . $t['id']) ?>">
                                <?= e($t['title']) ?>
                            </a>
                        </h2>

                        <p class="thread-snippet">
                            <?= e(truncate($t['body'], 140)) ?>
                        </p>

                        <div class="thread-footer">
                            <span class="footer-stat">▲ <?= (int) $t['vote_score'] ?> points</span>
                            <span class="footer-stat">💬 <?= (int) $t['reply_count'] ?> replies</span>
                            <span class="footer-stat">👁️ <?= (int) $t['views'] ?> views</span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

    <!-- Tab 2: User's Replies -->
    <?php else: ?>
        <?php if (empty($userReplies)): ?>
            <div class="card empty-state">
                <div class="empty-state-icon">💡</div>
                <div class="empty-state-title">No replies posted yet</div>
                <p style="font-size: 0.875rem;">This user has not answered any technical questions yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($userReplies as $r): ?>
                <article class="card" style="padding: 1.25rem; margin-bottom: 1rem;">
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                        Replied to: <a href="<?= url('public/thread.php?id=' . $r['parent_thread_id']) ?>" style="color:var(--text-primary); font-weight:600; text-decoration:none;">
                            <?= e($r['thread_title']) ?>
                        </a>
                        <span> • <?= time_ago($r['created_at']) ?></span>
                        <?php if ($r['is_best']): ?>
                            <span class="badge badge-best" style="margin-left: 0.5rem;">Accepted Solution</span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size: 0.95rem; margin-bottom: 0.5rem;">
                        <?= format_post_body(truncate($r['body'], 200)) ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem; color:var(--text-muted);">
                        <span>Score: <strong><?= (int) $r['vote_score'] ?></strong></span>
                        <a href="<?= url('public/thread.php?id=' . $r['parent_thread_id'] . '#reply-' . $r['id']) ?>" style="color:var(--primary); text-decoration:none;">
                            View full answer &rarr;
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
