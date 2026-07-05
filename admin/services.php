<?php
// services.php - TAMBAH FEEDBACK MESSAGE
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['message'] = 'Service deleted successfully';
            break;
        case 'toggle_featured':
            $stmt = $conn->prepare("UPDATE services SET is_featured = NOT is_featured WHERE service_id = ?");
            $stmt->execute([$_GET['id']]);
            
            // Get service info for feedback
            $serviceStmt = $conn->prepare("SELECT title, is_featured FROM services WHERE service_id = ?");
            $serviceStmt->execute([$_GET['id']]);
            $service = $serviceStmt->fetch();
            
            $status = $service['is_featured'] ? 'featured' : 'normal';
            $_SESSION['message'] = "Service '{$service['title']}' set to {$status}";
            break;
        case 'update_status':
            if (isset($_GET['status']) && isset($_GET['id'])) {
                $stmt = $conn->prepare("UPDATE services SET status = ? WHERE service_id = ?");
                $stmt->execute([$_GET['status'], $_GET['id']]);
                $_SESSION['message'] = "Service status updated to {$_GET['status']}";
            }
            break;
    }
    header('Location: services.php');
    exit();
}

// Get services with filters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

$query = "SELECT s.*, c.name as category_name, u.full_name as provider_name 
          FROM services s 
          LEFT JOIN categories c ON s.category_id = c.category_id 
          LEFT JOIN users u ON s.user_id = u.user_id 
          WHERE 1=1";
$params = [];

if ($status && $status != 'all') {
    $query .= " AND s.status = ?";
    $params[] = $status;
}

if ($category && $category != 'all') {
    $query .= " AND s.category_id = ?";
    $params[] = $category;
}

$query .= " ORDER BY s.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories WHERE is_active = TRUE")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
        /* Additional styling for better table display */
        .admin-table-container {
            overflow-x: auto;
        }
        
        .service-description {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d1ecf1; color: #0c5460; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        
        .btn-action {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            margin: 2px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-view { background-color: #17a2b8; color: white; }
        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-delete { background-color: #dc3545; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        
        .btn-action:hover {
            opacity: 0.8;
        }
        
        .filter-bar {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
            color: #495057;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-hands-helping"></i> Manage Services</h1>
                <div class="user-info">
                    <span>Total: <?php echo count($services); ?> services</span>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Status:</label>
                    <select class="form-select" onchange="filterServices()" id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Category:</label>
                    <select class="form-select" onchange="filterServices()" id="categoryFilter">
                        <option value="all">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Search:</label>
                    <input type="text" class="form-control" placeholder="Search services..." id="searchInput">
                </div>
            </div>
            
            <!-- Services Table -->
            <div class="admin-table-container">
                <table class="admin-table" id="servicesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service</th>
                            <th>Category</th>
                            <th>Provider</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($services as $service): ?>
                        <tr>
                            <td><?php echo $service['service_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($service['title']); ?></strong><br>
                                <small class="text-muted service-description"><?php echo htmlspecialchars($service['description']); ?></small>
                            </td>
                            <td><?php echo $service['category_name'] ?? 'Uncategorized'; ?></td>
                            <td>
                                <?php echo htmlspecialchars($service['provider_name']); ?>
                            </td>
                            <td class="fw-bold text-primary">
                                <?php echo $service['hours_required']; ?> hrs
                            </td>
                            <td>
                                <?php
                                $statusClass = 'status-';
                                switch($service['status']) {
                                    case 'available': $statusClass .= 'active'; break;
                                    case 'in_progress': $statusClass .= 'pending'; break;
                                    case 'completed': $statusClass .= 'completed'; break;
                                    case 'cancelled': $statusClass .= 'cancelled'; break;
                                    default: $statusClass .= 'active';
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $service['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($service['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn-action btn-view" onclick="viewService(<?php echo $service['service_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn-action btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $service['service_id']; ?>, 'available')">Set Available</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $service['service_id']; ?>, 'in_progress')">Set In Progress</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $service['service_id']; ?>, 'completed')">Set Completed</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $service['service_id']; ?>, 'cancelled')">Set Cancelled</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteService(<?php echo $service['service_id']; ?>)">Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Service Details Modal -->
    <div class="modal-overlay" id="serviceModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h4 class="modal-title">Service Details</h4>
                <button type="button" class="btn-close" onclick="closeServiceModal()"></button>
            </div>
            <div class="modal-body" id="serviceDetailsContent">
                Loading service details...
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function filterServices() {
        const status = document.getElementById('statusFilter').value;
        const category = document.getElementById('categoryFilter').value;
        window.location.href = `services.php?status=${status}&category=${category}`;
    }
    
    function updateStatus(id, status) {
        if(confirm('Update service status?')) {
            window.location.href = `services.php?action=update_status&id=${id}&status=${status}`;
        }
    }
    
    function deleteService(id) {
        if(confirm('Delete this service? This action cannot be undone!')) {
            window.location.href = `services.php?action=delete&id=${id}`;
        }
    }
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const search = this.value.toLowerCase();
        const rows = document.querySelectorAll('#servicesTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    });
    
    // View service details
    function viewService(serviceId) {
        // Show loading
        document.getElementById('serviceDetailsContent').innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Loading service details...</p>
            </div>
        `;
        
        // Show modal
        document.getElementById('serviceModal').style.display = 'flex';
        
        // Fetch service details
        fetch(`get_service_details.php?id=${serviceId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('serviceDetailsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('serviceDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error: ${error}
                    </div>
                `;
            });
    }
    
    function closeServiceModal() {
        document.getElementById('serviceModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    document.getElementById('serviceModal').addEventListener('click', function(e) {
        if(e.target === this) closeServiceModal();
    });
    
    // Escape key closes modal
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') closeServiceModal();
    });
    
    // Success message handling
    <?php if(isset($_SESSION['message'])): ?>
        alert("<?php echo $_SESSION['message']; ?>");
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    </script>
</body>
</html>