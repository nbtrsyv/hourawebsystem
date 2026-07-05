<?php
// request_service.php - Request a Service from Provider
require_once 'includes/session_start.php';
require_once 'config/database.php';
require_once 'config/settings.php';
require_once 'includes/check_verification.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is suspended or banned
$userStatus = checkUserStatus($conn, $_SESSION['user_id']);
if ($userStatus != 'active') {
    $_SESSION['message'] = 'Your account is not active. You cannot request services at this time.';
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

redirectIfUnverified($conn, $user_id);

// --- DAPATKAN BALANCE USER (AWAL) ---
$stmt = $conn->prepare("SELECT time_balance FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_balance = $stmt->fetch()['time_balance'];

// --- LOGIK DAILY LIMIT (GUNA CURDATE() - LEBIH RELIABLE) ---
$stmt = $conn->prepare("
    SELECT COUNT(*) as request_count 
    FROM service_requests 
    WHERE requester_id = ? 
    AND DATE(created_at) = CURDATE()
");
$stmt->execute([$user_id]);
$today_requests = $stmt->fetch()['request_count'];

// --- PAPAR SUCCESS MESSAGE DARI SESSION ---
if (isset($_SESSION['request_success'])) {
    $success = $_SESSION['request_success'];
    unset($_SESSION['request_success']);
}

// --- FETCH SERVICE DETAILS UNTUK DISPLAY ---
// HANYA fetch kalau TAK ADA success message
$service = null;
if (empty($success) && $service_id > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name as provider_name, u.user_id as provider_id
        FROM services s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.service_id = ? AND s.status = 'available'
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    
    if (!$service) {
        $error = "Service not found or no longer available!";
    } elseif ($service['provider_id'] == $user_id) {
        $error = "You cannot request your own service!";
    } elseif ($today_requests >= MAX_DAILY_REQUESTS) {
        // CHECK LIMIT DI SINI JUGA untuk display
        $error = "You have reached your daily limit of " . MAX_DAILY_REQUESTS . " requests. Please try again tomorrow.";
    }
}

// --- HANDLE FORM SUBMISSION (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $service_id > 0 && empty($success)) {
    $scheduled_date = $_POST['scheduled_date'];
    $scheduled_time = $_POST['scheduled_time'];
    $notes = trim($_POST['notes'] ?? '');
    
    // Fetch service untuk POST (pastikan masih available)
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name as provider_name, u.user_id as provider_id
        FROM services s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.service_id = ? AND s.status = 'available'
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    
    if (!$service) {
        $error = "Service not found or no longer available!";
    } elseif ($service['provider_id'] == $user_id) {
        $error = "You cannot request your own service!";
    } elseif (empty($scheduled_date)) {
        $error = "Please select a date!";
    } elseif ($user_balance < $service['hours_required']) {
        $error = "You don't have enough hours! You need {$service['hours_required']} hours, but you have {$user_balance}.";
    } else {
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // RECHECK DAILY LIMIT DALAM TRANSACTION DENGAN LOCK
            $stmt = $conn->prepare("
                SELECT COUNT(*) as request_count 
                FROM service_requests 
                WHERE requester_id = ? 
                AND DATE(created_at) = CURDATE()
                FOR UPDATE
            ");
            $stmt->execute([$user_id]);
            $current_count = $stmt->fetch()['request_count'];
            
            // DEBUG: Uncomment ni untuk check dalam browser
            // throw new Exception("Debug: Current count = " . $current_count . ", Max = " . MAX_DAILY_REQUESTS);
            
            if ($current_count >= MAX_DAILY_REQUESTS) {
                throw new Exception("You have reached your daily limit of " . MAX_DAILY_REQUESTS . " requests. Please try again tomorrow.");
            }
            
            // Create service request
            $sql = "INSERT INTO service_requests (service_id, requester_id, provider_id, hours_required, scheduled_date, scheduled_time, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $service_id, 
                $user_id, 
                $service['provider_id'],
                $service['hours_required'],
                $scheduled_date,
                $scheduled_time,
                $notes
            ]);
            $request_id = $conn->lastInsertId();
            
            // Update service status
            $stmt = $conn->prepare("UPDATE services SET status = 'in_progress' WHERE service_id = ?");
            $stmt->execute([$service_id]);
            
            // Create notification for provider
            $notification_msg = "New request for your service: '{$service['title']}'";
            $sql = "INSERT INTO notifications (user_id, title, message, type, related_id) 
                    VALUES (?, 'New Service Request', ?, 'request', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$service['provider_id'], $notification_msg, $request_id]);
            
            // Commit transaction
            $conn->commit();
            
            // REDIRECT dengan success
            $_SESSION['request_success'] = "Service requested successfully! The provider will contact you soon.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $service_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to request service: " . $e->getMessage();
        }
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="services.php">Services</a></li>
            <li class="breadcrumb-item"><a href="service_detail.php?id=<?php echo $service_id; ?>">
                <?php echo htmlspecialchars($service['title'] ?? 'Service'); ?>
            </a></li>
            <li class="breadcrumb-item active">Request Service</li>
        </ol>
    </nav>
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="alert alert-info mb-4">
                <div class="row">
                    <div class="col-md-6">
                        <i class="bi bi-clock me-2"></i>
                        <strong>Your Balance:</strong> <?php echo $user_balance; ?> hours
                    </div>
                    <div class="col-md-6 text-md-end">
                        <i class="bi bi-calendar-day me-2"></i>
                        <strong>Today's Requests:</strong> <?php echo $today_requests; ?>/<?php echo MAX_DAILY_REQUESTS; ?>
                    </div>
                </div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <div class="mt-2">
                        <a href="services.php" class="btn btn-sm btn-outline-primary">Browse Other Services</a>
                        <a href="time_wallet.php" class="btn btn-sm btn-outline-info">Check Your Time Balance</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    <div class="mt-3">
                        <a href="my_requests.php" class="btn btn-success me-2">View My Requests</a>
                        <a href="services.php" class="btn btn-outline-primary">Browse More Services</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($service && empty($success) && empty($error)): ?>
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-check-circle me-2"></i>Request Service</h4>
                </div>
                <div class="card-body p-4">
                    <div class="card border-primary mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5><?php echo htmlspecialchars($service['title']); ?></h5>
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-person me-1"></i>
                                        Provider: <?php echo htmlspecialchars($service['provider_name']); ?>
                                    </p>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-clock me-1"></i>
                                        Time required: <?php echo $service['hours_required']; ?> hours
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="display-6 fw-bold text-primary">
                                        <?php echo $service['hours_required']; ?>
                                    </div>
                                    <small>hours</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert <?php echo $user_balance >= $service['hours_required'] ? 'alert-success' : 'alert-warning'; ?> mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-wallet2 fs-4 me-3"></i>
                            <div>
                                <h6 class="mb-1">Your Time Balance: <?php echo $user_balance; ?> hours</h6>
                                <p class="mb-0">
                                    <?php if($user_balance >= $service['hours_required']): ?>
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        You have enough hours to request this service!
                                    <?php else: ?>
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        You need <?php echo $service['hours_required'] - $user_balance; ?> more hours.
                                        <a href="add_service.php" class="alert-link">Offer a service to earn hours</a>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="scheduled_date" class="form-label fw-bold">Preferred Date *</label>
                                <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                                <div class="form-text">Select when you need this service</div>
                            </div>
                            <div class="col-md-6">
                                <label for="scheduled_time" class="form-label fw-bold">Preferred Time</label>
                                <input type="time" class="form-control" id="scheduled_time" name="scheduled_time"
                                       value="14:00">
                                <div class="form-text">Optional - provider will confirm exact time</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label fw-bold">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"
                                      placeholder="Tell the provider about your specific needs, location details, or any special requirements..."><?php echo $_POST['notes'] ?? ''; ?></textarea>
                            <div class="form-text">
                                Be clear about what you need. This helps the provider prepare.
                            </div>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="agreement" required>
                            <label class="form-check-label" for="agreement">
                                I agree to pay <?php echo $service['hours_required']; ?> time credits upon service completion.
                                I understand that hours will be deducted from my wallet once the provider marks the service as complete.
                            </label>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="service_detail.php?id=<?php echo $service_id; ?>" 
                               class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Service
                            </a>
                            
                            <?php if($user_balance >= $service['hours_required'] && $today_requests < MAX_DAILY_REQUESTS): ?>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Confirm Request
                                </button>
                            <?php elseif($today_requests >= MAX_DAILY_REQUESTS): ?>
                                <button type="button" class="btn btn-secondary btn-lg" disabled>
                                    <i class="bi bi-exclamation-triangle me-2"></i>Daily Limit Reached (<?php echo $today_requests; ?>/<?php echo MAX_DAILY_REQUESTS; ?>)
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-lg" disabled>
                                    <i class="bi bi-exclamation-triangle me-2"></i>Insufficient Hours
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>What Happens After Request?</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center"
                                 style="width: 60px; height: 60px;">
                                <i class="bi bi-bell fs-4"></i>
                            </div>
                            <h6 class="mt-2">1. Provider Notified</h6>
                            <p class="small text-muted">Provider gets notification of your request</p>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center"
                                 style="width: 60px; height: 60px;">
                                <i class="bi bi-chat fs-4"></i>
                            </div>
                            <h6 class="mt-2">2. Communication</h6>
                            <p class="small text-muted">You can chat with provider to discuss details</p>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center"
                                 style="width: 60px; height: 60px;">
                                <i class="bi bi-clock fs-4"></i>
                            </div>
                            <h6 class="mt-2">3. Service Provided</h6>
                            <p class="small text-muted">Provider completes the service as agreed</p>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center"
                                 style="width: 60px; height: 60px;">
                                <i class="bi bi-check-circle fs-4"></i>
                            </div>
                            <h6 class="mt-2">4. Hours Transferred</h6>
                            <p class="small text-muted">Hours automatically transferred upon completion</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Set minimum date to tomorrow
const today = new Date().toISOString().split('T')[0];
document.getElementById('scheduled_date').min = today;
</script>

<?php include 'includes/footer.php'; ?>