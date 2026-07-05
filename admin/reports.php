<?php
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Get date range
$period = $_GET['period'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Get statistics using the VIEW
$stats = $conn->query("SELECT * FROM system_statistics")->fetch();

// Get monthly transactions
$monthlyData = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as transaction_count,
        SUM(CASE WHEN transaction_type = 'earn' THEN hours ELSE 0 END) as earned_hours,
        SUM(CASE WHEN transaction_type = 'spend' THEN hours ELSE 0 END) as spent_hours
    FROM time_transactions
    WHERE YEAR(created_at) = $year
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll();

// Get top users by time balance
$topUsers = $conn->query("
    SELECT user_id, full_name, email, time_balance, rating, total_transactions
    FROM users 
    WHERE role = 'user' AND status = 'active'
    ORDER BY time_balance DESC 
    LIMIT 10
")->fetchAll();

// Get service categories distribution
$categories = $conn->query("
    SELECT c.name, COUNT(s.service_id) as service_count
    FROM categories c
    LEFT JOIN services s ON c.category_id = s.category_id
    WHERE c.is_active = TRUE
    GROUP BY c.category_id
    ORDER BY service_count DESC
")->fetchAll();

// Get recent registrations
$recentUsers = $conn->query("
    SELECT user_id, full_name, email, time_balance, created_at
    FROM users 
    WHERE role = 'user'
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Statistics - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-chart-bar"></i> Reports & Statistics</h1>
                <div class="user-info">
                    <span>Last updated: <?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>
            
            <!-- System Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p class="text-muted mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-hands-helping fa-2x text-success mb-2"></i>
                            <h3><?php echo $stats['total_services']; ?></h3>
                            <p class="text-muted mb-0">Services Offered</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-tasks fa-2x text-info mb-2"></i>
                            <h3><?php echo $stats['completed_requests']; ?></h3>
                            <p class="text-muted mb-0">Completed Requests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h3><?php echo number_format($stats['total_hours_in_circulation'], 2); ?></h3>
                            <p class="text-muted mb-0">Hours in Circulation</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Monthly Transactions (<?php echo $year; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Service Categories</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Users & Recent Activity -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Users by Time Balance</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>User</th>
                                            <th>Balance</th>
                                            <th>Rating</th>
                                            <th>Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rank = 1; ?>
                                        <?php foreach($topUsers as $user): ?>
                                        <tr>
                                            <td><?php echo $rank++; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                                <small><?php echo $user['email']; ?></small>
                                            </td>
                                            <td class="fw-bold text-primary">
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
                                            <td><?php echo $user['total_transactions']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Registrations</h5>
                            <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php foreach($recentUsers as $user): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <div class="avatar-circle bg-primary text-white">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                    <p class="text-muted mb-0"><?php echo $user['email']; ?></p>
                                    <small class="text-muted">
                                        Joined: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark">
                                        <?php echo number_format($user['time_balance'], 2); ?> hrs
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
                                    <div class="stat-label">Total Requests</div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-value"><?php echo $stats['open_disputes']; ?></div>
                                    <div class="stat-label">Open Disputes</div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-value"><?php echo $stats['chatbot_today']; ?></div>
                                    <div class="stat-label">Chatbot Today</div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
                                    <div class="stat-label">Admins</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Export Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Export Reports</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100 mb-2" onclick="exportReport('users')">
                                <i class="fas fa-users"></i> Users Report
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-success w-100 mb-2" onclick="exportReport('transactions')">
                                <i class="fas fa-exchange-alt"></i> Transactions
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-info w-100 mb-2" onclick="exportReport('services')">
                                <i class="fas fa-hands-helping"></i> Services Report
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-warning w-100 mb-2" onclick="exportReport('full')">
                                <i class="fas fa-file-alt"></i> Full Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Monthly Transactions Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
            datasets: [{
                label: 'Earned Hours',
                data: <?php echo json_encode(array_column($monthlyData, 'earned_hours')); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }, {
                label: 'Spent Hours',
                data: <?php echo json_encode(array_column($monthlyData, 'spent_hours')); ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                }
            }
        }
    });
    
    // Categories Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($categories, 'name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($categories, 'service_count')); ?>,
                backgroundColor: [
                    '#8a2be2', '#20b2aa', '#ffc107', '#dc3545', '#17a2b8',
                    '#28a745', '#fd7e14', '#6f42c1', '#e83e8c', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    function exportReport(type) {
        window.open(`export_report.php?type=${type}`, '_blank');
    }
    </script>
    
    <style>
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    </style>
</body>
</html>