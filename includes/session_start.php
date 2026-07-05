<?php
// includes/session_start.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and validate their status
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/functions.php';
    $userStatus = checkUserStatus($conn, $_SESSION['user_id']);
    
    if ($userStatus == 'banned') {
        // Force logout banned user
        session_destroy();
        $_SESSION ['message'] = 'Your account has been permanently banned.';
        header('Location: ' . (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../../login.php' : 'login.php'));
        exit();
    } else if (strpos($userStatus, 'suspended:') === 0) {
        // Force logout suspended user
        session_destroy();
        $suspendedUntil = substr($userStatus, 10); // Extract date after "suspended:"
        $_SESSION['message'] = 'Your account is suspended until ' . date('d/m/Y', strtotime($suspendedUntil));
        header('Location: ' . (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '../../login.php' : 'login.php'));
        exit();
    }
}
?>