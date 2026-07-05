<?php
// edit_service.php - Edit Existing Service
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Fetch service details
$stmt = $conn->prepare("
    SELECT s.*, c.name as category_name
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.category_id
    WHERE s.service_id = ? AND s.user_id = ?
");
$stmt->execute([$service_id, $user_id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: my_services.php');
    exit();
}

// Fetch categories
$stmt = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $hours_required = (float)$_POST['hours_required'];
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    
    // Validation
    if (empty($title) || empty($description) || empty($hours_required)) {
        $error = "Title, description, and hours are required!";
    } elseif ($hours_required <= 0) {
        $error = "Hours required must be greater than 0!";
    } else {
        // Update service
        $sql = "UPDATE services SET 
                title = ?, 
                description = ?, 
                category_id = ?, 
                hours_required = ?, 
                location = ?, 
                status = ?,
                updated_at = NOW()
                WHERE service_id = ? AND user_id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$title, $description, $category_id, $hours_required, $location, $status, $service_id, $user_id])) {
            $success = "Service updated successfully!";
            // Refresh service data
            $stmt = $conn->prepare("SELECT * FROM services WHERE service_id = ?");
            $stmt->execute([$service_id]);
            $service = $stmt->fetch();
        } else {
            $error = "Failed to update service.";
        }
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="my_services.php">My Services</a></li>
            <li class="breadcrumb-item active">Edit Service</li>
        </ol>
    </nav>
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Service</h4>
                </div>
                <div class="card-body p-4">
                    
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
                    
                    <form method="POST" action="">
                        <!-- Service Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label fw-bold">Service Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($service['title']); ?>" required>
                        </div>
                        
                        <!-- Category & Hours -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select category</option>
                                    <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"
                                        <?php echo ($cat['category_id'] == $service['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="hours_required" class="form-label">Hours Required *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="hours_required" name="hours_required" 
                                           step="0.5" min="0.5" max="50"
                                           value="<?php echo $service['hours_required']; ?>" required>
                                    <span class="input-group-text">hours</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold">Description *</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="6" required><?php echo htmlspecialchars($service['description']); ?></textarea>
                        </div>
                        
                        <!-- Location & Status -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($service['location'] ?? ''); ?>"
                                       placeholder="e.g., Kuala Lumpur, Online">
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="available" <?php echo ($service['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="in_progress" <?php echo ($service['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo ($service['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($service['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="my_services.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to My Services
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save me-2"></i>Update Service
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>