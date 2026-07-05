<?php
// add_service.php - Offer a New Service
require_once 'includes/session_start.php';
require_once 'config/database.php';
require_once 'config/settings.php'; // Penting untuk MIN_HOURS & MAX_HOURS
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
    $_SESSION['message'] = 'Your account is not active. You cannot add services at this time.';
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

redirectIfUnverified($conn, $user_id);

// Fetch categories
$stmt = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $hours_required = floatval($_POST['hours_required']);
    $location = trim($_POST['location']);
    
    // --- VALIDATION BARU MASUK SINI ---
    if (empty($title) || empty($description) || empty($hours_required)) {
        $error = "Title, description, and hours are required!";
    } elseif ($hours_required < MIN_HOURS) {
        $error = "Minimum hours required is " . MIN_HOURS . " hours";
    } elseif ($hours_required > MAX_HOURS) {
        $error = "Maximum hours allowed is " . MAX_HOURS . " hours";
    } else {
        // Insert service
        $sql = "INSERT INTO services (title, description, category_id, user_id, hours_required, location) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$title, $description, $category_id, $user_id, $hours_required, $location])) {
            $service_id = $conn->lastInsertId();
            $success = "Service offered successfully! It's now visible in the community.";
            
            // Reset form
            $_POST = [];
            $title = $description = $location = '';
        } else {
            $error = "Failed to offer service. Please try again.";
        }
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="services.php">Services</a></li>
                    <li class="breadcrumb-item active">Offer Service</li>
                </ol>
            </nav>
            
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Offer a Service to Community</h4>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert alert-info mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle fs-4 me-3"></i>
                            <div>
                                <strong>Service Limits:</strong><br>
                                Minimum: <?php echo MIN_HOURS; ?> hours | 
                                Maximum: <?php echo MAX_HOURS; ?> hours
                            </div>
                        </div>
                    </div>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                            <div class="mt-2">
                                <a href="service_detail.php?id=<?php echo $service_id ?? ''; ?>" class="btn btn-sm btn-outline-success me-2">
                                    View Service
                                </a>
                                <a href="services.php" class="btn btn-sm btn-outline-primary">
                                    Browse All Services
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="title" class="form-label fw-bold">
                                Service Title *
                                <span class="text-muted fw-normal">(What service are you offering?)</span>
                            </label>
                            <input type="text" class="form-control form-control-lg" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($title ?? ($_POST['title'] ?? '')); ?>" 
                                   placeholder="e.g., Math Tutoring, Computer Repair, Garden Cleaning" required>
                            <div class="form-text">Be clear and specific about what you can help with.</div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label fw-bold">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"
                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="hours_required" class="form-label fw-bold">
                                    Hours Required *
                                    <span class="text-muted fw-normal">(per session/task)</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="hours_required" name="hours_required" 
                                           step="0.5" min="<?php echo MIN_HOURS; ?>" max="<?php echo MAX_HOURS; ?>"
                                           value="<?php echo $_POST['hours_required'] ?? '2.0'; ?>" required>
                                    <span class="input-group-text">hours</span>
                                </div>
                                <div class="form-text">
                                    Standard: 1-2 hours. Min: <?php echo MIN_HOURS; ?>, Max: <?php echo MAX_HOURS; ?>.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold">
                                Service Description *
                                <span class="text-muted fw-normal">(Details about what you'll do)</span>
                            </label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="6" required><?php echo htmlspecialchars($description ?? ($_POST['description'] ?? '')); ?></textarea>
                            <div class="form-text">
                                Include: What exactly you'll do, any requirements, your experience, etc.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="location" class="form-label fw-bold">
                                Location
                                <span class="text-muted fw-normal">(Where will you provide this service?)</span>
                            </label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($location ?? ($_POST['location'] ?? '')); ?>"
                                   placeholder="e.g., Kuala Lumpur, Online, Your Home, etc.">
                            <div class="form-text">
                                Leave blank for "Flexible" or "Online".
                            </div>
                        </div>
                        
                        <div class="card bg-light border-info mb-4">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="bi bi-lightbulb me-2"></i>Tips for a Great Service Listing
                                </h6>
                                <ul class="mb-0">
                                    <li>Be specific about what you can and cannot do</li>
                                    <li>Mention any tools or materials needed</li>
                                    <li>Set realistic time estimates</li>
                                    <li>Be clear about your availability</li>
                                    <li>Remember: All hours are valued equally!</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-5">
                            <a href="services.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Publish Service
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Service Examples</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-primary mb-3">
                                <div class="card-body">
                                    <h6>📚 Academic Tutoring</h6>
                                    <p class="small mb-0">"Math help for primary students, 1 hour sessions"</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-primary mb-3">
                                <div class="card-body">
                                    <h6>🔧 Home Repairs</h6>
                                    <p class="small mb-0">"Basic furniture assembly, 2 hours max per item"</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-primary mb-3">
                                <div class="card-body">
                                    <h6>💻 Tech Help</h6>
                                    <p class="small mb-0">"Computer setup and software installation, 1.5 hours"</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>