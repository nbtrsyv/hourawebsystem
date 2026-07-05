<?php
// user/upload_proof.php - FILE BARU
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

// Get completed requests that need proof
$completedRequests = $conn->prepare("
    SELECT sr.request_id, s.title, s.hours_required, 
           rq.full_name as requester_name, sr.created_at
    FROM service_requests sr
    JOIN services s ON sr.service_id = s.service_id
    JOIN users rq ON sr.requester_id = rq.user_id
    WHERE sr.provider_id = ?
    AND sr.status = 'completed'
    AND NOT EXISTS (
        SELECT 1 FROM task_proofs tp 
        WHERE tp.request_id = sr.request_id 
        AND tp.uploaded_by = ?
    )
    ORDER BY sr.created_at DESC
");
$completedRequests->execute([$user_id, $user_id]);
$requests = $completedRequests->fetchAll();

// Handle proof upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $description = trim($_POST['description'] ?? '');
    
    // Validate file
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['proof_image']['type'];
        $file_size = $_FILES['proof_image']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= 5242880) { // 5MB max
            // Create uploads directory
            $upload_dir = 'uploads/proofs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate filename
            $file_ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
            $new_filename = 'proof_' . $request_id . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            // Upload file
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target_file)) {
                // Save to database
                $stmt = $conn->prepare("
                    INSERT INTO task_proofs (request_id, image_path, description, uploaded_by, status) 
                    VALUES (?, ?, ?, ?, 'pending_review')
                ");
                
                if ($stmt->execute([$request_id, $target_file, $description, $user_id])) {
                    $success = "Proof uploaded successfully! Waiting for admin approval.";
                    // Refresh requests list
                    $completedRequests->execute([$user_id, $user_id]);
                    $requests = $completedRequests->fetchAll();
                } else {
                    $error = "Failed to save proof. Please try again.";
                }
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        } else {
            $error = "Invalid file. Only JPG, PNG, GIF allowed (max 5MB).";
        }
    } else {
        $error = "Please select an image file.";
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Upload Service Proof</h5>
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
                    
                    <!-- Select Request Form -->
                    <?php if(empty($requests)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle display-4 text-success mb-3"></i>
                        <h4>All Proofs Submitted</h4>
                        <p class="text-muted mb-4">You have uploaded proofs for all completed requests.</p>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                    <?php else: ?>
                    
                    <div class="mb-4">
                        <p class="text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            Upload proof of completed work for services you provided. This helps verify service completion.
                        </p>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="proofForm">
                        <!-- Select Request -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Completed Service</label>
                            <select class="form-select" name="request_id" id="requestSelect" required>
                                <option value="">-- Choose a service --</option>
                                <?php foreach($requests as $req): ?>
                                <option value="<?php echo $req['request_id']; ?>" 
                                        data-hours="<?php echo $req['hours_required']; ?>"
                                        data-requester="<?php echo htmlspecialchars($req['requester_name']); ?>">
                                    <?php echo htmlspecialchars($req['title']); ?> 
                                    (<?php echo $req['hours_required']; ?> hours)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Request Details (will be filled by JS) -->
                        <div class="card bg-light mb-4" id="requestDetails" style="display: none;">
                            <div class="card-body">
                                <h6 id="selectedTitle"></h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Hours:</small>
                                        <div id="selectedHours"></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Requester:</small>
                                        <div id="selectedRequester"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Proof Image -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Proof Image</label>
                            <input type="file" class="form-control" name="proof_image" 
                                   accept="image/*" required>
                            <div class="form-text">
                                Upload a clear photo showing the completed work (max 5MB).
                            </div>
                            
                            <!-- Image Preview -->
                            <div class="mt-3 text-center" id="imagePreview" style="display: none;">
                                <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Description (Optional)</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Briefly describe what was completed..."></textarea>
                            <div class="form-text">
                                Example: "Garden cleaned, all weeds removed, grass trimmed."
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-upload me-2"></i>Upload Proof
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Previous Proofs -->
            <?php
            $previousProofs = $conn->prepare("
                SELECT tp.*, s.title, tp.status as proof_status
                FROM task_proofs tp
                JOIN service_requests sr ON tp.request_id = sr.request_id
                JOIN services s ON sr.service_id = s.service_id
                WHERE tp.uploaded_by = ?
                ORDER BY tp.created_at DESC
                LIMIT 5
            ");
            $previousProofs->execute([$user_id]);
            $proofs = $previousProofs->fetchAll();
            
            if (!empty($proofs)): ?>
            <div class="card shadow mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-history me-2"></i>Previous Proofs</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($proofs as $proof): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($proof['title']); ?></td>
                                    <td>
                                        <?php 
                                        $badgeClass = 'secondary';
                                        if($proof['proof_status'] == 'approved') $badgeClass = 'success';
                                        if($proof['proof_status'] == 'rejected') $badgeClass = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($proof['proof_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($proof['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Show request details when selected
document.getElementById('requestSelect').addEventListener('change', function() {
    const detailsDiv = document.getElementById('requestDetails');
    const selectedOption = this.options[this.selectedIndex];
    
    if (this.value) {
        const title = selectedOption.text;
        const hours = selectedOption.dataset.hours;
        const requester = selectedOption.dataset.requester;
        
        document.getElementById('selectedTitle').textContent = title;
        document.getElementById('selectedHours').textContent = hours + ' hours';
        document.getElementById('selectedRequester').textContent = requester;
        
        detailsDiv.style.display = 'block';
    } else {
        detailsDiv.style.display = 'none';
    }
});

// Image preview
document.querySelector('input[name="proof_image"]').addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    const img = preview.querySelector('img');
    
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(this.files[0]);
    } else {
        preview.style.display = 'none';
    }
});

// Form validation
document.getElementById('proofForm').addEventListener('submit', function(e) {
    const fileInput = document.querySelector('input[name="proof_image"]');
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (fileInput.files[0].size > maxSize) {
        e.preventDefault();
        alert('File size must be less than 5MB');
        fileInput.focus();
    }
});
</script>

<style>
#requestDetails {
    border-left: 4px solid var(--primary-purple);
}

#imagePreview img {
    max-width: 100%;
    height: auto;
}
</style>

<?php include 'includes/footer.php'; ?>