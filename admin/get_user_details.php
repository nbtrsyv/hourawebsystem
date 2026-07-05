<?php
// admin/get_user_details.php
session_start();
require_once '../config/database.php';

// Check admin login
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    die('Access denied');
}

$userId = $_GET['id'] ?? 0;

// Get user details
$stmt = $conn->prepare("
    SELECT u.*,
           COUNT(DISTINCT s.service_id) as services_offered,
           COUNT(DISTINCT sr.request_id) as requests_made,
           COUNT(DISTINCT r.review_id) as reviews_received,
           AVG(rev.rating) as avg_rating
    FROM users u
    LEFT JOIN services s ON u.user_id = s.user_id
    LEFT JOIN service_requests sr ON u.user_id = sr.requester_id
    LEFT JOIN reviews r ON u.user_id = r.reviewee_id
    LEFT JOIN reviews rev ON u.user_id = rev.reviewee_id
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo '<div class="alert alert-danger">User not found</div>';
    exit();
}
?>

<div class="user-details">
    <!-- Header -->
    <div class="text-center mb-4">
        <div class="user-avatar-large mb-3">
            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
        </div>
        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
        <p class="text-muted">User ID: #<?php echo $user['user_id']; ?></p>
    </div>
    
    <!-- Basic Info -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="fas fa-envelope"></i> Contact</h6>
                <p class="mb-1"><strong>Email:</strong> <?php echo $user['email']; ?></p>
                <p class="mb-0"><strong>Phone:</strong> <?php echo $user['phone'] ?: 'Not provided'; ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="fas fa-info-circle"></i> Account Info</h6>
                <p class="mb-1"><strong>Role:</strong> 
                    <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </p>
                <p class="mb-0"><strong>Status:</strong> 
                    <span class="badge bg-<?php 
                        echo $user['status'] == 'active' ? 'success' : 
                              ($user['status'] == 'suspended' ? 'warning' : 'danger'); ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value"><?php echo number_format($user['time_balance'], 2); ?></div>
                <div class="stat-label">Time Balance</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value"><?php echo $user['services_offered']; ?></div>
                <div class="stat-label">Services Offered</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value"><?php echo $user['requests_made']; ?></div>
                <div class="stat-label">Requests Made</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value"><?php echo number_format($user['avg_rating'] ?? 0, 1); ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>
    </div>
    
    <!-- Address -->
    <?php if($user['address']): ?>
    <div class="info-card mb-4">
        <h6><i class="fas fa-map-marker-alt"></i> Address</h6>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($user['address'])); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Dates -->
    <div class="row">
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="fas fa-calendar-plus"></i> Account Created</h6>
                <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="fas fa-calendar-check"></i> Last Updated</h6>
                <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="mt-4 pt-3 border-top">
        <div class="d-flex justify-content-between">
            <button class="btn btn-outline-primary" onclick="window.location.href='users.php?search=<?php echo urlencode($user['email']); ?>'">
                <i class="fas fa-external-link-alt"></i> View in List
            </button>
            <div class="btn-group">
                <?php if($user['status'] == 'active'): ?>
                <button class="btn btn-warning" onclick="suspendUser(<?php echo $user['user_id']; ?>)">
                    <i class="fas fa-pause"></i> Suspend
                </button>
                <?php else: ?>
                <button class="btn btn-success" onclick="activateUser(<?php echo $user['user_id']; ?>)">
                    <i class="fas fa-play"></i> Activate
                </button>
                <?php endif; ?>
                <button class="btn btn-danger" onclick="banUser(<?php echo $user['user_id']; ?>)">
                    <i class="fas fa-ban"></i> Ban
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.user-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    margin: 0 auto;
}

.info-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
}

.info-card h6 {
    color: var(--dark-green);
    margin-bottom: 10px;
    font-size: 0.9rem;
    font-weight: 600;
}

.stat-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-green);
}

.stat-label {
    font-size: 0.8rem;
    color: #666;
    margin-top: 5px;
}
</style>

<script>
// These functions are defined in users.php
function suspendUser(id) {
    if(confirm('Suspend this user?')) {
        window.parent.location.href = `users.php?action=suspend&id=${id}`;
    }
}

function activateUser(id) {
    if(confirm('Activate this user?')) {
        window.parent.location.href = `users.php?action=activate&id=${id}`;
    }
}

function banUser(id) {
    if(confirm('Ban this user?')) {
        window.parent.location.href = `users.php?action=ban&id=${id}`;
    }
}
</script>