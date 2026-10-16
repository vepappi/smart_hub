<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

require_role('student');

$userId = (int) $_SESSION['user']['id'];

$studentStmt = $pdo->prepare(
    'SELECT student_id, first_name, last_name
     FROM STUDENTS
     WHERE user_id = ?
     LIMIT 1'
);

$studentStmt->execute([$userId]);
$student = $studentStmt->fetch();

if (!$student) {
    exit('Student profile not found.');
}

$averageStmt = $pdo->prepare(
    'SELECT AVG(marks)
     FROM RESULTS
     WHERE student_id = ?'
);

$averageStmt->execute([(int) $student['student_id']]);
$average = $averageStmt->fetchColumn();

$earnedBadge = null;

if ($average !== false && $average !== null) {
    $average = (float) $average;

    if ($average >= 85) {
        $badgeName = 'High Achiever';
    } elseif ($average >= 70) {
        $badgeName = 'Strong Performer';
    } elseif ($average >= 50) {
        $badgeName = 'Rising Star';
    } else {
        $badgeName = 'First Steps';
    }

    $badgeStmt = $pdo->prepare(
        'SELECT badges_id, badge_name, description, icon
         FROM BADGES
         WHERE badge_name = ?
         LIMIT 1'
    );

    $badgeStmt->execute([$badgeName]);
    $earnedBadge = $badgeStmt->fetch();

    if ($earnedBadge) {
        $checkStmt = $pdo->prepare(
            'SELECT student_badges_id
             FROM STUDENT_BADGES
             WHERE student_id = ? AND badge_id = ?
             LIMIT 1'
        );

        $checkStmt->execute([
            (int) $student['student_id'],
            (int) $earnedBadge['badges_id']
        ]);

        if (!$checkStmt->fetch()) {
            $insertStmt = $pdo->prepare(
                'INSERT INTO STUDENT_BADGES (student_id, badge_id)
                 VALUES (?, ?)'
            );

            $insertStmt->execute([
                (int) $student['student_id'],
                (int) $earnedBadge['badges_id']
            ]);
        }
    }
}

$badgesStmt = $pdo->prepare(
    'SELECT
        b.badges_id,
        b.badge_name,
        b.description,
        b.icon,
        sb.earned_at
     FROM STUDENT_BADGES sb
     INNER JOIN BADGES b ON b.badges_id = sb.badge_id
     WHERE sb.student_id = ?
     ORDER BY sb.earned_at DESC'
);

$badgesStmt->execute([(int) $student['student_id']]);
$badges = $badgesStmt->fetchAll();

$displayName = trim(
    $student['first_name'] . ' ' . $student['last_name']
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Badges | Smart Hub</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1e293b;
        }

        header {
            background: #101b3d;
            color: white;
            padding: 22px 7%;
        }

        header h1 {
            margin: 0;
        }

        header p {
            color: #cbd5e1;
        }

        main {
            max-width: 1000px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .summary,
        .badge-card {
            background: white;
            padding: 24px;
            border-radius: 14px;
            margin-bottom: 22px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.06);
        }

        .summary h2 {
            margin-top: 0;
        }

        .average {
            font-size: 30px;
            font-weight: bold;
            color: #3157d5;
        }

        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
        }

        .badge-card {
            text-align: center;
        }

        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .badge-card h3 {
            margin-bottom: 10px;
        }

        .badge-card p {
            color: #64748b;
            line-height: 1.6;
        }

        .earned-date {
            font-size: 13px;
        }

        .empty {
            background: white;
            padding: 25px;
            border-radius: 14px;
            color: #64748b;
        }

        .button {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 18px;
            background: #3157d5;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<header>
    <h1>Smart Hub Achievements</h1>
    <p>Your academic progress deserves recognition.</p>
</header>

<main>

    <section class="summary">
        <h2>Hello, <?= htmlspecialchars($displayName) ?>!</h2>

        <?php if ($average !== false && $average !== null): ?>
            <p>Your current overall average is:</p>

            <div class="average">
                <?= number_format((float) $average, 1) ?>%
            </div>

            <p>
                Keep learning, practising and improving your performance.
            </p>
        <?php else: ?>
            <p>
                You have no recorded results yet. Your achievements will
                appear here once results are available.
            </p>
        <?php endif; ?>
    </section>

    <h2>My Earned Badges</h2>

    <?php if (empty($badges)): ?>

        <div class="empty">
            No badges earned yet. Keep working on your courses and check
            this page again when results are recorded.
        </div>

    <?php else: ?>

        <div class="badge-grid">

            <?php foreach ($badges as $badge): ?>

                <article class="badge-card">

                    <div class="icon">
                        <?= htmlspecialchars($badge['icon']) ?>
                    </div>

                    <h3>
                        <?= htmlspecialchars($badge['badge_name']) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars($badge['description']) ?>
                    </p>

                    <p class="earned-date">
                        Earned:
                        <?= htmlspecialchars($badge['earned_at']) ?>
                    </p>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <a class="button" href="/smart_hub/public/dashboard.php">
        Back to Dashboard
    </a>

</main>

</body>
</html>