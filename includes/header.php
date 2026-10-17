<?php
/**
 * Shared Page Header & Navigation
 * Natural, clean GitHub/StackOverflow style layout
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

// Check if current user was banned while active (belt-and-suspenders with require_login)
if (is_logged_in()) {
    try {
        $pdoSync = get_db_connection();
        sync_user_status($pdoSync);
    } catch (Exception $e) {
        // Connection handled in db.php
    }
}

$appName = env('APP_NAME', 'ThredIt');
$pageTitle = isset($pageTitle) ? e($pageTitle) . ' · ' . e($appName) : e($appName) . ' · Student Developer Community';
$user = current_user();

// Determine if current page is an auth page (login or register)
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAuthPage = in_array($currentScript, ['login.php', 'register.php'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= $pageTitle ?></title>

    <!-- Prevents flash of unstyled theme -->
    <script>
        (function() {
            var theme = localStorage.getItem('forum_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body>

    <header class="navbar">
        <div class="nav-container">
            <!-- Mobile Menu Toggle & Brand Logo -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" id="mobile-menu-toggle" class="btn-icon mobile-only" title="Open Categories" aria-label="Toggle Navigation">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <a href="<?= url('public/index.php') ?>" class="brand-logo" aria-label="ThredIt Home">
                    <img src="<?= asset_url('images/Thredit-light.png') ?>" alt="ThredIt" class="brand-logo-img logo-light">
                    <img src="<?= asset_url('images/Thredit-dark.png') ?>" alt="ThredIt" class="brand-logo-img logo-dark">
                </a>
            </div>

            <!-- Search Bar (Reddit Pill Design) -->
            <?php if (!$isAuthPage): ?>
            <div class="nav-search">
                <form action="<?= url('public/search.php') ?>" method="GET" class="search-form">
                    <svg class="search-svg-icon" viewBox="0 0 16 16" width="16" height="16">
                        <path d="M10.68 11.74a6 6 0 0 1-7.922-8.982 6 6 0 0 1 8.982 7.922l3.04 3.04a.749.749 0 0 1-.326 1.275.749.749 0 0 1-.734-.215ZM11.5 7a4.499 4.499 0 1 0-8.997 0A4.499 4.499 0 0 0 11.5 7Z"></path>
                    </svg>
                    <input type="text" name="q" class="search-input" placeholder="Search ThredIt discussions, topics, code..." value="<?= e($_GET['q'] ?? '') ?>" required>
                </form>
            </div>
            <?php endif; ?>

            <!-- Header Actions -->
            <div class="nav-actions">
                <!-- Theme toggle -->
                <button type="button" id="theme-toggle" class="btn-icon" title="Toggle theme" aria-label="Toggle theme">
                    <svg id="theme-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>

                <?php if ($user): ?>
                    <!-- Reddit "+ Create" button -->
                    <a href="<?= url('public/new_thread.php') ?>" class="btn btn-primary btn-sm btn-create-post">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Create</span>
                    </a>

                    <!-- User Profile Chip -->
                    <a href="<?= url('public/profile.php') ?>" class="user-profile-chip" title="View Profile">
                        <span class="user-avatar-tiny"><?= strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8')) ?></span>
                        <span class="user-name-label"><?= e($user['name']) ?></span>
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="badge badge-role badge-role-admin">Admin</span>
                        <?php elseif ($user['role'] === 'moderator'): ?>
                            <span class="badge badge-role badge-role-mod">Mod</span>
                        <?php endif; ?>
                    </a>

                    <?php if (is_admin()): ?>
                        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-secondary btn-sm" title="Admin Dashboard">
                            ⚙️ Dashboard
                        </a>
                    <?php endif; ?>

                    <a href="<?= url('public/logout.php') ?>" class="btn btn-secondary btn-sm" title="Log out" aria-label="Log out">
                        Log Out
                    </a>

                <?php elseif (!$isAuthPage): ?>
                    <a href="<?= url('public/login.php') ?>" class="btn btn-secondary btn-sm">
                        Log In
                    </a>
                    <a href="<?= url('public/register.php') ?>" class="btn btn-primary btn-sm">
                        Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Modern Floating Toast Notification Container (Fixed Top-Right) -->
    <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true">
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="toast-item toast-<?= e($flash['type']) ?>" data-auto-dismiss="5500" role="alert">
                <div class="toast-icon">
                    <?php if ($flash['type'] === 'success'): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    <?php elseif ($flash['type'] === 'danger'): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?php elseif ($flash['type'] === 'warning'): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <?php else: ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <?php endif; ?>
                </div>
                <div class="toast-body">
                    <div class="toast-title"><?= ucfirst(e($flash['type'])) ?></div>
                    <div class="toast-message"><?= e($flash['message']) ?></div>
                </div>
                <button type="button" class="toast-close" onclick="dismissToast(this.closest('.toast-item'))" aria-label="Close notification">&times;</button>
                <div class="toast-progress">
                    <div class="toast-progress-bar"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <main class="main-content">
        <div class="container">
