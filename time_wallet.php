<?php
// time_wallet.php - Time Credits Wallet
require_once 'includes/session_start.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch transactions
// Handle export request
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="houra_transactions_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, ['Date', 'Time', 'Description', 'Related To', 'Hours', 'Balance']);
    
    // Data rows
    foreach($transactions as $t) {
        $date = date('Y-m-d', strtotime($t['created_at']));
        $time = date('H:i:s', strtotime($t['created_at']));
        $hours = $t['hours'] > 0 ? "+" . $t['hours'] : $t['hours'];
        
        fputcsv($output, [
            $date,
            $time,
            $t['description'],
            $t['service_title'] ?? 'System',
            $hours,
            $t['new_balance']
        ]);
    }
    
    fclose($output);
    exit();
}
$stmt = $conn->prepare("
    SELECT tt.*, 
           s.title as service_title,
           sr.request_id,
           u2.full_name as other_user_name
    FROM time_transactions tt
    LEFT JOIN service_requests sr ON tt.related_request_id = sr.request_id
    LEFT JOIN services s ON sr.service_id = s.service_id
    LEFT JOIN users u2 ON (
        CASE 
            WHEN tt.hours > 0 THEN s.user_id  -- Provider when earning
            ELSE sr.requester_id  -- Requester when spending
        END
    ) = u2.user_id
    WHERE tt.user_id = ?
    ORDER BY tt.created_at DESC
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Calculate summary
$total_earned = 0;
$total_spent = 0;
foreach($transactions as $t) {
    if ($t['hours'] > 0) {
        $total_earned += $t['hours'];
    } else {
        $total_spent += abs($t['hours']);
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Time Wallet</li>
        </ol>
    </nav>
    
    <!-- Wallet Header -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-0">
                        <i class="bi bi-wallet2 me-2"></i>My Time Wallet
                    </h4>
                </div>
                <div class="col-md-4 text-end">
                    <a href="add_service.php" class="btn btn-light btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Earn More Hours
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Current Balance -->
            <div class="row align-items-center">
                <div class="col-md-4 text-center mb-4 mb-md-0">
                    <div class="display-1 fw-bold" style="color: var(--primary-purple);">
                        <?php echo number_format($user['time_balance'], 2); ?>
                    </div>
                    <div class="fs-5">hours available</div>
                </div>
                
                <div class="col-md-8">
                    <!-- Balance Breakdown -->
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="card border-success border-2">
                                <div class="card-body text-center py-3">
                                    <div class="fs-4 fw-bold text-success">
                                        +<?php echo number_format($total_earned, 2); ?>
                                    </div>
                                    <small>Total Earned</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card border-danger border-2">
                                <div class="card-body text-center py-3">
                                    <div class="fs-4 fw-bold text-danger">
                                        -<?php echo number_format($total_spent, 2); ?>
                                    </div>
                                    <small>Total Spent</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="row">
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-arrow-down-circle fs-4 text-success me-2"></i>
                                <div>
                                    <div class="fw-bold"><?php echo count(array_filter($transactions, fn($t) => $t['hours'] > 0)); ?></div>
                                    <small class="text-muted">Earning Transactions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-arrow-up-circle fs-4 text-danger me-2"></i>
                                <div>
                                    <div class="fw-bold"><?php echo count(array_filter($transactions, fn($t) => $t['hours'] < 0)); ?></div>
                                    <small class="text-muted">Spending Transactions</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- How to Earn/Spend -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-down-circle me-2"></i>How to Earn Hours</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Offer services to other members</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Complete service requests</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Get 2 free hours when you join</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Receive bonuses from admin</li>
                    </ul>
                    <div class="mt-3">
                        <a href="add_service.php" class="btn btn-success btn-sm">
                            <i class="bi bi-plus-circle me-1"></i>Offer a Service
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>How to Spend Hours</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-info me-2"></i> Request help from other members</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-info me-2"></i> Use services offered in community</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-info me-2"></i> Pay for completed services</li>
                        <li><i class="bi bi-check-circle-fill text-info me-2"></i> Transfer hours to other members (coming soon)</li>
                    </ul>
                    <div class="mt-3">
                        <a href="services.php" class="btn btn-info btn-sm">
                            <i class="bi bi-search me-1"></i>Browse Services
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Transaction History</h5>
        </div>
        <div class="card-body">
            <?php if(empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-wallet display-4 text-muted mb-3"></i>
                    <h5>No Transactions Yet</h5>
                    <p class="text-muted">Start earning hours by offering services to the community!</p>
                    <a href="add_service.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Offer Your First Service
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Description</th>
                                <th>Related To</th>
                                <th class="text-end">Hours</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></small><br>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($transaction['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($transaction['description']); ?></div>
                                    <?php if($transaction['other_user_name']): ?>
                                        <small class="text-muted">
                                            With: <?php echo htmlspecialchars($transaction['other_user_name']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($transaction['service_title']): ?>
                                        <a href="service_detail.php?id=<?php echo $transaction['related_request_id']; ?>" 
                                           class="badge bg-info text-decoration-none">
                                            <?php echo htmlspecialchars($transaction['service_title']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">System</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if($transaction['hours'] > 0): ?>
                                        <span class="text-success fw-bold">+<?php echo $transaction['hours']; ?></span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold"><?php echo $transaction['hours']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold"><?php echo $transaction['new_balance']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Export Option -->
                <div class="mt-3 text-end">
                    <a href="time_wallet.php?export=csv" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1"></i>Export as CSV
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Time Bank Rules -->
    <div class="card shadow mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Time Bank Rules</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-clock fs-4 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>1 Hour = 1 Hour</h6>
                            <p class="small text-muted mb-0">All services are valued equally by time spent</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-shield-check fs-4 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>No Cash Involved</h6>
                            <p class="small text-muted mb-0">Time credits cannot be exchanged for money</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <i class="bi bi-arrow-repeat fs-4 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6>Keep It Circulating</h6>
                            <p class="small text-muted mb-0">Earn by giving, spend by receiving</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>