<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
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
$uploadDirectory = __DIR__ . '/../uploads/results/';
$uploadUrlPath = '/smart_hub/uploads/results/';
if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0750, true);
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $submissionType = $_POST['submission_type'] ?? '';
    if (!in_array($submissionType, ['result', 'test'], true)) {
        $error = 'Please select a valid submission type.';
    } elseif (
        !isset($_FILES['paper_image']) ||
        $_FILES['paper_image']['error'] !== UPLOAD_ERR_OK
    ) {
        $error = 'Please select an image and try again.';
    } else {
        $file = $_FILES['paper_image'];
        $maximumSize = 5 * 1024 * 1024;
        if ($file['size'] > $maximumSize) {
            $error = 'The image must not exceed 5 MB.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $error = 'The uploaded file could not be verified.';
        } else {
            $imageInfo = @getimagesize($file['tmp_name']);
            $mimeType = $imageInfo['mime'] ?? '';
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];
            if (!isset($allowedTypes[$mimeType])) {
                $error = 'Upload a valid JPG, PNG, or WEBP image.';
            } else {
                $fileName = bin2hex(random_bytes(16))
                    . '.'
                    . $allowedTypes[$mimeType];
                $destination = $uploadDirectory . $fileName;
                $relativePath = 'uploads/results/' . $fileName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $error = 'The image could not be saved. Check folder permissions.';
                } else {
                    try {
                        $insertQuery = $pdo->prepare(
                            'INSERT INTO RESULTS_SUBMISSIONS
                                (
                                    student_id,
                                    image_path,
                                    submission_type,
                                    extracted_text,
                                    processing_status,
                                    verification_status
                                )
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $insertQuery->execute([
                            $studentId,
                            $relativePath,
                            $submissionType,
                            '',
                            'pending',
                            'pending'
                        ]);
                        $message = 'Your paper was uploaded successfully. AI analysis will be added in the next step.';
                    } catch (Throwable $e) {
                        if (is_file($destination)) {
                            unlink($destination);
                        }
                        error_log(
                            'Smart Hub result upload error: '
                            . $e->getMessage()
                        );
                        $error = 'The submission could not be saved. Please try again.';
                    }
                }
            }
        }
    }
}
$historyQuery = $pdo->prepare(
    'SELECT
        submission_id,
        submission_type,
        processing_status,
        verification_status,
        submitted_at
     FROM RESULTS_SUBMISSIONS
     WHERE student_id = ?
     ORDER BY submitted_at DESC'
);
$historyQuery->execute([$studentId]);
$submissions = $historyQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Paper | Smart Hub</title>
</head>
<body>
    <h1>Submit a Result or Test Paper</h1>
    <p>
        Upload a clear photograph of your marked paper.
        JPG, PNG, and WEBP images up to 5 MB are accepted.
    </p>
    <?php if ($message !== ''): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label for="submission_type">Submission type</label>
        <select name="submission_type" id="submission_type" required>
            <option value="">Select a type</option>
            <option value="result">Result paper</option>
            <option value="test">Marked test paper</option>
        </select>
        <br><br>
        <label for="paper_image">Choose paper photograph</label>
        <input
            type="file"
            name="paper_image"
            id="paper_image"
            accept="image/jpeg,image/png,image/webp"
            required
        >
        <br><br>
        <button type="submit">Upload Paper</button>
    </form>
    <h2>My Submissions</h2>
    <?php if (!$submissions): ?>
        <p>You have not submitted any papers yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Submission</th>
                    <th>Type</th>
                    <th>Submitted</th>
                    <th>AI Processing</th>
                    <th>Verification</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission): ?>
                    <tr>
                        <td>
                            <?= (int) $submission['submission_id'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($submission['submission_type']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($submission['submitted_at']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($submission['processing_status']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($submission['verification_status']) ?>
                            
                        </td>
                        <td>
    <?php if ($submission['processing_status'] === 'pending'): ?>

        <form
            method="POST"
            action="/smart_hub/api/process_paper.php"
        >
            <input
                type="hidden"
                name="submission_id"
                value="<?= (int) $submission['submission_id'] ?>"
            >

            <button type="submit">
                Analyze with AI
            </button>
        </form>

    <?php elseif ($submission['processing_status'] === 'processed'): ?>

        <a href="/smart_hub/student/review_paper.php?id=<?= (int) $submission['submission_id'] ?>">
            Review AI Results
        </a>

    <?php else: ?>

        <span>Processing failed</span>

    <?php endif; ?>
</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p>
        <a href="/smart_hub/public/dashboard.php">Return to Dashboard</a>
    </p>
</body>
</html>