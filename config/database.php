<?php
$host = 'localhost';
$dbname = 'hourawebsystemdb';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function syncUnreadCounters($conn, $conversation_id) {
    $sql1 = "UPDATE chat_conversations c
            SET unread_count_user1 = (
                SELECT COUNT(*) 
                FROM chat_messages 
                WHERE conversation_id = c.conversation_id 
                AND sender_id = c.user2_id 
                AND is_read = 0
            )
            WHERE c.conversation_id = ?";
    
    $sql2 = "UPDATE chat_conversations c
            SET unread_count_user2 = (
                SELECT COUNT(*) 
                FROM chat_messages 
                WHERE conversation_id = c.conversation_id 
                AND sender_id = c.user1_id 
                AND is_read = 0
            )
            WHERE c.conversation_id = ?";
    
    $stmt1 = $conn->prepare($sql1);
    $stmt2 = $conn->prepare($sql2);
    
    $stmt1->execute([$conversation_id]);
    $stmt2->execute([$conversation_id]);
    
    return true;
}
?>