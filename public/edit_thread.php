<?php
/**
 * =====================================================================
 * Edit Thread
 * =====================================================================
 * Allows thread authors or administrators to edit the title, category,
 * or content of an existing discussion.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/db.php';

require_login();

$pdo = get_db_connection();
$threadId = (int) ($_GET['id'] ?? 0);
$userId = current_user_id();

// Look up thread
$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(404);
    $pageTitle = 'Thread Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="card empty-state"><h2>Thread Not Found</h2><a href="' . url('public/index.php') . '" class="btn btn-primary">Back to Home</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Permission check: Author or Admin
if ($userId !== (int) $thread['user_id'] && !is_admin()) {
    http_response_code(403);
    die("<h3>403 Forbidden</h3><p>You do not have permission to edit this thread.</p>");
}

$categories = get_all_categories($pdo);
$errors = [];
$title = $thread['title'];
$body = $thread['body'];
$selectedCatId = (int) $thread['category_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $selectedCatId = (int) ($_POST['category_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($selectedCatId <= 0) {
        $errors[] = 'Please select a valid category.';
    }

    if (mb_strlen($title) < 5 || mb_strlen($title) > 255) {
        $errors[] = 'Title must be between 5 and 255 characters.';
    }

    if (mb_strlen($body) < 10) {
        $errors[] = 'Body must be at least 10 characters long.';
    }

    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare("UPDATE threads SET category_id = :cat, title = :title, body = :body WHERE id = :id");
            $updateStmt->execute([
                ':cat'   => $selectedCatId,
                ':title' => $title,
                ':body'  => $body,
                ':id'    => $threadId
            ]);

            set_flash('success', 'Thread updated successfully!');
            redirect('public/thread.php?id=' . $threadId);

        } catch (PDOException $e) {
            $errors[] = 'Failed to update thread. Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit: ' . $thread['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card form-card" style="max-width: 800px;">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.35rem;">Edit Discussion Thread</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Modify the title, category, or code samples in your discussion.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin-left: 1.25rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= url('public/edit_thread.php?id=' . $threadId) ?>" method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="category_id">Category</label>
            <select name="category_id" id="category_id" class="form-control" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (int) $cat['id'] === $selectedCatId ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="title">Title</label>
            <input type="text" name="title" id="title" class="form-control" value="<?= e($title) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="body">Description & Code Snippets</label>
            <textarea name="body" id="body" class="form-control" style="min-height: 220px;" required><?= e($body) ?></textarea>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= url('public/thread.php?id=' . $threadId) ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
