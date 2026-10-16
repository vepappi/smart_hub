<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

require_role('student');

$userId = (int) $_SESSION['user']['id'];

$geminiConfig = require __DIR__ . '/../config/gemini_config.php';
$apiKey = $geminiConfig['api_key'] ?? '';

$analysis = '';
$error = '';

$stmt = $pdo->prepare(
    'SELECT
        r.marks,
        r.grade,
        r.semester,
        r.academic_year,
        c.course_code,
        c.course_name
     FROM RESULTS r
     INNER JOIN STUDENTS s
        ON s.student_id = r.student_id
     INNER JOIN COURSES c
        ON c.course_id = r.course_id
     WHERE s.user_id = ?
     ORDER BY r.result_id DESC'
);

$stmt->execute([$userId]);
$results = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($results)) {
        $error = 'No academic results were found for your account.';

    } elseif ($apiKey === '' || $apiKey === 'PASTE_YOUR_NEW_API_KEY_HERE') {
        $error = 'The Gemini API key has not been configured.';

    } else {

        $resultsText = '';

        foreach ($results as $row) {
            $resultsText .=
                $row['course_code'] . ' - ' .
                $row['course_name'] . ' - ' .
                $row['marks'] . '% - Grade ' .
                $row['grade'] . ' - Semester ' .
                $row['semester'] . ' - Academic Year ' .
                $row['academic_year'] . "\n";
        }

        $prompt = "
You are the academic performance assistant for Smart Hub.

Analyse the student's actual academic results below.

$resultsText

Provide the following sections:

1. Academic Performance Overview
Summarise the student's overall performance.

2. Strong Courses
Identify courses with stronger marks and explain why maintaining this performance matters.

3. Courses Needing Improvement
Identify courses with lower marks. Explain your reasoning using the actual marks.

4. Personalised Recommendations
Give practical, realistic actions for improving performance in weaker courses.

5. Weekly Study Timetable
Create a simple Monday-to-Sunday timetable that prioritises weaker courses while allowing time for revision and rest.

6. Motivation
Encourage the student without making unrealistic promises.

Use only the supplied results. Do not invent marks, courses, or grades.
If there are too few results to identify reliable patterns, explain this limitation.
Present the answer using clear headings and student-friendly language.
";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4
            ]
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = 'Unable to connect to the AI service. Please try again later.';
        } else {
            $aiResult = json_decode($response, true);

            if (
                $httpCode >= 200 &&
                $httpCode < 300 &&
                isset($aiResult['candidates'][0]['content']['parts'][0]['text'])
            ) {
                $analysis = $aiResult['candidates'][0]['content']['parts'][0]['text'];
            } else {
                error_log('Gemini API error: HTTP ' . $httpCode . ' - ' . $response);
                $error = 'The AI analysis could not be completed. Please check the API configuration and try again.';
            }
        }

        curl_close($ch);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Smart Hub AI Analysis</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.7;
        }

        main {
            max-width: 1000px;
            margin: 20px auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.06);
        }

        h1, h2 {
            color: #14213d;
        }

        .subtitle {
            color: #64748b;
        }

        button, .button {
            display: inline-block;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover, .button:hover {
            background: #1d4ed8;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
        }

        .analysis {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>

<main>

    <div class="card">
        <h1>AI Academic Performance Analysis</h1>

        <p class="subtitle">
            Understand your results, discover areas for improvement,
            and build a personalised study plan.
        </p>

        <p>
            Your analysis will be based on the academic results
            recorded for your Smart Hub account.
        </p>

        <form method="POST">
            <button type="submit">Analyse My Results</button>
        </form>
    </div>

    <?php if ($error !== ''): ?>
        <div class="card error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($analysis !== ''): ?>
        <div class="card">
            <h2>Your Personalised AI Recommendations</h2>

            <div class="analysis">
                <?= nl2br(htmlspecialchars($analysis)) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="actions">
        <a class="button" href="/smart_hub/public/dashboard.php">
            Back to Dashboard
        </a>

        <a class="button" href="/smart_hub/student/results.php">
            View My Results
        </a>
    </div>

</main>

</body>
</html>