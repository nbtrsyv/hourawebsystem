<?php
// register.php - COMPLETE VERSION WITH MISSIONS
require_once 'includes/session_start.php';
require_once 'config/settings.php';
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    if (empty($email) || empty($password) || empty($full_name)) {
        $error = "Email, password, and name are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Email already registered!";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // GUNA BONUS DARI SETTINGS
            $initial_balance = defined('NEW_USER_BONUS') ? NEW_USER_BONUS : 0;
    
            // Insert user with dynamic bonus hours
            $sql = "INSERT INTO users (email, password_hash, full_name, phone, time_balance) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$email, $password_hash, $full_name, $phone, $initial_balance])) {
                // Get new user ID
                $user_id = $conn->lastInsertId();
                
                // Set session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role'] = 'user';
                $_SESSION['time_balance'] = $initial_balance;
                
                // Create time transaction record
                $sql = "INSERT INTO time_transactions (user_id, hours, transaction_type, description, previous_balance, new_balance)
                        VALUES (?, ?, 'bonus', 'Welcome bonus for new member', 0.00, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$user_id, $initial_balance, $initial_balance]);
                
                // Log activity
                $log_stmt = $conn->prepare("
                    INSERT INTO activity_logs (user_id, activity_type, description) 
                    VALUES (?, 'registration', 'New user registered and missions initialized')
                ");
                $log_stmt->execute([$user_id]);
                
                // TUNJUK BONUS YANG DITERIMA MENGGUNAKAN SESSION SUCCESS
                $_SESSION['success'] = "Registration successful! You received " . $initial_balance . " hours bonus!";
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container" style="max-width: 600px; margin: 50px auto;">
    <div class="card shadow">
        <div class="card-header text-center bg-primary text-white">
            <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Join Houra Community</h4>
            <small class="fst-italic">Get 2 free hours instantly!</small>
        </div>
        <div class="card-body p-4">
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" novalidate>
                <!-- Full Name -->
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo $_POST['full_name'] ?? ''; ?>" required>
                    <div class="form-text">This will be visible to other community members</div>
                </div>
                
                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    <div class="form-text">We'll never share your email with others</div>
                </div>
                
                <!-- Phone (Optional) -->
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number (Optional)</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo $_POST['phone'] ?? ''; ?>">
                    <div class="form-text">For service coordination purposes</div>
                </div>
                
                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Minimum 6 characters</div>
                </div>
                
                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <!-- Terms -->
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to trade time fairly and respect community guidelines
                    </label>
                </div>
                
                <!-- Submit Button -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-person-plus me-2"></i>Create Account & Get 2 Free Hours
                    </button>
                </div>
                
                <!-- Login Link -->
                <div class="text-center mt-4">
                    <p>Already have an account? 
                        <a href="login.php" class="text-decoration-none fw-bold">Login here</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Benefits Card -->
    <div class="card mt-4 shadow-sm">
        <div class="card-body">
            <h6 class="mb-3"><i class="bi bi-stars text-warning me-2"></i>Why Join Houra?</h6>
            <ul class="list-unstyled">
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Start with <strong>2 free hours</strong></li>
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Trade services without money</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Build community connections</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Complete missions & earn badges</li>
                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Safe & verified members</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>