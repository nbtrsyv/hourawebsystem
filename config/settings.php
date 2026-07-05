<?php
require_once 'database.php';

putenv('OPENAI_API_KEY=sk-your-actual-api-key-here');

// Function untuk get settings
function getSetting($key, $default = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Define constants untuk global access
define('MIN_HOURS', floatval(getSetting('min_hours_per_service', '0.5')));
define('MAX_HOURS', floatval(getSetting('max_hours_per_service', '10')));
define('MAX_DAILY_REQUESTS', intval(getSetting('max_daily_requests', '5')));
define('NEW_USER_BONUS', floatval(getSetting('new_user_bonus', '2')));
?>