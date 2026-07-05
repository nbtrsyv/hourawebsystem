<?php
// ajax/get_messages.php - FIXED VERSION
require_once '../includes/session_start.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$last_message_id = (int)($_GET['last_message_id'] ?? 0);

if ($conversation_id <= 0) {
    echo json_encode([]);
    exit();
}

// Check permission - JANGAN reset apa-apa di sini!
$stmt = $conn->prepare("
    SELECT conversation_id FROM chat_conversations 
    WHERE conversation_id = ? AND (user1_id = ? OR user2_id = ?)
");
$stmt->execute([$conversation_id, $user_id, $user_id]);

if (!$stmt->fetch()) {
    echo json_encode([]);
    exit();
}

// Fetch new messages SAHAJA - JANGAN mark as read di sini!
$sql = "SELECT m.*, u.full_name as sender_name, u.profile_image 
        FROM chat_messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.conversation_id = ? 
        AND m.message_id > ?
        ORDER BY m.created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$conversation_id, $last_message_id]);
$messages = $stmt->fetchAll();

// ❌ JANGAN mark as read di sini! Biar chat.php handle
// ❌ JANGAN reset unread counter di sini!

echo json_encode($messages);
?>