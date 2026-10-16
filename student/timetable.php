<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_role('student');
$userId = (int) $_SESSION['user']['id'];
$studentQuery = $pdo->prepare(
    'SELECT student_id
     FROM STUDENTS
     WHERE user_id = ?'
);
$studentQuery->execute([$userId]);
$student = $studentQuery->fetch();
if (!$student) {
    exit('Student profile not found.');
}
$studentId = (int) $student['student_id'];
$days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday'
];
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    try {
        $recommendationQuery = $pdo->prepare(
            'SELECT
                c.course_id,
                c.course_name,
                c.course_code,
                AVG(r.marks) AS average_marks
             FROM RESULTS r
             INNER JOIN COURSES c ON c.course_id = r.course_id
             WHERE r.student_id = ?
             GROUP BY c.course_id, c.course_name, c.course_code
             ORDER BY average_marks ASC'
        );
        $recommendationQuery->execute([$studentId]);
        $courses = $recommendationQuery->fetchAll();
        if (!$courses) {
            $error = 'No results are available yet. Record results before generating your timetable.';
        } else {
            $pdo->beginTransaction();
            $deleteQuery = $pdo->prepare(
                'DELETE FROM STUDY_TIMETABLE WHERE student_id = ?'
            );
            $deleteQuery->execute([$studentId]);
            $insertQuery = $pdo->prepare(
                'INSERT INTO STUDY_TIMETABLE
                    (student_id, course_id, study_day, start_time, end_time)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $dayIndex = 0;
            foreach ($courses as $course) {
                $average = (float) $course['average_marks'];
                if ($average < 50) {
                    $sessions = 3;
                    $duration = 60;
                } elseif ($average < 70) {
                    $sessions = 2;
                    $duration = 45;
                } else {
                    $sessions = 1;
                    $duration = 45;
                }
                for ($i = 0; $i < $sessions; $i++) {
                    $day = $days[$dayIndex % count($days)];
                    $startMinutes = 16 * 60;
                    $startMinutes += ($i % 2) * 90;
                    $endMinutes = $startMinutes + $duration;
                    $startTime = sprintf(
                        '%02d:%02d:00',
                        intdiv($startMinutes, 60),
                        $startMinutes % 60
                    );
                    $endTime = sprintf(
                        '%02d:%02d:00',
                        intdiv($endMinutes, 60),
                        $endMinutes % 60
                    );
                    $insertQuery->execute([
                        $studentId,
                        $course['course_id'],
                        $day,
                        $startTime,
                        $endTime
                    ]);
                    $dayIndex++;
                }
            }
            $pdo->commit();
            $message = 'Your personalised weekly study timetable has been generated.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Smart Hub timetable error: ' . $e->getMessage());
        $error = 'The timetable could not be generated. Please check the database configuration and try again.';
    }
}
$timetableQuery = $pdo->prepare(
    'SELECT
        t.timetable_id,
        t.study_day,
        t.start_time,
        t.end_time,
        c.course_name,
        c.course_code,
        r.average_marks
     FROM STUDY_TIMETABLE t
     INNER JOIN COURSES c ON c.course_id = t.course_id
     LEFT JOIN (
        SELECT course_id, student_id, AVG(marks) AS average_marks
        FROM RESULTS
        GROUP BY course_id, student_id
     ) r ON r.course_id = t.course_id AND r.student_id = t.student_id
     WHERE t.student_id = ?
     ORDER BY FIELD(
        t.study_day,
        "Monday", "Tuesday", "Wednesday", "Thursday",
        "Friday", "Saturday", "Sunday"
     ), t.start_time'
);
$timetableQuery->execute([$studentId]);
$timetable = $timetableQuery->fetchAll();
function performanceLabel(?float $average): string
{
    if ($average === null) {
        return 'No results';
    }
    if ($average < 50) {
        return 'Needs priority';
    }
    if ($average < 70) {
        return 'Needs improvement';
    }
    return 'Strong performance';
}
function formatTime(string $time): string
{
    return date('g:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Study Timetable | Smart Hub</title>
</head>
<body>
    <h1>My Weekly Study Timetable</h1>
    <p>
        Your timetable uses your recorded results to allocate more study
        sessions to courses that need improvement.
    </p>
    <?php if ($message !== ''): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
        <button type="submit" name="generate" value="1">
            Generate My Timetable
        </button>
    </form>
    <?php if ($timetable): ?>
        <h2>Your Weekly Schedule</h2>
        <?php foreach ($days as $day): ?>
            <?php
            $daySessions = array_filter(
                $timetable,
                fn($session) => $session['study_day'] === $day
            );
            ?>
            <?php if ($daySessions): ?>
                <h3><?= htmlspecialchars($day) ?></h3>
                <table border="1" cellpadding="8" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Course</th>
                            <th>Average Mark</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daySessions as $session): ?>
                            <?php
                            $average = $session['average_marks'] !== null
                                ? (float) $session['average_marks']
                                : null;
                            ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(formatTime($session['start_time'])) ?>
                                    -
                                    <?= htmlspecialchars(formatTime($session['end_time'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($session['course_code']) ?>
                                    -
                                    <?= htmlspecialchars($session['course_name']) ?>
                                </td>
                                <td>
                                    <?= $average !== null
                                        ? number_format($average, 1) . '%'
                                        : 'N/A' ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(performanceLabel($average)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No timetable has been generated yet. Select the button above.</p>
    <?php endif; ?>
    <p>
        <a href="/smart_hub/student/recommendations.php">
            View Study Recommendations
        </a>
    </p>
    <p>
        <a href="/smart_hub/student/analytics.php">
            View Academic Analytics
        </a>
    </p>
    <p>
        <a href="/smart_hub/public/dashboard.php">
            Return to Dashboard
        </a>
    </p>
</body>
</html>