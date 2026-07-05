<?php
// request_detail.php - View & Respond to Service Request
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
    header('Location: service_requests.php');
    exit();
}

// Fetch request details
$stmt = $conn->prepare("
    SELECT sr.*, s.title, s.description, s.hours_required, s.user_id as provider_id,
           u1.full_name as requester_name, u1.email as requester_email, u1.profile_image as requester_avatar,
           u2.full_name as provider_name
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users u1 ON sr.requester_id = u1.user_id
    JOIN users u2 ON s.user_id = u2.user_id
    WHERE sr.request_id = ? AND s.user_id = ?
");
$stmt->execute([$request_id, $user_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: service_requests.php');
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $message = trim($_POST['message'] ?? '');
    
    if ($action == 'accept') {
        $stmt = $conn->prepare("
            UPDATE service_requests 
            SET status = 'accepted', 
                accepted_at = NOW(),
                notes = CONCAT(notes, '\nProvider: ', ?)
            WHERE request_id = ?
        ");
        if ($stmt->execute([$message, $request_id])) {
            // Update service status
            $stmt = $conn->prepare("UPDATE services SET status = 'in_progress' WHERE service_id = ?");
            $stmt->execute([$request['service_id']]);
            
            // Send notification to requester
            $notif_msg = "Your request for '{$request['title']}' has been accepted!";
            $sql = "INSERT INTO notifications (user_id, title, message, type, related_id) 
                    VALUES (?, 'Request Accepted', ?, 'request', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$request['requester_id'], $notif_msg, $request_id]);
            
            $success = "Request accepted successfully!";
        }
    } elseif ($action == 'reject') {
        $stmt = $conn->prepare("
            UPDATE service_requests 
            SET status = 'rejected',
                notes = CONCAT(notes, '\nRejection reason: ', ?)
            WHERE request_id = ?
        ");
        if ($stmt->execute([$message, $request_id])) {
            // Update service status back to available
            $stmt = $conn->prepare("UPDATE services SET status = 'available' WHERE service_id = ?");
            $stmt->execute([$request['service_id']]);
            $success = "Request rejected.";
        }
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="service_requests.php">Service Requests</a></li>
            <li class="breadcrumb-item active">Request Details</li>
        </ol>
    </nav>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <div class="mt-2">
                <a href="service_requests.php" class="btn btn-sm btn-outline-success">Back to Requests</a>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Left Column - Request Details -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Request Details</h4>
                </div>
                <div class="card-body">
                    <!-- Service Info -->
                    <h5><?php echo htmlspecialchars($request['title']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($request['description']); ?></p>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong><i class="bi bi-clock text-primary me-2"></i>Hours Required:</strong>
                                <?php echo $request['hours_required']; ?> hours
                            </div>
                            <div class="mb-3">
                                <strong><i class="bi bi-calendar text-primary me-2"></i>Requested Date:</strong>
                                <?php echo $request['scheduled_date'] 
                                    ? date('F j, Y', strtotime($request['scheduled_date'])) 
                                    : 'Not specified'; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong><i class="bi bi-person text-primary me-2"></i>Requester:</strong>
                                <?php echo htmlspecialchars($request['requester_name']); ?>
                            </div>
                            <div class="mb-3">
                                <strong><i class="bi bi-envelope text-primary me-2"></i>Email:</strong>
                                <?php echo htmlspecialchars($request['requester_email']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Notes -->
                    <?php if($request['notes']): ?>
                    <div class="alert alert-info">
                        <h6><i class="bi bi-chat-text me-2"></i>Additional Notes</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Status Badge -->
                    <div class="alert alert-<?php 
                        echo $request['status'] == 'pending' ? 'warning' :
                             ($request['status'] == 'accepted' ? 'info' :
                             ($request['status'] == 'completed' ? 'success' : 'secondary'));
                    ?>">
                        <strong>Status:</strong> <?php echo ucfirst($request['status']); ?>
                        <?php if($request['accepted_at']): ?>
                            <br><small>Accepted on: <?php echo date('F j, Y', strtotime($request['accepted_at'])); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Actions -->
        <div class="col-lg-4">
            <?php if($request['status'] == 'pending'): ?>
                <!-- Accept/Reject Forms -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Accept Request</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="accept">
                            
                            <div class="mb-3">
                                <label class="form-label">Message to Requester (Optional)</label>
                                <textarea class="form-control" name="message" rows="3" 
                                          placeholder="Add a note for the requester..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check-circle me-2"></i>Accept Request
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Reject Request</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="reject">
                            
                            <div class="mb-3">
                                <label class="form-label">Reason for Rejection</label>
                                <textarea class="form-control" name="message" rows="3" 
                                          placeholder="Why are you rejecting this request? (optional)"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-danger w-100"
                                    onclick="return confirm('Are you sure you want to reject this request?');">
                                <i class="bi bi-x-circle me-2"></i>Reject Request
                            </button>
                        </form>
                    </div>
                </div>
                
            <?php elseif($request['status'] == 'accepted'): ?>
                <!-- Complete Service -->
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Complete Service</h5>
                    </div>
                    <div class="card-body text-center">
                        <p>You have accepted this request. Once you complete the service, mark it as done.</p>
                        <a href="service_requests.php?action=complete&id=<?php echo $request_id; ?>" 
                           class="btn btn-warning w-100"
                           onclick="return confirm('Mark this service as completed? Hours will be transferred.');">
                            <i class="bi bi-check-circle me-2"></i>Mark as Completed
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- View Only -->
                <div class="card shadow">
                    <div class="card-body text-center">
                        <p>This request has been <?php echo $request['status']; ?>.</p>
                        <a href="chat.php?to_user=<?php echo $request['requester_id']; ?>" 
                           class="btn btn-outline-primary w-100">
                            <i class="bi bi-chat me-2"></i>Message Requester
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="card shadow mt-4">
                <div class="card-body">
                    <h6>Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="chat.php?to_user=<?php echo $request['requester_id']; ?>" 
                           class="btn btn-outline-primary">
                            <i class="bi bi-chat me-2"></i>Message Requester
                        </a>
                        <a href="service_detail.php?id=<?php echo $request['service_id']; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-eye me-2"></i>View Service
                        </a>
                        <a href="service_requests.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Requests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>