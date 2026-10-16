<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

if (!empty($_SESSION['user'])) {
    header('Location: /smart_hub/public/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

   $stmt = $pdo->prepare(
    'SELECT user_id AS id, username AS name, email, password, role
     FROM USERS
     WHERE email = ? AND status = "active"
     LIMIT 1'
);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        $_SESSION['user'] = $user;
        header('Location: /smart_hub/public/dashboard.php');
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Smart Hub Login</title></head>
<body>
<h1>Smart Hub</h1><h2>Sign in</h2>
<?php if ($error): ?><p><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
<label>Email<br><input type="email" name="email" required></label><br><br>
<label>Password<br><input type="password" name="password" required></label><br><br>
<button type="submit">Sign In</button>
</form>
</body></html>
