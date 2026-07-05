<?php
require_once 'includes/admin_auth.php';
AdminAuth::checkLogin();
require_once '../config/database.php';

$type = $_GET['type'] ?? 'users';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Generate filename
$filename = "houra_report_{$type}_" . date('Ymd_His') . ".csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

switch ($type) {
    case 'users':
        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Time Balance', 'Rating', 'Status', 'Joined Date']);
        
        $stmt = $conn->prepare("
            SELECT user_id, full_name, email, phone, time_balance, rating, status, created_at
            FROM users 
            WHERE role = 'user'
            AND created_at BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        break;
        
    case 'transactions':
        fputcsv($output, ['ID', 'User ID', 'Type', 'Hours', 'Description', 'Previous Balance', 'New Balance', 'Date']);
        
        $stmt = $conn->prepare("
            SELECT transaction_id, user_id, transaction_type, hours, description, 
                   previous_balance, new_balance, created_at
            FROM time_transactions
            WHERE created_at BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        break;
        
    case 'services':
        fputcsv($output, ['ID', 'Title', 'Category', 'Provider ID', 'Hours Required', 'Status', 'Views', 'Created Date']);
        
        $stmt = $conn->prepare("
            SELECT s.service_id, s.title, c.name as category, s.user_id, 
                   s.hours_required, s.status, s.views_count, s.created_at
            FROM services s
            LEFT JOIN categories c ON s.category_id = c.category_id
            WHERE s.created_at BETWEEN ? AND ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        break;
        
    case 'full':
        // Export multiple sheets (simulated with sections)
        fputcsv($output, ['=== HOURA TIME BANK - FULL REPORT ===']);
        fputcsv($output, ['Generated: ' . date('d/m/Y H:i:s')]);
        fputcsv($output, ['Period: ' . $startDate . ' to ' . $endDate]);
        fputcsv($output, []);
        
        // Users section
        fputcsv($output, ['--- USERS ---']);
        fputcsv($output, ['ID', 'Name', 'Email', 'Balance', 'Rating', 'Status']);
        
        $users = $conn->query("
            SELECT user_id, full_name, email, time_balance, rating, status
            FROM users WHERE role = 'user'
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            fputcsv($output, $user);
        }
        
        fputcsv($output, []);
        
        // Transactions summary
        fputcsv($output, ['--- TRANSACTIONS SUMMARY ---']);
        fputcsv($output, ['Type', 'Count', 'Total Hours']);
        
        $summary = $conn->query("
            SELECT transaction_type, COUNT(*) as count, SUM(hours) as total_hours
            FROM time_transactions
            GROUP BY transaction_type
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($summary as $row) {
            fputcsv($output, $row);
        }
        break;
}

fclose($output);
exit();
?>