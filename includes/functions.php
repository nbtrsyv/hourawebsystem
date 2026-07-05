<?php
// includes/functions.php
function updateUserRating($conn, $user_id) {
    // Kira average rating untuk user
    $sql = "SELECT 
                AVG(rating) as avg_rating,
                COUNT(*) as total_reviews
            FROM reviews 
            WHERE reviewee_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Update user rating dan total transactions
        $update_sql = "UPDATE users 
                      SET rating = ?, 
                          total_transactions = ?
                      WHERE user_id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->execute([
            round($result['avg_rating'], 2),
            $result['total_reviews'],
            $user_id
        ]);
        
        return true;
    }
    
    return false;
}

// Calculate user's current streak (consecutive days of activity)
function getUserStreak($conn, $user_id) {
    $sql = "
        SELECT DATE(activity_date) as activity_day
        FROM (
            SELECT created_at as activity_date FROM service_requests WHERE requester_id = ?
            UNION
            SELECT completed_at as activity_date FROM service_requests WHERE status = 'completed' AND requester_id = ?
        ) as all_activities
        WHERE activity_date IS NOT NULL
        GROUP BY DATE(activity_date)
        ORDER BY DATE(activity_date) DESC
        LIMIT 30
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($dates)) {
        return 0;
    }
    
    $streak = 0;
    $today = date('Y-m-d');
    $current_date = strtotime($today);
    
    // Check if user was active today or yesterday (to allow streak continuation)
    $first_date = strtotime($dates[0]);
    $diff_days = ($current_date - $first_date) / (24 * 60 * 60);
    
    // If last activity was more than 1 day ago, streak is broken
    if ($diff_days > 1) {
        return 0;
    }
    
    // Count consecutive days backwards
    foreach ($dates as $idx => $date) {
        $check_date = strtotime($date);
        $expected_date = $current_date - ($idx * 24 * 60 * 60);
        
        // Check if this date matches expected consecutive day
        $day_diff = abs(($check_date - $expected_date) / (24 * 60 * 60));
        
        if ($day_diff <= 1) {
            $streak++;
        } else {
            break;
        }
    }
    
    return $streak;
}

// Check if user is active and accessible
// Returns: 'active' = user boleh proceed
//          'banned' = user banned permanently
//          'suspended' = user suspended (show message with date)
function checkUserStatus($conn, $user_id) {
    $stmt = $conn->prepare("SELECT status, suspended_until FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return 'invalid';
    }
    
    if ($user['status'] == 'banned') {
        return 'banned';
    }
    
    if ($user['status'] == 'suspended') {
        // Check if suspension period expired
        if ($user['suspended_until'] && strtotime($user['suspended_until']) <= time()) {
            // Auto-reactivate
            $updateStmt = $conn->prepare("UPDATE users SET status = 'active', suspended_until = NULL WHERE user_id = ?");
            $updateStmt->execute([$user_id]);
            return 'active';
        }
        return 'suspended:' . $user['suspended_until'];
    }
    
    return 'active';
}
?>  