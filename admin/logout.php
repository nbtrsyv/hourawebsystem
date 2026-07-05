<?php
require_once '../includes/session_start.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to main site index
header('Location: ../index.php');
exit();
?>