<?php
// service_detail.php
require_once 'includes/session_start.php';
require_once 'config/database.php';
require_once 'config/settings.php';

// Get service ID from URL
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch service details
$service = null;
if ($service_id > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name, u.email, u.created_at as member_since 
        FROM services s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.service_id = ?
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If service not found, redirect
if (!$service) {
    header('Location: services.php');
    exit();
}

// Get provider's rating
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
    FROM reviews 
    WHERE reviewee_id = ?
");
$stmt->execute([$service['user_id']]);
$rating = $stmt->fetch(PDO::FETCH_ASSOC);

// Dalam service_details.php, tambah query untuk reviews
$stmt = $conn->prepare("
    SELECT r.*, 
           u.full_name as reviewer_name,
           s.title as service_title
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.user_id
    LEFT JOIN service_requests sr ON r.request_id = sr.request_id
    LEFT JOIN services s ON sr.service_id = s.service_id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$service['user_id']]);
$reviews = $stmt->fetchAll();

// Count total reviews
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reviews WHERE reviewee_id = ?");
$stmt->execute([$service['user_id']]);
$total_reviews = $stmt->fetch()['total'];

// Get similar services
$stmt = $conn->prepare("
    SELECT s.*, u.full_name 
    FROM services s 
    JOIN users u ON s.user_id = u.user_id 
    WHERE s.service_id != ? 
    AND s.status = 'available' 
    LIMIT 3
");
$stmt->execute([$service_id]);
$similar_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="services.php">Browse Services</a></li>
            <li class="breadcrumb-item active">Service Details</li>
        </ol>
    </nav>
    
    <!-- Main Service Details -->
    <div class="row">
        <!-- Left Column: Service Info -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">
                        <i class="bi bi-tools me-2"></i><?php echo htmlspecialchars($service['title']); ?>
                    </h2>
                </div>
                <div class="card-body">
                    <!-- Service Description -->
                    <div class="mb-4">
                        <h5 class="text-primary">Description</h5>
                        <p class="lead"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                    </div>
                    
                    <!-- Service Details -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-clock-history fs-4 text-primary me-3"></i>
                                <div>
                                    <h6 class="mb-1">Time Required</h6>
                                    <p class="mb-0 fw-bold"><?php echo $service['hours_required']; ?> hours</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-geo-alt fs-4 text-primary me-3"></i>
                                <div>
                                    <h6 class="mb-1">Location</h6>
                                    <p class="mb-0"><?php echo $service['location'] ? htmlspecialchars($service['location']) : 'Flexible'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                    <small>
                    <i class="bi bi-info-circle"></i> 
                    System limits: Min <?php echo MIN_HOURS; ?>h | Max <?php echo MAX_HOURS; ?>h per service
                    </small>
                    </div>
                    
                    <!-- Service Status -->
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle fs-4 me-3"></i>
                            <div>
                                <h6 class="mb-1">Status: <?php echo ucfirst($service['status']); ?></h6>
                                <p class="mb-0">
                                    <?php if($service['status'] == 'available'): ?>
                                        This service is currently available. You can request help now.
                                    <?php else: ?>
                                        This service is currently in progress or completed.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Provider Information -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h5 class="mb-0">About the Provider</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" 
                                 style="width: 100px; height: 100px; border: 3px solid var(--primary-purple);">
                                <i class="bi bi-person fs-1" style="color: var(--primary-purple);"></i>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <h4><?php echo htmlspecialchars($service['full_name']); ?></h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="bi bi-envelope me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($service['email']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="bi bi-calendar me-2 text-muted"></i>
                                        Member since: <?php echo date('M Y', strtotime($service['member_since'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="me-3">
                                            <span class="fw-bold">Rating:</span>
                                        </div>
                                        <div>
                                            <?php if($rating['avg_rating']): ?>
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                    $avg_rating = round($rating['avg_rating'], 1);
                                                    for($i = 1; $i <= 5; $i++): 
                                                        if($i <= floor($avg_rating)): ?>
                                                            <i class="bi bi-star-fill text-warning me-1"></i>
                                                        <?php elseif($i == ceil($avg_rating) && fmod($avg_rating, 1) >= 0.5): ?>
                                                            <i class="bi bi-star-half text-warning me-1"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-star text-warning me-1"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?php echo $avg_rating; ?> (<?php echo $rating['total_reviews']; ?> reviews)</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No ratings yet</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Action & Similar Services -->
        <div class="col-lg-4">
            <!-- Action Card -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Request This Service</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <div class="display-4 fw-bold text-primary"><?php echo $service['hours_required']; ?></div>
                        <div class="text-muted">hours required</div>
                    </div>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- Logged in user -->
                        <a href="request_service.php?id=<?php echo $service_id; ?>" 
                           class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="bi bi-check-circle me-2"></i>Request Help
                        </a>
                        <p class="small text-muted">
                            You'll need <?php echo $service['hours_required']; ?> hours in your Time Wallet
                        </p>
                    <?php else: ?>
                        <!-- Guest user -->
                        <a href="login.php" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login to Request
                        </a>
                        <p class="small text-muted">
                            You need an account to request services
                        </p>
                        <a href="register.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus me-2"></i>Create Free Account
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Similar Services -->
            <?php if(!empty($similar_services)): ?>
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Similar Services</h5>
                </div>
                <div class="card-body">
                    <?php foreach($similar_services as $similar): ?>
                    <div class="card mb-3 border-start border-primary">
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="service_detail.php?id=<?php echo $similar['service_id']; ?>" 
                                   class="text-decoration-none">
                                    <?php echo htmlspecialchars($similar['title']); ?>
                                </a>
                            </h6>
                            <p class="card-text small">
                                <?php echo substr(htmlspecialchars($similar['description']), 0, 80); ?>...
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> <?php echo $similar['hours_required']; ?>h
                                </small>
                                <a href="service_detail.php?id=<?php echo $similar['service_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Dalam service_details.php, tambah section untuk reviews -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-star me-2"></i>Reviews 
            <span class="badge bg-primary"><?php echo $total_reviews; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if(empty($reviews)): ?>
            <div class="text-center py-4">
                <i class="bi bi-chat-square-text display-4 text-muted mb-3"></i>
                <p class="text-muted">No reviews yet</p>
                <p>Be the first to review this service provider!</p>
            </div>
        <?php else: ?>
            <?php foreach($reviews as $review): ?>
            <div class="review-item border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <!-- Star Rating -->
                        <div class="mb-1">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <?php if($i <= $review['rating']): ?>
                                    <i class="bi bi-star-fill text-warning"></i>
                                <?php else: ?>
                                    <i class="bi bi-star text-muted"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span class="ms-2 small text-muted"><?php echo $review['rating']; ?>/5</span>
                        </div>
                        
                        <!-- Reviewer Name -->
                        <strong class="d-block">
                            <?php if($review['is_anonymous']): ?>
                                Anonymous User
                            <?php else: ?>
                                <?php echo htmlspecialchars($review['reviewer_name']); ?>
                            <?php endif; ?>
                        </strong>
                        
                        <!-- Review Date -->
                        <small class="text-muted">
                            <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                        </small>
                    </div>
                    
                    <!-- Service Info if available -->
                    <?php if(isset($review['service_title'])): ?>
                    <div class="text-end">
                        <small class="text-muted">For: <?php echo htmlspecialchars($review['service_title']); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Review Comment -->
                <?php if(!empty($review['comment'])): ?>
                <div class="mt-2">
                    <p class="mb-0">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <!-- View All Reviews Link -->
            <?php if($total_reviews > 3): ?>
            <div class="text-center mt-3">
                <a href="user_reviews.php?user_id=<?php echo $service['user_id']; ?>" 
                   class="btn btn-outline-primary btn-sm">
                    View All <?php echo $total_reviews; ?> Reviews
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>