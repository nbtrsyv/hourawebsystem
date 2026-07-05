<?php
// logout.php
require_once 'includes/session_start.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to homepage
header('Location: index.php');
exit();
?>