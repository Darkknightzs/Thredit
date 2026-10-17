<?php
/**
 * =====================================================================
 * Discussion Replies Controller
 * =====================================================================
 * Handles:
 * 1. Adding a new reply to an open thread (locked threads are blocked)
 * 2. Editing a reply (reply author or admin, CSRF-checked, min 3 chars, locked threads blocked)
 * 3. Marking / unmarking "Best Answer" by the thread author
 * 4. Deleting a reply by its author or a moderator/admin
 *    (votes for the reply are deleted first inside a transaction
 *     because votes.target_id has NO foreign key — cascading does NOT apply)
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

$action = $_POST['action'] ?? '';
$threadId = (int) ($_POST['thread_id'] ?? 0);
$userId = current_user_id();
$pdo = get_db_connection();

// Verify thread exists
$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    set_flash('danger', 'The specified thread does not exist.');
    redirect('public/index.php');
}

// ---------------------------------------------------------------------
// 1. Create a New Reply
// ---------------------------------------------------------------------
if ($action === 'create') {
    // Strictly prevent replying to locked threads
    if ((int) $thread['is_locked'] === 1) {
        set_flash('danger', 'Cannot reply: This discussion has been locked by a moderator.');
        redirect('public/thread.php?id=' . $threadId);
    }

    $body = trim($_POST['body'] ?? '');
    if (mb_strlen($body) < 3) {
        set_flash('danger', 'Your reply must be at least 3 characters long.');
        redirect('public/thread.php?id=' . $threadId);
    }

    $insStmt = $pdo->prepare("INSERT INTO replies (thread_id, user_id, body, is_best, created_at)
                              VALUES (:thread_id, :user_id, :body, 0, NOW())");
    $insStmt->execute([
        ':thread_id' => $threadId,
        ':user_id'   => $userId,
        ':body'      => $body
    ]);

    $newReplyId = (int) $pdo->lastInsertId();
    set_flash('success', 'Your answer has been posted!');
    redirect('public/thread.php?id=' . $threadId . '#reply-' . $newReplyId);
}

// ---------------------------------------------------------------------
// 2. Edit a Reply (Reply Author or Admin)
// ---------------------------------------------------------------------
elseif ($action === 'edit') {
    $replyId = (int) ($_POST['reply_id'] ?? 0);
    $rStmt = $pdo->prepare("SELECT user_id FROM replies WHERE id = :id AND thread_id = :thread_id LIMIT 1");
    $rStmt->execute([':id' => $replyId, ':thread_id' => $threadId]);
    $reply = $rStmt->fetch();

    if (!$reply) {
        set_flash('danger', 'Reply not found.');
        redirect('public/thread.php?id=' . $threadId);
    }

    // Only the reply author or an admin can edit
    if ($userId !== (int) $reply['user_id'] && !is_admin()) {
        http_response_code(403);
        die("<h3>403 Forbidden</h3><p>You do not have permission to edit this reply.</p>");
    }

    // Block editing on locked threads
    if ((int) $thread['is_locked'] === 1) {
        set_flash('danger', 'Cannot edit reply: This discussion has been locked by a moderator.');
        redirect('public/thread.php?id=' . $threadId);
    }

    $body = trim($_POST['body'] ?? '');
    if (mb_strlen($body) < 3) {
        set_flash('danger', 'Reply body must be at least 3 characters long.');
        redirect('public/thread.php?id=' . $threadId . '#reply-' . $replyId);
    }

    $pdo->prepare("UPDATE replies SET body = :body WHERE id = :id")->execute([
        ':body' => $body,
        ':id'   => $replyId
    ]);

    set_flash('success', 'Reply updated successfully!');
    redirect('public/thread.php?id=' . $threadId . '#reply-' . $replyId);
}

// ---------------------------------------------------------------------
// 3. Toggle "Best Answer" (Thread Author Only)
// ---------------------------------------------------------------------
elseif ($action === 'toggle_best') {
    // Only the thread author can accept an answer
    if ($userId !== (int) $thread['user_id'] && !is_admin()) {
        set_flash('danger', 'Only the thread author can accept an answer as the best solution.');
        redirect('public/thread.php?id=' . $threadId);
    }

    $replyId = (int) ($_POST['reply_id'] ?? 0);
    $rStmt = $pdo->prepare("SELECT is_best FROM replies WHERE id = :id AND thread_id = :thread_id LIMIT 1");
    $rStmt->execute([':id' => $replyId, ':thread_id' => $threadId]);
    $reply = $rStmt->fetch();

    if (!$reply) {
        set_flash('danger', 'Reply not found.');
        redirect('public/thread.php?id=' . $threadId);
    }

    if ((int) $reply['is_best'] === 1) {
        // Unmark
        $pdo->prepare("UPDATE replies SET is_best = 0 WHERE id = :id")->execute([':id' => $replyId]);
        set_flash('info', 'Unmarked accepted answer.');
    } else {
        // Reset previous best answers on this thread, then mark the new one
        $pdo->prepare("UPDATE replies SET is_best = 0 WHERE thread_id = :thread_id")->execute([':thread_id' => $threadId]);
        $pdo->prepare("UPDATE replies SET is_best = 1 WHERE id = :id")->execute([':id' => $replyId]);
        set_flash('success', 'Marked as the Best Answer / Accepted Solution!');
    }

    redirect('public/thread.php?id=' . $threadId . '#reply-' . $replyId);
}

// ---------------------------------------------------------------------
// 4. Delete a Reply (Reply Author or Moderator/Admin)
//    NOTE: votes.target_id has NO foreign key to replies, so we must
//    manually delete orphan votes inside a transaction.
// ---------------------------------------------------------------------
elseif ($action === 'delete') {
    $replyId = (int) ($_POST['reply_id'] ?? 0);
    $rStmt = $pdo->prepare("SELECT user_id FROM replies WHERE id = :id AND thread_id = :thread_id LIMIT 1");
    $rStmt->execute([':id' => $replyId, ':thread_id' => $threadId]);
    $reply = $rStmt->fetch();

    if (!$reply) {
        set_flash('danger', 'Reply not found.');
        redirect('public/thread.php?id=' . $threadId);
    }

    if ($userId !== (int) $reply['user_id'] && !is_moderator()) {
        http_response_code(403);
        die("<h3>403 Forbidden</h3><p>You do not have permission to delete this reply.</p>");
    }

    // Use a transaction to delete the reply's votes first, then the reply itself
    // This prevents orphan rows in the votes table (no FK cascade on target_id)
    $pdo->beginTransaction();
    try {
        // Delete votes that reference this reply
        $pdo->prepare("DELETE FROM votes WHERE target_type = 'reply' AND target_id = :id")
            ->execute([':id' => $replyId]);

        // Delete the reply
        $pdo->prepare("DELETE FROM replies WHERE id = :id")
            ->execute([':id' => $replyId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', 'Failed to delete the reply. Please try again.');
        redirect('public/thread.php?id=' . $threadId);
    }

    set_flash('success', 'The reply has been deleted.');
    redirect('public/thread.php?id=' . $threadId);
}

// Fallback
redirect('public/thread.php?id=' . $threadId);
