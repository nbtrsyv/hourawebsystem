<?php
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Handle proof approval/rejection
if (isset($_POST['action'])) {
    $proofId = $_POST['proof_id'];
    $status = $_POST['action'] == 'approve' ? 'approved' : 'rejected';
    $notes = $_POST['admin_notes'] ?? '';
    
    $stmt = $conn->prepare("UPDATE task_proofs SET 
                          status = ?, 
                          reviewed_by = ?, 
                          reviewed_at = NOW(),
                          description = ?
                          WHERE proof_id = ?");
    $stmt->execute([$status, $_SESSION['admin_id'], $notes, $proofId]);
    
    // If approved, complete the service request and process transactions
    if ($status == 'approved') {
        $proofStmt = $conn->prepare("SELECT tp.request_id, sr.requester_id, sr.provider_id, sr.hours_required, s.title
                                   FROM task_proofs tp
                                   JOIN service_requests sr ON tp.request_id = sr.request_id
                                   JOIN services s ON sr.service_id = s.service_id
                                   WHERE tp.proof_id = ?");
        $proofStmt->execute([$proofId]);
        $proofData = $proofStmt->fetch();
        
        // Update request status
        $conn->prepare("UPDATE service_requests SET status = 'completed' WHERE request_id = ?")
            ->execute([$proofData['request_id']]);
        
        // Process time transactions using stored procedure
        try {
            $stmt = $conn->prepare("CALL CompleteServiceRequest(?, ?)");
            $stmt->execute([$proofData['request_id'], $proofData['image_path']]);
        } catch (Exception $e) {
            // Log error but continue
            error_log("Error completing service request: " . $e->getMessage());
        }
    }
}

// Get proofs with filters
$status = $_GET['status'] ?? 'pending_review';

$query = "SELECT tp.*, 
                 sr.request_id,
                 s.title as service_title,
                 u.full_name as uploader_name,
                 r.full_name as requester_name,
                 p.full_name as provider_name,
                 a.full_name as reviewer_name
          FROM task_proofs tp
          JOIN service_requests sr ON tp.request_id = sr.request_id
          JOIN services s ON sr.service_id = s.service_id
          JOIN users u ON tp.uploaded_by = u.user_id
          JOIN users r ON sr.requester_id = r.user_id
          LEFT JOIN users p ON sr.provider_id = p.user_id
          LEFT JOIN users a ON tp.reviewed_by = a.user_id
          WHERE tp.status = ?";
$params = [$status];

$query .= " ORDER BY tp.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$proofs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Proofs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
    .proof-image {
        width: 100%;
        max-height: 200px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.3s;
    }
    
    .proof-image:hover {
        transform: scale(1.05);
    }
    
    .image-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 3000;
    }
    
    .image-modal img {
        max-width: 90%;
        max-height: 90%;
        border-radius: 10px;
    }
    
    .proof-card {
        border-left: 4px solid;
        border-left-color: #ffc107; /* Yellow for pending */
    }
    
    .proof-card.approved {
        border-left-color: #28a745; /* Green for approved */
    }
    
    .proof-card.rejected {
        border-left-color: #dc3545; /* Red for rejected */
    }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-images"></i> Task Proofs</h1>
                <div class="user-info">
                    <span>Status: <?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-bar">
                <div class="nav nav-pills">
                    <button class="nav-link <?php echo $status == 'pending_review' ? 'active' : ''; ?>" 
                            onclick="filterProofs('pending_review')">
                        <i class="fas fa-clock"></i> Pending Review (<?php 
                        $count = $conn->query("SELECT COUNT(*) FROM task_proofs WHERE status = 'pending_review'")->fetchColumn();
                        echo $count; ?>)
                    </button>
                    <button class="nav-link <?php echo $status == 'approved' ? 'active' : ''; ?>" 
                            onclick="filterProofs('approved')">
                        <i class="fas fa-check-circle"></i> Approved (<?php 
                        $count = $conn->query("SELECT COUNT(*) FROM task_proofs WHERE status = 'approved'")->fetchColumn();
                        echo $count; ?>)
                    </button>
                    <button class="nav-link <?php echo $status == 'rejected' ? 'active' : ''; ?>" 
                            onclick="filterProofs('rejected')">
                        <i class="fas fa-times-circle"></i> Rejected (<?php 
                        $count = $conn->query("SELECT COUNT(*) FROM task_proofs WHERE status = 'rejected'")->fetchColumn();
                        echo $count; ?>)
                    </button>
                </div>
            </div>
            
            <!-- Proofs Grid -->
            <div class="row">
                <?php if(empty($proofs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No proofs found with status "<?php echo $status; ?>"
                    </div>
                </div>
                <?php endif; ?>
                
                <?php foreach($proofs as $proof): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 proof-card <?php echo $proof['status']; ?>">
                        <div class="card-header">
                            <h6 class="mb-0">Proof #<?php echo $proof['proof_id']; ?></h6>
                            <small class="text-muted">Request #<?php echo $proof['request_id']; ?></small>
                        </div>
                        
                        <div class="card-body">
                            <!-- Proof Image -->
                            <div class="mb-3">
                                <img src="../uploads/proofs/<?php echo basename($proof['image_path']); ?>" 
                                     alt="Proof Image" 
                                     class="proof-image"
                                     onclick="viewImage(this.src)">
                                <small class="text-muted d-block mt-1">Click to enlarge</small>
                            </div>
                            
                            <!-- Proof Details -->
                            <div class="mb-2">
                                <strong>Service:</strong>
                                <p class="mb-1"><?php echo htmlspecialchars($proof['service_title']); ?></p>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-6">
                                    <strong>Uploaded By:</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($proof['uploader_name']); ?></p>
                                </div>
                                <div class="col-6">
                                    <strong>Requester:</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($proof['requester_name']); ?></p>
                                </div>
                            </div>
                            
                            <?php if($proof['provider_name']): ?>
                            <div class="mb-2">
                                <strong>Provider:</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($proof['provider_name']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($proof['description']): ?>
                            <div class="mb-2">
                                <strong>Description:</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($proof['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <strong>Uploaded:</strong>
                                <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($proof['created_at'])); ?></p>
                            </div>
                            
                            <?php if($proof['reviewed_by']): ?>
                            <div class="mb-2">
                                <strong>Reviewed By:</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($proof['reviewer_name']); ?></p>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($proof['reviewed_at'])); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <?php if($proof['status'] == 'pending_review'): ?>
                        <div class="card-footer bg-transparent">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="proof_id" value="<?php echo $proof['proof_id']; ?>">
                                
                                <button type="submit" name="action" value="approve" 
                                        class="btn btn-success btn-sm flex-fill"
                                        onclick="return confirm('Approve this proof? This will complete the service request and process time transactions.')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                
                                <button type="button" class="btn btn-danger btn-sm flex-fill"
                                        onclick="showRejectModal(<?php echo $proof['proof_id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <img id="modalImage" src="" alt="Enlarged Proof">
    </div>
    
    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal-content">
            <h4>Reject Proof</h4>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="proof_id" id="rejectProofId">
                <input type="hidden" name="action" value="reject">
                
                <div class="mb-3">
                    <label>Rejection Reason (Optional):</label>
                    <textarea name="admin_notes" class="form-control" rows="3" 
                              placeholder="Explain why this proof is rejected..."></textarea>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Proof</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function filterProofs(status) {
        window.location.href = `proofs.php?status=${status}`;
    }
    
    function viewImage(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').style.display = 'flex';
    }
    
    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
    }
    
    function showRejectModal(proofId) {
        document.getElementById('rejectProofId').value = proofId;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
    }
    
    // Close modals when clicking outside
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if(e.target === this) closeRejectModal();
    });
    
    document.getElementById('imageModal').addEventListener('click', function(e) {
        if(e.target === this) closeImageModal();
    });
    
    // Confirm rejection
    document.getElementById('rejectForm').addEventListener('submit', function(e) {
        if(!confirm('Are you sure you want to reject this proof?')) {
            e.preventDefault();
        }
    });
    
    // Escape key closes modals
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            closeImageModal();
            closeRejectModal();
        }
    });
    </script>
</body>
</html>