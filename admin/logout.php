<?php
// Start session to properly destroy it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy all session data
session_destroy();

// Delete remember-me cookie if it exists
if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 3600, "/");
}

// Redirect to login page
header('Location: ../login.php');
exit;
?>
