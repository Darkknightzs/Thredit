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

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';

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

<div class="admin-container">
<div class="admin-header">
    <div class="admin-title-group">
        <h1>Administrator Dashboard</h1>
        <p>Overview of technical community activity, developer moderation, and discussion metrics.</p>
    </div>
    <div class="admin-nav-tabs">
        <a href="<?= url('admin/dashboard.php') ?>" class="admin-tab active">
            <span>📊</span> Overview
        </a>
        <a href="<?= url('admin/users.php') ?>" class="admin-tab">
            <span>👥</span> Manage Users
        </a>
        <a href="<?= url('admin/categories.php') ?>" class="admin-tab">
            <span>📁</span> Categories
        </a>
    </div>
</div>

<!-- Stat Cards Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon users">👥</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-label">Registered Developers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon threads">💬</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalThreads) ?></div>
            <div class="stat-label">Technical Threads</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon replies">💡</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalReplies) ?></div>
            <div class="stat-label">Solutions &amp; Replies</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon categories">🏷️</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalCategories) ?></div>
            <div class="stat-label">Active Categories</div>
        </div>
    </div>
</div>

<!-- Recent Users Table Card -->
<div class="card" style="padding: 20px; margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">👤</span>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin: 0;">Recent User Registrations</h2>
        </div>
        <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary btn-sm">Manage All Users &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Developer</th>
                    <th>Email Address</th>
                    <th>Platform Role</th>
                    <th>Account Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><strong><?= e($u['name']) ?></strong></td>
                        <td style="color: var(--text-secondary);"><?= e($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-role-admin">Admin</span>
                            <?php elseif ($u['role'] === 'moderator'): ?>
                                <span class="badge badge-role-mod">Moderator</span>
                            <?php else: ?>
                                <span class="badge badge-role-user">Member</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_banned']): ?>
                                <span class="badge badge-role-admin">Suspended</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(70, 209, 96, 0.15); color: #16a34a; border: 1px solid rgba(70, 209, 96, 0.3);">Active</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 12.5px;"><?= time_ago($u['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Threads Table Card -->
<div class="card" style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">💬</span>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin: 0;">Latest Technical Discussions</h2>
        </div>
        <a href="<?= url('public/index.php') ?>" class="btn btn-secondary btn-sm">Browse Public Feed &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Thread Title</th>
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
                            <a href="<?= url('public/thread.php?id=' . $t['id']) ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;" onmouseover="this.style.color='var(--brand-primary)'" onmouseout="this.style.color='var(--text-primary)'">
                                <?= e($t['title']) ?>
                            </a>
                        </td>
                        <td><span class="badge badge-category"><?= e($t['category_name']) ?></span></td>
                        <td><?= e($t['author_name']) ?></td>
                        <td style="font-weight: 600;"><?= number_format((int) $t['views']) ?></td>
                        <td>
                            <?php if ($t['is_pinned']): ?>
                                <span class="badge badge-pinned">📌 Pinned</span>
                            <?php endif; ?>
                            <?php if ($t['is_locked']): ?>
                                <span class="badge badge-locked">🔒 Locked</span>
                            <?php endif; ?>
                            <?php if (!$t['is_pinned'] && !$t['is_locked']): ?>
                                <span style="color: var(--text-muted); font-size: 12px;">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 12.5px;"><?= time_ago($t['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
