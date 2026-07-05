<?php
// profile.php - User Profile Management
require_once 'includes/session_start.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Handle profile image upload
    $profile_image = $user['profile_image']; // Keep existing if not changed
    
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = $_FILES['profile_pic']['type'];
        $file_size = $_FILES['profile_pic']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= 2097152) { // 2MB max
            $upload_dir = 'uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $new_filename;
            if ($user['profile_image'] && file_exists($user['profile_image'])) {
                @unlink($user['profile_image']);
            }
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $profile_image = $target_file;
                $_SESSION['profile_image'] = $target_file;
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        } else {
            $error = "Invalid file. Only JPG, PNG, GIF allowed (max 2MB).";
        }
    }

    // Handle password change if provided
    $password_hash = $user['password_hash'];
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        if (password_verify($_POST['current_password'], $user['password_hash'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            } else {
                $error = "New passwords do not match!";
            }
        } else {
            $error = "Current password is incorrect!";
        }
    }

    // Update database if no errors
    if (empty($error)) {
        $sql = "UPDATE users SET 
                full_name = ?, 
                phone = ?, 
                address = ?, 
                profile_image = ?, 
                password_hash = ?,
                updated_at = NOW()
                WHERE user_id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$full_name, $phone, $address, $profile_image, $password_hash, $user_id])) {
            $_SESSION['full_name'] = $full_name;
            $success = "Profile updated successfully!";
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}

?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <div class="row">
        <!-- Left Column - Profile Overview -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0">My Profile</h5>
                </div>
                <div class="card-body text-center">
                    <!-- Profile Image -->
                    <div class="mb-4">
                        <?php if($user['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="Profile" 
                                 class="rounded-circle border border-3 border-primary"
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto"
                                 style="width: 150px; height: 150px; border: 3px solid var(--primary-purple);">
                                <i class="bi bi-person display-4" style="color: var(--primary-purple);"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- User Info -->
                    <h4>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                        <?php if(isset($user['is_verified']) && $user['is_verified'] == 1): ?>
                            <i class="bi bi-patch-check-fill text-primary" title="Verified Account"></i>
                        <?php endif; ?>
                    </h4>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div class="mb-3">
                        <?php if(isset($user['is_verified']) && $user['is_verified'] == 1): ?>
                            <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Verified Identity</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-shield-exclamation me-1"></i>Unverified</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stats -->
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="fw-bold fs-4" style="color: var(--primary-purple);">
                                <?php echo $user['time_balance']; ?>
                            </div>
                            <small class="text-muted">Hours</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold fs-4" style="color: var(--primary-purple);">
                                <?php echo $user['rating'] ?? '5.0'; ?>
                            </div>
                            <small class="text-muted">Rating</small>
                        </div>
                    </div>
                    
                    <!-- Member Since -->
                    <div class="alert alert-light">
                        <i class="bi bi-calendar-check me-2"></i>
                        Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card shadow mt-4">
                <div class="card-body">
                    <h6 class="mb-3">Quick Links</h6>
                    <div class="list-group list-group-flush">
                        <?php if(!isset($user['is_verified']) || $user['is_verified'] == 0): ?>
                        <a href="verify_identity.php" class="list-group-item list-group-item-action list-group-item-warning fw-bold">
                            <i class="bi bi-person-badge me-2"></i>Verify Identity (eKYC)
                        </a>
                        <?php endif; ?>
                        <a href="dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <a href="time_wallet.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-wallet2 me-2"></i>Time Wallet
                        </a>
                        <a href="my_services.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-tools me-2"></i>My Services
                        </a>
                        <a href="my_requests.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-list-check me-2"></i>My Requests
                        </a>
                        <a href="chat.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-chat me-2"></i>Messages
                        </a>
                        <a href="upload_proof.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Proofs
                        </a>
                        <a href="create_dispute.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-exclamation-triangle me-2"></i>Create Dispute
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Edit Profile -->
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Profile</h5>
                </div>
                <div class="card-body">
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- Profile Image Upload -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Profile Picture</label>
                            <div class="row align-items-center">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="border rounded p-3 text-center">
                                        <?php if($user['profile_image']): ?>
                                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                 alt="Current" 
                                                 class="img-fluid rounded"
                                                 style="max-height: 150px;">
                                        <?php else: ?>
                                            <i class="bi bi-person display-4 text-muted"></i>
                                            <p class="small text-muted mt-2">No photo yet</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <input type="file" class="form-control" name="profile_pic" 
                                           accept="image/*">
                                    <div class="form-text">
                                        Upload JPG, PNG or GIF (max 2MB). Recommended: 300x300 pixels.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Basic Information -->
                        <h6 class="border-bottom pb-2 mb-4">Basic Information</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <div class="form-text">Email cannot be changed</div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                <div class="form-text">For service coordination</div>
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" 
                                          rows="1"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                <div class="form-text">General area (e.g., "Kuala Lumpur City Center")</div>
                            </div>
                        </div>
                        
                        <!-- Change Password (Optional) -->
                        <h6 class="border-bottom pb-2 mb-4">Change Password (Optional)</h6>
                        
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Leave password fields blank if you don't want to change password.
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            <div class="col-md-4">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            <div class="col-md-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between mt-5">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-width Account Statistics (starts below Quick Links) -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Account Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Fetch statistics
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $services_count = $stmt->fetch()['count'];

                    $stmt = $conn->prepare("\n                        SELECT COUNT(*) as count FROM service_requests sr\n                        JOIN services s ON sr.service_id = s.service_id\n                        WHERE s.user_id = ? AND sr.status = 'completed'\n                    ");
                    $stmt->execute([$user_id]);
                    $services_completed = $stmt->fetch()['count'];

                    $stmt = $conn->prepare("\n                        SELECT COUNT(*) as count FROM service_requests \n                        WHERE requester_id = ? AND status = 'completed'\n                    ");
                    $stmt->execute([$user_id]);
                    $requests_completed = $stmt->fetch()['count'];

                    $stmt = $conn->prepare("\n                        SELECT COUNT(*) as count FROM reviews WHERE reviewee_id = ?\n                    ");
                    $stmt->execute([$user_id]);
                    $reviews_count = $stmt->fetch()['count'];
                    ?>

                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                                        <?php echo $services_count; ?>
                                    </div>
                                    <small>Services Offered</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                                        <?php echo $services_completed; ?>
                                    </div>
                                    <small>Services Given</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                                        <?php echo $requests_completed; ?>
                                    </div>
                                    <small>Requests Fulfilled</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="display-6 fw-bold" style="color: var(--primary-purple);">
                                        <?php echo $reviews_count; ?>
                                    </div>
                                    <small>Reviews Received</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>