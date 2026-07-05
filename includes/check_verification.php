<?php
// includes/check_verification.php

function isUserVerified($conn, $user_id) {
    $stmt = $conn->prepare("SELECT is_verified FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    return ($user && $user['is_verified'] == 1);
}

function redirectIfUnverified($conn, $user_id) {
    if (!isUserVerified($conn, $user_id)) {
        header('Location: profile.php?error=Please+verify+your+identity+to+access+this+feature');
        exit();
    }
}