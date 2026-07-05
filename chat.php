<?php
require_once 'includes/session_start.php';
require_once 'config/database.php';
require_once 'includes/check_verification.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$to_user_id = isset($_GET['to_user']) ? (int)$_GET['to_user'] : 0;

redirectIfUnverified($conn, $user_id);

$stmt = $conn->prepare("
    SELECT c.*, 
           CASE 
               WHEN c.user1_id = ? THEN u2.full_name 
               ELSE u1.full_name 
           END as other_user_name,
           CASE 
               WHEN c.user1_id = ? THEN u2.user_id 
               ELSE u1.user_id 
           END as other_user_id,
           CASE 
               WHEN c.user1_id = ? THEN u2.profile_image 
               ELSE u1.profile_image 
           END as other_user_avatar,
           CASE 
               WHEN c.user1_id = ? THEN c.unread_count_user1 
               ELSE c.unread_count_user2 
           END as unread_count
    FROM chat_conversations c
    JOIN users u1 ON c.user1_id = u1.user_id
    JOIN users u2 ON c.user2_id = u2.user_id
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY c.last_message_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);

$conversations = $stmt->fetchAll();

if ($to_user_id > 0 && $conversation_id <= 0) {
    $user1_id = min($user_id, $to_user_id);
    $user2_id = max($user_id, $to_user_id);
    
    $stmt = $conn->prepare("
        SELECT conversation_id FROM chat_conversations 
        WHERE user1_id = ? AND user2_id = ?
    ");
    $stmt->execute([$user1_id, $user2_id]);
    
    if ($result = $stmt->fetch()) {
        $conversation_id = $result['conversation_id'];
    } else {
        $stmt = $conn->prepare("
            INSERT INTO chat_conversations (user1_id, user2_id) 
            VALUES (?, ?)
        ");
        if ($stmt->execute([$user1_id, $user2_id])) {
            $conversation_id = $conn->lastInsertId();
        }
    }
    
    header('Location: chat.php?conversation_id=' . $conversation_id);
    exit();
}

$messages = [];
$current_conversation = null;
if ($conversation_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, 
               CASE 
                   WHEN c.user1_id = ? THEN u2.full_name 
                   ELSE u1.full_name 
               END as other_user_name,
               CASE 
                   WHEN c.user1_id = ? THEN u2.user_id 
                   ELSE u1.user_id 
               END as other_user_id
        FROM chat_conversations c
        JOIN users u1 ON c.user1_id = u1.user_id
        JOIN users u2 ON c.user2_id = u2.user_id
        WHERE c.conversation_id = ?
    ");
    $stmt->execute([$user_id, $user_id, $conversation_id]);
    $current_conversation = $stmt->fetch();
    
    if ($current_conversation) {
    error_log("=== DEBUG: User $user_id opening conversation $conversation_id ===");
    
    $other_user_id = ($current_conversation['user1_id'] == $user_id) 
        ? $current_conversation['user2_id'] 
        : $current_conversation['user1_id'];
    
    error_log("Other user ID: $other_user_id");
    
    $sql_check = "SELECT COUNT(*) as unread_before 
                  FROM chat_messages 
                  WHERE conversation_id = ? 
                  AND sender_id = ? 
                  AND is_read = 0";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$conversation_id, $other_user_id]);
    $check_result = $stmt_check->fetch();
    error_log("Unread messages before reset: " . $check_result['unread_before']);
    
    $sql = "UPDATE chat_messages SET is_read = 1 
            WHERE conversation_id = ? 
            AND sender_id = ? 
            AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$conversation_id, $other_user_id]);
    
    error_log("Mark as read query executed: " . ($result ? 'SUCCESS' : 'FAILED'));
    error_log("Rows affected: " . $stmt->rowCount());
    
    if ($current_conversation['user1_id'] == $user_id) {
        $sql = "UPDATE chat_conversations c
                SET unread_count_user1 = (
                    SELECT COUNT(*) 
                    FROM chat_messages 
                    WHERE conversation_id = c.conversation_id 
                    AND sender_id = c.user2_id 
                    AND is_read = 0
                )
                WHERE c.conversation_id = ?";
        error_log("Resetting unread_count_user1 for user $user_id");
    } else {
        $sql = "UPDATE chat_conversations c
                SET unread_count_user2 = (
                    SELECT COUNT(*) 
                    FROM chat_messages 
                    WHERE conversation_id = c.conversation_id 
                    AND sender_id = c.user1_id 
                    AND is_read = 0
                )
                WHERE c.conversation_id = ?";
        error_log("Resetting unread_count_user2 for user $user_id");
    }
    
    $stmt = $conn->prepare($sql);
    $reset_result = $stmt->execute([$conversation_id]);
    error_log("Reset counter query executed: " . ($reset_result ? 'SUCCESS' : 'FAILED'));
    
    $stmt_check->execute([$conversation_id, $other_user_id]);
    $check_after = $stmt_check->fetch();
    error_log("Unread messages after reset: " . $check_after['unread_before']);

    syncUnreadCounters($conn, $conversation_id);

        $stmt = $conn->prepare("
            SELECT m.*, u.full_name as sender_name, u.profile_image
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
        $messages = $stmt->fetchAll();
    }
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Left Sidebar - Conversations List -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-chat-text me-2"></i>Messages</h5>
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#newChatModal">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if(empty($conversations)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-chat display-4 text-muted mb-3"></i>
                            <p class="text-muted">No conversations yet</p>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newChatModal">
                                Start a Conversation
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="conversations-list">
                            <?php foreach($conversations as $conv): ?>
                            <a href="chat.php?conversation_id=<?php echo $conv['conversation_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo ($conv['conversation_id'] == $conversation_id) ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <!-- User Avatar -->
                                    <div class="flex-shrink-0">
                                        <?php if($conv['other_user_avatar']): ?>
                                            <img src="<?php echo htmlspecialchars($conv['other_user_avatar']); ?>" 
                                                 class="rounded-circle"
                                                 style="width: 45px; height: 45px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                 style="width: 45px; height: 45px;">
                                                <i class="bi bi-person fs-5 text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Conversation Info -->
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($conv['other_user_name']); ?></h6>
                                            <?php if($conv['unread_count'] > 0): ?>
                                                <span class="badge bg-danger rounded-pill"><?php echo $conv['unread_count']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php 
                                            if($conv['last_message']) {
                                                echo (strlen($conv['last_message']) > 30) 
                                                    ? substr($conv['last_message'], 0, 30) . '...' 
                                                    : $conv['last_message'];
                                            } else {
                                                echo 'No messages yet';
                                            }
                                            ?>
                                        </small>
                                        <div class="text-muted small">
                                            <?php echo $conv['last_message_at'] 
                                                ? date('M d', strtotime($conv['last_message_at'])) 
                                                : ''; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Chat Area -->
        <div class="col-md-8 col-lg-9">
            <?php if($current_conversation): ?>
                <!-- Chat Header -->
                <div class="card shadow mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center">
                            <!-- Back Button (Mobile) -->
                            <button class="btn btn-outline-primary d-md-none me-3" onclick="goBack()">
                                <i class="bi bi-arrow-left"></i>
                            </button>
                            
                            <!-- User Info -->
                            <div class="flex-shrink-0">
                                <?php 
                                $other_avatar = '';
                                foreach($conversations as $conv) {
                                    if($conv['conversation_id'] == $conversation_id) {
                                        $other_avatar = $conv['other_user_avatar'];
                                        break;
                                    }
                                }
                                ?>
                                <?php if($other_avatar): ?>
                                    <img src="<?php echo htmlspecialchars($other_avatar); ?>" 
                                         class="rounded-circle"
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                         style="width: 50px; height: 50px;">
                                        <i class="bi bi-person fs-4 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1"><?php echo htmlspecialchars($current_conversation['other_user_name']); ?></h5>
                                <small class="text-success">
                                    <i class="bi bi-circle-fill"></i> Active now
                                </small>
                            </div>
                            
                            <!-- Actions -->
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="user_profile.php?id=<?php echo $current_conversation['other_user_id']; ?>">
                                            <i class="bi bi-person me-2"></i>View Profile
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="create_dispute.php">
                                            <i class="bi bi-flag me-2"></i>Report User
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Messages Area -->
                <div class="card shadow flex-grow-1">
                    <div class="card-body p-0">
                        <div id="messages-container" 
                             style="height: 500px; overflow-y: auto; padding: 20px;">
                             
                            <?php if(empty($messages)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-chat-heart display-4 text-muted mb-3"></i>
                                    <h5>Start a Conversation</h5>
                                    <p class="text-muted">Send a message to begin chatting with <?php echo htmlspecialchars($current_conversation['other_user_name']); ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach($messages as $message): ?>
                                <div class="message-item mb-3 <?php echo $message['sender_id'] == $user_id ? 'text-end' : ''; ?>">
                                    <div class="d-flex <?php echo $message['sender_id'] == $user_id ? 'justify-content-end' : ''; ?>">
                                        <?php if($message['sender_id'] != $user_id): ?>
                                            <!-- Other user's message -->
                                            <div class="flex-shrink-0 me-2">
                                                <?php if($message['profile_image']): ?>
                                                    <img src="<?php echo htmlspecialchars($message['profile_image']); ?>" 
                                                         class="rounded-circle"
                                                         style="width: 35px; height: 35px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                         style="width: 35px; height: 35px;">
                                                        <i class="bi bi-person text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <div class="message-bubble p-3 rounded <?php echo $message['sender_id'] == $user_id 
                                                ? 'bg-primary text-white' 
                                                : 'bg-light'; ?>"
                                                 style="max-width: 500px;">
                                                <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                                                
                                                <?php if($message['attachment_path']): ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($message['attachment_path']); ?>" 
                                                           class="btn btn-sm btn-outline-<?php echo $message['sender_id'] == $user_id ? 'light' : 'primary'; ?>"
                                                           target="_blank">
                                                            <i class="bi bi-paperclip me-1"></i>Attachment
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <small class="text-muted mt-1 d-block">
                                                <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                                <?php if($message['sender_id'] == $user_id && $message['is_read']): ?>
                                                    <i class="bi bi-check-all text-primary ms-1"></i>
                                                <?php elseif($message['sender_id'] == $user_id): ?>
                                                    <i class="bi bi-check text-muted ms-1"></i>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <?php if($message['sender_id'] == $user_id): ?>
                                            <!-- Current user's message -->
                                            <div class="flex-shrink-0 ms-2">
                                                <?php if($_SESSION['profile_image'] ?? false): ?>
                                                    <img src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" 
                                                         class="rounded-circle"
                                                         style="width: 35px; height: 35px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center"
                                                         style="width: 35px; height: 35px;">
                                                        <i class="bi bi-person text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message Input -->
                        <div class="border-top p-3">
                            <form id="message-form" method="POST" enctype="multipart/form-data">
                                <div class="input-group">
                                    <!-- Dalam chat.php, dalam form message -->
                                    <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                                    
                                    <!-- Attachment Button -->
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="document.getElementById('file-input').click()">
                                        <i class="bi bi-paperclip"></i>
                                    </button>
                                    <input type="file" id="file-input" name="attachment" 
                                           style="display: none;" accept="image/*,.pdf,.doc,.docx">
                                    
                                    <!-- Message Input -->
                                    <input type="text" class="form-control" 
                                           id="message-input" 
                                           name="message"
                                           placeholder="Type your message..." 
                                           autocomplete="off">
                                    
                                    <!-- Send Button -->
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </div>
                                
                                <!-- File Preview -->
                                <div id="file-preview" class="mt-2" style="display: none;">
                                    <div class="alert alert-info py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span id="file-name"></span>
                                            <button type="button" class="btn-close" 
                                                    onclick="clearAttachment()"></button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- No Conversation Selected -->
                <div class="card shadow h-100">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-5">
                        <i class="bi bi-chat-text display-1 text-muted mb-3"></i>
                        <h4>Welcome to Houra Chat</h4>
                        <p class="text-muted text-center mb-4">
                            Select a conversation from the list or start a new one<br>
                            to chat with community members about services.
                        </p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newChatModal">
                            <i class="bi bi-plus-circle me-2"></i>Start New Conversation
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Chat Modal -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start New Conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Search Service Partners</label>
                    <input type="text" class="form-control" id="search-users" 
                           placeholder="Search by name or email...">
                </div>
                
                <div id="users-list" style="max-height: 300px; overflow-y: auto;">
                    <?php
                    // Fetch users who have service relationships with current user
                    // This includes: providers of services requested by current user, 
                    // and clients who requested services offered by current user
                    $stmt = $conn->prepare("
                        SELECT DISTINCT u.user_id, u.full_name, u.email, u.profile_image 
                        FROM users u
                        WHERE u.user_id != ? AND u.role = 'user'
                        AND (
                            -- Users who have offered services to current user (provider)
                            u.user_id IN (
                                SELECT DISTINCT s.user_id 
                                FROM services s
                                JOIN service_requests sr ON s.service_id = sr.service_id
                                WHERE sr.requester_id = ? AND sr.status IN ('pending', 'accepted', 'in_progress', 'completed')
                            )
                            OR
                            -- Users who have requested services from current user (client)
                            u.user_id IN (
                                SELECT DISTINCT sr.requester_id 
                                FROM service_requests sr
                                JOIN services s ON sr.service_id = s.service_id
                                WHERE s.user_id = ? AND sr.status IN ('pending', 'accepted', 'in_progress', 'completed')
                            )
                        )
                        ORDER BY u.full_name
                    ");
                    $stmt->execute([$user_id, $user_id, $user_id]);
                    $all_users = $stmt->fetchAll();
                    ?>
                    
                    <?php if(empty($all_users)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-info-circle display-5 text-muted mb-2"></i>
                            <p class="text-muted mb-0">No service partners yet</p>
                            <small class="text-muted d-block">You can only chat with users you have service relationships with.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach($all_users as $u): ?>
                        <div class="user-item border-bottom py-2">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <?php if($u['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($u['profile_image']); ?>" 
                                             class="rounded-circle"
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($u['full_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                </div>
                                <div>
                                    <a href="chat.php?to_user=<?php echo $u['user_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        Message
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.message-bubble {
    position: relative;
}
.message-bubble.bg-primary:after {
    content: '';
    position: absolute;
    right: -8px;
    top: 10px;
    width: 0;
    height: 0;
    border-left: 8px solid var(--primary-purple);
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
}
.message-bubble.bg-light:after {
    content: '';
    position: absolute;
    left: -8px;
    top: 10px;
    width: 0;
    height: 0;
    border-right: 8px solid #f8f9fa;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
}
#messages-container::-webkit-scrollbar {
    width: 6px;
}
#messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}
#messages-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}
#messages-container::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>

<script>

// Dalam chat.php, tambah auto-update unread
function updateUnreadCount() {
    fetch('ajax/update_unread.php')
        .then(response => response.json())
        .then(data => {
            // Update badge in navbar jika ada
            const badge = document.querySelector('.unread-badge');
            if (badge) {
                if (data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.style.display = 'inline';
                } else {
                    badge.style.display = 'none';
                }
            }
        });
}

// Update setiap 10 saat
setInterval(updateUnreadCount, 10000);

// Scroll to bottom of messages
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Auto-refresh messages every 5 seconds
let refreshInterval;
function startAutoRefresh() {
    <?php if($conversation_id > 0): ?>
    /*refreshInterval = setInterval(() => {
        fetch(`ajax/get_messages.php?conversation_id=<?php echo $conversation_id; ?>`)
            .then(response => response.json())
            .then(messages => {
                updateMessages(messages);
            });
    }, 5000);*/
    <?php endif; ?>
}

function updateMessages(messages) {
    const container = document.getElementById('messages-container');
    // Only update if messages changed
    // (Implementation depends on your AJAX response structure)
}

document.getElementById('message-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const message = document.getElementById('message-input').value.trim();
    const formData = new FormData(this);
    
    if (message || formData.get('attachment')) {
        if (!formData.has('conversation_id')) {
            formData.append('conversation_id', <?php echo $conversation_id ?? 0; ?>);
        }
        
        console.log("Sending message...");
        
        fetch('ajax/send_message.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log("Response status:", response.status);
            console.log("Response ok:", response.ok);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Response data:", data);
            
            if (data.success) {
                document.getElementById('message-input').value = '';
                clearAttachment();
                // Auto-refresh messages
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Fetch error details:', error);
            console.error('Error stack:', error.stack);
            
            // Show more specific error message
            let errorMsg = 'Network error. Please try again.';
            if (error.message.includes('Failed to fetch')) {
                errorMsg = 'Cannot connect to server. Check your internet connection.';
            } else if (error.message.includes('HTTP error')) {
                errorMsg = 'Server error: ' + error.message;
            }
            alert(errorMsg);
        });
    } else {
        alert('Message cannot be empty');
    }
});

// Handle file attachment
document.getElementById('file-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            alert('File too large! Maximum size is 5MB.');
            this.value = '';
            return;
        }
        
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-preview').style.display = 'block';
    }
});

function clearAttachment() {
    document.getElementById('file-input').value = '';
    document.getElementById('file-preview').style.display = 'none';
}

// Search users in modal
document.getElementById('search-users').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const userItems = document.querySelectorAll('.user-item');
    
    userItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
    });
});

// Mobile back button
function goBack() {
    window.history.back();
}

// Initial scroll to bottom
window.onload = function() {
    scrollToBottom();
    startAutoRefresh();
};
</script>

<?php include 'includes/footer.php'; ?>