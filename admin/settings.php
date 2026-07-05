<?php
// admin/settings.php - FINAL VERSION (Removed system_name & auto_approve_proof)
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php'; // GUNA $conn

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        // Get setting type for validation - GUNA $conn
        $stmt = $conn->prepare("SELECT setting_type FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();
        
        if ($setting) {
            // Validate based on type
            $validatedValue = $value;
            switch ($setting['setting_type']) {
                case 'number':
                    $validatedValue = is_numeric($value) ? $value : 0;
                    break;
                case 'boolean':
                    $validatedValue = ($value == 'true' || $value == '1') ? 'true' : 'false';
                    break;
                case 'json':
                    // Validate JSON
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $validatedValue = '{}';
                    }
                    break;
            }
            
            // Update setting
            $updateStmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $updateStmt->execute([$validatedValue, $key]);
        }
    }
    
    $_SESSION['success_message'] = 'Settings updated successfully!';
    header('Location: settings.php');
    exit();
}

// Get all settings grouped by category - GUNA $conn
$settings = $conn->query("
    SELECT * FROM system_settings 
    WHERE setting_key NOT IN ('system_name', 'auto_approve_proof', 'contact_email')
    ORDER BY category, setting_key
")->fetchAll();

// Check if settings exist
if (empty($settings)) {
    $_SESSION['error_message'] = 'No settings found in database!';
}

// Group settings by category
$categories = [];
foreach ($settings as $setting) {
    $cat = $setting['category'] ?: 'general';
    $categories[$cat][] = $setting;
}

// Debug - lihat data yang ada (boleh delete later)
// echo "<pre>"; print_r($settings); echo "</pre>"; exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <style>
    .setting-card {
        border-left: 4px solid var(--primary-purple);
    }
    
    .setting-type-badge {
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
    }
    
    .type-string { background: #e6e6fa; color: #6a0dad; }
    .type-number { background: #d4edda; color: #155724; }
    .type-boolean { background: #fff3cd; color: #856404; }
    .type-json { background: #cce5ff; color: #004085; }
    
    /* Your data specific styles */
    .category-header {
        background: linear-gradient(135deg, #f0f0ff, white);
        border-bottom: 2px solid var(--primary-purple);
    }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <div class="user-info">
                    <span><?php echo count($settings); ?> settings found</span>
                </div>
            </div>
            
            <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Current Settings Preview -->
            <div class="alert alert-info mb-4">
                <h6><i class="fas fa-info-circle"></i> Active Settings:</h6>
                <div class="row small">
                    <div class="col-md-4"><strong>Min Hours:</strong> <?php 
                        $min = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='min_hours_per_service'")->fetch();
                        echo $min['setting_value']; ?> hours
                    </div>
                    <div class="col-md-4"><strong>Max Hours:</strong> <?php 
                        $max = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='max_hours_per_service'")->fetch();
                        echo $max['setting_value']; ?> hours
                    </div>
                    <div class="col-md-4"><strong>New User Bonus:</strong> <?php 
                        $bonus = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='new_user_bonus'")->fetch();
                        echo $bonus['setting_value']; ?> hours
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <?php foreach($categories as $categoryName => $categorySettings): ?>
                <div class="card mb-4">
                    <div class="card-header category-header">
                        <h5 class="mb-0">
                            <i class="fas fa-folder"></i> 
                            <?php echo ucfirst($categoryName); ?> Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach($categorySettings as $setting): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card setting-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <label class="form-label fw-bold">
                                                <?php echo str_replace('_', ' ', $setting['setting_key']); ?>
                                            </label>
                                            <span class="setting-type-badge type-<?php echo $setting['setting_type']; ?>">
                                                <?php echo $setting['setting_type']; ?>
                                            </span>
                                        </div>
                                        
                                        <?php if($setting['description']): ?>
                                        <p class="text-muted small mb-2"><?php echo $setting['description']; ?></p>
                                        <?php endif; ?>
                                        
                                        <?php switch($setting['setting_type']): 
                                            case 'boolean': ?>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="settings[<?php echo $setting['setting_key']; ?>]"
                                                           value="true" 
                                                           <?php echo ($setting['setting_value'] == 'true') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">
                                                        <?php echo ($setting['setting_value'] == 'true') ? 'Enabled' : 'Disabled'; ?>
                                                    </label>
                                                </div>
                                            <?php break; ?>
                                            
                                            <?php case 'number': ?>
                                                <input type="number" class="form-control" 
                                                       name="settings[<?php echo $setting['setting_key']; ?>]"
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                       step="0.01">
                                            <?php break; ?>
                                            
                                            <?php case 'json': ?>
                                                <textarea class="form-control" rows="3"
                                                          name="settings[<?php echo $setting['setting_key']; ?>]"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                            <?php break; ?>
                                            
                                            <?php default: ?>
                                                <input type="text" class="form-control" 
                                                       name="settings[<?php echo $setting['setting_key']; ?>]"
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                        <?php endswitch; ?>
                                        
                                        <!-- Show last updated if exists -->
                                        <?php if($setting['updated_at']): ?>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-clock"></i> Updated: <?php echo date('d/m/Y H:i', strtotime($setting['updated_at'])); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="button" class="btn btn-secondary" onclick="resetSettings()">
                                    <i class="fas fa-undo"></i> Reset to Default
                                </button>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save All Settings
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Quick Stats -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="fw-bold text-primary"><?php echo count($settings); ?></div>
                            <small>Total Settings</small>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold text-success"><?php echo count($categories); ?></div>
                            <small>Categories</small>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold text-info">
                                <?php 
                                $numCount = $conn->query("SELECT COUNT(*) FROM system_settings WHERE setting_type='number'")->fetchColumn();
                                echo $numCount;
                                ?>
                            </div>
                            <small>Number Settings</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function resetSettings() {
        if(confirm('Reset all settings to default values?')) {
            window.location.href = 'reset_settings.php';
        }
    }
    
    // Update label when toggle switch changes
    document.querySelectorAll('.form-check-input').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const label = this.parentElement.querySelector('.form-check-label');
            label.textContent = this.checked ? 'Enabled' : 'Disabled';
        });
    });
    
    // Warn before leaving with unsaved changes
    let formChanged = false;
    document.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('change', () => formChanged = true);
    });
    
    window.addEventListener('beforeunload', (e) => {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    document.querySelector('form').addEventListener('submit', () => {
        formChanged = false;
    });
    </script>
</body>
</html>