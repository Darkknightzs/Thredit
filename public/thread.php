<?php
/**
 * =====================================================================
 * Single Thread View & Discussion Replies
 * =====================================================================
 * Features:
 * - Thread details with formatted code blocks and net vote score
 * - View counter incrementation (session deduplicated)
 * - Moderation actions (Pin/Unpin, Lock/Unlock, Delete) for Mod/Admin
 * - Thread author actions (Edit/Delete own thread, Mark Best Answer)
 * - Flat reply feed with highlighted Best Answer at the top
 * - Lock protection: Locked threads cannot receive replies
 * =====================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$pdo = get_db_connection();
$threadId = (int) ($_GET['id'] ?? 0);
$userId = current_user_id();

// Fetch thread with author and category details
$stmt = $pdo->prepare("SELECT t.*,
                              u.name AS author_name, u.role AS author_role,
                              c.name AS category_name,
                              COALESCE(SUM(v.value), 0) AS vote_score
                       FROM threads t
                       INNER JOIN users u ON t.user_id = u.id
                       INNER JOIN categories c ON t.category_id = c.id
                       LEFT JOIN votes v ON v.target_type = 'thread' AND v.target_id = t.id
                       WHERE t.id = :id
                       GROUP BY t.id, u.id, u.name, u.role, c.id, c.name
                       LIMIT 1");
$stmt->execute([':id' => $threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(404);
    $pageTitle = 'Thread Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card empty-state"><h2>Thread Not Found</h2><p>The discussion you are looking for does not exist or has been removed.</p><a href="' . url('public/index.php') . '" class="btn btn-primary" style="margin-top:1rem;">Back to Discussions</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Increment view counter once per session
if (!isset($_SESSION['viewed_threads'])) {
    $_SESSION['viewed_threads'] = [];
}
if (!in_array($threadId, $_SESSION['viewed_threads'], true)) {
    $pdo->prepare("UPDATE threads SET views = views + 1 WHERE id = :id")->execute([':id' => $threadId]);
    $_SESSION['viewed_threads'][] = $threadId;
    $thread['views']++; // Update current display view count
}

// Fetch all replies with author details and net vote scores
$replyStmt = $pdo->prepare("SELECT r.*,
                                   u.name AS author_name, u.role AS author_role,
                                   COALESCE(SUM(v.value), 0) AS vote_score
                            FROM replies r
                            INNER JOIN users u ON r.user_id = u.id
                            LEFT JOIN votes v ON v.target_type = 'reply' AND v.target_id = r.id
                            WHERE r.thread_id = :thread_id
                            GROUP BY r.id, u.id, u.name, u.role
                            ORDER BY r.is_best DESC, r.created_at ASC");
$replyStmt->execute([':thread_id' => $threadId]);
$replies = $replyStmt->fetchAll();

// Optimization: Batch-fetch user votes on all replies in a single query
$userReplyVotes = [];
if ($userId && !empty($replies)) {
    $replyIds = array_column($replies, 'id');
    $placeholders = implode(',', array_fill(0, count($replyIds), '?'));
    $vStmt = $pdo->prepare("SELECT target_id, value FROM votes WHERE user_id = ? AND target_type = 'reply' AND target_id IN ($placeholders)");
    $vStmt->execute(array_merge([$userId], $replyIds));
    $userReplyVotes = $vStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$pageTitle = $thread['title'];
require_once __DIR__ . '/../includes/header.php';

$threadVote = get_user_vote_value($pdo, $userId, 'thread', $threadId);
$threadScore = (int) $thread['vote_score'];

// Permission flags
$isAuthor = ($userId !== null && $userId === (int) $thread['user_id']);
$canModerate = is_moderator();
$canEditThread = ($isAuthor || is_admin());
$canDeleteThread = ($isAuthor || $canModerate);
?>

<div style="max-width: 960px; margin: 0 auto;">
    <!-- Back to Category Navigation -->
    <div style="margin-bottom: 1rem;">
        <a href="<?= url('public/category.php?id=' . $thread['category_id']) ?>" style="color: var(--text-muted); text-decoration: none; font-size: 0.875rem;">
            &larr; Back to <?= e($thread['category_name']) ?>
        </a>
    </div>

    <!-- Main Thread Card -->
    <article class="card thread-header-card <?= $thread['is_pinned'] ? 'thread-pinned' : '' ?> <?= $thread['is_locked'] ? 'thread-locked' : '' ?>">
        <div style="display: flex; gap: 1.25rem;">
            <!-- Vote Column -->
            <div class="vote-col">
                <button type="button" class="vote-btn <?= $threadVote === 1 ? 'active-up' : '' ?>"
                        data-target-type="thread"
                        data-target-id="<?= $threadId ?>"
                        data-vote="1"
                        data-vote-url="<?= url('public/vote.php') ?>"
                        title="Upvote" aria-label="Upvote">
                    ▲
                </button>
                <span class="vote-score <?= $threadScore > 0 ? 'score-positive' : ($threadScore < 0 ? 'score-negative' : '') ?>">
                    <?= $threadScore ?>
                </span>
                <button type="button" class="vote-btn <?= $threadVote === -1 ? 'active-down' : '' ?>"
                        data-target-type="thread"
                        data-target-id="<?= $threadId ?>"
                        data-vote="-1"
                        data-vote-url="<?= url('public/vote.php') ?>"
                        title="Downvote" aria-label="Downvote">
                    ▼
                </button>
            </div>

            <!-- Thread Details -->
            <div style="flex: 1; min-width: 0;">
                <div class="thread-meta">
                    <a href="<?= url('public/category.php?id=' . $thread['category_id']) ?>" class="badge badge-category">
                        <?= e($thread['category_name']) ?>
                    </a>

                    <?php if ($thread['is_pinned']): ?>
                        <span class="badge badge-pinned">📌 Pinned</span>
                    <?php endif; ?>

                    <?php if ($thread['is_locked']): ?>
                        <span class="badge badge-locked">🔒 Locked</span>
                    <?php endif; ?>

                    <span>Posted by <a href="<?= url('public/profile.php?id=' . $thread['user_id']) ?>" style="color:inherit; font-weight:600; text-decoration:none;"><?= e($thread['author_name']) ?></a></span>

                    <?php if ($thread['author_role'] === 'admin'): ?>
                        <span class="badge badge-role badge-role-admin">Admin</span>
                    <?php elseif ($thread['author_role'] === 'moderator'): ?>
                        <span class="badge badge-role badge-role-mod">Mod</span>
                    <?php endif; ?>

                    <span>• <?= time_ago($thread['created_at']) ?></span>
                    <span>• 👁️ <?= (int) $thread['views'] ?> views</span>
                </div>

                <h1 style="font-size: 1.6rem; font-weight: 700; line-height: 1.35; margin: 0.5rem 0 1rem;">
                    <?= e($thread['title']) ?>
                </h1>

                <!-- Formatted Thread Content (Supports Code Blocks) -->
                <div class="thread-detail-body">
                    <?= format_post_body($thread['body']) ?>
                </div>

                <!-- Thread Action Buttons (Edit, Moderate, Delete) -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); font-size: 0.85rem;">
                    <?php if ($canEditThread): ?>
                        <a href="<?= url('public/edit_thread.php?id=' . $threadId) ?>" class="btn btn-secondary btn-sm">
                            ✏️ Edit Thread
                        </a>
                    <?php endif; ?>

                    <?php if ($canModerate): ?>
                        <!-- Moderator Actions -->
                        <form action="<?= url('public/moderate.php') ?>" method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                            <input type="hidden" name="action" value="<?= $thread['is_pinned'] ? 'unpin' : 'pin' ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?= $thread['is_pinned'] ? '📍 Unpin' : '📌 Pin' ?>
                            </button>
                        </form>

                        <form action="<?= url('public/moderate.php') ?>" method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                            <input type="hidden" name="action" value="<?= $thread['is_locked'] ? 'unlock' : 'lock' ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?= $thread['is_locked'] ? '🔓 Unlock' : '🔒 Lock' ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canDeleteThread): ?>
                        <form action="<?= url('public/delete_thread.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this thread?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                🗑️ Delete Thread
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </article>

    <!-- Replies Section Anchor -->
    <div id="replies" class="replies-section">
        <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 1rem;">
            <?= count($replies) ?> <?= count($replies) === 1 ? 'Answer / Reply' : 'Answers / Replies' ?>
        </h2>

        <?php if (empty($replies)): ?>
            <div class="card empty-state" style="padding: 2rem;">
                <div class="empty-state-icon">💡</div>
                <div class="empty-state-title">No replies yet</div>
                <p style="font-size: 0.875rem;">Can you help solve this question? Leave a constructive reply below.</p>
            </div>
        <?php else: ?>
            <?php foreach ($replies as $r): ?>
                <?php
                $replyVote = $userReplyVotes[(int) $r['id']] ?? 0;
                $replyScore = (int) $r['vote_score'];
                $isReplyAuthor = ($userId !== null && $userId === (int) $r['user_id']);
                $canDeleteReply = ($isReplyAuthor || $canModerate);
                ?>
                <div class="card reply-card <?= $r['is_best'] ? 'reply-best' : '' ?>" id="reply-<?= $r['id'] ?>">
                    <!-- Vote Column -->
                    <div class="vote-col">
                        <button type="button" class="vote-btn <?= $replyVote === 1 ? 'active-up' : '' ?>"
                                data-target-type="reply"
                                data-target-id="<?= $r['id'] ?>"
                                data-vote="1"
                                data-vote-url="<?= url('public/vote.php') ?>"
                                title="Upvote" aria-label="Upvote">
                            ▲
                        </button>
                        <span class="vote-score <?= $replyScore > 0 ? 'score-positive' : ($replyScore < 0 ? 'score-negative' : '') ?>">
                            <?= $replyScore ?>
                        </span>
                        <button type="button" class="vote-btn <?= $replyVote === -1 ? 'active-down' : '' ?>"
                                data-target-type="reply"
                                data-target-id="<?= $r['id'] ?>"
                                data-vote="-1"
                                data-vote-url="<?= url('public/vote.php') ?>"
                                title="Downvote" aria-label="Downvote">
                            ▼
                        </button>
                    </div>

                    <!-- Reply Content -->
                    <div class="reply-content">
                        <?php if ($r['is_best']): ?>
                            <div class="best-answer-header">
                                <span>✅</span> <span>BEST ANSWER / ACCEPTED SOLUTION</span>
                            </div>
                        <?php endif; ?>

                        <div class="thread-meta">
                            <span>Answered by <a href="<?= url('public/profile.php?id=' . $r['user_id']) ?>" style="color:inherit; font-weight:600; text-decoration:none;"><?= e($r['author_name']) ?></a></span>

                            <?php if ($r['author_role'] === 'admin'): ?>
                                <span class="badge badge-role badge-role-admin">Admin</span>
                            <?php elseif ($r['author_role'] === 'moderator'): ?>
                                <span class="badge badge-role badge-role-mod">Mod</span>
                            <?php endif; ?>

                            <span>• <?= time_ago($r['created_at']) ?></span>
                        </div>

                        <div class="reply-body">
                            <?= format_post_body($r['body']) ?>
                        </div>

                        <!-- Reply Action Buttons -->
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.75rem; font-size: 0.8rem;">
                            <!-- Best Answer Toggle for Thread Author -->
                            <?php if ($isAuthor && !$thread['is_locked']): ?>
                                <form action="<?= url('public/reply.php') ?>" method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_best">
                                    <input type="hidden" name="reply_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="<?= $r['is_best'] ? 'color:var(--accent-best);' : '' ?>">
                                        <?= $r['is_best'] ? '★ Unmark Best Answer' : '☆ Mark as Best Answer' ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Edit Reply (Reply Author or Admin, not on locked threads) -->
                            <?php $canEditReply = (($isReplyAuthor || is_admin()) && !(int) $thread['is_locked']); ?>
                            <?php if ($canEditReply): ?>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditReply(<?= $r['id'] ?>)">
                                    ✏️ Edit
                                </button>
                            <?php endif; ?>

                            <!-- Delete Reply (Author or Moderator) -->
                            <?php if ($canDeleteReply): ?>
                                <form action="<?= url('public/reply.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Delete this reply permanently?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="reply_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--accent-danger);">
                                        🗑️ Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Inline Edit Form (hidden by default, toggled via JS) -->
                        <?php if ($canEditReply): ?>
                            <div id="edit-reply-<?= $r['id'] ?>" style="display:none; margin-top: 0.75rem;">
                                <form action="<?= url('public/reply.php') ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="reply_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                                    <div class="form-group">
                                        <textarea name="body" class="form-control" style="min-height: 100px;" required><?= e($r['body']) ?></textarea>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditReply(<?= $r['id'] ?>)">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Post a Reply Form Container -->
        <div class="card" style="padding: 1.5rem; margin-top: 2rem;">
            <?php if ($thread['is_locked']): ?>
                <!-- Locked Warning Banner -->
                <div class="alert alert-warning" style="margin: 0;">
                    🔒 <strong>This thread is locked.</strong> New replies cannot be submitted to locked discussions.
                </div>
            <?php elseif (is_logged_in()): ?>
                <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.5rem;">Your Answer / Contribution</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Format technical answers clearly. Code blocks enclosed in triple backticks are automatically highlighted.
                </p>

                <form action="<?= url('public/reply.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="thread_id" value="<?= $threadId ?>">

                    <div class="form-group">
                        <textarea name="body" class="form-control" style="min-height: 140px;" placeholder="Write your solution, debugging insight, or answer here..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Post Answer
                    </button>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 1rem 0;">
                    <p style="font-size: 0.95rem; margin-bottom: 1rem; color: var(--text-secondary);">
                        Join the conversation to share your solution or reply to this thread.
                    </p>
                    <a href="<?= url('public/login.php?redirect=' . urlencode(url('public/thread.php?id=' . $threadId))) ?>" class="btn btn-primary">
                        Sign In to Reply
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
