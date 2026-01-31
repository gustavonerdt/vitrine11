<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn() && isAdmin()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>
