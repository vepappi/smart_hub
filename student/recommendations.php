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

$studentId = (int) $student['student_id'];

$resultsStmt = $pdo->prepare(
    'SELECT
        c.course_id,
        c.course_code,
        c.course_name,
        AVG(r.marks) AS average_marks
     FROM RESULTS r
     INNER JOIN COURSES c ON c.course_id = r.course_id
     WHERE r.student_id = ?
     GROUP BY c.course_id, c.course_code, c.course_name
     ORDER BY average_marks ASC'
);

$resultsStmt->execute([$studentId]);
$results = $resultsStmt->fetchAll();

$saveStmt = $pdo->prepare(
    'INSERT INTO STUDY_RECOMMENDATIN
        (student_id, course_id, performance_level, recommendation, study_hours)
     VALUES (?, ?, ?, ?, ?)'
);

$updateStmt = $pdo->prepare(
    'UPDATE STUDY_RECOMMENDATIN
     SET performance_level = ?,
         recommendation = ?,
         study_hours = ?,
         created_at = CURRENT_TIMESTAMP
     WHERE recommendation_id = ? AND student_id = ?'
);

$existingStmt = $pdo->prepare(
    'SELECT recommendation_id
     FROM STUDY_RECOMMENDATIN
     WHERE student_id = ? AND course_id = ?
     ORDER BY recommendation_id DESC
     LIMIT 1'
);

$pdo->beginTransaction();

try {
    foreach ($results as $result) {
        $average = (float) $result['average_marks'];

        if ($average < 50) {
            $level = 'Weak';
            $hours = 5.0;
            $recommendation = 'Prioritise this course. Review difficult topics, practise past questions, and seek help from your lecturer. Aim for short, focused study sessions throughout the week.';
        } elseif ($average < 70) {
            $level = 'Needs Improvement';
            $hours = 3.0;
            $recommendation = 'Revise your lecture notes, practise questions regularly, and identify the topics where you lose marks. Review your progress each week.';
        } else {
            $level = 'Strong';
            $hours = 1.5;
            $recommendation = 'Maintain your progress through regular revision, practice questions, and review of challenging topics. Continue strengthening your understanding.';
        }

        $existingStmt->execute([$studentId, (int) $result['course_id']]);
        $existing = $existingStmt->fetchColumn();

        if ($existing !== false) {
            $updateStmt->execute([
                $level,
                $recommendation,
                $hours,
                (int) $existing,
                $studentId
            ]);
        } else {
            $saveStmt->execute([
                $studentId,
                (int) $result['course_id'],
                $level,
                $recommendation,
                $hours
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

$recommendationsStmt = $pdo->prepare(
    'SELECT
        sr.performance_level,
        sr.recommendation,
        sr.study_hours,
        sr.created_at,
        c.course_code,
        c.course_name
     FROM STUDY_RECOMMENDATIN sr
     INNER JOIN COURSES c ON c.course_id = sr.course_id
     WHERE sr.student_id = ?
     ORDER BY sr.study_hours DESC, c.course_name ASC'
);

$recommendationsStmt->execute([$studentId]);
$recommendations = $recommendationsStmt->fetchAll();

$displayName = trim(
    $student['first_name'] . ' ' . $student['last_name']
);

$totalHours = 0;

foreach ($recommendations as $item) {
    $totalHours += (float) $item['study_hours'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Recommendations | Smart Hub</title>

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
            max-width: 1050px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .summary,
        .recommendation-card {
            background: white;
            padding: 24px;
            margin-bottom: 22px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.06);
        }

        .summary h2 {
            margin-top: 0;
        }

        .hours {
            font-size: 30px;
            font-weight: bold;
            color: #3157d5;
        }

        .recommendation-card h3 {
            margin-top: 0;
        }

        .meta {
            color: #64748b;
            font-size: 14px;
            line-height: 1.8;
        }

        .level {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #e0e7ff;
            color: #3730a3;
        }

        .recommendation-text {
            line-height: 1.7;
        }

        .empty {
            background: white;
            padding: 24px;
            border-radius: 14px;
            color: #64748b;
        }

        .button {
            display: inline-block;
            margin-top: 12px;
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
    <h1>Smart Hub Study Planner</h1>
    <p>Personalised recommendations based on your academic results.</p>
</header>

<main>

    <section class="summary">
        <h2>Hello, <?= htmlspecialchars($displayName) ?>!</h2>

        <p>
            Use these recommendations to organise your revision and focus
            on the courses that need the most attention.
        </p>

        <?php if (!empty($recommendations)): ?>
            <p>Suggested weekly study hours across your recorded courses:</p>

            <div class="hours">
                <?= number_format($totalHours, 1) ?> hours
            </div>
        <?php endif; ?>
    </section>

    <h2>Your Study Recommendations</h2>

    <?php if (empty($recommendations)): ?>

        <div class="empty">
            No study recommendations are available yet. Recommendations
            will appear when your academic results have been recorded.
        </div>

    <?php else: ?>

        <?php foreach ($recommendations as $item): ?>

            <article class="recommendation-card">

                <h3>
                    <?= htmlspecialchars($item['course_name']) ?>
                </h3>

                <div class="meta">
                    Course code:
                    <?= htmlspecialchars($item['course_code']) ?>
                </div>

                <p>
                    <span class="level">
                        <?= htmlspecialchars($item['performance_level']) ?>
                    </span>
                </p>

                <p class="meta">
                    Recommended study time:
                    <strong>
                        <?= number_format((float) $item['study_hours'], 1) ?>
                        hours per week
                    </strong>
                </p>

                <p class="recommendation-text">
                    <?= htmlspecialchars($item['recommendation']) ?>
                </p>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

    <a class="button" href="/smart_hub/student/analytics.php">
        View Academic Analytics
    </a>

    <a class="button" href="/smart_hub/public/dashboard.php">
        Back to Dashboard
    </a>

</main>

</body>
</html>