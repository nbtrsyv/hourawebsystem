<?php
// requests.php - FIXED
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'update_status':
            if (isset($_GET['status']) && isset($_GET['id'])) {
                $stmt = $conn->prepare("UPDATE service_requests SET status = ? WHERE request_id = ?");
                $stmt->execute([$_GET['status'], $_GET['id']]);
                $_SESSION['message'] = "Request #{$_GET['id']} updated to {$_GET['status']}";
            }
            break;
        case 'assign_provider':
            if (isset($_POST['provider_id']) && isset($_POST['request_id'])) {
                $stmt = $conn->prepare("UPDATE service_requests SET provider_id = ?, status = 'accepted', accepted_at = NOW() WHERE request_id = ?");
                $stmt->execute([$_POST['provider_id'], $_POST['request_id']]);
                $_SESSION['message'] = "Provider assigned to request #{$_POST['request_id']}";
            }
            break;
    }
    header('Location: requests.php');
    exit();
}

// Get requests with filters - FIX DATE FILTER
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$query = "SELECT sr.*, 
                 s.title as service_title,
                 s.hours_required,
                 rq.full_name as requester_name,
                 rq.email as requester_email,
                 pr.full_name as provider_name,
                 pr.email as provider_email
          FROM service_requests sr
          JOIN services s ON sr.service_id = s.service_id
          JOIN users rq ON sr.requester_id = rq.user_id
          LEFT JOIN users pr ON sr.provider_id = pr.user_id
          WHERE 1=1";
$params = [];

if ($status && $status != 'all') {
    $query .= " AND sr.status = ?";
    $params[] = $status;
}

