<?php
// ajax/send_message.php - COMPLETE FIXED VERSION
require_once '../includes/session_start.php';
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Debug logging
error_log("=== SEND_MESSAGE.PHP STARTED ===");

if (!isset($_SESSION['user_id'])) {
    error_log("ERROR: User not logged in");
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$to_user_id = (int)($_POST['to_user'] ?? 0);

error_log("Params - user_id: $user_id, conv_id: $conversation_id, to_user: $to_user_id, message: '$message'");

// If no conversation_id but has to_user_id, find/create conversation
if ($conversation_id <= 0 && $to_user_id > 0) {
    // Ensure user1_id < user2_id
    $user1_id = min($user_id, $to_user_id);
    $user2_id = max($user_id, $to_user_id);
    
    $stmt = $conn->prepare("
        SELECT conversation_id FROM chat_conversations 
        WHERE user1_id = ? AND user2_id = ?
    ");
    $stmt->execute([$user1_id, $user2_id]);
    
    if ($row = $stmt->fetch()) {
        $conversation_id = $row['conversation_id'];
    } else {
        // Create new conversation
        $stmt = $conn->prepare("
            INSERT INTO chat_conversations (user1_id, user2_id) 
            VALUES (?, ?)
        ");
        if ($stmt->execute([$user1_id, $user2_id])) {
            $conversation_id = $conn->lastInsertId();
            error_log("Created new conversation: $conversation_id");
        }
    }
}

// Validate conversation
if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
    exit();
}

// Verify user is part of conversation
$stmt = $conn->prepare("
    SELECT conversation_id, user1_id, user2_id FROM chat_conversations 
    WHERE conversation_id = ? AND (user1_id = ? OR user2_id = ?)
");
$stmt->execute([$conversation_id, $user_id, $user_id]);

$conversation = $stmt->fetch();
if (!$conversation) {
    echo json_encode(['success' => false, 'error' => 'Not authorized for this conversation']);
    exit();
}

// Get the other user in conversation
$other_user_id = ($conversation['user1_id'] == $user_id) 
    ? $conversation['user2_id'] 
    : $conversation['user1_id'];

// Verify users have a service relationship
$stmt = $conn->prepare("
    SELECT 1 FROM (
        SELECT DISTINCT s.user_id as user1, sr.requester_id as user2
        FROM services s
        JOIN service_requests sr ON s.service_id = sr.service_id
        WHERE sr.status IN ('pending', 'accepted', 'in_progress', 'completed')
        
        UNION
        
        SELECT DISTINCT sr.requester_id as user1, s.user_id as user2
        FROM service_requests sr
        JOIN services s ON sr.service_id = s.service_id
        WHERE sr.status IN ('pending', 'accepted', 'in_progress', 'completed')
    ) as relationships
    WHERE (user1 = ? AND user2 = ?) OR (user1 = ? AND user2 = ?)
");
$stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'You can only chat with users you have service relationships with']);
    exit();
}

// Handle file upload
$attachment_path = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['attachment']['type'];
    $file_size = $_FILES['attachment']['size'];
    
    if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
        $upload_dir = '../uploads/chat/';
        
        // Debug folder
        error_log("Upload directory: " . realpath($upload_dir));
        
        if (!file_exists($upload_dir)) {
            error_log("Creating directory: $upload_dir");
            if (!mkdir($upload_dir, 0777, true)) {
                error_log("FAILED to create directory");
                echo json_encode(['success' => false, 'error' => 'Cannot create upload directory']);
                exit();
            }
        }
        
        $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $new_filename = 'chat_' . $conversation_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
        $target_file = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $attachment_path = 'uploads/chat/' . $new_filename;
            error_log("File uploaded successfully: $attachment_path");
        } else {
            error_log("File upload failed");
        }
    }
}

// If message is empty and no attachment, don't send
if (empty($message) && empty($attachment_path)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

try {
    // Insert message
    $sql = "INSERT INTO chat_messages (conversation_id, sender_id, message_text, attachment_path) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$conversation_id, $user_id, $message, $attachment_path])) {
        $message_id = $conn->lastInsertId();
        
        // Update conversation timestamp
        $update_msg = empty($message) ? '[Attachment]' : (strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message);
        
        $sql = "UPDATE chat_conversations 
                SET last_message = ?, 
                    last_message_at = NOW(),
                    updated_at = NOW()
                WHERE conversation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$update_msg, $conversation_id]);
        
        // ✅ FIX: Update unread count untuk RECEIVER sahaja
        // Dapatkan receiver (penerima)
        $receiver_id = ($conversation['user1_id'] == $user_id) 
            ? $conversation['user2_id'] 
            : $conversation['user1_id'];
        
        // Increment unread count untuk RECEIVER sahaja
        if ($conversation['user1_id'] == $receiver_id) {
            // Receiver adalah user1
            $sql = "UPDATE chat_conversations 
                    SET unread_count_user1 = unread_count_user1 + 1 
                    WHERE conversation_id = ?";
        } else {
            // Receiver adalah user2
            $sql = "UPDATE chat_conversations 
                    SET unread_count_user2 = unread_count_user2 + 1 
                    WHERE conversation_id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$conversation_id]);
        
        error_log("Message sent successfully: id=$message_id, sender=$user_id, receiver=$receiver_id");
        
        echo json_encode([
            'success' => true, 
            'message_id' => $message_id,
            'conversation_id' => $conversation_id,
            'sender_id' => $user_id,
            'receiver_id' => $receiver_id
        ]);
    } else {
        error_log("Failed to insert message");
        echo json_encode(['success' => false, 'error' => 'Database error: Failed to insert message']);
    }
} catch (Exception $e) {
    error_log("EXCEPTION in send_message.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

error_log("=== SEND_MESSAGE.PHP ENDED ===");
?>