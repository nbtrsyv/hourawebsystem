<?php
// service_requests.php - Manage Service Requests
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle request actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    // Verify ownership
    $stmt = $conn->prepare("
        SELECT sr.*, s.title, s.user_id as provider_id, s.hours_required
        FROM service_requests sr
        JOIN services s ON sr.service_id = s.service_id
        WHERE sr.request_id = ? AND s.user_id = ?
    ");
    $stmt->execute([$request_id, $user_id]);
    $request = $stmt->fetch();
    
    if ($request) {
        if ($action == 'accept') {
            $stmt = $conn->prepare("UPDATE service_requests SET status = 'accepted', accepted_at = NOW() WHERE request_id = ?");
            if ($stmt->execute([$request_id])) {
                $success = "Request accepted! Please contact the requester to arrange service.";
            }
        } elseif ($action == 'reject') {
            // GUNA 'cancelled' BUKAN 'rejected' sebab enum takde 'rejected'
            $stmt = $conn->prepare("UPDATE service_requests SET status = 'cancelled' WHERE request_id = ?");
            if ($stmt->execute([$request_id])) {
                // Update service status back to available
                $stmt = $conn->prepare("UPDATE services SET status = 'available' WHERE service_id = ?");
                $stmt->execute([$request['service_id']]);
                $success = "Request rejected.";
            }
        } elseif ($action == 'complete') {
            // Complete the service and transfer hours
            $conn->beginTransaction();
            try {

                 $stmt = $conn->prepare("
            SELECT s.hours_required, sr.requester_id, s.title
            FROM service_requests sr
            JOIN services s ON sr.service_id = s.service_id
            WHERE sr.request_id = ?
            ");
            $stmt->execute([$request_id]);
            $data = $stmt->fetch();
        
            $hours = $data['hours_required'];
            $requester_id = $data['requester_id'];
            $service_title = $data['title'];

                // Update request status
                $stmt = $conn->prepare("UPDATE service_requests SET status = 'completed', completed_at = NOW() WHERE request_id = ?");
                $stmt->execute([$request_id]);
                
                // Update service status
                $stmt = $conn->prepare("UPDATE services SET status = 'completed' WHERE service_id = ?");
                $stmt->execute([$request['service_id']]);
                
                // Provider earns hours
                $stmt = $conn->prepare("
                INSERT INTO time_transactions 
                (user_id, related_request_id, hours, transaction_type, description)
                VALUES (?, ?, ?, 'earn', ?)
                ");
                $stmt->execute([$user_id, $request_id, $hours, "Completed: $service_title"]);
                
                // Requester spends hours
                $stmt = $conn->prepare("
                INSERT INTO time_transactions 
                (user_id, related_request_id, hours, transaction_type, description)
                VALUES (?, ?, ?, 'spend', ?)
                ");
                $stmt->execute([$requester_id, $request_id, -$hours, "Paid for: $service_title"]);
                
                // Update user balances
                $stmt = $conn->prepare("
                UPDATE users 
                SET time_balance = time_balance + ? 
                WHERE user_id = ?
                ");
                $stmt->execute([$hours, $user_id]);
        
                $stmt = $conn->prepare("
                UPDATE users 
                SET time_balance = time_balance - ? 
                WHERE user_id = ?
                ");
                $stmt->execute([$hours, $requester_id]);
        
                $conn->commit();
                $success = "Service completed! " . $hours . " hours transferred.";
        
                } catch (Exception $e) {
                $conn->rollBack();
                $error = "Error: " . $e->getMessage();
    }
        }
    } else {
        $error = "Request not found or you don't have permission.";
    }
}

// Fetch service requests for user's services
$stmt = $conn->prepare("
    SELECT sr.*, s.title, s.hours_required,
           u.full_name as requester_name, u.email as requester_email,
           u.profile_image as requester_avatar
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users u ON sr.requester_id = u.user_id
    WHERE s.user_id = ?
    ORDER BY sr.created_at DESC
");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll();
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2"><i class="bi bi-bell me-2"></i>Service Requests</h1>
            <p class="text-muted">Manage requests for your services</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
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
    
    <!-- Requests Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#pending">Pending</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#accepted">Accepted</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#completed">Completed</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#all">All Requests</a>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Pending Requests -->
        <div class="tab-pane fade show active" id="pending">
            <?php 
            $pending_requests = array_filter($requests, fn($r) => $r['status'] == 'pending');
            if(empty($pending_requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-bell-slash display-4 text-muted mb-3"></i>
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
                                    <h6 class="mb-0">Pending Request</h6>
                                    <span class="badge bg-warning"><?php echo $req['hours_required']; ?> hours</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Requester Info -->
                                <div class="d-flex align-items-center mb-3">
                                    <?php if($req['requester_avatar']): ?>
                                        <img src="<?php echo htmlspecialchars($req['requester_avatar']); ?>" 
                                             class="rounded-circle me-3"
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3"
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person fs-4 text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($req['requester_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($req['requester_email']); ?></small>
                                    </div>
                                </div>
                                
                                <!-- Service Info -->
                                <h6><?php echo htmlspecialchars($req['title']); ?></h6>
                                
                                <?php if($req['scheduled_date']): ?>
                                <div class="mb-2">
                                    <i class="bi bi-calendar me-2 text-muted"></i>
                                    <small>Requested for: <?php echo date('M d, Y', strtotime($req['scheduled_date'])); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($req['notes']): ?>
                                <div class="alert alert-light small">
                                    <i class="bi bi-chat-text me-2"></i>
                                    <?php echo nl2br(htmlspecialchars($req['notes'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Actions -->
                                <div class="d-flex justify-content-between mt-3">
                                    <a href="chat.php?to_user=<?php echo $req['requester_id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-chat me-1"></i>Message
                                    </a>
                                    <div>
                                        <a href="service_requests.php?action=accept&id=<?php echo $req['request_id']; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-check-circle me-1"></i>Accept
                                        </a>
                                        <a href="service_requests.php?action=reject&id=<?php echo $req['request_id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to reject this request?');">
                                            <i class="bi bi-x-circle me-1"></i>Reject
                                        </a>
                                    </div>
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
        
        <!-- Accepted Requests -->
        <div class="tab-pane fade" id="accepted">
            <?php 
            $accepted_requests = array_filter($requests, fn($r) => $r['status'] == 'accepted');
            if(empty($accepted_requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-clock-history display-4 text-muted mb-3"></i>
                        <h4>No Accepted Requests</h4>
                        <p class="text-muted">You don't have any accepted service requests.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($accepted_requests as $req): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-info">
                            <div class="card-header bg-info text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Accepted</h6>
                                    <span class="badge bg-light text-dark"><?php echo $req['hours_required']; ?> hours</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($req['title']); ?></h6>
                                <p class="mb-3">With: <?php echo htmlspecialchars($req['requester_name']); ?></p>
                                
                                <?php if($req['accepted_at']): ?>
                                <div class="mb-3">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <small>Accepted on: <?php echo date('M d, Y', strtotime($req['accepted_at'])); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="chat.php?to_user=<?php echo $req['requester_id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-chat me-1"></i>Message
                                    </a>
                                    <a href="service_requests.php?action=complete&id=<?php echo $req['request_id']; ?>" 
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Mark this service as completed? Hours will be transferred.');">
                                        <i class="bi bi-check-circle me-1"></i>Mark Complete
                                    </a>
                                </div>
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
            // TAMBAH 'cancelled' dalam filter completed untuk display
            $completed_requests = array_filter($requests, fn($r) => in_array($r['status'], ['completed', 'cancelled', '']));
            if(empty($completed_requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-check-circle display-4 text-muted mb-3"></i>
                        <h4>No Completed/Rejected Requests</h4>
                        <p class="text-muted">You haven't completed or rejected any service requests yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Requester</th>
                                <th>Hours</th>
                                <th>Status</th>
                                <th>Completed/Rejected On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($completed_requests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['title']); ?></td>
                                <td><?php echo htmlspecialchars($req['requester_name']); ?></td>
                                <td class="<?php echo $req['status'] == 'completed' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                    <?php echo $req['status'] == 'completed' ? '+' : '-'; ?><?php echo $req['hours_required']; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $req['status'] == 'completed' ? 'success' : 'danger'; 
                                    ?>">
                                        <?php 
                                        echo $req['status'] == 'completed' ? 'Completed' : 'Rejected/Cancelled'; 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $req['completed_at'] 
                                        ? date('M d, Y', strtotime($req['completed_at'])) 
                                        : 'N/A'; ?>
                                </td>
                                <td>
                                    <a href="chat.php?to_user=<?php echo $req['requester_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-chat"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- All Requests -->
        <div class="tab-pane fade" id="all">
            <?php if(empty($requests)): ?>
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                        <h4>No Requests Yet</h4>
                        <p class="text-muted">You haven't received any service requests.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Requester</th>
                                <th>Hours</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($requests as $req): ?>
                            <tr>
                                <td><?php echo date('M d', strtotime($req['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($req['title']); ?></td>
                                <td><?php echo htmlspecialchars($req['requester_name']); ?></td>
                                <td><?php echo $req['hours_required']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        // FIX: Handle empty status dan cancelled
                                        $status = $req['status'] ?: 'cancelled'; // kalau empty, treat as cancelled
                                        echo $status == 'pending' ? 'warning' :
                                             ($status == 'accepted' ? 'info' :
                                             ($status == 'completed' ? 'success' : 
                                             ($status == 'cancelled' ? 'danger' : 'secondary'))); 
                                    ?>">
                                        <?php 
                                        echo $status == 'cancelled' ? 'Rejected' : ucfirst($status); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="service_detail.php?id=<?php echo $req['service_id']; ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="chat.php?to_user=<?php echo $req['requester_id']; ?>" 
                                           class="btn btn-outline-info">
                                            <i class="bi bi-chat"></i>
                                        </a>
                                        <?php if($req['status'] == 'pending'): ?>
                                            <a href="service_requests.php?action=accept&id=<?php echo $req['request_id']; ?>" 
                                               class="btn btn-outline-success">
                                                <i class="bi bi-check"></i>
                                            </a>
                                            <a href="service_requests.php?action=reject&id=<?php echo $req['request_id']; ?>" 
                                               class="btn btn-outline-danger">
                                                <i class="bi bi-x"></i>
                                            </a>
                                        <?php elseif($req['status'] == 'accepted'): ?>
                                            <a href="service_requests.php?action=complete&id=<?php echo $req['request_id']; ?>" 
                                               class="btn btn-outline-success">
                                                <i class="bi bi-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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