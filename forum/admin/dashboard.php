<?php
/**
 * =====================================================================
 * Administrator Dashboard
 * =====================================================================
 * Overview of platform activity for BCA assignment evaluators:
 * - High-level metrics (Total Users, Threads, Replies, Categories)
 * - Recent user registrations table
 * - Recent technical threads table
 * - Quick links to user moderation and category management
 * =====================================================================
 */

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_admin();

$pdo = get_db_connection();

// Aggregate platform metrics
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalThreads = (int) $pdo->query("SELECT COUNT(*) FROM threads")->fetchColumn();
$totalReplies = (int) $pdo->query("SELECT COUNT(*) FROM replies")->fetchColumn();
$totalCategories = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Fetch recent users
$recentUsers = $pdo->query("SELECT id, name, email, role, is_banned, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Fetch recent threads
$recentThreads = $pdo->query("SELECT t.id, t.title, t.views, t.is_pinned, t.is_locked, t.created_at, u.name AS author_name, c.name AS category_name
                              FROM threads t
                              INNER JOIN users u ON t.user_id = u.id
                              INNER JOIN categories c ON t.category_id = c.id
                              ORDER BY t.created_at DESC LIMIT 5")->fetchAll();
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 700;">Administrator Dashboard</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Manage platform users, roles, discussion categories, and inspect statistics.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary">👥 Manage Users</a>
            <a href="<?= url('admin/categories.php') ?>" class="btn btn-secondary">📁 Manage Categories</a>
        </div>
    </div>
</div>

<!-- Stat Cards Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalThreads ?></div>
        <div class="stat-label">Total Threads</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalReplies ?></div>
        <div class="stat-label">Total Replies</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalCategories ?></div>
        <div class="stat-label">Discussion Categories</div>
    </div>
</div>

<!-- Recent Users Table -->
<div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700;">Recent User Registrations</h2>
        <a href="<?= url('admin/users.php') ?>" style="font-size: 0.85rem; color: var(--primary); text-decoration:none;">View all users &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><strong><?= e($u['name']) ?></strong></td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-role badge-role-admin">Admin</span>
                            <?php elseif ($u['role'] === 'moderator'): ?>
                                <span class="badge badge-role badge-role-mod">Mod</span>
                            <?php else: ?>
                                <span class="badge badge-role badge-role-user">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_banned']): ?>
                                <span style="color:var(--accent-danger); font-weight:600;">Banned</span>
                            <?php else: ?>
                                <span style="color:var(--accent-best); font-weight:600;">Active</span>
                            <?php endif; ?>
                        </td>
                        <td><?= time_ago($u['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Threads Table -->
<div class="card" style="padding: 1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700;">Latest Discussions</h2>
        <a href="<?= url('public/index.php') ?>" style="font-size: 0.85rem; color: var(--primary); text-decoration:none;">Browse all &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Views</th>
                    <th>Status</th>
                    <th>Posted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentThreads as $t): ?>
                    <tr>
                        <td>
                            <a href="<?= url('public/thread.php?id=' . $t['id']) ?>" style="color:var(--text-primary); text-decoration:none; font-weight:600;">
                                <?= e($t['title']) ?>
                            </a>
                        </td>
                        <td><span class="badge badge-category"><?= e($t['category_name']) ?></span></td>
                        <td><?= e($t['author_name']) ?></td>
                        <td><?= (int) $t['views'] ?></td>
                        <td>
                            <?php if ($t['is_pinned']): ?>
                                <span class="badge badge-pinned">📌 Pinned</span>
                            <?php endif; ?>
                            <?php if ($t['is_locked']): ?>
                                <span class="badge badge-locked">🔒 Locked</span>
                            <?php endif; ?>
                            <?php if (!$t['is_pinned'] && !$t['is_locked']): ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">Normal</span>
                            <?php endif; ?>
                        </td>
                        <td><?= time_ago($t['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
