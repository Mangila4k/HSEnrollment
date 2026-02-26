<?php
// includes/chatbot_widget_teacher.php
// Teacher-specific floating chatbot widget
?>

<!-- Teacher Chatbot Widget Styles -->
<style>
    .chatbot-widget-teacher {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    /* Chat Button - Teacher Theme (Orange/Gold) */
    .chat-button-teacher {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: all 0.3s ease;
        position: relative;
        animation: pulseTeacher 2s infinite;
    }
    
    @keyframes pulseTeacher {
        0% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(245, 158, 11, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
        }
    }
    
    .chat-button-teacher:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        animation: none;
    }
    
    .chat-button-teacher .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #FFD700;
        color: #d97706;
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
    .chat-window-teacher {
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
        animation: slideInTeacher 0.3s ease;
        border: 1px solid rgba(245, 158, 11, 0.1);
    }
    
    @keyframes slideInTeacher {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .chat-window-teacher.open {
        display: flex;
    }
    
    /* Chat Header - Teacher Theme */
    .chat-header-teacher {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .chat-header-teacher h3 {
        margin: 0;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
    }
    
    .chat-header-teacher h3 i {
        color: #FFD700;
    }
    
    .chat-header-teacher .close-btn {
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
    
    .chat-header-teacher .close-btn:hover {
        background: rgba(255,255,255,0.2);
    }
    
    /* Chat Messages */
    .chat-messages-teacher {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #f8f9fa;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .message-teacher {
        display: flex;
        margin-bottom: 10px;
        animation: fadeInTeacher 0.3s ease;
    }
    
    @keyframes fadeInTeacher {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message-teacher.bot {
        justify-content: flex-start;
    }
    
    .message-teacher.user {
        justify-content: flex-end;
    }
    
    .message-teacher .message-content {
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
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        border-bottom-right-radius: 5px;
    }
    
    .message-time {
        font-size: 10px;
        margin-top: 5px;
        opacity: 0.7;
        display: block;
    }
    
    /* Quick Replies - Teacher Theme */
    .quick-replies-teacher {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px 15px;
        background: white;
        border-top: 1px solid #e0e0e0;
    }
    
    .quick-reply-btn-teacher {
        padding: 8px 12px;
        background: #fef3c7;
        border: none;
        border-radius: 20px;
        font-size: 12px;
        color: #d97706;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
        font-weight: 500;
    }
    
    .quick-reply-btn-teacher:hover {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    
    /* Chat Input - Teacher Theme */
    .chat-input-container-teacher {
        padding: 15px;
        background: white;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 10px;
    }
    
    .chat-input-container-teacher input {
        flex: 1;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        outline: none;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: 'Inter', sans-serif;
    }
    
    .chat-input-container-teacher input:focus {
        border-color: #f59e0b;
    }
    
    .chat-input-container-teacher button {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: all 0.3s;
    }
    
    .chat-input-container-teacher button:hover {
        transform: scale(1.1);
    }
    
    .chat-input-container-teacher button:disabled {
        background: #cbd5e0;
        cursor: not-allowed;
    }
    
    /* Typing Indicator - Teacher Theme */
    .typing-indicator-teacher {
        display: flex;
        gap: 5px;
        padding: 12px 15px;
        background: white;
        border-radius: 15px;
        width: fit-content;
        margin-bottom: 10px;
    }
    
    .typing-indicator-teacher span {
        width: 8px;
        height: 8px;
        background: #f59e0b;
        border-radius: 50%;
        animation: typingTeacher 1s infinite ease-in-out;
    }
    
    .typing-indicator-teacher span:nth-child(2) {
        animation-delay: 0.2s;
    }
    
    .typing-indicator-teacher span:nth-child(3) {
        animation-delay: 0.4s;
    }
    
    @keyframes typingTeacher {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-10px); }
    }

    @media (max-width: 480px) {
        .chat-window-teacher {
            width: 300px;
            height: 450px;
            right: 0;
        }
    }
</style>

<div class="chatbot-widget-teacher" id="chatbotWidgetTeacher">
    <!-- Chat Button -->
    <button class="chat-button-teacher" id="chatButtonTeacher" onclick="toggleChatTeacher()">
        👨‍🏫
        <span class="notification-badge" id="notificationBadgeTeacher" style="display: none;">1</span>
    </button>
    
    <!-- Chat Window -->
    <div class="chat-window-teacher" id="chatWindowTeacher">
        <div class="chat-header-teacher">
            <h3>
                <i class="fas fa-chalkboard-teacher"></i>
                Teacher Assistant
            </h3>
            <button class="close-btn" onclick="toggleChatTeacher()">×</button>
        </div>
        
        <div class="chat-messages-teacher" id="chatMessagesTeacher">
            <!-- Welcome Message -->
            <div class="message-teacher bot">
                <div class="message-content">
                    👋 Hello <?php echo isset($teacher_name) ? htmlspecialchars(explode(' ', $teacher_name)[0]) : 'Teacher'; ?>! I'm your teaching assistant. How can I help you today?
                    <span class="message-time"><?php echo date('h:i A'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Replies - Teacher Specific -->
        <div class="quick-replies-teacher" id="quickRepliesTeacher">
            <button class="quick-reply-btn-teacher" onclick="setQuestionTeacher('My classes today')">📚 My Classes</button>
            <button class="quick-reply-btn-teacher" onclick="setQuestionTeacher('Take attendance')">📝 Take Attendance</button>
            <button class="quick-reply-btn-teacher" onclick="setQuestionTeacher('Enter grades')">📊 Enter Grades</button>
            <button class="quick-reply-btn-teacher" onclick="setQuestionTeacher('Class schedule')">📅 Schedule</button>
            <button class="quick-reply-btn-teacher" onclick="setQuestionTeacher('Student list')">👥 Student List</button>
            <button class="quick-reply-btn-teacher" onclick="setQuestionTeacher('Department meeting')">📋 Meeting</button>
        </div>
        
        <!-- Chat Input -->
        <div class="chat-input-container-teacher">
            <input type="text" id="chatInputTeacher" placeholder="Type your question..." onkeypress="handleKeyPressTeacher(event)">
            <button onclick="sendMessageTeacher()" id="sendButtonTeacher">➤</button>
        </div>
    </div>
</div>

<script>
    // Teacher Chatbot functionality
    let teacherChatHistory = [];
    let teacherIsTyping = false;
    const teacherName = "<?php echo isset($teacher_name) ? htmlspecialchars(explode(' ', $teacher_name)[0]) : 'Teacher'; ?>";

    // Toggle chat window
    function toggleChatTeacher() {
        const chatWindow = document.getElementById('chatWindowTeacher');
        chatWindow.classList.toggle('open');
        
        // Hide notification when opened
        if(chatWindow.classList.contains('open')) {
            document.getElementById('notificationBadgeTeacher').style.display = 'none';
        }
    }

    // Set question from quick reply
    function setQuestionTeacher(question) {
        document.getElementById('chatInputTeacher').value = question;
        sendMessageTeacher();
    }

    // Send message
    function sendMessageTeacher() {
        const input = document.getElementById('chatInputTeacher');
        const question = input.value.trim();
        
        if(question === '') return;
        
        // Disable input and button
        input.disabled = true;
        document.getElementById('sendButtonTeacher').disabled = true;
        
        // Add user message
        addMessageTeacher(question, 'user');
        input.value = '';
        
        // Show typing indicator
        showTypingIndicatorTeacher();
        
        // Simulate bot response
        setTimeout(() => {
            // Remove typing indicator
            hideTypingIndicatorTeacher();
            
            // Generate response based on question
            let response = getTeacherBotResponse(question);
            
            // Add bot response
            addMessageTeacher(response, 'bot');
            
            // Re-enable input and button
            input.disabled = false;
            document.getElementById('sendButtonTeacher').disabled = false;
            input.focus();
        }, 1500);
    }

    // Handle enter key
    function handleKeyPressTeacher(event) {
        if(event.key === 'Enter') {
            sendMessageTeacher();
        }
    }

    // Add message to chat
    function addMessageTeacher(text, sender) {
        const messagesDiv = document.getElementById('chatMessagesTeacher');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message-teacher ${sender}`;
        
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
        teacherChatHistory.push({ text, sender, time });
    }

    // Show typing indicator
    function showTypingIndicatorTeacher() {
        const messagesDiv = document.getElementById('chatMessagesTeacher');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message-teacher bot';
        typingDiv.id = 'typingIndicatorTeacher';
        typingDiv.innerHTML = `
            <div class="typing-indicator-teacher">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    // Hide typing indicator
    function hideTypingIndicatorTeacher() {
        const typingIndicator = document.getElementById('typingIndicatorTeacher');
        if(typingIndicator) {
            typingIndicator.remove();
        }
    }

    // Get teacher-specific bot response based on keywords
    function getTeacherBotResponse(question) {
        question = question.toLowerCase();
        
        // Classes related
        if(question.includes('class') || question.includes('classes') || question.includes('teaching')) {
            return "You can view your assigned classes in the <strong>My Classes</strong> page. It shows all your sections and subjects.";
        }
        
        // Attendance related
        else if(question.includes('attendance') || question.includes('take attendance')) {
            return "Go to the <strong>Attendance</strong> page to mark student attendance. You can mark present, absent, or late for each class.";
        }
        
        // Grades related
        else if(question.includes('grade') || question.includes('grades') || question.includes('enter grades')) {
            return "You can enter and manage student grades in the <strong>Grades</strong> page. Add scores, compute averages, and submit grades.";
        }
        
        // Schedule related
        else if(question.includes('schedule') || question.includes('timetable')) {
            return "View your teaching schedule in the <strong>Schedule</strong> page. It shows your classes by day and time.";
        }
        
        // Student list related
        else if(question.includes('student') || question.includes('students') || question.includes('class list')) {
            return "Access your student lists in the <strong>My Classes</strong> page. Click on any class to see the enrolled students.";
        }
        
        // Meeting related
        else if(question.includes('meeting') || question.includes('department') || question.includes('faculty')) {
            return "Department meetings are typically held every Friday at 3:00 PM. Check your email for specific meeting schedules.";
        }
        
        // Profile related
        else if(question.includes('profile') || question.includes('account')) {
            return "You can update your profile information in the <strong>Profile</strong> settings page.";
        }
        
        // Default response
        else {
            const responses = [
                "I'm here to help with your teaching tasks! Ask me about classes, attendance, grades, or schedule.",
                "You can manage attendance, enter grades, and view your schedule. What would you like to do?",
                "Need help with taking attendance? Or perhaps entering grades? Just ask!",
                "I can assist you with class management, student records, and teaching schedules.",
                "Try asking about your classes, attendance tracking, or grade entry procedures!"
            ];
            return responses[Math.floor(Math.random() * responses.length)];
        }
    }

    // Show notification after 30 seconds if chat not opened
    setTimeout(() => {
        if(!document.getElementById('chatWindowTeacher').classList.contains('open')) {
            document.getElementById('notificationBadgeTeacher').style.display = 'flex';
        }
    }, 30000);
</script>