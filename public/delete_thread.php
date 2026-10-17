<?php
/**
 * =====================================================================
 * Delete Thread Handler
 * =====================================================================
 * Allows thread authors, moderators, or administrators to delete a thread.
 *
 * NOTE: votes.target_id has NO foreign key to threads or replies,
 * so cascading does NOT happen automatically. We must manually delete
 * all orphan votes (for the thread AND for all its replies) inside a
 * PDO transaction before deleting the thread itself.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/db.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('public/index.php');
}

validate_csrf();

$threadId = (int) ($_POST['thread_id'] ?? 0);
$userId = current_user_id();
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT user_id FROM threads WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    set_flash('danger', 'Thread not found.');
    redirect('public/index.php');
}

// Authorization check: Author, Moderator, or Admin
if ($userId !== (int) $thread['user_id'] && !is_moderator()) {
    http_response_code(403);
    die("<h3>403 Forbidden</h3><p>You do not have permission to delete this thread.</p>");
}

// Use a transaction to delete all related votes, then the thread itself.
// votes.target_id has NO foreign key — cascading does NOT apply to votes.
// The thread's FK to replies DOES cascade, so replies are auto-deleted,
// but we must clean up votes for BOTH the thread and its replies first.
$pdo->beginTransaction();
try {
    // 1. Delete votes on all replies belonging to this thread
    $pdo->prepare("DELETE FROM votes WHERE target_type = 'reply' AND target_id IN (SELECT id FROM replies WHERE thread_id = :tid)")
        ->execute([':tid' => $threadId]);

    // 2. Delete votes on the thread itself
    $pdo->prepare("DELETE FROM votes WHERE target_type = 'thread' AND target_id = :tid")
        ->execute([':tid' => $threadId]);

    // 3. Delete the thread (replies cascade via FK)
    $pdo->prepare("DELETE FROM threads WHERE id = :id")
        ->execute([':id' => $threadId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('danger', 'Failed to delete the thread. Please try again.');
    redirect('public/index.php');
}

set_flash('success', 'The thread has been permanently deleted.');
redirect('public/index.php');
