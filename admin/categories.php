<?php
/**
 * =====================================================================
 * Administrator - Category Management (CRUD)
 * =====================================================================
 * Allows administrators to:
 * - Create new technical categories
 * - Edit category name and description
 * - Delete categories (cascades cleanly through database foreign keys)
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_db_connection();

// Determine if editing an existing category
$editId = (int) ($_GET['edit'] ?? 0);
$editCategory = null;
if ($editId > 0) {
    $eStmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $eStmt->execute([':id' => $editId]);
    $editCategory = $eStmt->fetch();
}

$errors = [];

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $action = $_POST['action'] ?? '';

    // Create Category
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Category name must be between 2 and 100 characters.';
        }
        if (empty($description)) {
            $errors[] = 'Category description is required.';
        }

        if (empty($errors)) {
            try {
                $ins = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :desc)");
                $ins->execute([':name' => $name, ':desc' => $description]);
                set_flash('success', "Category '{$name}' created successfully.");
                redirect('admin/categories.php');
            } catch (PDOException $e) {
                $errors[] = 'Category with this name already exists or database error occurred.';
            }
        }
    }

    // Update Category
    elseif ($action === 'update') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Category name must be between 2 and 100 characters.';
        }
        if (empty($description)) {
            $errors[] = 'Category description is required.';
        }

        if (empty($errors)) {
            try {
                $up = $pdo->prepare("UPDATE categories SET name = :name, description = :desc WHERE id = :id");
                $up->execute([':name' => $name, ':desc' => $description, ':id' => $catId]);
                set_flash('success', "Category '{$name}' updated successfully.");
                redirect('admin/categories.php');
            } catch (PDOException $e) {
                $errors[] = 'Category name must be unique.';
            }
        }
    }

    // Delete Category
    elseif ($action === 'delete') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        try {
            $del = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $del->execute([':id' => $catId]);
            set_flash('success', 'Category and all associated discussions have been deleted.');
            redirect('admin/categories.php');
        } catch (PDOException $e) {
            $errors[] = 'Failed to delete category.';
        }
    }
}

// Fetch categories with thread counts
$categories = get_all_categories($pdo);

$pageTitle = 'Manage Categories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-header">
    <div class="admin-title-group">
        <h1>Category Management</h1>
        <p>Create, update, and organize technical categories and discussion domains.</p>
    </div>
    <div class="admin-nav-tabs">
        <a href="<?= url('admin/dashboard.php') ?>" class="admin-tab">
            <span>📊</span> Overview
        </a>
        <a href="<?= url('admin/users.php') ?>" class="admin-tab">
            <span>👥</span> Manage Users
        </a>
        <a href="<?= url('admin/categories.php') ?>" class="admin-tab active">
            <span>📁</span> Categories
        </a>
    </div>
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

<div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;">
    <!-- Categories List Table -->
    <div class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">Existing Categories</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Threads</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <strong><a href="<?= url('public/category.php?id=' . $cat['id']) ?>" style="color:var(--text-primary); text-decoration:none;"><?= e($cat['name']) ?></a></strong>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.85rem;">
                                <?= e(truncate($cat['description'], 70)) ?>
                            </td>
                            <td><span class="badge-count"><?= (int) $cat['thread_count'] ?></span></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.4rem;">
                                    <a href="<?= url('admin/categories.php?edit=' . $cat['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <form action="<?= url('admin/categories.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Deleting this category will permanently remove all threads inside it. Proceed?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Create / Edit Form Card -->
    <div class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">
            <?= $editCategory ? 'Edit Category' : 'Create New Category' ?>
        </h2>

        <form action="<?= url('admin/categories.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
            <?php if ($editCategory): ?>
                <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="cat_name">Category Name *</label>
                <input type="text" id="cat_name" name="name" class="form-control" placeholder="e.g. Cloud Computing" value="<?= e($editCategory['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="cat_desc">Description *</label>
                <textarea id="cat_desc" name="description" class="form-control" style="min-height: 100px;" placeholder="Brief summary of topics covered in this category..." required><?= e($editCategory['description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <?= $editCategory ? 'Update Category' : 'Save Category' ?>
                </button>
                <?php if ($editCategory): ?>
                    <a href="<?= url('admin/categories.php') ?>" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
