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

$resultsStmt = $pdo->prepare(
    'SELECT
        c.course_code,
        c.course_name,
        AVG(r.marks) AS average_marks,
        COUNT(r.result_id) AS assessments
     FROM RESULTS r
     INNER JOIN COURSES c ON c.course_id = r.course_id
     WHERE r.student_id = ?
     GROUP BY c.course_id, c.course_code, c.course_name
     ORDER BY average_marks DESC'
);

$resultsStmt->execute([(int) $student['student_id']]);
$results = $resultsStmt->fetchAll();

$totalMarks = 0;
$totalAssessments = 0;

foreach ($results as $result) {
    $totalMarks += (float) $result['average_marks'];
    $totalAssessments++;
}

$overallAverage = $totalAssessments > 0
    ? $totalMarks / $totalAssessments
    : 0;

$strongCourses = [];
$improvementCourses = [];
$weakCourses = [];

foreach ($results as $result) {
    $marks = (float) $result['average_marks'];

    if ($marks >= 70) {
        $strongCourses[] = $result;
    } elseif ($marks >= 50) {
        $improvementCourses[] = $result;
    } else {
        $weakCourses[] = $result;
    }
}

$bestCourse = $results[0] ?? null;
$weakestCourse = !empty($results)
    ? $results[count($results) - 1]
    : null;

function performanceLabel(float $marks): string
{
    if ($marks >= 70) {
        return 'Strong';
    }

    if ($marks >= 50) {
        return 'Needs Improvement';
    }

    return 'Weak';
}

function performanceClass(float $marks): string
{
    if ($marks >= 70) {
        return 'strong';
    }

    if ($marks >= 50) {
        return 'improve';
    }

    return 'weak';
}

$displayName = trim(
    $student['first_name'] . ' ' . $student['last_name']
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Analytics | Smart Hub</title>

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
            padding: 20px 7%;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
        }

        header p {
            margin-bottom: 0;
            color: #cbd5e1;
        }

        main {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.06);
        }

        .card p {
            color: #64748b;
            margin: 0 0 12px;
        }

        .card h2 {
            margin: 0;
            font-size: 28px;
        }

        .section {
            background: white;
            padding: 24px;
            margin-bottom: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.06);
        }

        .section h2 {
            margin-top: 0;
        }

        .course {
            padding: 18px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .course:last-child {
            border-bottom: none;
        }

        .course-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: center;
        }

        .course-name {
            font-weight: bold;
        }

        .course-code {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }

        .badge {
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .strong {
            color: #166534;
            background: #dcfce7;
        }

        .improve {
            color: #92400e;
            background: #fef3c7;
        }

        .weak {
            color: #991b1b;
            background: #fee2e2;
        }

        .progress {
            height: 9px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
        }

        .progress-bar {
            height: 100%;
            background: #3157d5;
            border-radius: 10px;
        }

        .recommendation {
            padding: 16px;
            margin-bottom: 12px;
            background: #f8fafc;
            border-left: 4px solid #3157d5;
            border-radius: 7px;
        }

        .button {
            display: inline-block;
            padding: 12px 18px;
            background: #3157d5;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .empty {
            color: #64748b;
            line-height: 1.6;
        }

        @media (max-width: 700px) {
            .cards {
                grid-template-columns: 1fr;
            }

            .course-top {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Smart Hub</h1>
    <p>Academic Performance Analytics</p>
</header>

<main>

    <div class="welcome">
        <h2>Hello, <?= htmlspecialchars($displayName) ?></h2>
        <p>Review your academic performance and identify areas for improvement.</p>
    </div>

    <?php if (empty($results)): ?>

        <section class="section">
            <h2>No Results Available</h2>
            <p class="empty">
                Your academic analytics will appear when results have been recorded
                for your courses.
            </p>
        </section>

    <?php else: ?>

        <div class="cards">

            <div class="card">
                <p>Overall Course Average</p>
                <h2><?= number_format($overallAverage, 1) ?>%</h2>
            </div>

            <div class="card">
                <p>Courses Recorded</p>
                <h2><?= $totalAssessments ?></h2>
            </div>

            <div class="card">
                <p>Courses Needing Improvement</p>
                <h2><?= count($improvementCourses) + count($weakCourses) ?></h2>
            </div>

        </div>

        <section class="section">
            <h2>Course Performance</h2>

            <?php foreach ($results as $result): ?>
                <?php
                    $marks = (float) $result['average_marks'];
                    $barWidth = max(0, min(100, $marks));
                    $class = performanceClass($marks);
                ?>

                <div class="course">

                    <div class="course-top">
                        <div>
                            <span class="course-name">
                                <?= htmlspecialchars($result['course_name']) ?>
                            </span>

                            <span class="course-code">
                                <?= htmlspecialchars($result['course_code']) ?>
                                · <?= (int) $result['assessments'] ?> assessment(s)
                            </span>
                        </div>

                        <div>
                            <strong><?= number_format($marks, 1) ?>%</strong>

                            <span class="badge <?= $class ?>">
                                <?= performanceLabel($marks) ?>
                            </span>
                        </div>
                    </div>

                    <div
                        class="progress"
                        role="progressbar"
                        aria-valuenow="<?= $barWidth ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label="<?= htmlspecialchars($result['course_name']) ?> performance"
                    >
                        <div
                            class="progress-bar"
                            style="width: <?= $barWidth ?>%;"
                        ></div>
                    </div>

                </div>

            <?php endforeach; ?>

        </section>

        <section class="section">
            <h2>Performance Highlights</h2>

            <div class="recommendation">
                <strong>Best-performing course</strong><br>

                <?= htmlspecialchars($bestCourse['course_name']) ?>
                — <?= number_format((float) $bestCourse['average_marks'], 1) ?>%
            </div>

            <div class="recommendation">
                <strong>Course requiring the most attention</strong><br>

                <?= htmlspecialchars($weakestCourse['course_name']) ?>
                — <?= number_format((float) $weakestCourse['average_marks'], 1) ?>%
            </div>

        </section>

        <section class="section">
            <h2>Study Recommendations</h2>

            <?php if (!empty($weakCourses)): ?>

                <?php foreach ($weakCourses as $course): ?>
                    <div class="recommendation">
                        Prioritise
                        <strong><?= htmlspecialchars($course['course_name']) ?></strong>.
                        Review the topics you find difficult, practise questions
                        regularly and seek help from your lecturer.
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>

            <?php if (!empty($improvementCourses)): ?>

                <?php foreach ($improvementCourses as $course): ?>
                    <div class="recommendation">
                        Work on improving
                        <strong><?= htmlspecialchars($course['course_name']) ?></strong>.
                        Schedule regular revision and complete practice exercises
                        before your next assessment.
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>

            <?php if (empty($weakCourses) && empty($improvementCourses)): ?>

                <div class="recommendation">
                    You are performing strongly across your recorded courses.
                    Continue revising consistently and challenge yourself with
                    more advanced practice questions.
                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

    <a class="button" href="/smart_hub/public/dashboard.php">
        Back to Dashboard
    </a>

    <a class="button" href="/smart_hub/api/gemini.php">
        AI Performance Analysis
    </a>

</main>

</body>
</html>