<?php
// view_review.php - View your submitted review
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

// Fetch review
$stmt = $conn->prepare("
    SELECT r.*, 
           u.full_name as reviewee_name,
           s.title as service_title
    FROM reviews r
    JOIN users u ON r.reviewee_id = u.user_id
    JOIN service_requests sr ON r.request_id = sr.request_id
    JOIN services s ON sr.service_id = s.service_id
    WHERE r.request_id = ? AND r.reviewer_id = ?
");
$stmt->execute([$request_id, $user_id]);
$review = $stmt->fetch();

if (!$review) {
    header('Location: my_requests.php');
    exit();
}

$page_title = "Your Review - " . htmlspecialchars($review['service_title']);
include 'includes/header2.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-star-fill me-2"></i>Your Review</h5>
                </div>
                <div class="card-body">
                    <!-- Service Info -->
                    <div class="alert alert-info">
                        <h6><?php echo htmlspecialchars($review['service_title']); ?></h6>
                        <p class="mb-0">Service with: <?php echo htmlspecialchars($review['reviewee_name']); ?></p>
                    </div>
                    
                    <!-- Your Rating -->
                    <div class="text-center mb-4">
                        <h6>Your Rating</h6>
                        <div style="font-size: 2rem;">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <?php if($i <= $review['rating']): ?>
                                    <i class="bi bi-star-fill text-warning"></i>
                                <?php else: ?>
                                    <i class="bi bi-star text-muted"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span class="ms-2"><?php echo $review['rating']; ?>/5</span>
                        </div>
                    </div>
                    
                    <!-- Your Review -->
                    <?php if(!empty($review['comment'])): ?>
                    <div class="mb-4">
                        <h6>Your Review</h6>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Review Details -->
                    <div class="row small text-muted">
                        <div class="col-md-6">
                            <p><i class="bi bi-person me-2"></i>
                                <?php echo $review['is_anonymous'] ? 'Submitted anonymously' : 'Submitted publicly'; ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p><i class="bi bi-calendar me-2"></i>
                                Reviewed on: <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Back Button -->
                    <div class="text-center mt-4">
                        <a href="my_requests.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to My Requests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>