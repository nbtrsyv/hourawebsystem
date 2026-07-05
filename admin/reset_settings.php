<?php
// admin/reset_settings.php - Reset settings to default
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

// Same as init_settings.php
$defaultSettings = [
    ['system_name', 'Houra Community Time Bank', 'string', 'general', 'System name displayed throughout the site'],
    ['min_hours_per_service', '0.5', 'number', 'transactions', 'Minimum hours allowed per service'],
    ['max_hours_per_service', '10', 'number', 'transactions', 'Maximum hours allowed per service'],
    ['new_user_bonus', '2', 'number', 'bonus', 'Bonus hours for new users'],
    ['auto_approve_proof', 'false', 'boolean', 'moderation', 'Auto approve task proofs without admin review'],
    ['max_daily_requests', '5', 'number', 'limits', 'Maximum service requests per day'],
    ['contact_email', 'support@houra.com', 'string', 'contact', 'System contact email']
];

try {
    // Update existing settings instead of delete
    foreach ($defaultSettings as $setting) {
        $stmt = $conn->prepare("
            UPDATE system_settings 
            SET setting_value = ?, setting_type = ?, category = ?, description = ?
            WHERE setting_key = ?
        ");
        $stmt->execute([$setting[1], $setting[2], $setting[3], $setting[4], $setting[0]]);
    }
    
    $_SESSION['success_message'] = 'Settings reset to default values!';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error resetting settings: ' . $e->getMessage();
}

header('Location: settings.php');
exit();
?>