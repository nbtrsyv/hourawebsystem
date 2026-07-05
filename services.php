<?php
// services.php - UPDATED with working search
require_once 'includes/session_start.php';
require_once 'config/database.php';

$user_id = $_SESSION['user_id'] ?? 0;
$search = $_GET['search'] ?? '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$min_hours = isset($_GET['min_hours']) ? (float)$_GET['min_hours'] : 0;
$max_hours = isset($_GET['max_hours']) ? (float)$_GET['max_hours'] : 50;
$provider_id = isset($_GET['provider']) ? (int)$_GET['provider'] : 0;
$location = $_GET['location'] ?? '';

// Build search query
$sql = "
    SELECT s.*, u.full_name, u.profile_image, c.name as category_name,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.service_id = s.service_id) as request_count
    FROM services s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN categories c ON s.category_id = c.category_id
    WHERE s.status = 'available'
";

$params = [];
$conditions = [];

// Search by keyword
if (!empty($search)) {
    $conditions[] = "(s.title LIKE ? OR s.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter by category
if ($category_id > 0) {
    $conditions[] = "s.category_id = ?";
    $params[] = $category_id;
}

// Filter by hours range
if ($min_hours > 0) {
    $conditions[] = "s.hours_required >= ?";
    $params[] = $min_hours;
}
if ($max_hours < 50) {
    $conditions[] = "s.hours_required <= ?";
    $params[] = $max_hours;
}

// Filter by location
if (!empty($location)) {
    $conditions[] = "s.location LIKE ?";
    $params[] = "%$location%";
}

// Filter by provider
if ($provider_id > 0) {
    $conditions[] = "s.user_id = ?";
    $params[] = $provider_id;
}

// Combine conditions
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Order by
$sql .= " ORDER BY s.created_at DESC";

// Execute query
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Fetch categories for filter dropdown
$stmt = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll();

// Count total services
$total_services = count($services);
?>

<?php include 'includes/header2.php'; ?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2"><i class="bi bi-search me-2"></i>Browse Services</h1>
            <p class="text-muted"><?php echo $total_services; ?> services available</p>
        </div>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="add_service.php" class="btn btn-success">
                <i class="bi bi-plus-circle me-2"></i>Offer a Service
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Advanced Search Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Search & Filter</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="search-form">
                <div class="row g-3">
                    <!-- Keyword Search -->
                    <div class="col-md-12 mb-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search services by title, description, or provider name..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                Search
                            </button>
                        </div>
                    </div>
                    
                    <!-- Advanced Filters (Collapsible) -->
                    <div class="col-12">
                        <a class="btn btn-outline-secondary btn-sm mb-3" 
                           data-bs-toggle="collapse" href="#advancedFilters" role="button">
                            <i class="bi bi-gear me-2"></i>Advanced Filters
                        </a>
                        
                        <div class="collapse <?php echo (!empty($category_id) || !empty($min_hours) || !empty($max_hours) || !empty($location)) ? 'show' : ''; ?>" 
                             id="advancedFilters">
                            <div class="row g-3">
                                <!-- Category Filter -->
                                <div class="col-md-4">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category">
                                        <option value="">All Categories</option>
                                        <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"
                                            <?php echo ($category_id == $cat['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Hours Range -->
                                <div class="col-md-4">
                                    <label class="form-label">Hours Required</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="number" class="form-control" name="min_hours" 
                                                   placeholder="Min" step="0.5" min="0"
                                                   value="<?php echo $min_hours; ?>">
                                        </div>
                                        <div class="col">
                                            <input type="number" class="form-control" name="max_hours" 
                                                   placeholder="Max" step="0.5" max="50"
                                                   value="<?php echo $max_hours; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Location Filter -->
                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location" 
                                           placeholder="City or area..."
                                           value="<?php echo htmlspecialchars($location); ?>">
                                </div>
                                
                                <!-- Filter Actions -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-funnel me-2"></i>Apply Filters
                                        </button>
                                        <a href="services.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle me-2"></i>Clear Filters
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Active Filters Display -->
    <?php if(!empty($search) || $category_id > 0 || $min_hours > 0 || $max_hours < 50 || !empty($location)): ?>
    <div class="alert alert-info mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>Active Filters:</strong>
                <?php 
                $filters = [];
                if(!empty($search)) $filters[] = "Search: \"$search\"";
                if($category_id > 0) {
                    $cat_name = '';
                    foreach($categories as $cat) {
                        if($cat['category_id'] == $category_id) {
                            $cat_name = $cat['name'];
                            break;
                        }
                    }
                    $filters[] = "Category: $cat_name";
                }
                if($min_hours > 0) $filters[] = "Min hours: $min_hours";
                if($max_hours < 50) $filters[] = "Max hours: $max_hours";
                if(!empty($location)) $filters[] = "Location: $location";
                
                echo implode(', ', $filters);
                ?>
            </div>
            <a href="services.php" class="btn btn-sm btn-outline-danger">
                Clear All
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Services Grid -->
    <?php if(empty($services)): ?>
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="bi bi-search display-1 text-muted mb-3"></i>
                <h4>No Services Found</h4>
                <p class="text-muted mb-4">
                    <?php if(!empty($search) || $category_id > 0 || $min_hours > 0 || $max_hours < 50 || !empty($location)): ?>
                        Try adjusting your search filters or clear them to see all services.
                    <?php else: ?>
                        No services available yet. Be the first to offer a service!
                    <?php endif; ?>
                </p>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="add_service.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Offer Your First Service
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Join to Offer Services
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Sort Options -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                Showing <?php echo count($services); ?> of <?php echo $total_services; ?> services
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                        data-bs-toggle="dropdown">
                    <i class="bi bi-sort-down me-2"></i>Sort by
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="sortServices('newest')">Newest First</a></li>
                    <li><a class="dropdown-item" href="#" onclick="sortServices('hours_low')">Hours: Low to High</a></li>
                    <li><a class="dropdown-item" href="#" onclick="sortServices('hours_high')">Hours: High to Low</a></li>
                    <li><a class="dropdown-item" href="#" onclick="sortServices('popular')">Most Popular</a></li>
                </ul>
            </div>
        </div>
        
        <div class="row" id="services-container">
            <?php foreach($services as $service): ?>
            <div class="col-md-6 col-lg-4 mb-4 service-item" 
                 data-hours="<?php echo $service['hours_required']; ?>"
                 data-date="<?php echo strtotime($service['created_at']); ?>"
                 data-requests="<?php echo $service['request_count']; ?>">
                <div class="card h-100 service-card">
                    <!-- Service Image Placeholder -->
                    <div class="card-img-top bg-light text-center py-4" 
                         style="background: linear-gradient(135deg, #e6e6fa, #f0f0ff);">
                        <i class="bi bi-tools display-4" style="color: var(--primary-purple);"></i>
                    </div>
                    
                    <div class="card-body">
                        <!-- Service Title & Hours -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title">
                                <a href="service_detail.php?id=<?php echo $service['service_id']; ?>" 
                                   class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($service['title']); ?>
                                </a>
                            </h5>
                            <span class="badge bg-primary"><?php echo $service['hours_required']; ?>h</span>
                        </div>
                        
                        <!-- Category Badge -->
                        <?php if($service['category_name']): ?>
                        <div class="mb-3">
                            <span class="badge bg-info"><?php echo htmlspecialchars($service['category_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Service Description -->
                        <p class="card-text text-muted mb-3">
                            <?php 
                            $description = htmlspecialchars($service['description']);
                            echo (strlen($description) > 100) ? substr($description, 0, 100) . '...' : $description;
                            ?>
                        </p>
                        
                        <!-- Provider Info -->
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <?php if($service['profile_image']): ?>
                                    <img src="<?php echo htmlspecialchars($service['profile_image']); ?>" 
                                         class="rounded-circle"
                                         style="width: 35px; height: 35px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                         style="width: 35px; height: 35px;">
                                        <i class="bi bi-person text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <small class="text-muted">By <?php echo htmlspecialchars($service['full_name']); ?></small>
                            </div>
                        </div>
                        
                        <!-- Location & Requests -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <?php if($service['location']): ?>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?php echo htmlspecialchars($service['location']); ?>
                            </small>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class="bi bi-bell me-1"></i>
                                <?php echo $service['request_count']; ?> requests
                            </small>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="service_detail.php?id=<?php echo $service['service_id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View Details
                            </a>
                            
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <?php if($_SESSION['user_id'] != $service['user_id']): ?>
                                    <?php 
                                    // Check if user has enough hours
                                    $stmt = $conn->prepare("SELECT time_balance FROM users WHERE user_id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $user_balance = $stmt->fetch()['time_balance'];
                                    
                                    if($user_balance >= $service['hours_required']):
                                    ?>
                                        <a href="request_service.php?id=<?php echo $service['service_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-circle me-1"></i>Request
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled 
                                                title="You need <?php echo $service['hours_required'] - $user_balance; ?> more hours">
                                            <i class="bi bi-clock me-1"></i>Need Hours
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-info">Your Service</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">Login to request</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Card Footer -->
                    <div class="card-footer bg-transparent border-top-0">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>
                            Posted <?php echo date('M d, Y', strtotime($service['created_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- No Results Message (hidden by default) -->
        <div id="no-results" class="text-center py-5" style="display: none;">
            <i class="bi bi-search display-1 text-muted mb-3"></i>
            <h4>No Matching Services</h4>
            <p class="text-muted">Try adjusting your search criteria</p>
        </div>
    <?php endif; ?>
    
    <!-- Sign Up Prompt (for guests) -->
    <?php if(!isset($_SESSION['user_id'])): ?>
    <div class="card mt-5 shadow" style="border-left: 5px solid var(--primary-purple);">
        <div class="card-body text-center py-4">
            <h4 class="mb-3">Ready to Start Trading Time?</h4>
            <p class="mb-4">Join Houra today to request services or offer your skills to the community!</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="register.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-person-plus me-2"></i>Sign Up Free
                </a>
                <a href="login.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.service-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: var(--primary-purple);
}

.card-img-top {
    border-radius: 8px 8px 0 0;
}

#search-form .form-control:focus {
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 0.2rem rgba(138, 43, 226, 0.25);
}
</style>

<script>
// Real-time search filtering (client-side)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const servicesContainer = document.getElementById('services-container');
    const noResults = document.getElementById('no-results');
    
    if (searchInput && servicesContainer) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const serviceItems = servicesContainer.querySelectorAll('.service-item');
            let visibleCount = 0;
            
            serviceItems.forEach(item => {
                const title = item.querySelector('.card-title').textContent.toLowerCase();
                const description = item.querySelector('.card-text').textContent.toLowerCase();
                const provider = item.querySelector('.text-muted').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm) || provider.includes(searchTerm)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (noResults) {
                if (visibleCount === 0 && searchTerm.length > 0) {
                    noResults.style.display = 'block';
                    servicesContainer.style.display = 'none';
                } else {
                    noResults.style.display = 'none';
                    servicesContainer.style.display = 'flex';
                }
            }
        });
    }
});

// Sort services
function sortServices(sortBy) {
    const container = document.getElementById('services-container');
    const items = Array.from(container.querySelectorAll('.service-item'));
    
    items.sort((a, b) => {
        switch(sortBy) {
            case 'newest':
                return parseInt(b.dataset.date) - parseInt(a.dataset.date);
            case 'hours_low':
                return parseFloat(a.dataset.hours) - parseFloat(b.dataset.hours);
            case 'hours_high':
                return parseFloat(b.dataset.hours) - parseFloat(a.dataset.hours);
            case 'popular':
                return parseInt(b.dataset.requests) - parseInt(a.dataset.requests);
            default:
                return 0;
        }
    });
    
    // Reorder items in container
    items.forEach(item => container.appendChild(item));
    
    // Update sort button text
    const sortLabels = {
        'newest': 'Newest First',
        'hours_low': 'Hours: Low to High',
        'hours_high': 'Hours: High to Low',
        'popular': 'Most Popular'
    };
    document.querySelector('.dropdown-toggle').innerHTML = 
        `<i class="bi bi-sort-down me-2"></i>${sortLabels[sortBy]}`;
}

// Auto-submit form when filter changes (optional)
document.querySelectorAll('#advancedFilters select, #advancedFilters input').forEach(element => {
    element.addEventListener('change', function() {
        document.getElementById('search-form').submit();
    });
});
</script>

<?php include 'includes/footer.php'; ?>