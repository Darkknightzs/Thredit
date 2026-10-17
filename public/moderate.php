<?php
/**
 * =====================================================================
 * Thread Moderation Controller
 * =====================================================================
 * Allows moderators and administrators to pin/unpin and lock/unlock threads.
 * Protected by server-side role check (require_moderator) and CSRF token.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/db.php';

require_moderator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('public/index.php');
}

validate_csrf();

$action = $_POST['action'] ?? '';
$threadId = (int) ($_POST['thread_id'] ?? 0);
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT id, title, is_pinned, is_locked FROM threads WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    set_flash('danger', 'Thread not found.');
    redirect('public/index.php');
}

switch ($action) {
    case 'pin':
        $pdo->prepare("UPDATE threads SET is_pinned = 1 WHERE id = :id")->execute([':id' => $threadId]);
        set_flash('success', 'Thread has been pinned to the top of discussions.');
        break;

    case 'unpin':
        $pdo->prepare("UPDATE threads SET is_pinned = 0 WHERE id = :id")->execute([':id' => $threadId]);
        set_flash('info', 'Thread has been unpinned.');
        break;

    case 'lock':
        $pdo->prepare("UPDATE threads SET is_locked = 1 WHERE id = :id")->execute([':id' => $threadId]);
        set_flash('warning', 'Thread has been locked. New replies are now disabled.');
        break;

    case 'unlock':
        $pdo->prepare("UPDATE threads SET is_locked = 0 WHERE id = :id")->execute([':id' => $threadId]);
        set_flash('success', 'Thread has been unlocked. Users can now reply again.');
        break;

    default:
        set_flash('danger', 'Unknown moderation action.');
        break;
}

redirect('public/thread.php?id=' . $threadId);
