<?php
// ajax/update_unread.php - FIXED VERSION
require_once '../includes/session_start.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ FIX: Kira unread dari chat_messages secara langsung
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_unread
    FROM chat_messages m
    JOIN chat_conversations c ON m.conversation_id = c.conversation_id
    WHERE (c.user1_id = ? OR c.user2_id = ?)  -- User adalah sebahagian conversation
    AND m.sender_id != ?                      -- Messages dari user lain
    AND m.is_read = 0                         -- Yang belum dibaca
    AND (
        (c.user1_id = ? AND m.sender_id = c.user2_id) OR
        (c.user2_id = ? AND m.sender_id = c.user1_id)
    )
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$result = $stmt->fetch();

echo json_encode([
    'unread' => $result['total_unread'] ?? 0
]);
?>