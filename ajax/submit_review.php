<?php
// ajax/submit_review.php
require_once '../includes/session_start.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = (int)($_POST['request_id'] ?? 0);
$reviewee_id = (int)($_POST['reviewee_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

// Validation
if ($request_id <= 0 || $reviewee_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1-5']);
    exit();
}

// Check if request exists and user is authorized
$stmt = $conn->prepare("
    SELECT status, requester_id, provider_id 
    FROM service_requests 
    WHERE request_id = ? 
    AND status = 'completed'
    AND (requester_id = ? OR provider_id = ?)
");
$stmt->execute([$request_id, $user_id, $user_id]);
$request = $stmt->fetch();

if (!$request) {
    echo json_encode(['success' => false, 'error' => 'Request not found or not authorized']);
    exit();
}

// Check if already reviewed
$stmt = $conn->prepare("
    SELECT review_id FROM reviews 
    WHERE request_id = ? AND reviewer_id = ?
");
$stmt->execute([$request_id, $user_id]);

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'You have already reviewed this service']);
    exit();
}

// Check if reviewee_id is valid (must be the other user in the request)
$valid_reviewee = false;
if ($request['requester_id'] == $user_id && $request['provider_id'] == $reviewee_id) {
    $valid_reviewee = true;
} elseif ($request['provider_id'] == $user_id && $request['requester_id'] == $reviewee_id) {
    $valid_reviewee = true;
}

if (!$valid_reviewee) {
    echo json_encode(['success' => false, 'error' => 'Invalid user to review']);
    exit();
}

try {
    // Insert review
    $sql = "INSERT INTO reviews 
            (request_id, reviewer_id, reviewee_id, rating, comment, is_anonymous) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $request_id,
        $user_id,
        $reviewee_id,
        $rating,
        $comment,
        $is_anonymous
    ]);
    
    $review_id = $conn->lastInsertId();
    
    // Update user rating
    require_once '../includes/functions.php';
    if (function_exists('updateUserRating')) {
        updateUserRating($conn, $reviewee_id);
    } else {
        // Fallback: simple update
        $sql = "UPDATE users 
                SET rating = (
                    SELECT AVG(rating) 
                    FROM reviews 
                    WHERE reviewee_id = ?
                ),
                total_transactions = total_transactions + 1
                WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$reviewee_id, $reviewee_id]);
    }
    
    // Create notification for the reviewed user
    $notification_sql = "INSERT INTO notifications 
                        (user_id, title, message, type, related_id) 
                        VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($notification_sql);
    $stmt->execute([
        $reviewee_id,
        'New Review Received',
        'You have received a ' . $rating . '-star review for your service.',
        'system',
        $review_id
    ]);
    
    echo json_encode([
        'success' => true,
        'review_id' => $review_id,
        'message' => 'Review submitted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Review submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>