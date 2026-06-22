<?php
// logout.php - User logout handler
session_start();
session_destroy();

// Redirect to login page
header('Location: main/login.html');
exit;
?>
