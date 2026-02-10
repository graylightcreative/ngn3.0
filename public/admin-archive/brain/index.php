<?php
$root = $_SERVER['DOCUMENT_ROOT'].'/';

require $root.'lib/definitions/site-settings.php';
require $root.'admin/lib/definitions/admin-settings.php';
require $root.'lib/partials/head.php';
?>
</head>
<body>
<?php require '../lib/partials/header.php'; ?>
<main class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <h2 class="card-header h6">Brain Conversation</h2>
                    <div class="card-body">
                        <div class="card-text">
                            <div id="chat-box" class="chat-box"></div> <!-- Add chat box here to display conversation -->
                        </div>
                    </div>
                </div>

            </div>
            <div class="col-md-8">
                <h1>Brain</h1>
                <div class="card">
                    <div class="card-body">
                        <div class="card-text">
                            <?= createFloatingTextarea('What do you need today?', 'message', '', '4', true); ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-lg btn-primary d-block w-100 no-loading" id="ask">Use The Brain</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>
<?php require '../lib/partials/footer.php'; ?>
<script>
    function appendMessage(sender, message) {
        const chatBox = document.getElementById('chat-box');
        const messageElement = document.createElement('div');
        messageElement.className = `${sender}-message`;
        messageElement.innerHTML = `<div class="message-content">${message}</div>`;
        chatBox.appendChild(messageElement);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    $('#ask').click(function(e){
        e.preventDefault();
        let message = $('#message');
        if (message.val() === ''){
            alert('No message');
        } else {
            appendMessage('user', message.val()); // Append user message to chat box
            axios.post('lib/handlers/ask.php', { message: message.val() })
                .then((res) => {
                    if (res.data) {
                        if (res.data.message) {
                            appendMessage('bot', res.data.message); // Append bot reply to chat box
                        } else {
                            alert(res.data.message || 'An unknown error has occurred');
                            console.error(res.data);
                        }
                    } else {
                        alert('An unknown error has occurred');
                        console.error(res.data);
                    }
                })
                .catch(error => {
                    alert('An error occurred while communicating with the server');
                    console.error(error);
                });
        }
        message.val(''); // Clear the message input field
    });
</script>
<style>
    .chat-box { padding: 15px; max-height: 400px; overflow-y: auto; min-height:400px;}
    .user-message, .bot-message { margin: 10px 0; }
    .user-message { text-align: right; }
    .user-message .message-content { display: inline-block; background: #007bff; color: #fff; padding: 10px; border-radius: 5px; }
    .bot-message { text-align: left; }
    .bot-message .message-content { display: inline-block; background: #e2e2e2; padding: 10px; border-radius: 5px; }
</style>
</body>
</html>