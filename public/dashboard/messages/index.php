<?php

// Include necessary NGN bootstrap and configurations
require_once __DIR__ . '/../../../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\Http\Request;
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Fans\MessageService;
use NGN\Lib\Fans\SubscriptionService; // Potentially needed to check subscription status for artists or to get tier info
use PDO;
use DateTime;

// Basic page setup for Admin theme
$config = new Config();
$pageTitle = 'Direct Messages';

$inbox = [];
$currentUserId = null;
$activeThreadUserId = null;
$threadMessages = [];
$otherUserName = '';

// --- Initialize Services ---
$messageSvc = new MessageService($config);
$tokenSvc = new TokenService($config);

// --- Get Current User ID ---
$authHeader = $request->header('Authorization');
if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
    $token = trim(substr($authHeader, 7));
    try {
        $claims = $tokenSvc->decode($token);
        $sub = $claims['sub'] ?? null;
        if ($sub && ctype_digit($sub)) {
            $currentUserId = (int)$sub;
        }
    } catch (\Throwable $e) {
        // Handle token errors if necessary, but for UI, we might just not load features.
        error_log("Auth error on messages page: " . $e->getMessage());
    }
}

// --- Fetch Inbox if User is Authenticated ---
if ($currentUserId) {
    $inbox = $messageSvc->getInbox($currentUserId);
    // Determine active thread user if ?user_id= is present in URL
    $activeThreadUserId = $request->param('user_id');
    if ($activeThreadUserId) {
        $activeThreadUserId = (int)$activeThreadUserId;
        // Fetch messages for the active thread
        $threadMessages = $messageSvc->getThread($currentUserId, $activeThreadUserId);
        // Fetch other user's name for display in the header (requires user lookup)
        try {
            $pdo = ConnectionFactory::read($config);
            $stmt = $pdo->prepare("SELECT FirstName, LastName FROM `Users` WHERE Id = ? LIMIT 1");
            $stmt->execute([$activeThreadUserId]);
            $otherUser = $stmt->fetch();
            if ($otherUser) {
                $otherUserName = trim($otherUser['FirstName'] . ' ' . $otherUser['LastName']);
                if (empty($otherUserName)) $otherUserName = 'User ' . $activeThreadUserId;
            }
        } catch (\Throwable $e) {
            error_log("Error fetching user name for message thread: " . $e->getMessage());
            $otherUserName = 'User ' . $activeThreadUserId; // Fallback name
        }
    }
}

