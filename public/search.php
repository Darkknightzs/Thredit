<?php
/**
 * =====================================================================
 * Technical Discussion Forum - Search with Pagination
 * =====================================================================
 * Searches thread titles and bodies using SQL LIKE with PDO prepared statements.
 * Pagination requirement: 10 results per page.
 * =====================================================================
 */

$pageTitle = 'Search Results';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();
$query = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$threads = [];
$totalResults = 0;
$totalPages = 1;

if (!empty($query)) {
    // Escape SQL LIKE wildcards (% and _) in the user's search term
    // This prevents users from manipulating the LIKE pattern
    $escapedQuery = addcslashes($query, '%_\\');
    $searchTerm = '%' . $escapedQuery . '%';

    // 1. Count matching threads (with ESCAPE clause for safe LIKE)
    $countSql = "SELECT COUNT(*) FROM threads WHERE title LIKE :q1 ESCAPE '\\\\' OR body LIKE :q2 ESCAPE '\\\\'";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm]);
    $totalResults = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalResults / $perPage));

    // 2. Fetch paginated results
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
            WHERE t.title LIKE :q1 ESCAPE '\\\\' OR t.body LIKE :q2 ESCAPE '\\\\'
            GROUP BY t.id, u.id, u.name, u.role, c.id, c.name
            ORDER BY t.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':q1', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':q2', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $threads = $stmt->fetchAll();
}

$userId = current_user_id();
?>

<div style="max-width: 960px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700;">Search Results</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            <?php if (!empty($query)): ?>
                Found <strong><?= $totalResults ?></strong> <?= $totalResults === 1 ? 'discussion' : 'discussions' ?> matching &ldquo;<strong><?= e($query) ?></strong>&rdquo;
            <?php else: ?>
                Please enter a search keyword to find discussions.
            <?php endif; ?>
        </p>
    </div>

    <!-- Search Form -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form action="<?= url('public/search.php') ?>" method="GET" style="display: flex; gap: 0.75rem;">
            <input type="text" name="q" class="form-control" placeholder="Search keywords in title or content..." value="<?= e($query) ?>" required autofocus>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <?php if (empty($query)): ?>
        <!-- No search term entered -->
    <?php elseif (empty($threads)): ?>
        <div class="card empty-state">
            <div class="empty-state-icon">🔍</div>
            <div class="empty-state-title">No matching discussions found</div>
            <p style="font-size: 0.875rem; margin-bottom: 1rem;">Try searching with different technical keywords or check spelling.</p>
            <a href="<?= url('public/new_thread.php') ?>" class="btn btn-primary">Create a Thread about &ldquo;<?= e($query) ?>&rdquo;</a>
        </div>
    <?php else: ?>
        <!-- Results List -->
        <?php foreach ($threads as $t): ?>
            <?php
            $userVote = get_user_vote_value($pdo, $userId, 'thread', (int) $t['id']);
            $score = (int) $t['vote_score'];
            ?>
            <article class="card thread-card <?= $t['is_pinned'] ? 'thread-pinned' : '' ?> <?= $t['is_locked'] ? 'thread-locked' : '' ?>">
                <div class="vote-col">
                    <button type="button" class="vote-btn <?= $userVote === 1 ? 'active-up' : '' ?>"
                            data-target-type="thread"
                            data-target-id="<?= $t['id'] ?>"
                            data-vote="1"
                            data-vote-url="<?= url('public/vote.php') ?>"
                            title="Upvote" aria-label="Upvote">
                        ▲
                    </button>
                    <span class="vote-score <?= $score > 0 ? 'score-positive' : ($score < 0 ? 'score-negative' : '') ?>">
                        <?= $score ?>
                    </span>
                    <button type="button" class="vote-btn <?= $userVote === -1 ? 'active-down' : '' ?>"
                            data-target-type="thread"
                            data-target-id="<?= $t['id'] ?>"
                            data-vote="-1"
                            data-vote-url="<?= url('public/vote.php') ?>"
                            title="Downvote" aria-label="Downvote">
                        ▼
                    </button>
                </div>

                <div class="thread-content">
                    <div class="thread-meta">
                        <a href="<?= url('public/category.php?id=' . $t['category_id']) ?>" class="badge badge-category">
                            <?= e($t['category_name']) ?>
                        </a>
                        <span>Posted by <strong><?= e($t['author_name']) ?></strong></span>
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
                        <span class="footer-stat">💬 <?= (int) $t['reply_count'] ?> replies</span>
                        <span class="footer-stat">👁️ <?= (int) $t['views'] ?> views</span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <!-- Pagination Controls (10 results per page) -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= url('public/search.php?q=' . urlencode($query) . '&page=' . ($page - 1)) ?>" class="page-item">&laquo; Prev</a>
                <?php else: ?>
                    <span class="page-item disabled">&laquo; Prev</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?= url('public/search.php?q=' . urlencode($query) . '&page=' . $i) ?>" class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= url('public/search.php?q=' . urlencode($query) . '&page=' . ($page + 1)) ?>" class="page-item">Next &raquo;</a>
                <?php else: ?>
                    <span class="page-item disabled">Next &raquo;</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
