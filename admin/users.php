<?php
/**
 * =====================================================================
 * Administrator - User Management
 * =====================================================================
 * Features:
 * - List all registered users with role and status
 * - Ban / Unban user accounts
 * - Promote / demote roles (user, moderator, admin)
 * - Self-lockout prevention (admin cannot ban or demote own account)
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_db_connection();
$currentAdminId = current_user_id();

// Handle User Actions (Ban/Unban & Role Change)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $action = $_POST['action'] ?? '';
    $targetUserId = (int) ($_POST['user_id'] ?? 0);

    // Prevent modifying own admin privileges to avoid lockout
    if ($targetUserId === $currentAdminId) {
        set_flash('danger', 'Action forbidden: You cannot ban or change the role of your own admin account.');
        redirect('admin/users.php');
    }

    if ($action === 'toggle_ban') {
        $stmt = $pdo->prepare("SELECT is_banned, name FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $targetUserId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            $newBanState = ((int) $targetUser['is_banned'] === 1) ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_banned = :banned WHERE id = :id")
                ->execute([':banned' => $newBanState, ':id' => $targetUserId]);

            $statusText = $newBanState ? 'banned' : 'unbanned';
            set_flash('success', "User '{$targetUser['name']}' has been {$statusText}.");
        }
    } elseif ($action === 'change_role') {
        $newRole = $_POST['role'] ?? 'user';
        if (in_array($newRole, ['user', 'moderator', 'admin'], true)) {
            $pdo->prepare("UPDATE users SET role = :role WHERE id = :id")
                ->execute([':role' => $newRole, ':id' => $targetUserId]);
            set_flash('success', "User role updated to '{$newRole}'.");
        } else {
            set_flash('danger', 'Invalid role selected.');
        }
    }

    redirect('admin/users.php');
}

// Fetch all users with thread and reply counts
$users = $pdo->query("SELECT u.*,
                             (SELECT COUNT(*) FROM threads WHERE user_id = u.id) AS thread_count,
                             (SELECT COUNT(*) FROM replies WHERE user_id = u.id) AS reply_count
                      FROM users u
                      ORDER BY u.created_at DESC")->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-header">
    <div class="admin-title-group">
        <h1>User Management</h1>
        <p>Inspect registered developers, manage access roles, and moderate platform accounts.</p>
    </div>
    <div class="admin-nav-tabs">
        <a href="<?= url('admin/dashboard.php') ?>" class="admin-tab">
            <span>📊</span> Overview
        </a>
        <a href="<?= url('admin/users.php') ?>" class="admin-tab active">
            <span>👥</span> Manage Users
        </a>
        <a href="<?= url('admin/categories.php') ?>" class="admin-tab">
            <span>📁</span> Categories
        </a>
    </div>
</div>

<div class="card" style="padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 18px;">👥</span>
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                All Registered Users (<?= count($users) ?>)
            </h2>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Developer</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Activity</th>
                    <th>Joined</th>
                    <th style="text-align: right;">Moderation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $isSelf = ((int) $u['id'] === $currentAdminId); ?>
                    <tr>
                        <td>
                            <strong><?= e($u['name']) ?></strong>
                            <?php if ($isSelf): ?>
                                <span style="font-size: 0.75rem; color: var(--brand-primary); font-weight: 600;">(Current Session)</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-secondary);"><?= e($u['email']) ?></td>
                        <td>
                            <!-- Change Role Form -->
                            <?php if ($isSelf): ?>
                                <span class="badge badge-role-admin">Admin</span>
                            <?php else: ?>
                                <form action="<?= url('admin/users.php') ?>" method="POST" style="display:inline-flex; align-items:center; gap:0.25rem;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" class="form-control" style="padding: 4px 8px; font-size: 12px; width: auto; height: 28px;" onchange="this.form.submit()">
                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="moderator" <?= $u['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $u['is_banned'] === 1): ?>
                                <span class="badge badge-role-admin">Suspended</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(70, 209, 96, 0.15); color: #16a34a; border: 1px solid rgba(70, 209, 96, 0.3);">Active</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12.5px; color: var(--text-muted);">
                            <strong><?= (int) $u['thread_count'] ?></strong> threads &middot; <strong><?= (int) $u['reply_count'] ?></strong> replies
                        </td>
                        <td style="color: var(--text-muted); font-size: 12.5px;"><?= time_ago($u['created_at']) ?></td>
                        <td style="text-align: right;">
                            <?php if (!$isSelf): ?>
                                <form action="<?= url('admin/users.php') ?>" method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_ban">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <?php if ((int) $u['is_banned'] === 1): ?>
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #16a34a;">
                                            Unban
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--state-danger); border-color: rgba(234, 0, 39, 0.3);" onclick="return confirm('Suspend and ban this user account?');">
                                            Ban User
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 12px; color: var(--text-muted);">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
