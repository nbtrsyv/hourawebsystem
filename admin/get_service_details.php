<?php
// admin/get_service_details.php
session_start();
require_once '../config/database.php';

// Check admin login
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    die('Access denied');
}

$serviceId = $_GET['id'] ?? 0;

// Get service details
$stmt = $conn->prepare("
    SELECT s.*, 
           u.full_name as provider_name,
           u.email as provider_email,
           u.phone as provider_phone,
           c.name as category_name
    FROM services s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN categories c ON s.category_id = c.category_id
    WHERE s.service_id = ?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    echo '<div class="alert alert-danger">Service not found</div>';
    exit();
}
?>

<div class="service-details">
    <!-- Header -->
    <div class="mb-4">
        <h4><?php echo htmlspecialchars($service['title']); ?></h4>
        <div class="d-flex gap-3">
            <span class="badge bg-<?php echo $service['is_featured'] ? 'warning text-dark' : 'secondary'; ?>">
                <i class="fas fa-star"></i> <?php echo $service['is_featured'] ? 'Featured' : 'Normal'; ?>
            </span>
            <span class="badge bg-info">
                <?php echo ucfirst($service['status']); ?>
            </span>
            <span class="badge bg-primary">
                <?php echo $service['category_name']; ?>
            </span>
        </div>
    </div>
    
    <!-- Description -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-file-alt"></i> Description</h6>
        </div>
        <div class="card-body">
            <?php echo nl2br(htmlspecialchars($service['description'])); ?>
        </div>
    </div>
    
    <!-- Details -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Service Info</h6>
                </div>
                <div class="card-body">
                    <p><strong>Hours Required:</strong> <?php echo $service['hours_required']; ?> hours</p>
                    <p><strong>Location:</strong> <?php echo $service['location'] ?: 'Not specified'; ?></p>
                    <p><strong>Views:</strong> <?php echo $service['views_count']; ?></p>
                    <p><strong>Created:</strong> <?php echo date('d/m/Y H:i', strtotime($service['created_at'])); ?></p>
                    <p><strong>Updated:</strong> <?php echo date('d/m/Y H:i', strtotime($service['updated_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user"></i> Provider Info</h6>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($service['provider_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo $service['provider_email']; ?></p>
                    <p><strong>Phone:</strong> <?php echo $service['provider_phone'] ?: 'Not provided'; ?></p>
                    <div class="mt-3">
                        <a href="users.php?search=<?php echo urlencode($service['provider_name']); ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt"></i> View Provider Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <?php
    // Get request stats for this service
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests
        FROM service_requests 
        WHERE service_id = ?
    ");
    $statsStmt->execute([$serviceId]);
    $stats = $statsStmt->fetch();
    ?>
    
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Request Statistics</h6>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-4">
                    <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="col-4">
                    <div class="stat-value"><?php echo $stats['completed_requests']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="col-4">
                    <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="mt-4 pt-3 border-top">
        <div class="d-flex justify-content-between">
            <button class="btn btn-outline-secondary" onclick="closeServiceModal()">
                <i class="fas fa-times"></i> Close
            </button>
            <div class="btn-group">
                <button class="btn btn-danger" 
                        onclick="if(confirm('Delete this service?')) window.parent.deleteService(<?php echo $serviceId; ?>)">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    color: var(--primary-green);
}

.stat-label {
    font-size: 0.9rem;
    color: #666;
}
</style>