<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat</title>
    <style>
        body { font-family: sans-serif; display: flex; height: 100vh; margin: 0; background: #fff; color: #000; }
        #sidebar { width: 30%; border-right: 1px solid #ccc; padding: 10px; overflow-y: auto; background: #f9f9f9; }
        #chat-area { flex: 1; padding: 10px; display: flex; flex-direction: column; }
        #messages { flex: 1; border: 1px solid #ccc; padding: 10px; overflow-y: scroll; margin-bottom: 10px; background: #fff; }
        
        .message { margin-bottom: 10px; padding: 5px; background: #f1f1f1; border-radius: 5px; }
        .message.self { background: #d1e7dd; text-align: right; }
        .message strong { color: #333; }
        .time { font-size: 0.8em; color: #888; }
        
        .user-item { cursor: pointer; padding: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
        .user-item:hover, .user-item.active { background-color: #e2e2e2; }
        .online-dot { color: green; font-weight: bold; font-size: 1.2em; display: none; }
        .online-dot.visible { display: inline; }
        
        #typing { font-style: italic; color: gray; height: 20px; font-size: 0.9em; margin-bottom: 5px;}
        input[type="text"] { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 20px; border: 1px solid #ccc; background: #e0e0e0; cursor: pointer; border-radius: 4px; }
        button:hover { background: #d0d0d0; }
    </style>
</head>
<body>

    <div id="sidebar">
        <h3>Users</h3>
        <p>You are: <strong>{{ auth()->user()->name }}</strong></p>
        <hr>
        <div class="user-item active" id="user-tab-null" onclick="switchChat(null, 'Global Group Chat')">
            <span>Global Group Chat</span>
        </div>
        <div id="user-list">
            <!-- Fetch users loaded here -->
        </div>
    </div>

    <div id="chat-area">
        <h3 id="chat-title">Global Group Chat</h3>
        <p><small id="chat-status">Public Channel</small></p>
        
        <div id="messages">
            <!-- Messages fetched via API go here -->
        </div>
        
        <div id="typing"></div>
        
        <div style="display: flex; gap: 10px;">
            <input type="text" id="message-input" placeholder="Type a message..." autocomplete="off">
            <button id="send-btn">Send Message</button>
        </div>
    </div>

    {{-- Inject Reverb credentials from PHP config so Echo works regardless of build-time env --}}
    <script>
        window.__REVERB_KEY__  = "{{ config('broadcasting.connections.reverb.key') }}";
        window.__REVERB_HOST__ = "{{ parse_url(config('app.url'), PHP_URL_HOST) }}";
        window.__REVERB_PORT__ = 443;
        window.__REVERB_TLS__  = true;
    </script>
    @vite(['resources/js/app.js'])
    
    <script>
        const authId = {{ auth()->id() ?? 'null' }};
        const authName = "{{ auth()->user()->name ?? 'Guest' }}";
        let currentReceiverId = null;
        let typingTimeout = null;

        const messagesContainer = document.getElementById('messages');
        const inputField = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const userListEl = document.getElementById('user-list');
        const typingIndicator = document.getElementById('typing');

        window.onload = async () => {
            if (!authId) return;
            await loadUsers();
            await loadMessages(null);

            // Group Presence Channel — tracks who is online + group messages
            window.Echo.join('group.chat')
                .here(users => {
                    users.forEach(u => updatePresence(u.id, true));
                })
                .joining(user => updatePresence(user.id, true))
                .leaving(user => updatePresence(user.id, false))
                .listen('.MessageSent', (e) => {
                    if (currentReceiverId === null) addMessageToDOM(e.message);
                })
                .listenForWhisper('typing', handleTyping);

            // Private channel — receives direct messages for this user
            window.Echo.private('chat.' + authId)
                .listen('.MessageSent', (e) => {
                    if (currentReceiverId === e.message.user_id) addMessageToDOM(e.message);
                })
                .listenForWhisper('typing', handleTyping);
        };

        // UI Listeners
        inputField.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') sendMessage();
            else triggerTyping();
        });
        sendBtn.addEventListener('click', sendMessage);

        async function loadUsers() {
            try {
                const res = await window.axios.get('/api/users');
                res.data.forEach(user => {
                    userListEl.insertAdjacentHTML('beforeend', `
                        <div class="user-item" id="user-tab-${user.id}" onclick="switchChat(${user.id}, '${esc(user.name)}')">
                            <span>${esc(user.name)}</span>
                            <span class="online-dot" id="dot-${user.id}">●</span>
                        </div>
                    `);
                });
            } catch (err) { console.error(err); }
        }

        async function loadMessages(receiverId) {
            messagesContainer.innerHTML = '<p style="color:gray;">Loading messages...</p>';
            try {
                const url = receiverId ? `/api/messages/${receiverId}` : '/api/messages';
                const res = await window.axios.get(url);
                messagesContainer.innerHTML = '';
                
                if (res.data.length === 0) {
                    messagesContainer.innerHTML = '<p style="color:gray;">No messages yet.</p>';
                    return;
                }
                res.data.forEach(msg => addMessageToDOM(msg));
            } catch (err) { messagesContainer.innerHTML = '<p style="color:red;">Error loading chat</p>'; }
        }

        window.switchChat = async function(id, name) {
            currentReceiverId = id;
            
            document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
            document.getElementById(`user-tab-${id || 'null'}`).classList.add('active');
            document.getElementById('chat-title').innerText = name;
            
            let status = 'Offline';
            if (id === null) status = 'Public Channel';
            else if (document.getElementById(`dot-${id}`)?.classList.contains('visible')) status = 'Online';
            document.getElementById('chat-status').innerText = status;

            await loadMessages(id);
        }

        function addMessageToDOM(msg) {
            const placeholder = messagesContainer.querySelector('p');
            if (placeholder) placeholder.remove();

            const isSelf = msg.user_id === authId;
            const senderName = isSelf ? 'You' : (msg.user ? msg.user.name : `User ${msg.user_id}`);
            const timeStr = new Date(msg.created_at || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            const div = document.createElement('div');
            div.className = `message ${isSelf ? 'self' : ''}`;
            
            let nameOutput = '';
            if (currentReceiverId === null || isSelf) {
                nameOutput = `<strong>${esc(senderName)}: </strong><br>`;
            }

            div.innerHTML = `
                ${nameOutput}
                <span>${esc(msg.body)}</span>
                <span class="time" style="float:right;">${timeStr}</span>
            `;
            
            messagesContainer.appendChild(div);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        async function sendMessage() {
            const body = inputField.value.trim();
            if (!body) return;
            
            inputField.value = '';
            inputField.focus();
            
            // Show own message instantly (optimistic)
            addMessageToDOM({ user_id: authId, body: body, created_at: new Date().toISOString() });

            try {
                await window.axios.post('/messages', { body: body, receiver_id: currentReceiverId });
            } catch (err) { console.error("Message send failed.", err); }
        }

        function triggerTyping() {
            window.Echo.join('group.chat').whisper('typing', { 
                name: authName, 
                sender_id: authId, 
                receiver_id: currentReceiverId 
            });
        }

        function handleTyping(e) {
            const isGroupWhisper = currentReceiverId === null && e.receiver_id === null;
            const isPrivateWhisper = currentReceiverId !== null && e.receiver_id === authId && e.sender_id === currentReceiverId;

            if (isGroupWhisper || isPrivateWhisper) {
                typingIndicator.innerText = `${e.name} is typing...`;
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => { typingIndicator.innerText = ''; }, 1500);
            }
        }

        function updatePresence(id, isOnline) {
            if (id === authId) return;
            const dot = document.getElementById(`dot-${id}`);
            if (dot) dot.classList.toggle('visible', isOnline);
            
            if (currentReceiverId === id) {
                document.getElementById('chat-status').innerText = isOnline ? 'Online' : 'Offline';
            }
        }

        function esc(s) { 
            return String(s).replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[c])); 
        }
    </script>
</body>
</html>