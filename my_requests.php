<?php
// my_requests.php - My Service Requests
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle success messages
$success = '';
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'review_submitted':
            $success = "Thank you for your review! Your feedback helps improve the community.";
            break;
        case 'already_reviewed':
            $success = "You have already reviewed this service.";
            break;
        case 'service_completed':
            $success = "Service marked as completed successfully!";
            break;
    }
}

// Handle request cancellation
if (isset($_GET['cancel']) && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    // Verify ownership
    $stmt = $conn->prepare("SELECT * FROM service_requests WHERE request_id = ? AND requester_id = ?");
    $stmt->execute([$request_id, $user_id]);
    $request = $stmt->fetch();
    
    if ($request && $request['status'] == 'pending') {
        $stmt = $conn->prepare("UPDATE service_requests SET status = 'cancelled' WHERE request_id = ?");
        if ($stmt->execute([$request_id])) {
            // Update service status back to available
            $stmt = $conn->prepare("UPDATE services SET status = 'available' WHERE service_id = ?");
            $stmt->execute([$request['service_id']]);
            $success = "Request cancelled successfully.";
        }
    }
}

// Fetch user's service requests
$stmt = $conn->prepare("
    SELECT sr.*, s.title, s.description, s.hours_required,
           u.full_name as provider_name, u.profile_image as provider_avatar
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users u ON s.user_id = u.user_id
    WHERE sr.requester_id = ?
    ORDER BY sr.created_at DESC
");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll();
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Success Message Display -->
    <?php if(!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2"><i class="bi bi-list-check me-2"></i>My Requests</h1>
            <p class="text-muted">Track services you've requested from the community</p>
        </div>
        <a href="services.php" class="btn btn-primary">
            <i class="bi bi-search me-2"></i>Request More Services
        </a>
    </div>
    
    <!-- Requests Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#active">Active</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#pending">Pending</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#completed">Completed</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#cancelled">Cancelled</a>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Active Requests (accepted) -->
        <div class="tab-pane fade show active" id="active">
            <?php 
            $active_requests = array_filter($requests, fn($r) => $r['status'] == 'accepted');
            if(empty($active_requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-play-circle display-4 text-muted mb-3"></i>
                        <h4>No Active Requests</h4>
                        <p class="text-muted">You don't have any active service requests.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($active_requests as $req): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">In Progress</h6>
                            </div>
                            <div class="card-body">
                                <!-- Provider Info -->
                                <div class="d-flex align-items-center mb-3">
                                    <?php if($req['provider_avatar']): ?>
                                        <img src="<?php echo htmlspecialchars($req['provider_avatar']); ?>" 
                                             class="rounded-circle me-3"
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3"
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person fs-4 text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($req['provider_name']); ?></h6>
                                        <small class="text-muted">Service Provider</small>
                                    </div>
                                </div>
                                
                                <!-- Service Info -->
                                <h6><?php echo htmlspecialchars($req['title']); ?></h6>
                                <p class="text-muted small mb-3">
                                    <?php echo (strlen($req['description']) > 100) 
                                        ? substr(htmlspecialchars($req['description']), 0, 100) . '...' 
                                        : htmlspecialchars($req['description']); ?>
                                </p>
                                
                                <!-- Details -->
                                <div class="row small mb-3">
                                    <div class="col-6">
                                        <i class="bi bi-clock text-success me-2"></i>
                                        <?php echo $req['hours_required']; ?> hours
                                    </div>
                                    <div class="col-6">
                                        <?php if($req['accepted_at']): ?>
                                            <i class="bi bi-calendar-check text-success me-2"></i>
                                            <?php echo date('M d', strtotime($req['accepted_at'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="d-flex justify-content-between">
                                    <a href="chat.php?to_user=<?php 
                                        $stmt = $conn->prepare("SELECT user_id FROM services WHERE service_id = ?");
                                        $stmt->execute([$req['service_id']]);
                                        $provider = $stmt->fetch();
                                        echo $provider['user_id'] ?? 0;
                                    ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-chat me-1"></i>Message Provider
                                    </a>
                                    <button class="btn btn-outline-warning btn-sm" disabled>
                                        <i class="bi bi-clock me-1"></i>In Progress
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pending Requests -->
        <div class="tab-pane fade" id="pending">
            <?php 
            $pending_requests = array_filter($requests, fn($r) => $r['status'] == 'pending');
            if(empty($pending_requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-hourglass-split display-4 text-muted mb-3"></i>
                        <h4>No Pending Requests</h4>
                        <p class="text-muted">You don't have any pending service requests.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($pending_requests as $req): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-warning">
                            <div class="card-header bg-warning text-dark">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Awaiting Response</h6>
                                    <span class="badge bg-warning"><?php echo $req['hours_required']; ?> hours</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($req['title']); ?></h6>
                                <p class="small text-muted mb-3">
                                    Waiting for <?php echo htmlspecialchars($req['provider_name']); ?> to respond to your request
                                </p>
                                
                                <?php if($req['scheduled_date']): ?>
                                <div class="alert alert-light small">
                                    <i class="bi bi-calendar me-2"></i>
                                    Requested for: <?php echo date('M d, Y', strtotime($req['scheduled_date'])); ?>
                                    <?php if($req['scheduled_time']): ?>
                                        at <?php echo date('h:i A', strtotime($req['scheduled_time'])); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between mt-3">
                                    <a href="service_detail.php?id=<?php echo $req['service_id']; ?>" 
                                       class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-eye me-1"></i>View Service
                                    </a>
                                    <a href="my_requests.php?cancel=1&id=<?php echo $req['request_id']; ?>" 
                                       class="btn btn-outline-danger btn-sm"
                                       onclick="return confirm('Cancel this request?');">
                                        <i class="bi bi-x-circle me-1"></i>Cancel Request
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <small class="text-muted">
                                    Requested: <?php echo date('M d, h:i A', strtotime($req['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Completed Requests -->
        <div class="tab-pane fade" id="completed">
            <?php 
            $completed_requests = array_filter($requests, fn($r) => $r['status'] == 'completed');
            if(empty($completed_requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-check-circle display-4 text-muted mb-3"></i>
                        <h4>No Completed Requests</h4>
                        <p class="text-muted">You haven't completed any service requests yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($completed_requests as $req): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-secondary">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">Completed</h6>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($req['title']); ?></h6>
                                <p class="small text-muted mb-3">
                                    Provided by: <?php echo htmlspecialchars($req['provider_name']); ?>
                                </p>
                                
                                <div class="row small mb-3">
                                    <div class="col-6">
                                        <i class="bi bi-clock text-success me-2"></i>
                                        <span class="text-danger">-<?php echo $req['hours_required']; ?> hours</span>
                                    </div>
                                    <div class="col-6">
                                        <?php if($req['completed_at']): ?>
                                            <i class="bi bi-calendar-check text-success me-2"></i>
                                            <?php echo date('M d', strtotime($req['completed_at'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="rate_service.php?request_id=<?php echo $req['request_id']; ?>" class="btn btn-outline-warning btn-sm">
                                        <i class="bi bi-star me-1"></i>Rate Service
                                    </a>
                                    <a href="chat.php?to_user=<?php 
                                        $stmt = $conn->prepare("SELECT user_id FROM services WHERE service_id = ?");
                                        $stmt->execute([$req['service_id']]);
                                        $provider = $stmt->fetch();
                                        echo $provider['user_id'] ?? 0;
                                    ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-chat me-1"></i>Message Again
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cancelled Requests -->
        <div class="tab-pane fade" id="cancelled">
            <?php 
            $cancelled_requests = array_filter($requests, fn($r) => $r['status'] == 'cancelled');
            if(empty($cancelled_requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-x-circle display-4 text-muted mb-3"></i>
                        <h4>No Cancelled Requests</h4>
                        <p class="text-muted">You haven't cancelled any service requests.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Provider</th>
                                <th>Hours</th>
                                <th>Requested On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cancelled_requests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['title']); ?></td>
                                <td><?php echo htmlspecialchars($req['provider_name']); ?></td>
                                <td><?php echo $req['hours_required']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <a href="service_detail.php?id=<?php echo $req['service_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        Request Again
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>