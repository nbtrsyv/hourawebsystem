<?php
// my_services.php - Manage My Services
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle service deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $service_id = (int)$_GET['id'];
    
    // Verify ownership
    $stmt = $conn->prepare("SELECT user_id FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    
    if ($service && $service['user_id'] == $user_id) {
        $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
        if ($stmt->execute([$service_id])) {
            $success = "Service deleted successfully!";
        } else {
            $error = "Failed to delete service.";
        }
    } else {
        $error = "Service not found or you don't have permission.";
    }
}

// Fetch user's services
$stmt = $conn->prepare("
    SELECT s.*, c.name as category_name,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.service_id = s.service_id) as request_count
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.category_id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user_id]);
$services = $stmt->fetchAll();
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2"><i class="bi bi-tools me-2"></i>My Services</h1>
            <p class="text-muted">Manage services you're offering to the community</p>
        </div>
        <a href="add_service.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Offer New Service
        </a>
    </div>
    
    <?php if($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Services List -->
    <?php if(empty($services)): ?>
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="bi bi-tools display-1 text-muted mb-3"></i>
                <h4>No Services Yet</h4>
                <p class="text-muted mb-4">
                    Start offering services to earn time credits and help your community!
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach($services as $service): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <!-- Service Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($service['title']); ?></h5>
                            <span class="badge bg-<?php 
                                echo $service['status'] == 'available' ? 'success' : 
                                     ($service['status'] == 'in_progress' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($service['status']); ?>
                            </span>
                        </div>
                        
                        <!-- Service Info -->
                        <p class="card-text text-muted mb-3">
                            <?php echo (strlen($service['description']) > 100) 
                                ? substr(htmlspecialchars($service['description']), 0, 100) . '...' 
                                : htmlspecialchars($service['description']); ?>
                        </p>
                        
                        <!-- Details -->
                        <ul class="list-unstyled small mb-4">
                            <li class="mb-2">
                                <i class="bi bi-clock text-primary me-2"></i>
                                <strong><?php echo $service['hours_required']; ?> hours</strong> per service
                            </li>
                            <?php if($service['category_name']): ?>
                            <li class="mb-2">
                                <i class="bi bi-tag text-primary me-2"></i>
                                <?php echo htmlspecialchars($service['category_name']); ?>
                            </li>
                            <?php endif; ?>
                            <?php if($service['location']): ?>
                            <li class="mb-2">
                                <i class="bi bi-geo-alt text-primary me-2"></i>
                                <?php echo htmlspecialchars($service['location']); ?>
                            </li>
                            <?php endif; ?>
                            <li>
                                <i class="bi bi-bell text-primary me-2"></i>
                                <strong><?php echo $service['request_count']; ?></strong> requests
                            </li>
                        </ul>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-between">
                            <a href="service_detail.php?id=<?php echo $service['service_id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="edit_service.php?id=<?php echo $service['service_id']; ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                            <a href="my_services.php?delete=1&id=<?php echo $service['service_id']; ?>" 
                               class="btn btn-outline-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this service?');">
                                <i class="bi bi-trash me-1"></i>Delete
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <small class="text-muted">
                            Created: <?php echo date('M d, Y', strtotime($service['created_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Stats -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="mb-3">Service Statistics</h5>
                <div class="row text-center">
                    <?php
                    $total_services = count($services);
                    $active_services = count(array_filter($services, fn($s) => $s['status'] == 'available'));
                    $total_requests = array_sum(array_column($services, 'request_count'));
                    $total_hours = array_sum(array_column($services, 'hours_required'));
                    ?>
                    <div class="col-md-3 mb-3">
                        <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                            <?php echo $total_services; ?>
                        </div>
                        <small>Total Services</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                            <?php echo $active_services; ?>
                        </div>
                        <small>Active Services</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                            <?php echo $total_requests; ?>
                        </div>
                        <small>Total Requests</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                            <?php echo $total_hours; ?>
                        </div>
                        <small>Hours Offered</small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>