<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_role('student');
$userId = (int) $_SESSION['user']['id'];
$stmt = $pdo->prepare(
    'SELECT
        r.result_id,
        r.marks,
        r.grade,
        r.semester,
        r.academic_year,
        c.course_code,
        c.course_name
     FROM RESULTS r
     JOIN STUDENTS s ON s.student_id = r.student_id
     JOIN COURSES c ON c.course_id = r.course_id
     WHERE s.user_id = ?
     ORDER BY r.result_id DESC'
);
$stmt->execute([$userId]);
$results = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - Smart Hub</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1e293b;
        }
        main {
            max-width: 1100px;
            margin: 20px auto;
        }
        h1 {
            color: #14213d;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 25px;
        }
        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #14213d;
            color: white;
        }
        tr:hover td {
            background: #f8fafc;
        }
        .empty {
            background: white;
            padding: 25px;
            border-radius: 12px;
            color: #64748b;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 18px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        .button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
<main>
    <h1>My Academic Results</h1>
    <p class="subtitle">
        View your recorded marks, grades, and academic progress.
    </p>
    <?php if (!empty($results)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Mark</th>
                        <th>Grade</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($r['course_code'] ?? '') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($r['course_name'] ?? '') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars((string) $r['marks']) ?>%
                            </td>
                            <td>
                                <?= htmlspecialchars($r['grade']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($r['semester']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($r['academic_year']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2>No Results Yet</h2>
            <p>Your academic results will appear here once they have been recorded.</p>
        </div>
    <?php endif; ?>
    <a class="button" href="/smart_hub/public/dashboard.php">
        Back to Dashboard
    </a>
</main>
</body>
</html>