if ($date_from) {
    $query .= " AND DATE(sr.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(sr.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY sr.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Get available providers
$providers = $conn->query("
    SELECT user_id, full_name, email, time_balance 
    FROM users 
    WHERE role = 'user' 
    AND status = 'active'
    ORDER BY full_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests - Admin</title>
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
                <h1><i class="fas fa-tasks"></i> Service Requests</h1>
                <div class="user-info">
                    <span>Total: <?php echo count($requests); ?> requests</span>
                </div>
            </div>
            
            <?php if(isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <!-- Filter Bar dengan Form -->
            <div class="filter-bar">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all">All Status</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="accepted" <?php echo $status == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo $date_from; ?>" 
                               placeholder="From Date">
                    </div>
                    
                    <div class="col-md-3">
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo $date_to; ?>" 
                               placeholder="To Date">
                    </div>
                    
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="requests.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Requests Table -->
            <div class="admin-table-container">
                <?php if(empty($requests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <h5>No requests found</h5>
                    <p class="text-muted">Try changing your filters</p>
                </div>
                <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service</th>
                            <th>Requester</th>
                            <th>Provider</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($requests as $request): ?>
                        <tr>
                            <td>#<?php echo $request['request_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($request['service_title']); ?></strong><br>
                                <small class="text-muted">Required: <?php echo $request['hours_required']; ?> hrs</small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($request['requester_name']); ?></strong><br>
                                <small class="text-muted"><?php echo $request['requester_email']; ?></small>
                                <br>
                            </td>
                            <td>
                                <?php if($request['provider_name']): ?>
                                <strong><?php echo htmlspecialchars($request['provider_name']); ?></strong><br>
                                <small class="text-muted"><?php echo $request['provider_email']; ?></small>
                                <br>
                                <?php else: ?>
                                <span class="text-danger">Unassigned</span><br>
                                <button class="btn btn-sm btn-warning mt-1" 
                                        onclick="showAssignModal(<?php echo $request['request_id']; ?>)">
                                    <i class="fas fa-user-plus"></i> Assign
                                </button>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-primary">
                                <?php echo $request['hours_required']; ?> hrs
                            </td>
                            <td>
                                <?php
                                $statusClass = 'status-';
                                switch($request['status']) {
                                    case 'pending': $statusClass .= 'pending'; break;
                                    case 'accepted': $statusClass .= 'active'; break;
                                    case 'in_progress': $statusClass .= 'pending'; break;
                                    case 'completed': $statusClass .= 'completed'; break;
                                    case 'cancelled': $statusClass .= 'cancelled'; break;
                                    case 'disputed': $statusClass .= 'cancelled'; break;
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($request['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn-action btn-view" 
                                            onclick="viewRequestDetails(<?php echo $request['request_id']; ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <div class="dropdown">
                                        <button class="btn-action btn-edit dropdown-toggle" 
                                                type="button" 
                                                data-bs-toggle="dropdown"
                                                title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if($request['status'] != 'completed' && $request['status'] != 'cancelled'): ?>
                                                <?php if($request['status'] != 'accepted'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       onclick="updateRequestStatus(<?php echo $request['request_id']; ?>, 'accepted')">
                                                        <i class="fas fa-check"></i> Accept
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php if($request['status'] != 'in_progress'): ?>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                            <?php endif; ?>
                                            
                                            <?php if($request['status'] != 'cancelled'): ?>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" 
                                                   onclick="updateRequestStatus(<?php echo $request['request_id']; ?>, 'cancelled')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Assign Provider Modal -->
    <div class="modal-overlay" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Provider</h5>
                <button type="button" class="btn-close" onclick="closeAssignModal()"></button>
            </div>
            <form method="POST" action="requests.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_provider">
                    <input type="hidden" name="request_id" id="assignRequestId">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Provider:</label>
                        <select name="provider_id" class="form-select" required>
                            <option value="">-- Select Provider --</option>
                            <?php foreach($providers as $provider): ?>
                            <option value="<?php echo $provider['user_id']; ?>">
                                <?php echo htmlspecialchars($provider['full_name']); ?> 
                                (<?php echo $provider['email']; ?>)
                                - Balance: <?php echo $provider['time_balance']; ?> hrs
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Assigning a provider will automatically set the request status to "Accepted".
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Provider</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Request Details Modal -->
    <div class="modal-overlay" id="requestModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h4 class="modal-title">Request Details</h4>
                <button type="button" class="btn-close" onclick="closeRequestModal()"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                Loading request details...
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Request status update
    function updateRequestStatus(id, status) {
        if(confirm(`Update request #${id} status to "${status}"?`)) {
            window.location.href = `requests.php?action=update_status&id=${id}&status=${status}`;
        }
    }
    
    // Assign provider modal
    function showAssignModal(requestId) {
        document.getElementById('assignRequestId').value = requestId;
        document.getElementById('assignModal').style.display = 'flex';
    }
    
    function closeAssignModal() {
        document.getElementById('assignModal').style.display = 'none';
    }
    
    // View request details
    function viewRequestDetails(requestId) {
        document.getElementById('requestDetailsContent').innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Loading request details...</p>
            </div>
        `;
        
        document.getElementById('requestModal').style.display = 'flex';
        
        fetch(`get_request_details.php?id=${requestId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('requestDetailsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('requestDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        Error: ${error}
                    </div>
                `;
            });
    }
    
    function closeRequestModal() {
        document.getElementById('requestModal').style.display = 'none';
    }
    
    // View user details (gunakan function dari users.php)
    function viewUserDetails(userId) {
        if (typeof window.parent.viewUserDetails === 'function') {
            window.parent.viewUserDetails(userId);
        } else {
            window.location.href = `users.php?search=${userId}`;
        }
    }
    
    // Close modals when clicking outside
    document.getElementById('assignModal').addEventListener('click', function(e) {
        if(e.target === this) closeAssignModal();
    });
    
    document.getElementById('requestModal').addEventListener('click', function(e) {
        if(e.target === this) closeRequestModal();
    });
    
    // Escape key closes modals
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            closeAssignModal();
            closeRequestModal();
        }
    });
    
    // Auto-submit date filters when both are selected
    document.querySelectorAll('input[type="date"]').forEach(input => {
        input.addEventListener('change', function() {
            const dateFrom = document.querySelector('input[name="date_from"]').value;
            const dateTo = document.querySelector('input[name="date_to"]').value;
            if(dateFrom && dateTo) {
                document.querySelector('form').submit();
            }
        });
    });
    </script>
</body>
</html>