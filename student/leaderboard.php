<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

require_role('student');

$userId = (int) $_SESSION['user']['id'];

$studentStmt = $pdo->prepare(
    'SELECT student_id
     FROM STUDENTS
     WHERE user_id = ?
     LIMIT 1'
);

$studentStmt->execute([$userId]);
$currentStudent = $studentStmt->fetch();

if (!$currentStudent) {
    exit('Student profile not found.');
}

$sql = "
    SELECT
        s.student_id,
        s.first_name,
        s.last_name,
        c.course_id,
        c.course_code,
        c.course_name,
        AVG(r.marks) AS average_marks
    FROM RESULTS r
    INNER JOIN STUDENTS s
        ON s.student_id = r.student_id
    INNER JOIN COURSES c
        ON c.course_id = r.course_id
    WHERE r.academic_year = (
        SELECT MAX(r2.academic_year)
        FROM RESULTS r2
        WHERE r2.student_id = r.student_id
          AND r2.course_id = r.course_id
    )
    GROUP BY
        s.student_id,
        s.first_name,
        s.last_name,
        c.course_id,
        c.course_code,
        c.course_name
    ORDER BY
        c.course_name ASC,
        average_marks DESC
";

$stmt = $pdo->query($sql);
$allResults = $stmt->fetchAll();

$leaderboards = [];

foreach ($allResults as $row) {
    $courseId = (int) $row['course_id'];
    $leaderboards[$courseId]['course_name'] = $row['course_name'];
    $leaderboards[$courseId]['course_code'] = $row['course_code'];
    $leaderboards[$courseId]['students'][] = $row;
}

$rankStmt = $pdo->prepare(
    'SELECT
        s.student_id,
        s.first_name,
        s.last_name,
        c.course_id,
        c.course_name,
        AVG(r.marks) AS average_marks
     FROM RESULTS r
     INNER JOIN STUDENTS s ON s.student_id = r.student_id
     INNER JOIN COURSES c ON c.course_id = r.course_id
     WHERE c.course_id = ?
     GROUP BY
        s.student_id,
        s.first_name,
        s.last_name,
        c.course_id,
        c.course_name
     ORDER BY average_marks DESC, s.student_id ASC'
);

$upsertStmt = $pdo->prepare(
    'INSERT INTO leaderboard
        (student_id, course_id, total_points, rank_position, badges, academic_year, last_updated)
     VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
     ON DUPLICATE KEY UPDATE
        total_points = VALUES(total_points),
        rank_position = VALUES(rank_position),
        badges = VALUES(badges),
        academic_year = VALUES(academic_year),
        last_updated = CURRENT_TIMESTAMP'
);

$pdo->beginTransaction();

