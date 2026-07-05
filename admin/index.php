<?php
// admin/index.php - FIXED VERSION
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin(); // Ini akan handle session

require_once '../config/database.php';

// Get statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'total_admins' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'total_services' => $conn->query("SELECT COUNT(*) FROM services")->fetchColumn(),
    'active_services' => $conn->query("SELECT COUNT(*) FROM services WHERE status = 'available'")->fetchColumn(),
    'total_requests' => $conn->query("SELECT COUNT(*) FROM service_requests")->fetchColumn(),
    'pending_requests' => $conn->query("SELECT COUNT(*) FROM service_requests WHERE status = 'pending'")->fetchColumn(),
    'open_disputes' => $conn->query("SELECT COUNT(*) FROM disputes WHERE status = 'open'")->fetchColumn(),
    'total_hours' => $conn->query("SELECT SUM(time_balance) FROM users")->fetchColumn() ?? 0
];

// Recent activities
$recentActivities = $conn->query("
    SELECT a.*, u.full_name 
    FROM activity_logs a 
    LEFT JOIN users u ON a.user_id = u.user_id 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Houra Time Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="admin-content">
            <!-- Header -->
            <div class="admin-header">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <div class="user-info">
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end">
                            <div class="small">Logged in as</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Welcome Message -->
            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-shield fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</h5>
                        <p class="mb-0">Last login: <?php 
                            // Get last login from database if column exists
                            try {
                                $lastLoginStmt = $conn->prepare("SELECT last_login FROM users WHERE user_id = ?");
                                $lastLoginStmt->execute([$_SESSION['admin_id']]);
                                $lastLoginResult = $lastLoginStmt->fetch();
                                if ($lastLoginResult && $lastLoginResult['last_login']) {
                                    $lastLoginTime = new DateTime($lastLoginResult['last_login']);
                                    echo $lastLoginTime->format('d/m/Y H:i:s');
                                } else {
                                    echo 'First login';
                                }
                            } catch (PDOException $e) {
                                // Column doesn't exist yet, show N/A
                                echo 'Database migration needed';
                            }
                        ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon services">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_services']; ?></div>
                    <div class="stat-label">Services Offered</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon requests">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
                    <div class="stat-label">Service Requests</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon transactions">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_hours'], 2); ?></div>
                    <div class="stat-label">Total Hours in Circulation</div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="admin-table-container mt-4">
                <h5><i class="fas fa-history"></i> Recent Activities</h5>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Activity</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recentActivities)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fas fa-info-circle"></i> No recent activities
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($recentActivities as $activity): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
                            <td><?php echo $activity['full_name'] ?: 'System'; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($activity['activity_type']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($activity['description']); ?></small>
                            </td>
                            <td><code><?php echo $activity['ip_address']; ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Quick Links -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <a href="users.php" class="card text-decoration-none text-dark">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h6>Manage Users</h6>
                            <small class="text-muted">View and manage all users</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="services.php" class="card text-decoration-none text-dark">
                        <div class="card-body text-center">
                            <i class="fas fa-hands-helping fa-2x text-success mb-2"></i>
                            <h6>Services</h6>
                            <small class="text-muted">Manage offered services</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="requests.php" class="card text-decoration-none text-dark">
                        <div class="card-body text-center">
                            <i class="fas fa-tasks fa-2x text-info mb-2"></i>
                            <h6>Service Requests</h6>
                            <small class="text-muted">Handle service requests</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="reports.php" class="card text-decoration-none text-dark">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-2x text-warning mb-2"></i>
                            <h6>Reports</h6>
                            <small class="text-muted">View system reports</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>