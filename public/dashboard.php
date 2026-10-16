<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

require_login();

$user = $_SESSION['user'];

$stats = [
    'courses' => 0,
    'average' => 0,
    'notifications' => 0
];

$student = null;

if ($user['role'] === 'student') {
    $s = $pdo->prepare(
        'SELECT student_id, first_name, last_name, program, year_of_study, academic_year
         FROM STUDENTS
         WHERE user_id = ?'
    );

    $s->execute([$user['id']]);
    $student = $s->fetch();

    if ($student) {
        $q = $pdo->prepare(
            'SELECT
                COUNT(DISTINCT course_id) AS courses,
                COALESCE(AVG(marks), 0) AS average
             FROM RESULTS
             WHERE student_id = ?'
        );

        $q->execute([$student['student_id']]);
        $resultStats = $q->fetch();

        $stats['courses'] = (int) $resultStats['courses'];
        $stats['average'] = (float) $resultStats['average'];
    }
}

$displayName = $user['name'] ?? '';

if ($student) {
    $displayName = trim(
        $student['first_name'] . ' ' . $student['last_name']
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Smart Hub Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
        }

        nav {
            background: #14213d;
            color: white;
            padding: 20px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        nav strong {
            font-size: 23px;
            letter-spacing: 1px;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }

        nav a:hover {
            color: #80ed99;
        }

        main {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .welcome h1 {
            margin-bottom: 8px;
            color: #14213d;
        }

        .welcome p {
            color: #64748b;
        }

        .profile {
            background: white;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        }

        .card h3 {
            margin-top: 0;
            color: #64748b;
            font-size: 15px;
        }

        .value {
            font-size: 32px;
            font-weight: bold;
            color: #14213d;
        }

        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .button {
            display: inline-block;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .secondary {
            background: #14213d;
        }

        .secondary:hover {
            background: #263b60;
        }

        .ai-button {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 22px;
            margin-top: 28px;
            background: linear-gradient(135deg, #14213d, #2563eb);
            color: white;
            text-decoration: none;
            border-radius: 14px;
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .ai-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
        }

        .ai-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            font-size: 28px;
        }

        .ai-content {
            min-width: 0;
        }

        .ai-content strong {
            display: block;
            font-size: 17px;
            margin-bottom: 5px;
        }

        .ai-content small {
            display: block;
            color: #e2e8f0;
            font-size: 13px;
            line-height: 1.5;
        }

        .ai-arrow {
            margin-left: auto;
            font-size: 25px;
        }

        .notice {
            background: white;
            padding: 20px;
            margin-top: 25px;
            border-radius: 12px;
        }

        @media (max-width: 600px) {
            nav {
                padding: 18px;
            }

            nav a {
                margin-left: 0;
                margin-right: 12px;
            }

            main {
                margin-top: 25px;
            }

            .ai-button {
                gap: 10px;
                padding: 16px;
            }

            .ai-content strong {
                font-size: 15px;
            }

            .ai-content small {
                font-size: 12px;
            }

            .ai-icon {
                min-width: 40px;
                height: 40px;
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<nav>
    <strong>SMART HUB</strong>

    <div>
        <a href="/smart_hub/public/dashboard.php">Dashboard</a>

        <?php if ($user['role'] === 'student'): ?>
            <a href="/smart_hub/student/results.php">My Results</a>
        <?php endif; ?>

        <a href="/smart_hub/auth/logout.php">Logout</a>
    </div>
</nav>

<main>

    <section class="welcome">
        <h1>
            Welcome, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <p>Your academic progress, all in one place.</p>
    </section>

    <?php if ($user['role'] === 'student' && $student): ?>

        <section class="profile">
            <h2>Student Profile</h2>

            <p>
                <strong>Programme:</strong>
                <?= htmlspecialchars($student['program'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p>
                <strong>Year of study:</strong>
                <?= (int) $student['year_of_study'] ?>
            </p>

            <p>
                <strong>Academic year:</strong>
                <?= htmlspecialchars($student['academic_year'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </section>

        <section class="stats">
            <div class="card">
                <h3>Courses Tracked</h3>
                <div class="value"><?= $stats['courses'] ?></div>
            </div>

            <div class="card">
                <h3>Average Mark</h3>
                <div class="value">
                    <?= number_format($stats['average'], 1) ?>%
                </div>
            </div>

            <div class="card">
                <h3>Notifications</h3>
                <div class="value"><?= $stats['notifications'] ?></div>
            </div>
        </section>

        <a class="ai-button" href="/smart_hub/api/gemini.php">
            <span class="ai-icon">✦</span>

            <span class="ai-content">
                <strong>AI Performance Analysis</strong>
                <small>
                    Analyse your results, discover areas for improvement,
                    and receive personalised study recommendations.
                </small>
            </span>

            <span class="ai-arrow">→</span>
        </a>

        <section class="actions">
            <a class="button" href="/smart_hub/student/results.php">
                View My Results
            </a>

            <a class="button secondary" href="/smart_hub/student/">
                Student Area
            </a>
        </section>

    <?php elseif ($user['role'] === 'student'): ?>

        <section class="notice">
            <h2>Student Profile Not Found</h2>

            <p>
                Your account is logged in, but no student profile is linked
                to it. Please contact the administrator.
            </p>
        </section>

    <?php else: ?>

        <section class="notice">
            <h2>Welcome to Smart Hub</h2>

            <p>
                You are logged in as
                <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>.
            </p>
        </section>

    <?php endif; ?>

</main>

</body>
</html>