?>
<!-- Include Admin theme header partial -->
<?php require __DIR__ . '/../_header.php'; ?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <!-- Left Sidebar: Inbox -->
                <div class="col-md-4 border-right">
                    <div class="inbox-header d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Inbox</h5>
                        <!-- Optional: Search/New Message button -->
                    </div>
                    <ul class="list-unstyled">
                        <?php if (empty($inbox) && !$currentUserId): ?>
                            <li class="text-center text-muted py-3">Please log in to view your inbox.</li>
                        <?php elseif (empty($inbox)): ?>
                            <li class="text-center text-muted py-3">Your inbox is empty.</li>
                        <?php else:
                            foreach ($inbox as $conversation):
                                // Determine the other user in the conversation
                                $otherUserInConv = ($conversation['sender_id'] == $currentUserId) ? $conversation['receiver_id'] : $conversation['sender_id'];
                                $otherUserNameInConv = $conversation['sender_id'] == $currentUserId ? ($conversation['receiver_FirstName'] ?? 'User') . ' ' . ($conversation['receiver_LastName'] ?? '') : ($conversation['sender_FirstName'] ?? 'User') . ' ' . ($conversation['sender_LastName'] ?? '');
                                $otherUserNameInConv = trim($otherUserNameInConv);
                                if (empty($otherUserNameInConv)) $otherUserNameInConv = 'User ' . $otherUserInConv;
                                
                                // Check if message is unread for the current user
                                // This logic needs adjustment: `getInbox` should return unread count or status.
                                // For now, assuming `getInbox` provides enough info or we'll check it later.
                                $isUnread = ($conversation['receiver_id'] == $currentUserId && $conversation['is_read'] == 0) || ($conversation['sender_id'] == $currentUserId && $conversation['is_read'] == 0);
                                $unreadClass = $isUnread ? 'font-weight-bold text-white' : ''; // Highlight unread messages
                        ?>
                            <li class="media p-2 border-bottom border-dark mb-1 rounded chat-list-item <?php echo ($otherUserInConv == $activeThreadUserId) ? 'active-chat-item' : ''; ?>" 
                                data-user-id="<?php echo $otherUserInConv; ?>">
                                <img class="mr-3 rounded-circle" src="https://via.placeholder.com/50" alt="User Avatar" width="50">
                                <div class="media-body">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mt-0 mb-1 <?php echo $unreadClass; ?>"><?php echo htmlspecialchars($otherUserNameInConv); ?></h6>
                                        <small class="text-muted"><?php echo (new DateTime($conversation['created_at']))->format('H:i'); ?></small>
                                    </div>
                                    <p class="mb-0 <?php echo $unreadClass; ?>" style="font-size: 0.9em;"><?php echo htmlspecialchars(substr($conversation['body'], 0, 40) . '...'); // Snippet of last message ?></p>
                                </div>
                            </li>
                        <?php endforeach;
                        endif; ?>
                    </ul>
                </div>

                <!-- Right Pane: Chat Thread -->
                <div class="col-md-8">
                    <?php if (!$activeThreadUserId && $currentUserId): ?>
                        <div class="chat-placeholder text-center py-5">
                            <h5 class="text-muted">Select a conversation to start chatting</h5>
                            <p class="text-secondary">Your inbox appears to be empty or no conversation is selected.</p>
                        </div>
                    <?php elseif ($activeThreadUserId && $currentUserId): ?>
                        <div class="chat-header d-flex justify-content-between align-items-center p-3 border-bottom">
                            <div>
                                <img class="mr-2 rounded-circle" src="https://via.placeholder.com/40" alt="Other User Avatar" width="40">
                                <span class="h6 mb-0"><strong><?php echo htmlspecialchars($otherUserName); ?></strong></span>
                            </div>
                            <!-- Optional: More options or call info -->
                        </div>
                        <div class="chat-body p-3" id="chat-messages" style="height: 400px; overflow-y: auto;">
                            <!-- Messages will be loaded here by JavaScript -->
                            <?php if (!empty($threadMessages)): ?>
                                <?php foreach ($threadMessages as $message):
                                    $isSender = ($message['sender_id'] == $currentUserId);
                                ?>
                                    <div class="message mb-3 d-flex <?php echo $isSender ? 'justify-content-end' : 'justify-content-start'; ?>">
                                        <div class="message-bubble <?php echo $isSender ? 'bg-primary' : 'bg-secondary'; ?> text-white p-2 rounded" style="max-width: 70%;">
                                            <?php echo nl2br(htmlspecialchars($message['body'])); // nl2br for newlines in messages ?>
                                            <div class="message-meta text-right text-white-50 mt-1" style="font-size: 0.7em;">
                                                <?php echo (new DateTime($message['created_at']))->format('H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else:
                                // If thread exists but no messages yet
                                if ($activeThreadUserId !== $currentUserId) { // Avoid showing this if it's the same user
                            ?>
                                <div class="text-center text-muted py-3">No messages yet. Start the conversation!</div>
                            <?php } endif; ?>
                        </div>
                        <div class="chat-footer p-3 border-top">
                            <form id="sendMessageForm">
                                <div class="input-group">
                                    <input type="text" id="messageInput" class="form-control" placeholder="Type your message...">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="submit">Send</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php elseif (!$currentUserId):
                        // If user is not logged in
                    ?>
                        <div class="chat-placeholder text-center py-5">
                            <h5 class="text-muted">Please Log In</h5>
                            <p class="text-secondary">You need to be logged in to send and receive messages.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatMessagesContainer = document.getElementById('chat-messages');
        const sendMessageForm = document.getElementById('sendMessageForm');
        const messageInput = document.getElementById('messageInput');
        const inboxItems = document.querySelectorAll('.chat-list-item');
        const currentUserId = <?php echo json_encode($currentUserId); ?>;
        const activeThreadUserId = <?php echo json_encode($activeThreadUserId); ?>;

        // Function to append a new message bubble to the chat
        function appendMessage(message, senderClass) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message mb-3 d-flex ${senderClass}`;
            messageDiv.innerHTML = `
                <div class="message-bubble bg-${senderClass === 'justify-content-end' ? 'primary' : 'secondary'} text-white p-2 rounded" style="max-width: 70%;">
                    ${message.body.replace(/\n/g, '<br>')}
                    <div class="message-meta text-right text-white-50 mt-1" style="font-size: 0.7em;">
                        ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </div>
                </div>
            `;
            chatMessagesContainer.appendChild(messageDiv);
            chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight; // Scroll to bottom
        }

        // Handle form submission for sending messages
        if (sendMessageForm) {
            sendMessageForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const messageText = messageInput.value.trim();
                if (messageText && currentUserId && activeThreadUserId) {
                    // Send message via API
                    fetch('/api/v1/fans/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + localStorage.getItem('authToken') // Assuming token is stored
                        },
                        body: JSON.stringify({
                            receiver_id: activeThreadUserId,
                            body: messageText
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            appendMessage({ body: messageText }, 'justify-content-end'); // Append as sender's message
                            messageInput.value = ''; // Clear input
                        } else {
                            alert('Failed to send message: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error sending message:', error);
                        alert('An error occurred. Please try again.');
                    });
                }
            });
        }

        // Handle click on inbox items to load thread
        inboxItems.forEach(item => {
            item.addEventListener('click', function() {
                const targetUserId = this.getAttribute('data-user-id');
                if (targetUserId) {
                    // Redirect to the thread view
                    window.location.href = `?user_id=${targetUserId}`;
                }
            });
        });

        // Scroll to bottom if messages are loaded initially
        if (chatMessagesContainer) {
            chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
        }
    });
</script>

<style>
    .chat-list-item.active-chat-item {
        background-color: #2a2a2a; /* Darker background for active chat */
        border-radius: 8px;
    }
    .chat-list-item:hover {
        background-color: #2a2a2a;
        cursor: pointer;
    }
    .message-bubble {
        border-radius: 15px;
        padding: 10px 15px;
    }
    .message-meta {
        font-size: 0.7em;
        opacity: 0.7;
    }
    .bg-primary .message-meta, .bg-primary .message-bubble {
        color: #fff !important; /* Ensure sender messages are white */
    }
    .bg-secondary .message-meta, .bg-secondary .message-bubble {
        color: #fff !important; /* Ensure receiver messages are white */
    }
</style>

<!-- Include Admin theme footer partial -->
<?php require __DIR__ . '/../_footer.php'; ?>
