<?php
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please enter email and password!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] == 'banned') {
                $error = "Your account has been permanently banned. You cannot login.";
            } else if ($user['status'] == 'suspended') {
                if ($user['suspended_until'] && strtotime($user['suspended_until']) <= time()) {
                    $reactivateStmt = $conn->prepare("UPDATE users SET status = 'active', suspended_until = NULL WHERE user_id = ?");
                    $reactivateStmt->execute([$user['user_id']]);
                    
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['time_balance'] = $user['time_balance'];
                    
                    try {
                        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        $updateStmt->execute([$user['user_id']]);
                    } catch (PDOException $e) {
                    }
                    
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                        header('Location: admin/index.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit();
                } else {
                    $suspendedUntil = date('d/m/Y', strtotime($user['suspended_until']));
                    $error = "Your account is temporarily suspended until $suspendedUntil. You cannot login during this period.";
                }
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['time_balance'] = $user['time_balance'];
                
                try {
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $updateStmt->execute([$user['user_id']]);
                } catch (PDOException $e) {
                }
                
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    header('Location: admin/index.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            }
        } else {
            $error = "Invalid email or password!";
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container" style="max-width: 500px; margin: 50px auto;">
    <div class="card shadow">
        <div class="card-header text-center bg-primary text-white">
            <h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Login to Houra</h4>
        </div>
        <div class="card-body p-4">
            
            <?php if(isset($_SESSION['message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo $_POST['email'] ?? ''; ?>" required>
                </div>
                
                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <!-- Submit Button -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </div>
                
                <!-- Register Link -->
                <div class="text-center mt-4">
                    <p>Don't have an account? 
                        <a href="register.php" class="text-decoration-none fw-bold">Sign up for free</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>