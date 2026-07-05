<?php
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Update database
    $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?");
    
    if ($stmt->execute([$user_id])) {
        // Berjaya
        echo "success";
    } else {
        // Gagal
        http_response_code(500);
        echo "error";
    }
}
?>