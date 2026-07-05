<?php
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Get filters
$type = $_GET['type'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT tt.*, 
                 u.full_name as user_name,
                 u.email as user_email,
                 sr.request_id,
                 s.title as service_title
          FROM time_transactions tt
          JOIN users u ON tt.user_id = u.user_id
          LEFT JOIN service_requests sr ON tt.related_request_id = sr.request_id
          LEFT JOIN services s ON sr.service_id = s.service_id
          WHERE 1=1";
$params = [];

if ($type && $type != 'all') {
    $query .= " AND tt.transaction_type = ?";
    $params[] = $type;
}

if ($user_id && $user_id != 'all') {
    $query .= " AND tt.user_id = ?";
    $params[] = $user_id;
}

if ($date_from) {
    $query .= " AND DATE(tt.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(tt.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY tt.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get users for filter
$users = $conn->query("SELECT user_id, full_name, email FROM users ORDER BY full_name")->fetchAll();

// Summary statistics
$summary = $conn->query("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN transaction_type = 'earn' THEN hours ELSE 0 END) as total_earned,
        SUM(CASE WHEN transaction_type = 'spend' THEN hours ELSE 0 END) as total_spent,
        SUM(CASE WHEN transaction_type = 'bonus' THEN hours ELSE 0 END) as total_bonus
    FROM time_transactions
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Transactions - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
    .transaction-earn { color: #28a745; }
    .transaction-spend { color: #dc3545; }
    .transaction-bonus { color: #ffc107; }
    .transaction-adjustment { color: #17a2b8; }
    .transaction-transfer { color: #6f42c1; }
    
    .hours-cell {
        font-weight: bold;
        font-size: 1.1em;
    }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-exchange-alt"></i> Time Transactions</h1>
                <div class="user-info">
                    <span><?php echo $summary['total_transactions']; ?> transactions</span>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #28a745;">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-value">+<?php echo number_format($summary['total_earned'], 2); ?></div>
                    <div class="stat-label">Total Earned (hours)</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dc3545;">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-value">-<?php echo number_format(abs($summary['total_spent']), 2); ?></div>
                    <div class="stat-label">Total Spent (hours)</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ffc107;">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="stat-value">+<?php echo number_format($summary['total_bonus'], 2); ?></div>
                    <div class="stat-label">Total Bonus (hours)</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: #17a2b8;">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-value">
                        <?php echo number_format($summary['total_earned'] + $summary['total_bonus'] + $summary['total_spent'], 2); ?>
                    </div>
                    <div class="stat-label">Net Circulation</div>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="all">All Types</option>
                            <option value="earn" <?php echo $type == 'earn' ? 'selected' : ''; ?>>Earn</option>
                            <option value="spend" <?php echo $type == 'spend' ? 'selected' : ''; ?>>Spend</option>
                            <option value="bonus" <?php echo $type == 'bonus' ? 'selected' : ''; ?>>Bonus</option>
                            <option value="adjustment" <?php echo $type == 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                            <option value="transfer" <?php echo $type == 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select name="user_id" class="form-select" onchange="this.form.submit()">
                            <option value="all">All Users</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $user_id == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['email']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo $date_from; ?>" placeholder="From">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo $date_to; ?>" placeholder="To">
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-130">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Transactions Table -->
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Hours</th>
                            <th>Previous Balance</th>
                            <th>New Balance</th>
                            <th>Description</th>
                            <th>Related To</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $transaction): ?>
                        <tr>
                            <td>#<?php echo $transaction['transaction_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($transaction['user_name']); ?></strong><br>
                                <small><?php echo $transaction['user_email']; ?></small>
                            </td>
                            <td>
                                <?php
                                $typeClass = 'transaction-' . $transaction['transaction_type'];
                                $typeIcon = '';
                                switch($transaction['transaction_type']) {
                                    case 'earn': $typeIcon = 'fa-arrow-up'; break;
                                    case 'spend': $typeIcon = 'fa-arrow-down'; break;
                                    case 'bonus': $typeIcon = 'fa-gift'; break;
                                    case 'adjustment': $typeIcon = 'fa-adjust'; break;
                                    case 'transfer': $typeIcon = 'fa-exchange-alt'; break;
                                }
                                ?>
                                <span class="<?php echo $typeClass; ?>">
                                    <i class="fas <?php echo $typeIcon; ?>"></i>
                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                </span>
                            </td>
                            <td class="hours-cell <?php echo $typeClass; ?>">
                                <?php 
                                echo ($transaction['transaction_type'] == 'spend' ? '-' : '+');
                                echo number_format(abs($transaction['hours']), 2);
                                ?> hrs
                            </td>
                            <td><?php echo number_format($transaction['previous_balance'], 2); ?> hrs</td>
                            <td class="fw-bold">
                                <?php echo number_format($transaction['new_balance'], 2); ?> hrs
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($transaction['description']); ?></small>
                            </td>
                            <td>
                                <?php if($transaction['request_id']): ?>
                                <a href="requests.php?search=<?php echo $transaction['request_id']; ?>" 
                                   class="badge bg-info text-decoration-none">
                                    Request #<?php echo $transaction['request_id']; ?>
                                </a>
                                <?php if($transaction['service_title']): ?>
                                <br><small><?php echo htmlspecialchars($transaction['service_title']); ?></small>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?><br>
                                <small><?php echo date('H:i', strtotime($transaction['created_at'])); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if(empty($transactions)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No transactions found with selected filters</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Export Button -->
            <div class="mt-3 text-end">
                <button class="btn btn-outline-primary" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export to CSV
                </button>
            </div>
        </div>
    </div>
    
    <script>
    function exportToCSV() {
        // Get current filters
        const params = new URLSearchParams(window.location.search);
        window.open(`export_transactions.php?${params.toString()}`, '_blank');
    }
    
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