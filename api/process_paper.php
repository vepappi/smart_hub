<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
$geminiConfig = require __DIR__ . '/../config/gemini_config.php';
$apiKey = $geminiConfig['api_key'] ?? '';

require_role('student');

$userId = (int) ($_SESSION['user']['id'] ?? 0);

$studentQuery = $pdo->prepare(
    'SELECT student_id
     FROM STUDENTS
     WHERE user_id = ?'
);
$studentQuery->execute([$userId]);
$student = $studentQuery->fetch();

if (!$student) {
    http_response_code(403);
    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Use the form on the submission page to process a paper.');
}

$submissionId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);

if (!$submissionId || $submissionId < 1) {
    http_response_code(400);
    exit('Invalid submission.');
}

$submissionQuery = $pdo->prepare(
    'SELECT submission_id, image_path, processing_status
     FROM RESULTS_SUBMISSIONS
     WHERE submission_id = ? AND student_id = ?'
);
$submissionQuery->execute([$submissionId, $studentId]);
$submission = $submissionQuery->fetch();

if (!$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

if ($submission['processing_status'] === 'processed') {
    exit('This paper has already been processed. Review the extracted information before submitting it again.');
}

$imagePath = realpath(
    __DIR__ . '/../' . $submission['image_path']
);

$uploadDirectory = realpath(__DIR__ . '/../uploads/results');

if (
    !$imagePath ||
    !$uploadDirectory ||
    !str_starts_with($imagePath, $uploadDirectory . DIRECTORY_SEPARATOR) ||
    !is_file($imagePath) ||
    !is_readable($imagePath)
) {
    http_response_code(400);
    exit('The uploaded image could not be accessed.');
}

$imageData = file_get_contents($imagePath);

if ($imageData === false) {
    http_response_code(500);
    exit('Could not read the uploaded image.');
}

$mimeType = mime_content_type($imagePath);

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(400);
    exit('Unsupported image type.');
}

$prompt = <<<'PROMPT'
Read this photograph of a marked student result or test paper.

Extract only information that is visibly supported by the image.
Do not guess missing marks, course names, student names, or feedback.
If something cannot be read, identify it as unclear.

Return:
1. Course or subject name, if visible.
2. Assessment type or title, if visible.
3. Marks obtained, if visible.
4. Total possible marks, if visible.
5. Lecturer feedback, if visible.
6. Any other relevant readable information.
7. A list of details that are unclear or need human confirmation.

Clearly distinguish marks obtained from total possible marks.
Do not treat a percentage as the raw mark.
Do not claim that extracted information has been verified as official.
Return the result in plain text.
PROMPT;

$requestBody = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($imageData)
                    ]
                ]
            ]
        ]
    ]
];

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';

$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($requestBody)
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false || $curlError !== '') {
    error_log('Smart Hub Gemini image request failed: ' . $curlError);

    http_response_code(502);
    exit('The AI service could not be reached. Please try again.');
}

$responseData = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300) {
    error_log(
        'Smart Hub Gemini API returned HTTP ' . $httpCode
        . ': ' . substr($response, 0, 1500)
    );

    http_response_code(502);
    exit('The AI service could not process this paper. Please check the error log.');
}

$extractedText = trim(
    $responseData['candidates'][0]['content']['parts'][0]['text'] ?? ''
);

if ($extractedText === '') {
    http_response_code(502);
    exit('The AI returned no readable information. Try a clearer photograph.');
}

$updateQuery = $pdo->prepare(
    "UPDATE RESULTS_SUBMISSIONS
     SET extracted_text = ?,
         processing_status = 'processed'
     WHERE submission_id = ?
       AND student_id = ?"
);

$updateQuery->execute([
    $extractedText,
    $submissionId,
    $studentId
]);

header(
    'Location: /smart_hub/student/review_paper.php?id='
    . $submissionId
);
exit;