<?php
session_start();
require_once '../config/database.php';

// Check admin login
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    die('Access denied');
}

// Get filters
$type = $_GET['type'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT tt.*, 
                 u.full_name as user_name,
                 u.email as user_email,
                 sr.request_id,
                 s.title as service_title
          FROM time_transactions tt
          JOIN users u ON tt.user_id = u.user_id
          LEFT JOIN service_requests sr ON tt.related_request_id = sr.request_id
          LEFT JOIN services s ON sr.service_id = s.service_id
          WHERE 1=1";
$params = [];

if ($type && $type != 'all') {
    $query .= " AND tt.transaction_type = ?";
    $params[] = $type;
}

if ($user_id && $user_id != 'all') {
    $query .= " AND tt.user_id = ?";
    $params[] = $user_id;
}

if ($date_from) {
    $query .= " AND DATE(tt.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(tt.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY tt.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="houra_transactions_' . date('Ymd_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fwrite($output, "\xEF\xBB\xBF");

// CSV headers
fputcsv($output, [
    'Transaction ID',
    'User ID', 
    'User Name',
    'User Email',
    'Transaction Type',
    'Hours',
    'Description',
    'Previous Balance',
    'New Balance',
    'Related Request ID',
    'Service Title',
    'Created Date'
]);

// Data rows
foreach ($transactions as $row) {
    fputcsv($output, [
        $row['transaction_id'],
        $row['user_id'],
        $row['user_name'],
        $row['user_email'],
        $row['transaction_type'],
        $row['hours'],
        $row['description'],
        $row['previous_balance'],
        $row['new_balance'],
        $row['request_id'] ?: 'N/A',
        $row['service_title'] ?: 'N/A',
        $row['created_at']
    ]);
}

fclose($output);
exit();
?>