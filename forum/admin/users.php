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

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
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
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 700;">User Management</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Manage user permissions, assign moderator roles, and suspend malicious accounts.</p>
        </div>
        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-secondary">&larr; Back to Dashboard</a>
    </div>
</div>

<div class="card" style="padding: 1.5rem;">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Activity</th>
                    <th>Joined</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $isSelf = ((int) $u['id'] === $currentAdminId); ?>
                    <tr>
                        <td>
                            <strong><?= e($u['name']) ?></strong>
                            <?php if ($isSelf): ?>
                                <span style="font-size: 0.7rem; color: var(--text-muted);">(You)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <!-- Change Role Form -->
                            <?php if ($isSelf): ?>
                                <span class="badge badge-role badge-role-admin">admin</span>
                            <?php else: ?>
                                <form action="<?= url('admin/users.php') ?>" method="POST" style="display:inline-flex; align-items:center; gap:0.25rem;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" class="form-control" style="padding: 0.2rem 0.4rem; font-size: 0.8rem; width: auto;" onchange="this.form.submit()">
                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="moderator" <?= $u['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $u['is_banned'] === 1): ?>
                                <span class="badge" style="background-color:rgba(239, 68, 68, 0.15); color:var(--accent-danger);">Banned</span>
                            <?php else: ?>
                                <span class="badge" style="background-color:rgba(16, 185, 129, 0.15); color:var(--accent-best);">Active</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            <?= (int) $u['thread_count'] ?> threads / <?= (int) $u['reply_count'] ?> replies
                        </td>
                        <td><?= time_ago($u['created_at']) ?></td>
                        <td style="text-align: right;">
                            <?php if (!$isSelf): ?>
                                <form action="<?= url('admin/users.php') ?>" method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_ban">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <?php if ((int) $u['is_banned'] === 1): ?>
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--accent-best);">
                                            Unban
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Suspend and ban this user account?');">
                                            Ban User
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
