<?php
/**
 * =====================================================================
 * Create New Discussion Thread
 * =====================================================================
 * Allows authenticated students/users to start a technical discussion.
 * Supports category selection and markdown-style code snippets.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/header.php';
require_login();

$pdo = get_db_connection();
$categories = get_all_categories($pdo);

$selectedCatId = (int) ($_GET['category_id'] ?? 0);
$errors = [];
$title = '';
$body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $selectedCatId = (int) ($_POST['category_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    // Validation
    if ($selectedCatId <= 0) {
        $errors[] = 'Please choose a valid category for your thread.';
    }

    if (mb_strlen($title) < 5 || mb_strlen($title) > 255) {
        $errors[] = 'Thread title must be between 5 and 255 characters.';
    }

    if (mb_strlen($body) < 10) {
        $errors[] = 'Thread description/question must be at least 10 characters long.';
    }

    if (empty($errors)) {
        try {
            $insertStmt = $pdo->prepare("INSERT INTO threads (category_id, user_id, title, body, is_pinned, is_locked, views, created_at)
                                         VALUES (:category_id, :user_id, :title, :body, 0, 0, 0, NOW())");
            $insertStmt->execute([
                ':category_id' => $selectedCatId,
                ':user_id'     => current_user_id(),
                ':title'       => $title,
                ':body'        => $body
            ]);

            $newId = (int) $pdo->lastInsertId();
            set_flash('success', 'Your thread has been posted successfully!');
            redirect('public/thread.php?id=' . $newId);

        } catch (PDOException $e) {
            $errors[] = 'Failed to create thread. Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="card form-card" style="max-width: 800px;">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.35rem;">Start a New Discussion</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Ask a coding question, share a project, or start a technical discussion.</p>
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

    <form action="<?= url('public/new_thread.php') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="category_id">Select Category *</label>
            <select name="category_id" id="category_id" class="form-control" required>
                <option value="">-- Choose a Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (int) $cat['id'] === $selectedCatId ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="title">Thread Title *</label>
            <input type="text" name="title" id="title" class="form-control" placeholder="e.g. How does TiDB achieve distributed transactions without two-phase locking bottlenecks?" value="<?= e($title) ?>" required>
            <div class="form-text">Keep your title descriptive and concise.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="body">Question or Topic Body *</label>
            <textarea name="body" id="body" class="form-control" style="min-height: 220px;" placeholder="Describe your question or discussion in detail. Tip: Wrap code snippets with ```php and ``` for syntax styling." required><?= e($body) ?></textarea>
            <div class="form-text">💡 Tip: You can enclose code in triple backticks (```python ... ```) to render formatted code blocks.</div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= url('public/index.php') ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Publish Discussion</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
