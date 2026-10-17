<?php
/**
 * =====================================================================
 * Category Thread Listing
 * =====================================================================
 * Displays threads filtered by a specific category.
 * Includes category description, thread counts, and fast create button.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

$pdo = get_db_connection();
$catId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $catId]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    $pageTitle = 'Category Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card empty-state"><h2>Category Not Found</h2><p>The category you are looking for does not exist.</p><a href="' . url('public/index.php') . '" class="btn btn-primary" style="margin-top:1rem;">Back to Home</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $category['name'];
require_once __DIR__ . '/../includes/header.php';

$categories = get_all_categories($pdo);
$userId = current_user_id();
$stats = get_community_stats($pdo);

// Sorting mode: 'hot', 'latest', or 'top'
$sort = $_GET['sort'] ?? 'hot';

// Fetch threads for this specific category
$sql = "SELECT t.*,
               u.name AS author_name, u.role AS author_role,
               c.name AS category_name,
               COUNT(DISTINCT r.id) AS reply_count,
               COALESCE(SUM(v.value), 0) AS vote_score
        FROM threads t
        INNER JOIN users u ON t.user_id = u.id
        INNER JOIN categories c ON t.category_id = c.id
        LEFT JOIN replies r ON t.id = r.thread_id
        LEFT JOIN votes v ON v.target_type = 'thread' AND v.target_id = t.id
        WHERE t.category_id = :cat_id
        GROUP BY t.id, u.id, u.name, u.role, c.id, c.name ";

if ($sort === 'top') {
    $sql .= "ORDER BY t.is_pinned DESC, vote_score DESC, t.created_at DESC";
} elseif ($sort === 'latest') {
    $sql .= "ORDER BY t.is_pinned DESC, t.created_at DESC";
} else {
    $sql .= "ORDER BY t.is_pinned DESC, (COALESCE(SUM(v.value), 0) * 2 + COUNT(DISTINCT r.id)) DESC, t.created_at DESC";
}

$threadStmt = $pdo->prepare($sql);
$threadStmt->execute([':cat_id' => $catId]);
$threads = $threadStmt->fetchAll();
?>

<!-- =====================================================================
     Reddit-Style 3-Column Responsive Grid (Category View)
     ===================================================================== -->
<div class="reddit-container">

    <!-- Column 1: Left Navigation Sidebar -->
    <aside class="reddit-left-nav" id="left-nav-sidebar">
        <!-- Feeds Section -->
        <div class="nav-group">
            <div class="nav-group-title">FEEDS</div>
            <ul class="nav-links">
                <li class="nav-link-item">
                    <a href="<?= url('public/index.php') ?>">
                        <span class="nav-icon">🏠</span>
                        <span>All Topics</span>
                    </a>
                </li>
                <li class="nav-link-item">
                    <a href="<?= url('public/index.php?sort=top') ?>">
                        <span class="nav-icon">🔥</span>
                        <span>Popular / Top</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Topics / Communities Section -->
        <div class="nav-group">
            <div class="nav-group-title">COMMUNITIES &amp; TOPICS</div>
            <ul class="nav-links">
                <?php foreach ($categories as $cat): ?>
                    <li class="nav-link-item <?= (int) $cat['id'] === $catId ? 'active' : '' ?>">
                        <a href="<?= url('public/category.php?id=' . $cat['id']) ?>">
                            <span class="category-circle-icon">#</span>
                            <span class="category-name-text"><?= e($cat['name']) ?></span>
                            <span class="badge-count"><?= (int) $cat['thread_count'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- User Account Navigation (Only when logged in) -->
        <?php if ($user): ?>
        <div class="nav-group">
            <div class="nav-group-title">ACCOUNT</div>
            <ul class="nav-links">
                <li class="nav-link-item">
                    <a href="<?= url('public/profile.php') ?>">
                        <span class="nav-icon">👤</span>
                        <span><?= e($user['name']) ?> (Profile)</span>
                    </a>
                </li>
                <?php if (is_admin()): ?>
                    <li class="nav-link-item">
                        <a href="<?= url('admin/dashboard.php') ?>">
                            <span class="nav-icon">⚙️</span>
                            <span>Admin Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-link-item">
                    <a href="<?= url('public/logout.php') ?>" style="color: var(--state-danger);">
                        <span class="nav-icon">🚪</span>
                        <span>Log Out</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Create Button -->
        <div class="nav-group nav-group-footer">
            <a href="<?= url('public/new_thread.php?category_id=' . $catId) ?>" class="btn btn-primary" style="width: 100%; justify-content: center; gap: 8px;">
                <span>✏️</span> Post in <?= e($category['name']) ?>
            </a>
        </div>
    </aside>

    <!-- Column 2: Center Main Discussion Feed -->
    <main class="reddit-main-feed">

        <!-- Mobile-Only Category Chips Horizontal Scroller -->
        <div class="mobile-category-chips">
            <a href="<?= url('public/index.php') ?>" class="chip-item">All Topics</a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= url('public/category.php?id=' . $cat['id']) ?>" class="chip-item <?= (int) $cat['id'] === $catId ? 'active' : '' ?>">
                    <?= e($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Category Banner (Reddit Subreddit Header) -->
        <div class="card reddit-community-banner-card">
            <div class="banner-gradient-strip"></div>
            <div class="banner-inner-content">
                <div class="banner-avatar-icon">#</div>
                <div class="banner-info-text">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <h1 class="banner-title">c/<?= e($category['name']) ?></h1>
                        <span class="badge badge-category">Community</span>
                    </div>
                    <p class="banner-description"><?= e($category['description']) ?></p>
                </div>
            </div>
        </div>

        <!-- Reddit "Create Post" Quick Prompt Box -->
        <div class="card reddit-create-box">
            <div class="create-avatar-circle">
                <?= $user ? '<span class="user-initial">' . strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8')) . '</span>' : render_default_avatar_svg(24) ?>
            </div>
            <a href="<?= url('public/new_thread.php?category_id=' . $catId) ?>" class="create-input-fake" aria-label="Create Post">
                <span>Post a question in c/<?= e($category['name']) ?>...</span>
            </a>
            <div class="create-action-buttons">
                <a href="<?= url('public/new_thread.php?category_id=' . $catId) ?>" class="btn-icon-subtle" title="Share Code Snippet">
                    💻
                </a>
                <a href="<?= url('public/new_thread.php?category_id=' . $catId) ?>" class="btn-icon-subtle" title="Ask Question">
                    ❓
                </a>
            </div>
        </div>

        <!-- Reddit Sort Controls Bar (Hot, New, Top) -->
        <div class="card reddit-sort-bar">
            <div class="sort-tab-group">
                <a href="<?= url('public/category.php?id=' . $catId . '&sort=hot') ?>" class="sort-tab <?= $sort === 'hot' ? 'active' : '' ?>">
                    <span>🔥</span> Hot
                </a>
                <a href="<?= url('public/category.php?id=' . $catId . '&sort=latest') ?>" class="sort-tab <?= $sort === 'latest' ? 'active' : '' ?>">
                    <span>✨</span> New
                </a>
                <a href="<?= url('public/category.php?id=' . $catId . '&sort=top') ?>" class="sort-tab <?= $sort === 'top' ? 'active' : '' ?>">
                    <span>🏆</span> Top
                </a>
            </div>
            <div class="feed-count-hint">
                <?= count($threads) ?> <?= count($threads) === 1 ? 'post' : 'posts' ?>
            </div>
        </div>

        <!-- Feed Threads List -->
        <?php if (empty($threads)): ?>
            <div class="card empty-state">
                <div class="empty-state-icon">📂</div>
                <div class="empty-state-title">No discussions in c/<?= e($category['name']) ?> yet.</div>
                <p style="font-size: 0.875rem; margin-bottom: 1.25rem;">Be the first developer to start a discussion here!</p>
                <a href="<?= url('public/new_thread.php?category_id=' . $catId) ?>" class="btn btn-primary">Start a New Thread</a>
            </div>
        <?php else: ?>
            <div class="reddit-posts-list">
                <?php foreach ($threads as $t): ?>
                    <?php
                    $userVote = get_user_vote_value($pdo, $userId, 'thread', (int) $t['id']);
                    $score = (int) $t['vote_score'];
                    $threadUrl = url('public/thread.php?id=' . $t['id']);
                    ?>
                    <article class="card reddit-post-card <?= $t['is_pinned'] ? 'post-pinned' : '' ?> <?= $t['is_locked'] ? 'post-locked' : '' ?>">
                        
                        <!-- Reddit Left Vote Column -->
                        <div class="reddit-vote-column">
                            <button type="button" class="reddit-vote-btn vote-up <?= $userVote === 1 ? 'voted' : '' ?>"
                                    data-target-type="thread"
                                    data-target-id="<?= $t['id'] ?>"
                                    data-vote="1"
                                    data-vote-url="<?= url('public/vote.php') ?>"
                                    title="Upvote" aria-label="Upvote">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="18 15 12 9 6 15"></polyline>
                                </svg>
                            </button>
                            <span class="reddit-vote-number <?= $score > 0 ? 'score-up' : ($score < 0 ? 'score-down' : '') ?>">
                                <?= $score ?>
                            </span>
                            <button type="button" class="reddit-vote-btn vote-down <?= $userVote === -1 ? 'voted' : '' ?>"
                                    data-target-type="thread"
                                    data-target-id="<?= $t['id'] ?>"
                                    data-vote="-1"
                                    data-vote-url="<?= url('public/vote.php') ?>"
                                    title="Downvote" aria-label="Downvote">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                        </div>

                        <!-- Reddit Main Content Area -->
                        <div class="reddit-post-main">
                            <!-- Post Meta Header -->
                            <div class="reddit-post-meta">
                                <span class="reddit-community-pill">c/<?= e($category['name']) ?></span>
                                <span class="meta-dot">&bull;</span>
                                <span class="meta-author">Posted by <strong>u/<?= e($t['author_name']) ?></strong></span>

                                <?php if ($t['author_role'] === 'admin'): ?>
                                    <span class="badge badge-role badge-role-admin">Admin</span>
                                <?php elseif ($t['author_role'] === 'moderator'): ?>
                                    <span class="badge badge-role badge-role-mod">Mod</span>
                                <?php endif; ?>

                                <span class="meta-dot">&bull;</span>
                                <span class="meta-time"><?= time_ago($t['created_at']) ?></span>

                                <?php if ($t['is_pinned']): ?>
                                    <span class="badge badge-pinned">📌 Pinned</span>
                                <?php endif; ?>

                                <?php if ($t['is_locked']): ?>
                                    <span class="badge badge-locked">🔒 Locked</span>
                                <?php endif; ?>
                            </div>

                            <!-- Post Title -->
                            <h2 class="reddit-post-title">
                                <a href="<?= $threadUrl ?>">
                                    <?= e($t['title']) ?>
                                </a>
                            </h2>

                            <!-- Post Body Snippet -->
                            <div class="reddit-post-snippet">
                                <?= e(truncate($t['body'], 180)) ?>
                            </div>

                            <!-- Reddit Action Bar (Footer) -->
                            <div class="reddit-post-actions">
                                <a href="<?= $threadUrl ?>#replies" class="action-pill">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <span><?= (int) $t['reply_count'] ?> <?= (int) $t['reply_count'] === 1 ? 'Comment' : 'Comments' ?></span>
                                </a>

                                <button type="button" class="action-pill share-btn" data-url="<?= $threadUrl ?>" title="Copy link to discussion">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="18" cy="5" r="3"></circle>
                                        <circle cx="6" cy="12" r="3"></circle>
                                        <circle cx="18" cy="19" r="3"></circle>
                                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                    </svg>
                                    <span>Share</span>
                                </button>

                                <div class="action-pill action-views">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <span><?= (int) $t['views'] ?> views</span>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Column 3: Right Sidebar (Reddit Community Widget) -->
    <aside class="reddit-right-sidebar">

        <!-- About Category Card -->
        <div class="card community-info-card">
            <div class="community-banner">
                <div class="community-avatar">#</div>
            </div>
            <div class="community-body">
                <div class="community-header-text">
                    <h3 class="community-name">c/<?= e($category['name']) ?></h3>
                    <div class="community-handle">Community Topic</div>
                </div>
                <p class="community-desc">
                    <?= e($category['description']) ?>
                </p>

                <!-- Category Stats -->
                <div class="community-metrics">
                    <div class="metric-item">
                        <div class="metric-val"><?= count($threads) ?></div>
                        <div class="metric-lbl">Discussions</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-val"><?= $stats['users'] ?></div>
                        <div class="metric-lbl">Members</div>
                    </div>
                </div>

                <a href="<?= url('public/new_thread.php?category_id=' . $catId) ?>" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 14px;">
                    + Post in this Topic
                </a>
            </div>
        </div>

        <!-- Community Guidelines Card -->
        <div class="card community-rules-card">
            <div class="card-header-styled">
                <span>📋</span> Guidelines for <?= e($category['name']) ?>
            </div>
            <ol class="rules-list">
                <li>
                    <strong>Keep it relevant</strong>
                    <div>Only post topics related to <?= e($category['name']) ?>.</div>
                </li>
                <li>
                    <strong>Format snippets cleanly</strong>
                    <div>Use appropriate syntax highlighting and indentation.</div>
                </li>
                <li>
                    <strong>Be constructive</strong>
                    <div>Explain reasoning when reviewing answers or solutions.</div>
                </li>
            </ol>
        </div>

    </aside>
</div>

<!-- Simple Toast Notification Container for Share Link -->
<div id="toast-notification" class="toast-popup" style="display: none;">
    <span id="toast-message">Link copied to clipboard!</span>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
