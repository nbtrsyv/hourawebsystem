<?php
// dashboard.php - User Dashboard
require_once 'includes/session_start.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch user's services
$stmt = $conn->prepare("
    SELECT * FROM services 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$my_services = $stmt->fetchAll();

// Fetch service requests for user
$stmt = $conn->prepare("
    SELECT sr.*, s.title, u.full_name as requester_name 
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users u ON sr.requester_id = u.user_id
    WHERE s.user_id = ? 
    AND sr.status IN ('pending', 'accepted')
    ORDER BY sr.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$service_requests = $stmt->fetchAll();

// Fetch user's service requests (where they requested help)
$stmt = $conn->prepare("
    SELECT sr.*, s.title, u.full_name as provider_name 
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users u ON s.user_id = u.user_id
    WHERE sr.requester_id = ? 
    ORDER BY sr.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$my_requests = $stmt->fetchAll();

// Count statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE user_id = ? AND status = 'available'");
$stmt->execute([$user_id]);
$active_services = $stmt->fetch()['count'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    WHERE s.user_id = ? AND sr.status = 'pending'
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetch()['count'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM service_requests 
    WHERE requester_id = ? AND status = 'pending'
");
$stmt->execute([$user_id]);
$my_pending_requests = $stmt->fetch()['count'];

?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Welcome Header -->
    <div class="welcome-header mb-4">
        <h1 class="welcome-title">
            <i class="bi bi-house-door me-2"></i>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!
        </h1>
        <p class="welcome-subtitle">Here's your community time banking overview</p>
    </div>
    
    <!-- Stats Cards Grid -->
    <div class="row mb-4">
        <!-- Time Balance Card (expanded) -->
        <div class="col-12 mb-4">
            <div class="card time-balance-card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-wallet2 me-2"></i>Time Balance
                    </h5>
                    <div class="time-balance text-center my-3">
                        <div style="font-size:3.4rem;font-weight:800;"><?php echo number_format($user['time_balance'], 1); ?></div>
                        <small class="fs-6 text-muted">available hours</small>
                    </div>
                    <div class="d-grid">
                        <a href="time_wallet.php" class="btn btn-outline-primary">
                            <i class="bi bi-clock-history me-2"></i>View Transactions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- My Services Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tools me-2"></i>My Services</h5>
                    <a href="my_services.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if(empty($my_services)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                            <p class="text-muted">You haven't offered any services yet</p>
                            <a href="add_service.php" class="btn btn-primary">Offer Your First Service</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($my_services as $service): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($service['title']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo $service['hours_required']; ?> hours • 
                                            <?php echo ucfirst($service['status']); ?>
                                        </small>
                                    </div>
                                    <a href="service_detail.php?id=<?php echo $service['service_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Service Requests -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Requests</h5>
                    <a href="service_requests.php" class="btn btn-sm btn-outline-primary">Manage All</a>
                </div>
                <div class="card-body">
                    <?php if(empty($service_requests)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-bell-slash display-4 text-muted mb-3"></i>
                            <p class="text-muted">No pending requests</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($service_requests as $request): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['title']); ?></h6>
                                        <small class="text-muted">
                                            From: <?php echo htmlspecialchars($request['requester_name']); ?> • 
                                            Status: <span class="badge bg-<?php echo $request['status'] == 'pending' ? 'warning' : 'info'; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <a href="request_detail.php?id=<?php echo $request['request_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">Respond</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>My Requests</h6>
                    <?php if(empty($my_requests)): ?>
                        <p class="text-muted">No recent activity</p>
                    <?php else: ?>
                        <ul class="list-unstyled">
                            <?php foreach($my_requests as $request): ?>
                            <li class="mb-2">
                                <i class="bi bi-arrow-right-circle text-primary me-2"></i>
                                Requested: <?php echo htmlspecialchars($request['title']); ?>
                                <small class="text-muted d-block">
                                    Status: <?php echo $request['status']; ?> • 
                                    Provider: <?php echo htmlspecialchars($request['provider_name']); ?>
                                </small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h6>Time Transactions</h6>
                    <?php
                    $stmt = $conn->prepare("
                        SELECT * FROM time_transactions 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ");
                    $stmt->execute([$user_id]);
                    $transactions = $stmt->fetchAll();
                    
                    if(empty($transactions)): ?>
                        <p class="text-muted">No transactions yet</p>
                    <?php else: ?>
                        <ul class="list-unstyled">
                            <?php foreach($transactions as $transaction): ?>
                            <li class="mb-2">
                                <?php if($transaction['hours'] > 0): ?>
                                    <i class="bi bi-arrow-down-circle text-success me-2"></i>
                                    <span class="text-success">+<?php echo $transaction['hours']; ?> hours</span>
                                <?php else: ?>
                                    <i class="bi bi-arrow-up-circle text-danger me-2"></i>
                                    <span class="text-danger"><?php echo $transaction['hours']; ?> hours</span>
                                <?php endif; ?>
                                <small class="text-muted d-block">
                                    <?php echo $transaction['description']; ?> • 
                                    <?php echo date('M d, H:i', strtotime($transaction['created_at'])); ?>
                                </small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>