<?php
// admin/users.php - COMPLETE FIXED VERSION
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']); // Validate ID is integer
    
    if ($user_id <= 0) {
        $_SESSION['error'] = 'Invalid user ID';
    } else {
        try {
            switch ($_GET['action']) {
                case 'suspend':
                    // Suspend for 1 month (30 days)
                    $suspensionDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $stmt = $conn->prepare("UPDATE users SET status = 'suspended', suspended_until = ? WHERE user_id = ?");
                    if ($stmt->execute([$suspensionDate, $user_id])) {
                        $_SESSION['message'] = 'User suspended for 30 days';
                    } else {
                        $_SESSION['error'] = 'Failed to suspend user';
                    }
                    break;
                case 'activate':
                    $stmt = $conn->prepare("UPDATE users SET status = 'active', suspended_until = NULL WHERE user_id = ?");
                    if ($stmt->execute([$user_id])) {
                        $_SESSION['message'] = 'User activated successfully';
                    } else {
                        $_SESSION['error'] = 'Failed to activate user';
                    }
                    break;
                case 'ban':
                    // Permanent ban - no suspended_until date
                    $stmt = $conn->prepare("UPDATE users SET status = 'banned', suspended_until = NULL WHERE user_id = ?");
                    if ($stmt->execute([$user_id])) {
                        $_SESSION['message'] = 'User banned permanently';
                    } else {
                        $_SESSION['error'] = 'Failed to ban user';
                    }
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
    }
    
    header('Location: users.php');
    exit();
}

// Get users with filters
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($role) {
    $query .= " AND role = ?";
    $params[] = $role;
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 10px;
    }
    
    .user-info-row {
        display: flex;
        align-items: center;
    }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-users-cog"></i> Manage Users</h1>
                <div class="user-info">
                    <span>Total: <?php echo count($users); ?> users</span>
                </div>
            </div>
            
            <?php if(isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <select name="role" class="form-select" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="user" <?php echo $role == 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="banned" <?php echo $status == 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search by name, email, phone..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="admin-table-container">
                <?php if(empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>No users found</h5>
                    <p class="text-muted">Try changing your filters or search terms</p>
                </div>
                <?php else: ?>
                <table class="admin-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Time Balance</th>
                            <th>Rating</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td>#<?php echo $user['user_id']; ?></td>
                            <td>
                                <div class="user-info-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo $user['phone']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = '';
                                $statusText = ucfirst($user['status']);
                                switch($user['status']) {
                                    case 'active': $statusClass = 'status-active'; break;
                                    case 'suspended': 
                                        $statusClass = 'status-suspended';
                                        if ($user['suspended_until']) {
                                            $suspendedUntil = date('d/m/Y', strtotime($user['suspended_until']));
                                            $statusText = "Suspended until $suspendedUntil";
                                        }
                                        break;
                                    case 'banned': $statusClass = 'status-banned'; break;
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>" title="<?php echo $statusText; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="text-primary fw-bold">
                                <?php echo number_format($user['time_balance'], 2); ?> hrs
                            </td>
                            <td>
                                <div class="rating-stars">
                                    <?php
                                    $rating = $user['rating'];
                                    for($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '★' : '☆';
                                    }
                                    ?>
                                    <small>(<?php echo $rating; ?>)</small>
                                </div>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn-action btn-view" 
                                            onclick="viewUserDetails(<?php echo $user['user_id']; ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if($user['status'] == 'active'): ?>
                                    <button class="btn-action btn-warning" 
                                            onclick="suspendUser(<?php echo $user['user_id']; ?>)"
                                            title="Suspend User">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-action btn-success" 
                                            onclick="activateUser(<?php echo $user['user_id']; ?>)"
                                            title="Activate User">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-action btn-danger" 
                                            onclick="banUser(<?php echo $user['user_id']; ?>)"
                                            title="Ban User">
                                        <i class="fas fa-ban"></i>
                                    </button>
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
    
    <!-- User Details Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h4 class="modal-title">User Details</h4>
                <button type="button" class="btn-close" onclick="closeModal()"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                Loading user details...
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // View user details
    function viewUserDetails(userId) {
        // Show loading
        document.getElementById('userDetailsContent').innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Loading user details...</p>
            </div>
        `;
        
        // Show modal
        document.getElementById('userModal').style.display = 'flex';
        
        // Fetch user details via AJAX
        fetch(`get_user_details.php?id=${userId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('userDetailsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('userDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error loading user details: ${error}
                    </div>
                `;
            });
    }
    
    // Close modal
    function closeModal() {
        document.getElementById('userModal').style.display = 'none';
    }
    
    // User actions
    function suspendUser(id) {
        if(confirm('Are you sure you want to suspend this user?\n\nThey will not be able to login or use the system.')) {
            window.location.href = `users.php?action=suspend&id=${id}`;
        }
    }
    
    function activateUser(id) {
        if(confirm('Activate this user?\n\nThey will be able to login and use the system again.')) {
            window.location.href = `users.php?action=activate&id=${id}`;
        }
    }
    
    function banUser(id) {
        if(confirm('⚠️ BAN THIS USER?\n\nThis action is permanent!\n\n• User cannot login\n• All services will be cancelled\n• Time balance will be frozen')) {
            window.location.href = `users.php?action=ban&id=${id}`;
        }
    }
    
    // Close modal when clicking outside
    document.getElementById('userModal').addEventListener('click', function(e) {
        if(e.target === this) closeModal();
    });
    
    // Escape key closes modal
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') closeModal();
    });
    </script>
</body>
</html>