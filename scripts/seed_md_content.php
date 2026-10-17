<?php
/**
 * ThredIt - Seed Content Importer
 * Parses thredit_seed_content.md and populates users, threads, replies, and votes.
 * Ensures 100% Indian names for all users across the platform.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

echo "=== Starting ThredIt Content Seeder ===\n";

$pdo = get_db_connection();

// ---------------------------------------------------------------------
// 1. Update Existing Users (id 1..4) to Indian Names
// ---------------------------------------------------------------------
$existingUpdates = [
    1 => ['name' => 'Dark', 'email' => 'dark@example.com'],
    2 => ['name' => 'Deepak Verma', 'email' => 'deepak@example.com'],
    3 => ['name' => 'Ananya Sen', 'email' => 'ananya@example.com'],
    4 => ['name' => 'Bhavin Patel', 'email' => 'bhavin@example.com'],
];

foreach ($existingUpdates as $id => $u) {
    $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email WHERE id = :id");
    $stmt->execute(['name' => $u['name'], 'email' => $u['email'], 'id' => $id]);
}
echo "[✔] Existing default accounts updated to Indian names.\n";

// ---------------------------------------------------------------------
// 2. Parse and Seed Users from thredit_seed_content.md & Extra Indian Users
// ---------------------------------------------------------------------
$mdFile = __DIR__ . '/../thredit_seed_content.md';
if (!file_exists($mdFile)) {
    die("Error: {$mdFile} not found.\n");
}
$content = file_get_contents($mdFile);

// Parse the === USERS === table
$newUsers = [];
if (preg_match('/=== USERS ===.*?(handle \| display name \| joined_days_ago\s*\n)(.*?)(?==== CATEGORY:)/s', $content, $matches)) {
    $userLines = explode("\n", trim($matches[2]));
    foreach ($userLines as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) === 3 && !empty($parts[0])) {
            $newUsers[$parts[0]] = [
                'name' => $parts[1],
                'joined_days_ago' => (int) $parts[2]
            ];
        }
    }
}

// Extra Indian users to enrich discussion pool & voting activity
$extraUsers = [
    'siddharth' => ['name' => 'Siddharth Roy', 'joined_days_ago' => 65],
    'diya'      => ['name' => 'Diya Sengupta', 'joined_days_ago' => 74],
    'kavya'     => ['name' => 'Kavya Pillai', 'joined_days_ago' => 55],
    'aditya'    => ['name' => 'Aditya Deshmukh', 'joined_days_ago' => 82],
    'tanya'     => ['name' => 'Tanya Bakshi', 'joined_days_ago' => 48],
    'rahul'     => ['name' => 'Rahul Nambiar', 'joined_days_ago' => 61],
    'sakshi'    => ['name' => 'Sakshi Jain', 'joined_days_ago' => 59],
    'ayush'     => ['name' => 'Ayush Saxena', 'joined_days_ago' => 77],
    'shruti'    => ['name' => 'Shruti Kulkarni', 'joined_days_ago' => 43],
    'kartik'    => ['name' => 'Kartik Singhal', 'joined_days_ago' => 51],
    'swati'     => ['name' => 'Swati Mishra', 'joined_days_ago' => 69],
    'pranav'    => ['name' => 'Pranav Bhat', 'joined_days_ago' => 37],
    'nandini'   => ['name' => 'Nandini Chawla', 'joined_days_ago' => 44],
    'varun'     => ['name' => 'Varun Hegde', 'joined_days_ago' => 84],
    'anushka'   => ['name' => 'Anushka Rathi', 'joined_days_ago' => 39],
    'chaitanya' => ['name' => 'Chaitanya Joshi', 'joined_days_ago' => 53],
];

foreach ($extraUsers as $h => $u) {
    if (!isset($newUsers[$h])) {
        $newUsers[$h] = $u;
    }
}

$defaultPasswordHash = password_hash('User@123', PASSWORD_BCRYPT);

$userInsertStmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, role, is_banned, created_at)
    VALUES (:name, :email, :password_hash, 'user', 0, :created_at)
    ON DUPLICATE KEY UPDATE name = VALUES(name)
");

$now = time();
foreach ($newUsers as $handle => $u) {
    $email = $handle . '@example.com';
    $createdAt = date('Y-m-d H:i:s', $now - ($u['joined_days_ago'] * 86400));
    $userInsertStmt->execute([
        'name' => $u['name'],
        'email' => $email,
        'password_hash' => $defaultPasswordHash,
        'created_at' => $createdAt
    ]);
}
echo "[✔] Inserted/verified " . count($newUsers) . " Indian users.\n";

// Map handles to user IDs
$userMap = [
    'admin' => 1,
    'dan'   => 2,
    'alice' => 3,
    'bob'   => 4,
];

$allUsers = $pdo->query("SELECT id, email FROM users")->fetchAll();
foreach ($allUsers as $row) {
    $handle = explode('@', $row['email'])[0];
    $userMap[$handle] = (int) $row['id'];
}

// Map category names to IDs
$catRows = $pdo->query("SELECT id, name FROM categories")->fetchAll();
$categoryMap = [];
foreach ($catRows as $c) {
    $categoryMap[$c['name']] = (int) $c['id'];
}

// ---------------------------------------------------------------------
// 3. Helper to parse time offset (e.g. "2h", "3d", "30m", "45s")
// ---------------------------------------------------------------------
function parse_time_offset(string $afterStr): int {
    $afterStr = trim($afterStr);
    if (preg_match('/^(\d+)\s*([smhdw])$/i', $afterStr, $m)) {
        $val = (int) $m[1];
        $unit = strtolower($m[2]);
        return match ($unit) {
            's' => $val,
            'm' => $val * 60,
            'h' => $val * 3600,
            'd' => $val * 86400,
            'w' => $val * 604800,
            default => $val * 3600,
        };
    }
    return 3600;
}

// ---------------------------------------------------------------------
// 4. Parse Threads & Replies from Markdown
// ---------------------------------------------------------------------
// Split content into thread chunks
$threadChunks = preg_split('/(?==== THREAD \d+ ===)/', $content);

$threadInsertStmt = $pdo->prepare("
    INSERT INTO threads (category_id, user_id, title, body, is_pinned, is_locked, views, created_at)
    VALUES (:category_id, :user_id, :title, :body, :is_pinned, :is_locked, :views, :created_at)
");

$threadCheckStmt = $pdo->prepare("SELECT id FROM threads WHERE title = :title LIMIT 1");

$replyInsertStmt = $pdo->prepare("
    INSERT INTO replies (thread_id, user_id, body, is_best, created_at)
    VALUES (:thread_id, :user_id, :body, :is_best, :created_at)
");

$replyCheckStmt = $pdo->prepare("SELECT id FROM replies WHERE thread_id = :thread_id AND user_id = :user_id AND created_at = :created_at LIMIT 1");

$voteInsertStmt = $pdo->prepare("
    INSERT IGNORE INTO votes (user_id, target_type, target_id, value, created_at)
    VALUES (:user_id, :target_type, :target_id, :value, :created_at)
");

$allUserIds = array_values(array_unique($userMap));
$threadsInserted = 0;
$repliesInserted = 0;
$votesInserted = 0;

foreach ($threadChunks as $chunk) {
    $chunk = trim($chunk);
    if (!str_starts_with($chunk, '=== THREAD')) {
        continue;
    }

    // Split thread chunk into thread main and replies
    $replyParts = explode('--- reply ---', $chunk);
    $threadHeaderAndBody = trim($replyParts[0]);

    // Parse thread metadata and body
    $lines = explode("\n", $threadHeaderAndBody);
    $categoryName = '';
    $authorHandle = '';
    $ageDays = 0;
    $views = 0;
    $votesTier = 'warm';
    $isPinned = 0;
    $isLocked = 0;
    $title = '';
    $bodyLines = [];
    $parsingBody = false;

    foreach ($lines as $line) {
        if ($parsingBody) {
            $bodyLines[] = $line;
            continue;
        }

        $trimmed = trim($line);
        if (str_starts_with($trimmed, '=== THREAD')) {
            continue;
        } elseif (preg_match('/^category:\s*(.+)$/i', $trimmed, $m)) {
            $categoryName = trim($m[1]);
        } elseif (preg_match('/^author:\s*([a-zA-Z0-9_-]+)/i', $trimmed, $m)) {
            $authorHandle = trim($m[1]);
        } elseif (preg_match('/^age_days:\s*(\d+)/i', $trimmed, $m)) {
            $ageDays = (int) $m[1];
        } elseif (preg_match('/^views:\s*(\d+)/i', $trimmed, $m)) {
            $views = (int) $m[1];
        } elseif (preg_match('/^votes:\s*(hot|warm|low)/i', $trimmed, $m)) {
            $votesTier = strtolower($m[1]);
        } elseif (preg_match('/^pinned:\s*yes/i', $trimmed)) {
            $isPinned = 1;
        } elseif (preg_match('/^locked:\s*yes/i', $trimmed)) {
            $isLocked = 1;
        } elseif (preg_match('/^title:\s*(.+)$/i', $trimmed, $m)) {
            $title = trim($m[1]);
        } elseif ($trimmed === 'body:') {
            $parsingBody = true;
        }
    }

    if (empty($title)) {
        continue;
    }

    $categoryId = $categoryMap[$categoryName] ?? 1;
    $authorId = $userMap[$authorHandle] ?? 1;
    $threadTime = $now - ($ageDays * 86400);
    $threadCreatedAt = date('Y-m-d H:i:s', $threadTime);
    $threadBody = trim(implode("\n", $bodyLines));

    // Check if thread already exists
    $threadCheckStmt->execute(['title' => $title]);
    $existingThreadId = $threadCheckStmt->fetchColumn();

    if ($existingThreadId) {
        $threadId = (int) $existingThreadId;
    } else {
        $threadInsertStmt->execute([
            'category_id' => $categoryId,
            'user_id' => $authorId,
            'title' => $title,
            'body' => $threadBody,
            'is_pinned' => $isPinned,
            'is_locked' => $isLocked,
            'views' => $views,
            'created_at' => $threadCreatedAt,
        ]);
        $threadId = (int) $pdo->lastInsertId();
        $threadsInserted++;
    }

    // Seed realistic thread votes according to tier
    $voteCount = match ($votesTier) {
        'hot' => rand(18, 30),
        'warm' => rand(7, 16),
        'low' => rand(2, 5),
        default => rand(5, 12),
    };

    $potentialVoters = array_diff($allUserIds, [$authorId]);
    shuffle($potentialVoters);
    $voters = array_slice($potentialVoters, 0, min($voteCount, count($potentialVoters)));

    foreach ($voters as $voterId) {
        $voteTime = date('Y-m-d H:i:s', $threadTime + rand(60, min(86400 * 3, $now - $threadTime)));
        $voteInsertStmt->execute([
            'user_id' => $voterId,
            'target_type' => 'thread',
            'target_id' => $threadId,
            'value' => 1,
            'created_at' => $voteTime,
        ]);
        $votesInserted++;
    }

    // Parse replies
    for ($i = 1; $i < count($replyParts); $i++) {
        $replyChunk = trim($replyParts[$i]);
        $rLines = explode("\n", $replyChunk);

        $rAuthorHandle = '';
        $rAfter = '2h';
        $rIsBest = 0;
        $rBodyLines = [];
        $rParsingBody = false;

        foreach ($rLines as $rLine) {
            if ($rParsingBody) {
                $rBodyLines[] = $rLine;
                continue;
            }

            $rTrimmed = trim($rLine);
            if (preg_match('/^author:\s*([a-zA-Z0-9_-]+)/i', $rTrimmed, $m)) {
                $rAuthorHandle = trim($m[1]);
            } elseif (preg_match('/^after:\s*(.+)$/i', $rTrimmed, $m)) {
                $rAfter = trim($m[1]);
            } elseif (preg_match('/^best:\s*yes/i', $rTrimmed)) {
                $rIsBest = 1;
            } elseif ($rTrimmed === 'body:') {
                $rParsingBody = true;
            }
        }

        $rAuthorId = $userMap[$rAuthorHandle] ?? 1;
        $offsetSecs = parse_time_offset($rAfter);
        $replyTime = min($now, $threadTime + $offsetSecs);
        $replyCreatedAt = date('Y-m-d H:i:s', $replyTime);
        $replyBody = trim(implode("\n", $rBodyLines));

        $replyCheckStmt->execute([
            'thread_id' => $threadId,
            'user_id' => $rAuthorId,
            'created_at' => $replyCreatedAt,
        ]);

        if (!$replyCheckStmt->fetchColumn()) {
            $replyInsertStmt->execute([
                'thread_id' => $threadId,
                'user_id' => $rAuthorId,
                'body' => $replyBody,
                'is_best' => $rIsBest,
                'created_at' => $replyCreatedAt,
            ]);
            $replyId = (int) $pdo->lastInsertId();
            $repliesInserted++;

            // Upvotes for replies (especially Best Answers)
            $rVoteCount = $rIsBest ? rand(4, 9) : rand(1, 3);
            $rPotentialVoters = array_diff($allUserIds, [$rAuthorId]);
            shuffle($rPotentialVoters);
            $rVoters = array_slice($rPotentialVoters, 0, min($rVoteCount, count($rPotentialVoters)));

            foreach ($rVoters as $voterId) {
                $voteInsertStmt->execute([
                    'user_id' => $voterId,
                    'target_type' => 'reply',
                    'target_id' => $replyId,
                    'value' => 1,
                    'created_at' => date('Y-m-d H:i:s', min($now, $replyTime + rand(60, 7200))),
                ]);
                $votesInserted++;
            }
        }
    }
}

echo "=== Seeding Complete ===\n";
echo "[✔] New Discussions Added: {$threadsInserted}\n";
echo "[✔] New Replies Added:     {$repliesInserted}\n";
echo "[✔] Upvotes Distributed:   {$votesInserted}\n";

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalThreads = (int) $pdo->query("SELECT COUNT(*) FROM threads")->fetchColumn();
$totalReplies = (int) $pdo->query("SELECT COUNT(*) FROM replies")->fetchColumn();
$totalVotes = (int) $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();

echo "\nPlatform Metrics Now in Database:\n";
echo " - Total Users:       {$totalUsers}\n";
echo " - Total Discussions: {$totalThreads}\n";
echo " - Total Replies:     {$totalReplies}\n";
echo " - Total Votes:       {$totalVotes}\n";
