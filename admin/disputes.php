<?php
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Handle dispute actions
if (isset($_POST['action'])) {
    $disputeId = $_POST['dispute_id'];
    $status = $_POST['status'];
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    $stmt = $conn->prepare("UPDATE disputes SET 
                          status = ?, 
                          resolved_by = ?, 
                          resolved_at = NOW(),
                          admin_notes = ?
                          WHERE dispute_id = ?");
    $stmt->execute([$status, $_SESSION['admin_id'], $adminNotes, $disputeId]);
    
    // If resolved, update request status
    if ($status == 'resolved') {
        $disputeStmt = $conn->prepare("SELECT request_id FROM disputes WHERE dispute_id = ?");
        $disputeStmt->execute([$disputeId]);
        $dispute = $disputeStmt->fetch();
        
        $conn->prepare("UPDATE service_requests SET status = 'completed' WHERE request_id = ?")
            ->execute([$dispute['request_id']]);
    }
}

// Get disputes with filters
$status = $_GET['status'] ?? 'open';

$query = "SELECT d.*, 
                 sr.request_id,
                 s.title as service_title,
                 u.full_name as opened_by_name,
                 r.full_name as resolved_by_name
          FROM disputes d
          JOIN service_requests sr ON d.request_id = sr.request_id
          JOIN services s ON sr.service_id = s.service_id
          JOIN users u ON d.opened_by = u.user_id
          LEFT JOIN users r ON d.resolved_by = r.user_id
          WHERE d.status = ?";
$params = [$status];

$query .= " ORDER BY d.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$disputes = $stmt->fetchAll();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disputes Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-exclamation-triangle"></i> Disputes Management</h1>
                <div class="user-info">
                    <span>Status: <?php echo ucfirst($status); ?></span>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-bar">
                <div class="nav nav-pills">
                    <button class="nav-link <?php echo $status == 'open' ? 'active' : ''; ?>" 
                            onclick="filterDisputes('open')">
                        <i class="fas fa-exclamation-circle"></i> Open
                    </button>
                    <button class="nav-link <?php echo $status == 'in_review' ? 'active' : ''; ?>" 
                            onclick="filterDisputes('in_review')">
                        <i class="fas fa-search"></i> In Review
                    </button>
                    <button class="nav-link <?php echo $status == 'resolved' ? 'active' : ''; ?>" 
                            onclick="filterDisputes('resolved')">
                        <i class="fas fa-check-circle"></i> Resolved
                    </button>
                    <button class="nav-link <?php echo $status == 'dismissed' ? 'active' : ''; ?>" 
                            onclick="filterDisputes('dismissed')">
                        <i class="fas fa-times-circle"></i> Dismissed
                    </button>
                </div>
            </div>
            
            <!-- Disputes List -->
            <div class="admin-table-container">
                <?php if(empty($disputes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5>No disputes found</h5>
                    <p class="text-muted">No disputes with status "<?php echo $status; ?>"</p>
                </div>
                <?php endif; ?>
                
                <?php foreach($disputes as $dispute): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Dispute #<?php echo $dispute['dispute_id']; ?></h5>
                            <small class="text-muted">Request #<?php echo $dispute['request_id']; ?> - <?php echo htmlspecialchars($dispute['service_title']); ?></small>
                        </div>
                        <span class="badge bg-<?php 
                            switch($dispute['status']) {
                                case 'open': echo 'danger'; break;
                                case 'in_review': echo 'warning'; break;
                                case 'resolved': echo 'success'; break;
                                case 'dismissed': echo 'secondary'; break;
                            }
                        ?>">
                            <?php echo ucfirst($dispute['status']); ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6>Reason for Dispute:</h6>
                                <p class="border p-3 rounded bg-light">
                                    <?php echo nl2br(htmlspecialchars($dispute['reason'])); ?>
                                </p>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Opened By:</strong>
                                        <p><?php echo htmlspecialchars($dispute['opened_by_name']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Opened At:</strong>
                                        <p><?php echo date('d/m/Y H:i', strtotime($dispute['created_at'])); ?></p>
                                    </div>
                                </div>
                                
                                <?php if($dispute['resolved_by_name']): ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Resolution Details</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Resolved By:</strong>
                                            <p><?php echo htmlspecialchars($dispute['resolved_by_name']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Resolved At:</strong>
                                            <p><?php echo date('d/m/Y H:i', strtotime($dispute['resolved_at'])); ?></p>
                                        </div>
                                    </div>
                                    <?php if($dispute['admin_notes']): ?>
                                    <strong>Admin Notes:</strong>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($dispute['admin_notes'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <?php if($dispute['status'] == 'open' || $dispute['status'] == 'in_review'): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Admin Actions</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="dispute_id" value="<?php echo $dispute['dispute_id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label>Status:</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="in_review" <?php echo $dispute['status'] == 'in_review' ? 'selected' : ''; ?>>In Review</option>
                                                    <option value="resolved">Resolved</option>
                                                    <option value="dismissed">Dismissed</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label>Admin Notes:</label>
                                                <textarea name="admin_notes" class="form-control" rows="3" 
                                                          placeholder="Add resolution notes..."><?php echo htmlspecialchars($dispute['admin_notes']); ?></textarea>
                                            </div>
                                            
                                            <button type="submit" name="action" value="update" 
                                                    class="btn btn-primary w-100"
                                                    onclick="return confirm('Update dispute status?')">
                                                <i class="fas fa-save"></i> Update Dispute
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <h6><i class="fas fa-check"></i> Dispute <?php echo $dispute['status']; ?></h6>
                                    <p class="mb-0">This dispute has been <?php echo $dispute['status']; ?>.</p>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="requests.php?search=<?php echo $dispute['request_id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-external-link-alt"></i> View Request
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
    function filterDisputes(status) {
        window.location.href = `disputes.php?status=${status}`;
    }
    </script>
</body>
</html>