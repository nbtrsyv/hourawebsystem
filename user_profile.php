<?php
// user_profile.php - View Other User's Profile
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$viewer_id = $_SESSION['user_id'];
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: community.php');
    exit();
}

// Fetch user data
$stmt = $conn->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM services WHERE user_id = u.user_id) as total_services,
           (SELECT COUNT(*) FROM service_requests sr JOIN services s ON sr.service_id = s.service_id WHERE s.user_id = u.user_id AND sr.status = 'completed') as services_given,
           (SELECT COUNT(*) FROM service_requests WHERE requester_id = u.user_id AND status = 'completed') as services_received,
           (SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.user_id) as avg_rating,
           (SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.user_id) as reviews_count
    FROM users u
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: community.php');
    exit();
}

// Fetch user's services
$stmt = $conn->prepare("
    SELECT * FROM services 
    WHERE user_id = ? AND status = 'available'
    ORDER BY created_at DESC 
    LIMIT 6
");
$stmt->execute([$user_id]);
$services = $stmt->fetchAll();

// Fetch recent reviews
$stmt = $conn->prepare("
    SELECT r.*, u.full_name as reviewer_name, u.profile_image as reviewer_avatar
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.user_id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
    LIMIT 3
");
$stmt->execute([$user_id]);
$reviews = $stmt->fetchAll();
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="community.php">Community</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($user['full_name']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Left Column - Profile Info -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-body text-center">
                    <!-- Profile Image -->
                    <div class="mb-4">
                        <?php if($user['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 class="rounded-circle border border-4 border-primary"
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto"
                                 style="width: 150px; height: 150px; border: 4px solid var(--primary-purple);">
                                <i class="bi bi-person display-1" style="color: var(--primary-purple);"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- User Info -->
                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <!-- Stats -->
                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                                <?php echo $user['total_services']; ?>
                            </div>
                            <small class="text-muted">Services</small>
                        </div>
                        <div class="col-4">
                            <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                                <?php echo $user['avg_rating'] ? number_format($user['avg_rating'], 1) : '5.0'; ?>
                            </div>
                            <small class="text-muted">Rating</small>
                        </div>
                        <div class="col-4">
                            <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                                <?php echo $user['reviews_count']; ?>
                            </div>
                            <small class="text-muted">Reviews</small>
                        </div>
                    </div>
                    
                    <!-- Member Since -->
                    <div class="alert alert-light mb-4">
                        <i class="bi bi-calendar-check me-2"></i>
                        Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                    </div>
                    
                    <!-- Contact Info -->
                    <?php if($user['phone']): ?>
                    <div class="mb-3">
                        <i class="bi bi-telephone me-2 text-muted"></i>
                        <?php echo htmlspecialchars($user['phone']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($user['address']): ?>
                    <div class="mb-4">
                        <i class="bi bi-geo-alt me-2 text-muted"></i>
                        <?php echo htmlspecialchars($user['address']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div class="d-grid gap-2">
                        <a href="chat.php?to_user=<?php echo $user_id; ?>" 
                           class="btn btn-primary">
                            <i class="bi bi-chat me-2"></i>Send Message
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Details -->
        <div class="col-lg-8">
            <!-- Services Offered -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Services Offered</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($services)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-tools display-4 text-muted mb-3"></i>
                            <p class="text-muted">This member hasn't offered any services yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach($services as $service): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($service['title']); ?></h6>
                                        <p class="card-text small text-muted">
                                            <?php echo (strlen($service['description']) > 80) 
                                                ? substr(htmlspecialchars($service['description']), 0, 80) . '...' 
                                                : htmlspecialchars($service['description']); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-primary"><?php echo $service['hours_required']; ?>h</span>
                                            <a href="service_detail.php?id=<?php echo $service['service_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if(count($services) >= 6): ?>
                        <div class="text-center mt-3">
                            <a href="services.php?provider=<?php echo $user_id; ?>" 
                               class="btn btn-outline-primary">
                                View All Services
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reviews -->
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-star me-2"></i>Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($reviews)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-star display-4 text-muted mb-3"></i>
                            <p class="text-muted">No reviews yet. Be the first to review this member!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($reviews as $review): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-start mb-2">
                                <!-- Reviewer Avatar -->
                                <?php if($review['reviewer_avatar']): ?>
                                    <img src="<?php echo htmlspecialchars($review['reviewer_avatar']); ?>" 
                                         class="rounded-circle me-3"
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3"
                                         style="width: 40px; height: 40px;">
                                        <i class="bi bi-person text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($review['reviewer_name']); ?></h6>
                                        <div>
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <?php if($i <= $review['rating']): ?>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-star text-warning"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <?php if($review['comment']): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if($user['reviews_count'] > 3): ?>
                        <div class="text-center">
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                View All Reviews (<?php echo $user['reviews_count']; ?>)
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Community Stats -->
            <div class="card shadow mt-4">
                <div class="card-body">
                    <h5 class="mb-3">Community Contributions</h5>
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="display-4 fw-bold" style="color: var(--primary-purple);">
                                <?php echo $user['services_given']; ?>
                            </div>
                            <small>Services Given</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="display-4 fw-bold" style="color: var(--primary-purple);">
                                <?php echo $user['services_received']; ?>
                            </div>
                            <small>Services Received</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="display-4 fw-bold" style="color: var(--primary-purple);">
                                <?php echo $user['time_balance']; ?>
                            </div>
                            <small>Time Balance</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges Section -->
    <div class="container mt-5 mb-5">
        <div class="row mb-4">
            <div class="col-12">
                <h3 style="color: var(--dark-purple); font-weight: 800;">🏆 Badges & Achievements</h3>
            </div>
        </div>

        <div class="row g-4">
            <?php
            // Get user's earned badges
            $badges_query = "
                SELECT b.*, ub.earned_at
                FROM badges b
                INNER JOIN user_badges ub ON b.badge_id = ub.badge_id
                WHERE ub.user_id = ?
                ORDER BY ub.earned_at DESC
            ";
            
            $stmt = $conn->prepare($badges_query);
            $stmt->execute([$user_id]);
            $earned_badges = $stmt->fetchAll();
            
            if (count($earned_badges) > 0):
                foreach ($earned_badges as $badge):
            ?>
            <div class="col-md-4 col-lg-3">
                <div class="card h-100 border-0 shadow-lg">
                    <div class="card-body text-center p-4">
                        <div style="font-size: 2.5rem; margin-bottom: 15px;">
                            <?php echo $badge['icon']; ?>
                        </div>
                        
                        <h6 style="color: var(--dark-purple); font-weight: 700; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($badge['badge_name']); ?>
                        </h6>
                        
                        <p class="text-muted small mb-3">
                            <?php echo htmlspecialchars($badge['description']); ?>
                        </p>
                        
                        <div style="background: #d4edda; padding: 8px; border-radius: 8px;">
                            <small style="color: var(--success-green); font-weight: 600;">
                                ✓ Earned <?php echo date('M d, Y', strtotime($badge['earned_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
                endforeach;
            else:
            ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <p class="mb-0">No badges earned yet. This user is working on their achievements!</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>