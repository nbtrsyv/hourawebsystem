<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php'; // Guna config anda

class AdminAuth {
    public static function checkLogin() {
        // If admin-specific session not set, allow a normal logged-in user with role 'admin'
        if (!isset($_SESSION['admin_id'])) {
            if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                // Map general user session to admin session variables for compatibility
                $_SESSION['admin_id'] = $_SESSION['user_id'];
                $_SESSION['admin_name'] = $_SESSION['full_name'] ?? '';
                $_SESSION['admin_email'] = $_SESSION['email'] ?? '';
                $_SESSION['admin_role'] = $_SESSION['role'];
                return;
            }
            header('Location: ../login.php');
            exit();
        }

        // If admin_id is set, ensure role is admin
        if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
            header('Location: ../login.php');
            exit();
        }
    }
    
    public static function logout() {
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
}
?>