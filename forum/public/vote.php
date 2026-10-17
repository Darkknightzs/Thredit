<?php
/**
 * =====================================================================
 * Upvote & Downvote Controller (AJAX & Form Fallback)
 * =====================================================================
 * Handles voting on threads and replies:
 * - One vote per user constraint (enforced in DB UNIQUE KEY and PHP logic)
 * - Toggling: clicking the same vote removes it (resets to 0)
 * - Switching: clicking opposite vote transitions -1 <-> +1
 * - Returns JSON for AJAX fetch requests or redirects for standard form posts
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/db.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
       || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

// Check authentication
if (!is_logged_in()) {
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => false,
            'error'    => 'Please sign in to vote.',
            'redirect' => url('public/login.php')
        ]);
        exit;
    }
    set_flash('warning', 'Please sign in to vote.');
    redirect('public/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('public/index.php');
}

// Validate CSRF token
if (!verify_csrf_token()) {
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF security token.']);
        exit;
    }
    set_flash('danger', 'Security validation failed.');
    redirect('public/index.php');
}

$targetType = $_POST['target_type'] ?? '';
$targetId = (int) ($_POST['target_id'] ?? 0);
$value = (int) ($_POST['value'] ?? 0);
$userId = current_user_id();

if (!in_array($targetType, ['thread', 'reply'], true) || $targetId <= 0 || !in_array($value, [1, -1], true)) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid voting payload.']);
        exit;
    }
    redirect('public/index.php');
}

$pdo = get_db_connection();

// Verify target exists
if ($targetType === 'thread') {
    $check = $pdo->prepare("SELECT id FROM threads WHERE id = :id LIMIT 1");
} else {
    $check = $pdo->prepare("SELECT id FROM replies WHERE id = :id LIMIT 1");
}
$check->execute([':id' => $targetId]);
if (!$check->fetch()) {
    if ($isAjax) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Target not found.']);
        exit;
    }
    redirect('public/index.php');
}

// Check existing vote
$voteStmt = $pdo->prepare("SELECT id, value FROM votes WHERE user_id = :uid AND target_type = :type AND target_id = :tid LIMIT 1");
$voteStmt->execute([':uid' => $userId, ':type' => $targetType, ':tid' => $targetId]);
$existingVote = $voteStmt->fetch();

$userVoteOutcome = 0;

if ($existingVote) {
    if ((int) $existingVote['value'] === $value) {
        // Toggle OFF (remove vote)
        $pdo->prepare("DELETE FROM votes WHERE id = :id")->execute([':id' => $existingVote['id']]);
        $userVoteOutcome = 0;
    } else {
        // Switch vote (-1 -> +1 or +1 -> -1)
        $pdo->prepare("UPDATE votes SET value = :val WHERE id = :id")->execute([':val' => $value, ':id' => $existingVote['id']]);
        $userVoteOutcome = $value;
    }
} else {
    // Insert new vote
    $pdo->prepare("INSERT INTO votes (user_id, target_type, target_id, value, created_at)
                   VALUES (:uid, :type, :tid, :val, NOW())")
        ->execute([':uid' => $userId, ':type' => $targetType, ':tid' => $targetId, ':val' => $value]);
    $userVoteOutcome = $value;
}

// Compute new aggregate vote score
$scoreStmt = $pdo->prepare("SELECT COALESCE(SUM(value), 0) AS new_score FROM votes WHERE target_type = :type AND target_id = :tid");
$scoreStmt->execute([':type' => $targetType, ':tid' => $targetId]);
$newScore = (int) $scoreStmt->fetchColumn();

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'   => true,
        'new_score' => $newScore,
        'user_vote' => $userVoteOutcome
    ]);
    exit;
}

// For non-AJAX form fallback, redirect to a validated same-site relative URL
// instead of the raw HTTP_REFERER (which could be spoofed for open redirects)
$fallbackUrl = $_POST['redirect_url'] ?? '';

if (is_safe_redirect($fallbackUrl)) {
    // Safe relative redirect_url
    header("Location: {$fallbackUrl}");
    exit;
}

// Fallback: check HTTP_REFERER but ensure it belongs strictly to the current host
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer)) {
    $parsed = parse_url($referer);
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!empty($parsed['host']) && $parsed['host'] === $currentHost) {
        $safePath = ($parsed['path'] ?? '/') . (!empty($parsed['query']) ? '?' . $parsed['query'] : '');
        if (is_safe_redirect($safePath)) {
            header("Location: {$safePath}");
            exit;
        }
    }
}

redirect('public/index.php');
