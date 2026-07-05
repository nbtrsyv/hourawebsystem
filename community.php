<?php
// community.php - Community Members
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

// Build query
$sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM services WHERE user_id = u.user_id AND status = 'available') as services_count,
               (SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.user_id) as avg_rating
        FROM users u
        WHERE u.role = 'user' AND u.user_id != ?";
$params = [$user_id];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Get total member count
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$total_members = $stmt->fetch()['count'];
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2"><i class="bi bi-people me-2"></i>Community Members</h1>
            <p class="text-muted"><?php echo $total_members; ?> members in Houra community</p>
        </div>
        <a href="services.php" class="btn btn-outline-primary">
            <i class="bi bi-search me-2"></i>Browse Services
        </a>
    </div>
    
    <!-- Search & Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search members by name or email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Members Grid -->
    <?php if(empty($members)): ?>
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="bi bi-people display-4 text-muted mb-3"></i>
                <h4>No Members Found</h4>
                <p class="text-muted">
                    <?php echo empty($search) 
                        ? 'No other members in the community yet.' 
                        : 'No members found matching your search.'; ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach($members as $member): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <!-- Profile Image -->
                        <div class="mb-3">
                            <?php if($member['profile_image']): ?>
                                <img src="<?php echo htmlspecialchars($member['profile_image']); ?>" 
                                     class="rounded-circle border border-3 border-primary"
                                     style="width: 100px; height: 100px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto"
                                     style="width: 100px; height: 100px; border: 3px solid var(--primary-purple);">
                                    <i class="bi bi-person display-4" style="color: var(--primary-purple);"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Member Info -->
                        <h5 class="card-title"><?php echo htmlspecialchars($member['full_name']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($member['email']); ?></p>
                        
                        <!-- Stats -->
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="fw-bold fs-5" style="color: var(--primary-purple);">
                                    <?php echo $member['services_count']; ?>
                                </div>
                                <small class="text-muted">Services</small>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold fs-5" style="color: var(--primary-purple);">
                                    <?php echo number_format($member['avg_rating'] ?? 5.0, 1); ?>
                                </div>
                                <small class="text-muted">Rating</small>
                            </div>
                        </div>
                        
                        <!-- Member Since -->
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="bi bi-calendar me-1"></i>
                                Member since <?php echo date('M Y', strtotime($member['created_at'])); ?>
                            </small>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-center gap-2">
                            <a href="user_profile.php?id=<?php echo $member['user_id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View Profile
                            </a>
                            <a href="services.php?provider=<?php echo $member['user_id']; ?>" 
                               class="btn btn-outline-info btn-sm">
                                <i class="bi bi-tools me-1"></i>Services
                            </a>
                            <a href="chat.php?to_user=<?php echo $member['user_id']; ?>" 
                               class="btn btn-outline-success btn-sm">
                                <i class="bi bi-chat me-1"></i>Message
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>