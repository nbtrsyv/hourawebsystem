<?php
// rate_service.php - Page untuk beri rating selepas service selesai
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

if ($request_id <= 0) {
    header('Location: my_requests.php');
    exit();
}

// Dapatkan request details
$stmt = $conn->prepare("
    SELECT sr.*, 
           s.title as service_title,
           s.description as service_description,
           u_provider.full_name as provider_name,
           u_requester.full_name as requester_name,
           CASE 
               WHEN sr.requester_id = ? THEN u_provider.user_id
               ELSE u_requester.user_id
           END as user_to_rate_id,
           CASE 
               WHEN sr.requester_id = ? THEN u_provider.full_name
               ELSE u_requester.full_name
           END as user_to_rate_name
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users u_provider ON sr.provider_id = u_provider.user_id
    JOIN users u_requester ON sr.requester_id = u_requester.user_id
    WHERE sr.request_id = ? 
    AND sr.status = 'completed'
    AND (sr.requester_id = ? OR sr.provider_id = ?)
");
$stmt->execute([$user_id, $user_id, $request_id, $user_id, $user_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: my_requests.php?error=invalid_request');
    exit();
}

// Check jika sudah rate
$stmt = $conn->prepare("
    SELECT * FROM reviews 
    WHERE request_id = ? 
    AND reviewer_id = ?
");
$stmt->execute([$request_id, $user_id]);
$existing_review = $stmt->fetch();

if ($existing_review) {
    header('Location: my_requests.php?message=already_reviewed');
    exit();
}

$page_title = "Rate Service - " . htmlspecialchars($request['service_title']);
include 'includes/header2.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="my_requests.php">My Requests</a></li>
                    <li class="breadcrumb-item active">Rate Service</li>
                </ol>
            </nav>
            
            <!-- Rate Service Card -->
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-star-fill me-2"></i>Rate Service</h4>
                </div>
                
                <div class="card-body">
                    <!-- Service Info -->
                    <div class="alert alert-info">
                        <h5><?php echo htmlspecialchars($request['service_title']); ?></h5>
                        <p class="mb-1"><?php echo htmlspecialchars($request['service_description']); ?></p>
                        <hr>
                        <p class="mb-1">
                            <strong>Service with:</strong> <?php echo htmlspecialchars($request['user_to_rate_name']); ?>
                        </p>
                        <p class="mb-0">
                            <strong>Hours:</strong> <?php echo $request['hours_required']; ?> hours
                        </p>
                    </div>
                    
                    <!-- Rating Form -->
                    <form id="review-form" method="POST" action="ajax/submit_review.php">
                        <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
                        <input type="hidden" name="reviewee_id" value="<?php echo $request['user_to_rate_id']; ?>">
                        
                        <!-- Rating Stars -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Your Rating</label>
                            <div class="rating-stars text-center mb-2" style="font-size: 2.5rem;">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star rating-star" 
                                   data-rating="<?php echo $i; ?>" 
                                   style="cursor: pointer; color: #ddd; margin: 0 5px;"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="rating-value" value="0" required>
                            <div class="text-center text-muted small" id="rating-text">
                                Tap stars to rate (1-5)
                            </div>
                        </div>
                        
                        <!-- Review Comment -->
                        <div class="mb-4">
                            <label for="comment" class="form-label fw-bold">Your Review (Optional)</label>
                            <textarea class="form-control" id="comment" name="comment" 
                                      rows="4" placeholder="Share your experience with this service..."></textarea>
                            <div class="form-text">
                                Your review will help others in the community.
                            </div>
                        </div>
                        
                        <!-- Anonymous Option -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_anonymous" id="is_anonymous">
                                <label class="form-check-label" for="is_anonymous">
                                    Submit anonymously
                                </label>
                            </div>
                            <div class="form-text">
                                Your name will not be shown on this review.
                            </div>
                        </div>
                        
                        <!-- Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send-check me-2"></i>Submit Review
                            </button>
                            <a href="my_requests.php" class="btn btn-outline-secondary">
                                Skip for Now
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Rating Guidelines -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6><i class="bi bi-info-circle me-2"></i>Rating Guidelines</h6>
                    <ul class="small mb-0">
                        <li><strong>5 Stars:</strong> Excellent service, exceeded expectations</li>
                        <li><strong>4 Stars:</strong> Very good service, minor issues</li>
                        <li><strong>3 Stars:</strong> Satisfactory service, met expectations</li>
                        <li><strong>2 Stars:</strong> Below average, needs improvement</li>
                        <li><strong>1 Star:</strong> Poor service, would not recommend</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating-star:hover,
.rating-star.active {
    color: #ffc107 !important;
}
.rating-star:hover ~ .rating-star {
    color: #ddd !important;
}
</style>

<script>
// Star Rating System
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.rating-star');
    const ratingValue = document.getElementById('rating-value');
    const ratingText = document.getElementById('rating-text');
    
    const ratingTexts = {
        1: 'Poor - Would not recommend',
        2: 'Fair - Needs improvement',
        3: 'Good - Met expectations',
        4: 'Very Good - Exceeded expectations',
        5: 'Excellent - Outstanding service'
    };
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            ratingValue.value = rating;
            
            // Update stars display
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('active');
                    s.classList.remove('bi-star');
                    s.classList.add('bi-star-fill');
                } else {
                    s.classList.remove('active');
                    s.classList.remove('bi-star-fill');
                    s.classList.add('bi-star');
                }
            });
            
            // Update rating text
            ratingText.textContent = ratingTexts[rating] || 'Tap stars to rate';
        });
        
        // Hover effect
        star.addEventListener('mouseover', function() {
            const hoverRating = parseInt(this.getAttribute('data-rating'));
            stars.forEach((s, index) => {
                s.style.color = index < hoverRating ? '#ffc107' : '#ddd';
            });
        });
        
        star.addEventListener('mouseout', function() {
            const currentRating = parseInt(ratingValue.value);
            stars.forEach((s, index) => {
                s.style.color = index < currentRating ? '#ffc107' : '#ddd';
            });
        });
    });
    
    // Form submission
    document.getElementById('review-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const rating = parseInt(ratingValue.value);
        if (rating < 1 || rating > 5) {
            alert('Please select a rating (1-5 stars)');
            return;
        }
        
        const formData = new FormData(this);
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass me-2"></i>Submitting...';
        
        fetch('ajax/submit_review.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert('Thank you for your review!');
                window.location.href = 'my_requests.php?message=review_submitted';
            } else {
                alert('Error: ' + data.error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>