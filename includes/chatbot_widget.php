<?php
// includes/chatbot_widget.php
// This file contains the floating chatbot widget that can be included in any page
?>

<!-- Floating Chatbot Widget Styles -->
<style>
    .chatbot-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    /* Chat Button */
    .chat-button {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0B4F2E, #1a7a42);
        color: white;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(11, 79, 46, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: all 0.3s ease;
        position: relative;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(11, 79, 46, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(11, 79, 46, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(11, 79, 46, 0);
        }
    }
    
    .chat-button:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(11, 79, 46, 0.4);
        animation: none;
    }
    
    .chat-button .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #FFD700;
        color: #0B4F2E;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        font-weight: bold;
    }
    
    /* Chat Window */
    .chat-window {
        position: absolute;
        bottom: 80px;
        right: 0;
        width: 350px;
        height: 500px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: slideIn 0.3s ease;
        border: 1px solid rgba(11, 79, 46, 0.1);
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .chat-window.open {
        display: flex;
    }
    
    /* Chat Header */
    .chat-header {
        background: linear-gradient(135deg, #0B4F2E, #1a7a42);
        color: white;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .chat-header h3 {
        margin: 0;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
    }
    
    .chat-header h3 i {
        color: #FFD700;
    }
    
    .chat-header .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.3s;
    }
    
    .chat-header .close-btn:hover {
        background: rgba(255,255,255,0.2);
    }
    
    /* Chat Messages */
    .chat-messages {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #f8f9fa;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .message {
        display: flex;
        margin-bottom: 10px;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message.bot {
        justify-content: flex-start;
    }
    
    .message.user {
        justify-content: flex-end;
    }
    
    .message-content {
        max-width: 80%;
        padding: 12px 15px;
        border-radius: 15px;
        font-size: 14px;
        line-height: 1.4;
    }
    
    .bot .message-content {
        background: white;
        color: #333;
        border-bottom-left-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .user .message-content {
        background: linear-gradient(135deg, #0B4F2E, #1a7a42);
        color: white;
        border-bottom-right-radius: 5px;
    }
    
    .message-time {
        font-size: 10px;
        margin-top: 5px;
        opacity: 0.7;
        display: block;
    }
    
    /* Quick Replies */
    .quick-replies {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px 15px;
        background: white;
        border-top: 1px solid #e0e0e0;
    }
    
    .quick-reply-btn {
        padding: 8px 12px;
        background: #f0f0f0;
        border: none;
        border-radius: 20px;
        font-size: 12px;
        color: #0B4F2E;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
        font-weight: 500;
    }
    
    .quick-reply-btn:hover {
        background: #0B4F2E;
        color: white;
    }
    
    /* Chat Input */
    .chat-input-container {
        padding: 15px;
        background: white;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 10px;
    }
    
    .chat-input-container input {
        flex: 1;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        outline: none;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: 'Inter', sans-serif;
    }
    
    .chat-input-container input:focus {
        border-color: #0B4F2E;
    }
    
    .chat-input-container button {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0B4F2E, #1a7a42);
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: all 0.3s;
    }
    
    .chat-input-container button:hover {
        transform: scale(1.1);
    }
    
    .chat-input-container button:disabled {
        background: #cbd5e0;
        cursor: not-allowed;
    }
    
    /* Typing Indicator */
    .typing-indicator {
        display: flex;
        gap: 5px;
        padding: 12px 15px;
        background: white;
        border-radius: 15px;
        width: fit-content;
        margin-bottom: 10px;
    }
    
    .typing-indicator span {
        width: 8px;
        height: 8px;
        background: #0B4F2E;
        border-radius: 50%;
        animation: typing 1s infinite ease-in-out;
    }
    
    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }
    
    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }
    
    @keyframes typing {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-10px); }
    }

    @media (max-width: 480px) {
        .chat-window {
            width: 300px;
            height: 450px;
            right: 0;
        }
    }
</style>

<div class="chatbot-widget" id="chatbotWidget">
    <!-- Chat Button -->
    <button class="chat-button" id="chatButton" onclick="toggleChat()">
        💬
        <span class="notification-badge" id="notificationBadge" style="display: none;">1</span>
    </button>
    
    <!-- Chat Window -->
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <h3>
                <i class="fas fa-robot"></i>
                Student Assistant
            </h3>
            <button class="close-btn" onclick="toggleChat()">×</button>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <!-- Welcome Message -->
            <div class="message bot">
                <div class="message-content">
                    👋 Hi <?php echo isset($student_name) ? htmlspecialchars(explode(' ', $student_name)[0]) : 'Student'; ?>! I'm your student assistant. How can I help you today?
                    <span class="message-time"><?php echo date('h:i A'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Replies -->
        <div class="quick-replies" id="quickReplies">
            <button class="quick-reply-btn" onclick="setQuestion('How to enroll?')">📝 Enrollment</button>
            <button class="quick-reply-btn" onclick="setQuestion('My schedule')">📅 Schedule</button>
            <button class="quick-reply-btn" onclick="setQuestion('My attendance')">📊 Attendance</button>
            <button class="quick-reply-btn" onclick="setQuestion('My grades')">📚 Grades</button>
            <button class="quick-reply-btn" onclick="setQuestion('Office hours')">⏰ Office Hours</button>
        </div>
        
        <!-- Chat Input -->
        <div class="chat-input-container">
            <input type="text" id="chatInput" placeholder="Type your question..." onkeypress="handleKeyPress(event)">
            <button onclick="sendMessage()" id="sendButton">➤</button>
        </div>
    </div>
</div>

<script>
    // Chatbot functionality
    let chatHistory = [];
    let isTyping = false;
    const studentName = "<?php echo isset($student_name) ? htmlspecialchars(explode(' ', $student_name)[0]) : 'Student'; ?>";

    // Toggle chat window
    function toggleChat() {
        const chatWindow = document.getElementById('chatWindow');
        const chatButton = document.getElementById('chatButton');
        
        chatWindow.classList.toggle('open');
        
        // Hide notification when opened
        if(chatWindow.classList.contains('open')) {
            document.getElementById('notificationBadge').style.display = 'none';
        }
    }

    // Set question from quick reply
    function setQuestion(question) {
        document.getElementById('chatInput').value = question;
        sendMessage();
    }

    // Send message
    function sendMessage() {
        const input = document.getElementById('chatInput');
        const question = input.value.trim();
        
        if(question === '') return;
        
        // Disable input and button
        input.disabled = true;
        document.getElementById('sendButton').disabled = true;
        
        // Add user message
        addMessage(question, 'user');
        input.value = '';
        
        // Show typing indicator
        showTypingIndicator();
        
        // Simulate bot response
        setTimeout(() => {
            // Remove typing indicator
            hideTypingIndicator();
            
            // Generate response based on question
            let response = getBotResponse(question);
            
            // Add bot response
            addMessage(response, 'bot');
            
            // Re-enable input and button
            input.disabled = false;
            document.getElementById('sendButton').disabled = false;
            input.focus();
        }, 1500);
    }

    // Handle enter key
    function handleKeyPress(event) {
        if(event.key === 'Enter') {
            sendMessage();
        }
    }

    // Add message to chat
    function addMessage(text, sender) {
        const messagesDiv = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        
        messageDiv.innerHTML = `
            <div class="message-content">
                ${text}
                <span class="message-time">${time}</span>
            </div>
        `;
        
        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
        // Add to history
        chatHistory.push({ text, sender, time });
    }

    // Show typing indicator
    function showTypingIndicator() {
        const messagesDiv = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = `
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    // Hide typing indicator
    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typingIndicator');
        if(typingIndicator) {
            typingIndicator.remove();
        }
    }

    // Get bot response based on keywords
    function getBotResponse(question) {
        question = question.toLowerCase();
        
        // Enrollment related
        if(question.includes('enroll') || question.includes('enrollment')) {
            return "You can enroll by going to the <strong>Enrollment</strong> page. Click on 'Enroll Now' to start the process!";
        }
        
        // Schedule related
        else if(question.includes('schedule') || question.includes('class') || question.includes('time')) {
            return "Your class schedule is available in the <strong>Class Schedule</strong> page. You can view your daily and weekly schedule there.";
        }
        
        // Attendance related
        else if(question.includes('attendance')) {
            return "You can view your attendance records in the <strong>Attendance</strong> page. It shows your present, absent, and late records.";
        }
        
        // Grades related
        else if(question.includes('grade') || question.includes('grades') || question.includes('score')) {
            return "Check your grades in the <strong>My Grades</strong> page. It shows your performance in all subjects per quarter.";
        }
        
        // Profile related
        else if(question.includes('profile') || question.includes('account') || question.includes('information')) {
            return "You can view and edit your profile information in the <strong>My Profile</strong> page.";
        }
        
        // Office hours related
        else if(question.includes('office') || question.includes('hour') || question.includes('teacher')) {
            return "Office hours are typically Monday to Friday, 8:00 AM to 5:00 PM. You can check specific teacher schedules in the faculty directory.";
        }
        
        // Default response
        else {
            const responses = [
                "I'm here to help! Could you please provide more details about what you need?",
                "You can find most information in the dashboard sections. Which area would you like to know more about?",
                "I can help you with enrollment, schedules, attendance, grades, and profile information. What would you like to know?",
                "Feel free to ask about enrollment, class schedules, attendance tracking, or grades!",
                "For specific questions, try using the quick reply buttons above!"
            ];
            return responses[Math.floor(Math.random() * responses.length)];
        }
    }

    // Show notification after 30 seconds if chat not opened
    setTimeout(() => {
        if(!document.getElementById('chatWindow').classList.contains('open')) {
            document.getElementById('notificationBadge').style.display = 'flex';
        }
    }, 30000);
</script>