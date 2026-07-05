<?php
require_once '../config/database.php';

class AdminFunctions {
    
    /**
     * Get user activity summary
     */
    public static function getUserActivity($userId, $days = 30) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT sr.request_id) as requests_made,
                COUNT(DISTINCT s.service_id) as services_offered,
                COUNT(DISTINCT r.review_id) as reviews_given,
                SUM(CASE WHEN tt.transaction_type = 'earn' THEN tt.hours ELSE 0 END) as hours_earned,
                SUM(CASE WHEN tt.transaction_type = 'spend' THEN tt.hours ELSE 0 END) as hours_spent
            FROM users u
            LEFT JOIN service_requests sr ON u.user_id = sr.requester_id AND sr.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            LEFT JOIN services s ON u.user_id = s.user_id AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            LEFT JOIN reviews r ON u.user_id = r.reviewer_id AND r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            LEFT JOIN time_transactions tt ON u.user_id = tt.user_id AND tt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE u.user_id = ?
        ");
        
        $stmt->execute([$days, $days, $days, $days, $userId]);
        return $stmt->fetch();
    }
    
    /**
     * Get system health status
     */
    public static function getSystemHealth() {
        global $conn;
        
        $health = [
            'status' => 'healthy',
            'issues' => []
        ];
        
        // Check for pending proofs older than 3 days
        $oldProofs = $conn->query("
            SELECT COUNT(*) as count 
            FROM task_proofs 
            WHERE status = 'pending_review' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
        ")->fetchColumn();
        
        if ($oldProofs > 0) {
            $health['status'] = 'warning';
            $health['issues'][] = "$oldProofs proofs pending review for > 3 days";
        }
        
        // Check for open disputes older than 7 days
        $oldDisputes = $conn->query("
            SELECT COUNT(*) as count 
            FROM disputes 
            WHERE status = 'open' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetchColumn();
        
        if ($oldDisputes > 0) {
            $health['status'] = 'warning';
            $health['issues'][] = "$oldDisputes disputes open for > 7 days";
        }
        
        // Check for users with negative balance
        $negativeBalance = $conn->query("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE time_balance < 0
        ")->fetchColumn();
        
        if ($negativeBalance > 0) {
            $health['status'] = 'warning';
            $health['issues'][] = "$negativeBalance users with negative balance";
        }
        
        return $health;
    }
    
    /**
     * Send admin notification
     */
    public static function sendAdminNotification($title, $message, $type = 'system', $relatedId = null) {
        global $conn;
        
        // Get all admins
        $admins = $conn->query("SELECT user_id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        
        foreach ($admins as $admin) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$admin['user_id'], $title, $message, $type, $relatedId]);
        }
        
        return true;
    }
    
    /**
     * Log admin activity
     */
    public static function logActivity($adminId, $activityType, $description) {
        global $conn;
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$adminId, $activityType, $description, $ip, $userAgent]);
    }
    
    /**
     * Generate report data
     */
    public static function generateReport($type, $startDate = null, $endDate = null) {
        global $conn;
        
        $report = [];
        
        switch ($type) {
            case 'users':
                $stmt = $conn->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as registrations,
                        SUM(time_balance) as total_balance
                    FROM users
                    WHERE role = 'user'
                    AND created_at BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                    ORDER BY date
                ");
                $stmt->execute([$startDate ?? date('Y-m-01'), $endDate ?? date('Y-m-d')]);
                $report = $stmt->fetchAll();
                break;
                
            case 'transactions':
                $stmt = $conn->prepare("
                    SELECT 
                        transaction_type,
                        COUNT(*) as count,
                        SUM(hours) as total_hours
                    FROM time_transactions
                    WHERE created_at BETWEEN ? AND ?
                    GROUP BY transaction_type
                    ORDER BY total_hours DESC
                ");
                $stmt->execute([$startDate ?? date('Y-m-01'), $endDate ?? date('Y-m-d')]);
                $report = $stmt->fetchAll();
                break;
                
            case 'services':
                $stmt = $conn->prepare("
                    SELECT 
                        c.name as category,
                        COUNT(s.service_id) as service_count,
                        AVG(s.hours_required) as avg_hours,
                        SUM(sr.views_count) as total_views
                    FROM categories c
                    LEFT JOIN services s ON c.category_id = s.category_id
                    AND s.created_at BETWEEN ? AND ?
                    GROUP BY c.category_id
                    ORDER BY service_count DESC
                ");
                $stmt->execute([$startDate ?? date('Y-m-01'), $endDate ?? date('Y-m-d')]);
                $report = $stmt->fetchAll();
                break;
        }
        
        return $report;
    }
}
?>