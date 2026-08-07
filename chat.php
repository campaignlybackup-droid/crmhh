<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$isManager = isManager();
$current_user_id = getCurrentUserId();
$current_username = getCurrentUsername();

include 'header.php';
?>

<div class="row h-100">
    <div class="col-12 d-flex flex-column h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-0"><i class="bi bi-chat-dots-fill text-primary me-2"></i> Team Chat</h3>
                <p class="text-muted mb-0 small">Global chat for all team members. Mention users with @username.</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column" style="height: 70vh;">
            
            <!-- Pinned Messages Container -->
            <div id="pinnedMessagesContainer" class="bg-light border-bottom p-2 d-none" style="max-height: 150px; overflow-y: auto;">
                <div class="small fw-bold text-muted mb-2 px-2"><i class="bi bi-pin-angle-fill me-1"></i> Pinned Messages</div>
                <div id="pinnedMessagesList"></div>
            </div>

            <!-- Chat History -->
            <div id="chatHistory" class="card-body overflow-auto p-4 flex-grow-1 bg-white" style="scroll-behavior: smooth;">
                <div class="text-center text-muted small my-3" id="chatLoadingSpinner">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div> Loading messages...
                </div>
            </div>

            <!-- Chat Input -->
            <div class="card-footer bg-white border-top p-3">
                <form id="chatForm" class="d-flex gap-2">
                    <div class="flex-grow-1 position-relative">
                        <input type="text" id="chatInput" class="form-control rounded-pill px-4 py-2" placeholder="Type a message... (Use @username to tag)" autocomplete="off" required>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-circle" style="width: 45px; height: 45px;" id="sendBtn">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.chat-msg {
    max-width: 75%;
    margin-bottom: 1rem;
}
.chat-msg-self {
    margin-left: auto;
}
.chat-bubble {
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    position: relative;
    word-wrap: break-word;
}
.chat-bubble-self {
    background-color: var(--bs-primary);
    color: white;
    border-bottom-right-radius: 0.25rem;
}
.chat-bubble-other {
    background-color: var(--bs-gray-200);
    color: var(--bs-dark);
    border-bottom-left-radius: 0.25rem;
}
[data-bs-theme="dark"] .chat-bubble-other {
    background-color: var(--bs-gray-800);
    color: var(--bs-gray-100);
}
.chat-meta {
    font-size: 0.7rem;
    margin-top: 0.25rem;
}
.mention {
    font-weight: bold;
    color: #ffc107;
    background: rgba(255, 193, 7, 0.1);
    padding: 0 4px;
    border-radius: 4px;
}
.chat-bubble-other .mention {
    color: var(--bs-primary);
    background: rgba(13, 110, 253, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatHistory = document.getElementById('chatHistory');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const pinnedMessagesContainer = document.getElementById('pinnedMessagesContainer');
    const pinnedMessagesList = document.getElementById('pinnedMessagesList');
    
    let lastId = 0;
    let isFetching = false;
    let shouldScrollToBottom = true;
    let currentUserId = <?= (int)$current_user_id ?>;
    let canPin = <?= ($isManager || $isSuper) ? 'true' : 'false' ?>;

    // Format mentions in text
    function formatText(text) {
        // Escape HTML
        let div = document.createElement('div');
        div.textContent = text;
        let html = div.innerHTML;
        
        // Replace @username
        return html.replace(/@([a-zA-Z0-9_]+)/g, '<span class="mention">@$1</span>');
    }

    // Format date time
    function formatTime(dateStr) {
        let d = new Date(dateStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function createMessageHTML(msg, isSelf) {
        let html = `
            <div class="chat-msg ${isSelf ? 'chat-msg-self text-end' : 'text-start'}" data-id="${msg.id}">
                ${!isSelf ? `<div class="small fw-bold mb-1 text-muted ms-2">${msg.username}</div>` : ''}
                <div class="chat-bubble ${isSelf ? 'chat-bubble-self' : 'chat-bubble-other'} d-inline-block text-start">
                    ${formatText(msg.message)}
                </div>
                <div class="chat-meta text-muted px-2 d-flex align-items-center ${isSelf ? 'justify-content-end' : ''}">
                    ${formatTime(msg.created_at)}
                    ${canPin && !isSelf ? `
                        <button class="btn btn-sm btn-link p-0 ms-2 text-muted pin-btn" data-id="${msg.id}" title="Pin Message">
                            <i class="bi bi-pin-angle"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
        return html;
    }

    function createPinnedHTML(msg) {
        return `
            <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded mb-1 border" data-pinned-id="${msg.id}">
                <div class="text-truncate small">
                    <strong>${msg.username}:</strong> ${formatText(msg.message)}
                </div>
                ${canPin ? `
                    <button class="btn btn-sm btn-link text-danger p-0 ms-2 unpin-btn" data-id="${msg.id}" title="Unpin">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                ` : ''}
            </div>
        `;
    }

    // Fetch messages
    function fetchMessages() {
        if (isFetching) return;
        isFetching = true;

        fetch(`api_chat.php?action=fetch&last_id=${lastId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('chatLoadingSpinner')?.remove();
                    
                    // Render Pinned Messages
                    if (data.pinned && data.pinned.length > 0) {
                        pinnedMessagesContainer.classList.remove('d-none');
                        pinnedMessagesList.innerHTML = data.pinned.map(m => createPinnedHTML(m)).join('');
                    } else {
                        pinnedMessagesContainer.classList.add('d-none');
                        pinnedMessagesList.innerHTML = '';
                    }

                    // Render New Messages
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            let isSelf = parseInt(msg.user_id) === currentUserId;
                            chatHistory.insertAdjacentHTML('beforeend', createMessageHTML(msg, isSelf));
                            lastId = parseInt(msg.id);
                        });
                        
                        if (shouldScrollToBottom) {
                            chatHistory.scrollTop = chatHistory.scrollHeight;
                        }
                    }
                }
                isFetching = false;
            })
            .catch(err => {
                console.error(err);
                isFetching = false;
            });
    }

    // Scroll detection to pause auto-scroll if user scrolls up
    chatHistory.addEventListener('scroll', function() {
        shouldScrollToBottom = (chatHistory.scrollTop + chatHistory.clientHeight >= chatHistory.scrollHeight - 50);
    });

    // Send Message
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        let text = chatInput.value.trim();
        if (!text) return;
        
        chatInput.disabled = true;
        sendBtn.disabled = true;

        let formData = new FormData();
        formData.append('action', 'send');
        formData.append('message', text);

        fetch('api_chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                chatInput.value = '';
                shouldScrollToBottom = true;
                fetchMessages();
            } else {
                alert('Error sending message');
            }
        })
        .finally(() => {
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
        });
    });

    // Pin/Unpin actions (Event Delegation)
    document.addEventListener('click', function(e) {
        let pinBtn = e.target.closest('.pin-btn');
        let unpinBtn = e.target.closest('.unpin-btn');

        if (pinBtn) {
            let id = pinBtn.getAttribute('data-id');
            togglePin(id, 'pin');
        } else if (unpinBtn) {
            let id = unpinBtn.getAttribute('data-id');
            togglePin(id, 'unpin');
        }
    });

    function togglePin(id, action) {
        let formData = new FormData();
        formData.append('action', action);
        formData.append('id', id);

        fetch('api_chat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Immediately fetch to reflect pin status
                fetchMessages();
            }
        });
    }

    // Initial Fetch
    fetchMessages();
    
    // Poll every 3 seconds
    setInterval(fetchMessages, 3000);
});
</script>

<?php include 'footer.php'; ?>
