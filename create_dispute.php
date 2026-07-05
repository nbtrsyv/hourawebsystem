<?php
// user/create_dispute.php - FILE BARU
require_once 'includes/session_start.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user's requests that can be disputed
$requests = $conn->prepare("
    SELECT sr.request_id, s.title, s.hours_required, 
           sr.status as request_status, sr.created_at,
           CASE 
               WHEN sr.requester_id = ? THEN 'requester'
               WHEN sr.provider_id = ? THEN 'provider'
           END as user_role,
           CASE 
               WHEN sr.requester_id = ? THEN pr.full_name
               WHEN sr.provider_id = ? THEN rq.full_name
           END as other_party
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users rq ON sr.requester_id = rq.user_id
    JOIN users pr ON sr.provider_id = pr.user_id
    WHERE (sr.requester_id = ? OR sr.provider_id = ?)
    AND sr.status IN ('in_progress', 'completed')
    AND NOT EXISTS (
        SELECT 1 FROM disputes d 
        WHERE d.request_id = sr.request_id 
        AND d.opened_by = ?
    )
    ORDER BY sr.created_at DESC
");
$requests->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$disputableRequests = $requests->fetchAll();

// Handle dispute creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $reason = trim($_POST['reason']);
    
    if (empty($reason)) {
        $error = "Please provide a reason for the dispute.";
    } else {
        // Create dispute
        $stmt = $conn->prepare("
            INSERT INTO disputes (request_id, opened_by, reason, status) 
            VALUES (?, ?, ?, 'open')
        ");
        
        if ($stmt->execute([$request_id, $user_id, $reason])) {
            $success = "Dispute created successfully. Admin will review it shortly.";
            
            // Update request status to disputed
            $updateStmt = $conn->prepare("
                UPDATE service_requests SET status = 'disputed' 
                WHERE request_id = ?
            ");
            $updateStmt->execute([$request_id]);
            
            // Refresh requests list
            $requests->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
            $disputableRequests = $requests->fetchAll();
        } else {
            $error = "Failed to create dispute. Please try again.";
        }
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Create Dispute</h5>
                </div>
                
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <h6><i class="bi bi-info-circle me-2"></i>What is a Dispute?</h6>
                        <p class="mb-0">
                            A dispute is a formal complaint about a service request. Use this only when you cannot resolve issues directly with the other party. Common reasons include:
                        </p>
                        <ul class="mb-0 mt-2">
                            <li>Service not completed as agreed</li>
                            <li>Poor quality of work</li>
                            <li>Payment or time credit issues</li>
                            <li>Communication problems</li>
                        </ul>
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
                    
                    <?php if(empty($disputableRequests)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle display-4 text-success mb-3"></i>
                        <h4>No Disputable Requests</h4>
                        <p class="text-muted mb-4">
                            You don't have any active requests that require dispute resolution.
                        </p>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                    <?php else: ?>
                    
                    <form method="POST" action="" id="disputeForm">
                        <!-- Select Request -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Request to Dispute</label>
                            <select class="form-select" name="request_id" id="requestSelect" required>
                                <option value="">-- Choose a request --</option>
                                <?php foreach($disputableRequests as $req): ?>
                                <option value="<?php echo $req['request_id']; ?>" 
                                        data-role="<?php echo $req['user_role']; ?>"
                                        data-party="<?php echo htmlspecialchars($req['other_party']); ?>"
                                        data-hours="<?php echo $req['hours_required']; ?>">
                                    <?php echo htmlspecialchars($req['title']); ?> 
                                    (<?php echo $req['hours_required']; ?> hours)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Request Details -->
                        <div class="card bg-light mb-4" id="requestDetails" style="display: none;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">Your Role:</small>
                                        <div id="userRole" class="fw-bold"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Other Party:</small>
                                        <div id="otherParty" class="fw-bold"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Hours:</small>
                                        <div id="requestHours" class="fw-bold"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reason for Dispute -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Reason for Dispute *</label>
                            <textarea class="form-control" name="reason" rows="4" 
                                      placeholder="Explain clearly why you are opening a dispute. Include specific details, dates, and any evidence you have..."
                                      required></textarea>
                            <div class="form-text">
                                Be specific and factual. Admin will use this information to resolve the dispute.
                            </div>
                        </div>
                        
                        <!-- Warning -->
                        <div class="alert alert-warning mb-4">
                            <h6><i class="bi bi-exclamation-octagon me-2"></i>Important Notice</h6>
                            <p class="mb-0">
                                Creating a dispute will temporarily freeze this request. Admin will review and make a decision. Please ensure you have tried to resolve the issue directly first.
                            </p>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-warning" 
                                    onclick="return confirm('Are you sure you want to open a dispute? This action cannot be undone easily.')">
                                <i class="bi bi-exclamation-triangle me-2"></i>Open Dispute
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Previous Disputes -->
            <?php
            $previousDisputes = $conn->prepare("
                SELECT d.*, s.title, d.status as dispute_status, d.created_at
                FROM disputes d
                JOIN service_requests sr ON d.request_id = sr.request_id
                JOIN services s ON sr.service_id = s.service_id
                WHERE d.opened_by = ?
                ORDER BY d.created_at DESC
                LIMIT 5
            ");
            $previousDisputes->execute([$user_id]);
            $disputes = $previousDisputes->fetchAll();
            
            if (!empty($disputes)): ?>
            <div class="card shadow mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-history me-2"></i>Previous Disputes</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Opened</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($disputes as $dispute): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dispute['title']); ?></td>
                                    <td>
                                        <?php 
                                        $badgeClass = 'warning';
                                        if($dispute['dispute_status'] == 'resolved') $badgeClass = 'success';
                                        if($dispute['dispute_status'] == 'dismissed') $badgeClass = 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($dispute['dispute_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($dispute['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Show request details when selected
document.getElementById('requestSelect').addEventListener('change', function() {
    const detailsDiv = document.getElementById('requestDetails');
    const selectedOption = this.options[this.selectedIndex];
    
    if (this.value) {
        const role = selectedOption.dataset.role;
        const party = selectedOption.dataset.party;
        const hours = selectedOption.dataset.hours;
        
        document.getElementById('userRole').textContent = role === 'requester' ? 'Requester' : 'Provider';
        document.getElementById('otherParty').textContent = party;
        document.getElementById('requestHours').textContent = hours + ' hours';
        
        detailsDiv.style.display = 'block';
    } else {
        detailsDiv.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>