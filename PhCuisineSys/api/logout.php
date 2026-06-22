<?php
// api/logout.php - User logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();
header('Location: ../main/login.html');
exit;
?>