try {
    foreach ($leaderboards as $courseId => $courseData) {
        $students = $courseData['students'];
        $position = 0;
        $previousAverage = null;

        foreach ($students as $index => $student) {
            $average = (float) $student['average_marks'];

            if ($previousAverage === null || $average < $previousAverage) {
                $position = $index + 1;
            }

            $previousAverage = $average;

            $badge = $average >= 85
                ? 'Excellent Performance'
                : ($average >= 70
                    ? 'Strong Performance'
                    : ($average >= 50
                        ? 'Improving'
                        : 'Needs Support'));

            $academicYearStmt = $pdo->prepare(
                'SELECT academic_year
                 FROM RESULTS
                 WHERE student_id = ? AND course_id = ?
                 ORDER BY result_id DESC
                 LIMIT 1'
            );

            $academicYearStmt->execute([
                $student['student_id'],
                $courseId
            ]);

            $academicYear = $academicYearStmt->fetchColumn();

            if ($academicYear === false || $academicYear === null) {
                $academicYear = date('Y');
            }

            $upsertStmt->execute([
                $student['student_id'],
                $courseId,
                (int) round($average),
                $position,
                $badge,
                (string) $academicYear
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

$viewStmt = $pdo->prepare(
    'SELECT
        l.student_id,
        l.course_id,
        l.total_points,
        l.rank_position,
        l.badges,
        l.academic_year,
        s.first_name,
        s.last_name,
        c.course_code,
        c.course_name
     FROM leaderboard l
     INNER JOIN STUDENTS s ON s.student_id = l.student_id
     INNER JOIN COURSES c ON c.course_id = l.course_id
     WHERE l.academic_year = (
         SELECT MAX(l2.academic_year)
         FROM leaderboard l2
         WHERE l2.course_id = l.course_id
     )
     ORDER BY c.course_name ASC, l.rank_position ASC'
);

$viewStmt->execute();
$leaderboardRows = $viewStmt->fetchAll();

$displayLeaderboards = [];

foreach ($leaderboardRows as $row) {
    $courseId = (int) $row['course_id'];
    $displayLeaderboards[$courseId]['course_name'] = $row['course_name'];
    $displayLeaderboards[$courseId]['course_code'] = $row['course_code'];
    $displayLeaderboards[$courseId]['students'][] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard | Smart Hub</title>

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
            margin-bottom: 0;
        }

        main {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .course-card {
            background: white;
            padding: 24px;
            margin-bottom: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.06);
        }

        .course-card h2 {
            margin-top: 0;
        }

        .course-code {
            color: #64748b;
            font-size: 14px;
        }

        .student-row {
            display: grid;
            grid-template-columns: 55px minmax(0, 1fr) auto;
            gap: 15px;
            align-items: center;
            padding: 17px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .student-row:last-child {
            border-bottom: none;
        }

        .rank {
            font-size: 21px;
            font-weight: bold;
        }

        .student-name {
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            margin-top: 7px;
            padding: 6px 9px;
            border-radius: 20px;
            background: #e0e7ff;
            color: #3730a3;
            font-size: 12px;
        }

        .points {
            text-align: right;
            font-weight: bold;
        }

        .points small {
            display: block;
            margin-top: 5px;
            color: #64748b;
            font-weight: normal;
        }

        .empty {
            background: white;
            padding: 25px;
            border-radius: 14px;
            color: #64748b;
            line-height: 1.6;
        }

        .button {
            display: inline-block;
            margin-top: 10px;
            padding: 12px 18px;
            border-radius: 8px;
            background: #3157d5;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            .student-row {
                grid-template-columns: 40px minmax(0, 1fr);
            }

            .points {
                grid-column: 2;
                text-align: left;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Smart Hub Leaderboard</h1>
    <p>Track academic performance and celebrate progress.</p>
</header>

<main>

    <h2>Course Rankings</h2>

    <?php if (empty($displayLeaderboards)): ?>

        <div class="empty">
            No leaderboard entries are available yet. Rankings will appear
            when students have recorded results for their courses.
        </div>

    <?php else: ?>

        <?php foreach ($displayLeaderboards as $course): ?>

            <section class="course-card">

                <h2><?= htmlspecialchars($course['course_name']) ?></h2>

                <div class="course-code">
                    <?= htmlspecialchars($course['course_code']) ?>
                </div>

                <?php foreach ($course['students'] as $student): ?>

                    <?php
                        $rank = (int) $student['rank_position'];

                        $medal = match ($rank) {
                            1 => '🥇',
                            2 => '🥈',
                            3 => '🥉',
                            default => '#' . $rank
                        };

                        $isCurrentStudent =
                            (int) $student['student_id']
                            === (int) $currentStudent['student_id'];
                    ?>

                    <div class="student-row">

                        <div class="rank">
                            <?= $medal ?>
                        </div>

                        <div>
                            <div class="student-name">
                                <?= htmlspecialchars(
                                    $student['first_name'] . ' ' . $student['last_name']
                                ) ?>

                                <?= $isCurrentStudent ? '(You)' : '' ?>
                            </div>

                            <span class="badge">
                                <?= htmlspecialchars($student['badges']) ?>
                            </span>
                        </div>

                        <div class="points">
                            <?= (int) $student['total_points'] ?> points
                            <small><?= htmlspecialchars($student['academic_year']) ?></small>
                        </div>

                    </div>

                <?php endforeach; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

    <a class="button" href="/smart_hub/public/dashboard.php">
        Back to Dashboard
    </a>

</main>

</body>
</html>