<?php
// admin/get_request_details.php
session_start();
require_once '../config/database.php';

// Check admin login
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    die('Access denied');
}

$requestId = $_GET['id'] ?? 0;

// Get request details
$stmt = $conn->prepare("
    SELECT sr.*,
           s.title as service_title,
           s.description as service_description,
           s.hours_required,
           s.location as service_location,
           rq.full_name as requester_name,
           rq.email as requester_email,
           rq.phone as requester_phone,
           rq.time_balance as requester_balance,
           pr.full_name as provider_name,
           pr.email as provider_email,
           pr.phone as provider_phone,
           pr.time_balance as provider_balance
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users rq ON sr.requester_id = rq.user_id
    LEFT JOIN users pr ON sr.provider_id = pr.user_id
    WHERE sr.request_id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    echo '<div class="alert alert-danger">Request not found</div>';
    exit();
}
?>

<div class="request-details">
    <!-- Header -->
    <div class="mb-4">
        <h4>Request #<?php echo $request['request_id']; ?></h4>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-<?php 
                switch($request['status']) {
                    case 'pending': echo 'warning'; break;
                    case 'accepted': echo 'info'; break;
                    case 'in_progress': echo 'primary'; break;
                    case 'completed': echo 'success'; break;
                    case 'cancelled': echo 'danger'; break;
                    default: echo 'secondary';
                }
            ?>">
                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
            </span>
            <span class="badge bg-dark">
                <i class="fas fa-clock"></i> <?php echo $request['hours_required']; ?> hours
            </span>
            <?php if($request['scheduled_date']): ?>
            <span class="badge bg-secondary">
                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($request['scheduled_date'])); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Service Info -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-hands-helping"></i> Service Information</h6>
        </div>
        <div class="card-body">
            <h5><?php echo htmlspecialchars($request['service_title']); ?></h5>
            <p class="text-muted"><?php echo nl2br(htmlspecialchars($request['service_description'])); ?></p>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <p><strong>Required Hours:</strong> <?php echo $request['hours_required']; ?> hours</p>
                    <p><strong>Charged Hours:</strong> <?php echo $request['hours_required']; ?> hours</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Location:</strong> <?php echo $request['service_location'] ?: 'Not specified'; ?></p>
                    <p><strong>Requested:</strong> <?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Users Info -->
    <div class="row mb-4">
        <!-- Requester -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user"></i> Requester</h6>
                </div>
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($request['requester_name']); ?></h5>
                    <p class="mb-1"><strong>Email:</strong> <?php echo $request['requester_email']; ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <?php echo $request['requester_phone'] ?: 'Not provided'; ?></p>
                    <p class="mb-3"><strong>Time Balance:</strong> <?php echo $request['requester_balance']; ?> hours</p>
                    
                    <a href="users.php?search=<?php echo urlencode($request['requester_email']); ?>" 
                       class="btn btn-sm btn-outline-info">
                        <i class="fas fa-eye"></i> View Profile
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Provider -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user-tie"></i> Provider</h6>
                </div>
                <div class="card-body">
                    <?php if($request['provider_name']): ?>
                    <h5><?php echo htmlspecialchars($request['provider_name']); ?></h5>
                    <p class="mb-1"><strong>Email:</strong> <?php echo $request['provider_email']; ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <?php echo $request['provider_phone'] ?: 'Not provided'; ?></p>
                    <p class="mb-3"><strong>Time Balance:</strong> <?php echo $request['provider_balance']; ?> hours</p>
                    
                    <a href="users.php?search=<?php echo urlencode($request['provider_email']); ?>" 
                       class="btn btn-sm btn-outline-info">
                        <i class="fas fa-eye"></i> View Profile
                    </a>
                    
                    <?php if($request['accepted_at']): ?>
                    <div class="mt-3 alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        Accepted on: <?php echo date('d/m/Y H:i', strtotime($request['accepted_at'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-times fa-2x text-muted mb-3"></i>
                        <p class="text-muted">No provider assigned yet</p>
                        <button class="btn btn-sm btn-warning" 
                                onclick="window.parent.showAssignModal(<?php echo $request['request_id']; ?>)">
                            <i class="fas fa-user-plus"></i> Assign Provider
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notes & Schedule -->
    <div class="row mb-4">
        <?php if($request['notes']): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-sticky-note"></i> Notes</h6>
                </div>
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($request['notes'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($request['scheduled_date']): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-calendar-alt"></i> Schedule</h6>
                </div>
                <div class="card-body">
                    <p><strong>Date:</strong> <?php echo date('l, d F Y', strtotime($request['scheduled_date'])); ?></p>
                    <?php if($request['scheduled_time']): ?>
                    <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($request['scheduled_time'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Timeline -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-history"></i> Timeline</h6>
        </div>
        <div class="card-body">
            <div class="timeline">
                <div class="timeline-item <?php echo $request['created_at'] ? 'active' : ''; ?>">
                    <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?></div>
                    <div class="timeline-content">
                        <strong>Request Created</strong>
                    </div>
                </div>
                
                <?php if($request['accepted_at']): ?>
                <div class="timeline-item active">
                    <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($request['accepted_at'])); ?></div>
                    <div class="timeline-content">
                        <strong>Request Accepted</strong>
                        <?php if($request['provider_name']): ?>
                        <p>By: <?php echo htmlspecialchars($request['provider_name']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($request['completed_at']): ?>
                <div class="timeline-item active">
                    <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($request['completed_at'])); ?></div>
                    <div class="timeline-content">
                        <strong>Request Completed</strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="mt-4 pt-3 border-top">
        <div class="d-flex justify-content-between">
            <button class="btn btn-outline-secondary" onclick="window.parent.closeRequestModal()">
                <i class="fas fa-times"></i> Close
            </button>
            
            <div class="btn-group">
                <?php if($request['status'] == 'pending'): ?>
                <button class="btn btn-success" 
                        onclick="window.parent.updateRequestStatus(<?php echo $request['request_id']; ?>, 'accepted')">
                    <i class="fas fa-check"></i> Accept Request
                </button>
                <?php endif; ?>
                
                <?php if($request['status'] == 'accepted'): ?>
                <button class="btn btn-primary" 
                        onclick="window.parent.updateRequestStatus(<?php echo $request['request_id']; ?>, 'in_progress')">
                    <i class="fas fa-play"></i> Start Progress
                </button>
                <?php endif; ?>
                
                <?php if($request['status'] == 'in_progress'): ?>
                <button class="btn btn-success" 
                        onclick="window.parent.updateRequestStatus(<?php echo $request['request_id']; ?>, 'completed')">
                    <i class="fas fa-check-circle"></i> Mark Complete
                </button>
                <?php endif; ?>
                
                <?php if(!in_array($request['status'], ['completed', 'cancelled'])): ?>
                <button class="btn btn-danger" 
                        onclick="if(confirm('Cancel this request?')) window.parent.updateRequestStatus(<?php echo $request['request_id']; ?>, 'cancelled')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #ddd;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -25px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #ddd;
    border: 2px solid white;
}

.timeline-item.active::before {
    background: var(--primary-green);
}

.timeline-date {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 5px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    border-left: 3px solid var(--primary-green);
}
</style>