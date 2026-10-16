<?php
require_once __DIR__ . '/../config/session.php';
session_unset();
session_destroy();
header('Location: /smart_hub/auth/login.php');
exit;
