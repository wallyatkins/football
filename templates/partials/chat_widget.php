<?php
/**
 * templates/partials/chat_widget.php
 * Floating Action Button (FAB) & Live Chat / Feedback Widget for Wally's Football Pool.
 * Only loaded for authenticated users.
 */
$currentUser = $user ?? $_SESSION['user'] ?? null;
if (empty($currentUser)) {
    return;
}
$cuUsername = htmlspecialchars($currentUser['username'] ?? 'Player');
$cuEmail = htmlspecialchars($currentUser['email'] ?? '');
?>

<!-- Floating Action Button (FAB) -->
<div id="wallyChatFabContainer" class="fixed bottom-20 right-4 md:bottom-6 md:right-6 z-50 select-none">
    <button
        id="wallyChatFabBtn"
        type="button"
        title="Chat with Wally or Send Feedback"
        aria-label="Open Chat and Feedback"
        class="relative group w-12 h-12 md:w-14 md:h-14 rounded-full bg-[#0B1626] border-2 border-[#243247] shadow-[0_10px_30px_rgba(0,0,0,0.6)] flex items-center justify-center hover:border-amber-400 hover:scale-105 active:scale-95 transition-all duration-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-400/50"
    >
        <!-- Active Pulse Badge -->
        <span id="wallyFabPulse" class="hidden absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-[#0B1626] animate-pulse"></span>

        <!-- WA Logo in Inter Font -->
        <span class="font-sans font-black text-white text-base md:text-lg tracking-tighter leading-none select-none group-hover:text-amber-300 transition-colors" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;">
            WA
        </span>
    </button>
</div>

<!-- Floating Chat & Feedback Widget Modal -->
<div
    id="wallyChatWidget"
    class="hidden fixed bottom-24 right-4 md:bottom-24 md:right-6 z-50 w-[calc(100vw-2rem)] max-w-sm sm:max-w-md bg-[#0B1626]/98 backdrop-blur-xl border border-[#243247] rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.85)] text-slate-100 flex flex-col overflow-hidden max-h-[85vh] h-[540px] transition-all duration-200"
    role="dialog"
    aria-labelledby="wallyWidgetTitle"
>
    <!-- Modal Header -->
    <div class="px-4 py-3 bg-[#162235] border-b border-[#243247] flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <button id="wallyWidgetBackBtn" type="button" class="hidden text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition" title="Back">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <div class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block" id="wallyHeaderDot"></div>
            <div>
                <h2 id="wallyWidgetTitle" class="text-sm font-bold text-white leading-tight">Wally's Desk</h2>
                <p id="wallyWidgetSubtitle" class="text-[10px] text-slate-400 font-mono">Commissioner Support & Chat</p>
            </div>
        </div>

        <button id="wallyWidgetCloseBtn" type="button" class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition" title="Close">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- VIEW 1: Main Menu Options -->
    <div id="wallyViewMenu" class="flex-1 p-5 flex flex-col justify-between overflow-y-auto">
        <div class="space-y-4">
            <div class="bg-[#162235] border border-[#243247] p-4 rounded-xl">
                <div class="text-sm font-bold text-white flex items-center gap-1.5">
                    <span>Hey <?= $cuUsername ?>!</span>
                    <span>👋</span>
                </div>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                    Have feedback, found a scoring error, have a new idea, or want to chat with Wally? Choose an option below:
                </p>
            </div>

            <!-- Option A: Live Chat -->
            <button
                id="wallyBtnStartChat"
                type="button"
                class="w-full text-left p-4 rounded-xl bg-[#162235] hover:bg-slate-800/80 border border-[#243247] hover:border-amber-400/80 transition-all duration-150 group shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-300 text-lg group-hover:scale-110 transition-transform">
                            💬
                        </div>
                        <div>
                            <div class="text-sm font-bold text-white group-hover:text-amber-300 transition-colors">Launch Live Chat</div>
                            <div class="text-[11px] text-slate-400">Ping Wally in real time & open instant chat</div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-500 group-hover:text-amber-400 group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
            </button>

            <!-- Option B: Send Feedback -->
            <button
                id="wallyBtnStartFeedback"
                type="button"
                class="w-full text-left p-4 rounded-xl bg-[#162235] hover:bg-slate-800/80 border border-[#243247] hover:border-sky-400/80 transition-all duration-150 group shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-sky-500/15 border border-sky-500/30 flex items-center justify-center text-sky-300 text-lg group-hover:scale-110 transition-transform">
                            📝
                        </div>
                        <div>
                            <div class="text-sm font-bold text-white group-hover:text-sky-300 transition-colors">Send Feedback / Bug Report</div>
                            <div class="text-[11px] text-slate-400">Report errors, propose features, or share thoughts</div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-500 group-hover:text-sky-400 group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
            </button>
        </div>

        <div class="pt-4 border-t border-[#243247]/70 text-center">
            <p class="text-[11px] text-slate-500">
                Your input directly helps improve our league & portal experience.
            </p>
        </div>
    </div>

    <!-- VIEW 2: Feedback Form -->
    <div id="wallyViewFeedback" class="hidden flex-1 p-5 flex flex-col justify-between overflow-y-auto">
        <form id="wallyFeedbackForm" class="space-y-4">
            <div>
                <label class="block text-xs font-mono font-bold uppercase tracking-wider text-slate-400 mb-2">Category</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="feedback_cat" value="bug" class="sr-only peer" checked>
                        <div class="p-2.5 rounded-xl bg-[#162235] border border-[#243247] peer-checked:border-rose-500 peer-checked:bg-rose-500/10 peer-checked:text-rose-300 text-xs font-medium text-slate-300 flex items-center gap-2 transition">
                            <span>🐞</span>
                            <span>Bug / Error</span>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="feedback_cat" value="idea" class="sr-only peer">
                        <div class="p-2.5 rounded-xl bg-[#162235] border border-[#243247] peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:text-amber-300 text-xs font-medium text-slate-300 flex items-center gap-2 transition">
                            <span>💡</span>
                            <span>Feature Idea</span>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="feedback_cat" value="scoring" class="sr-only peer">
                        <div class="p-2.5 rounded-xl bg-[#162235] border border-[#243247] peer-checked:border-purple-500 peer-checked:bg-purple-500/10 peer-checked:text-purple-300 text-xs font-medium text-slate-300 flex items-center gap-2 transition">
                            <span>🏈</span>
                            <span>Scoring Issue</span>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="feedback_cat" value="general" class="sr-only peer">
                        <div class="p-2.5 rounded-xl bg-[#162235] border border-[#243247] peer-checked:border-sky-500 peer-checked:bg-sky-500/10 peer-checked:text-sky-300 text-xs font-medium text-slate-300 flex items-center gap-2 transition">
                            <span>💬</span>
                            <span>General Talk</span>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label for="wallyFeedbackText" class="block text-xs font-mono font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                    Your Message
                </label>
                <textarea
                    id="wallyFeedbackText"
                    rows="5"
                    required
                    placeholder="Tell Wally what you spotted, your idea, or any feedback..."
                    class="w-full bg-[#162235] border border-[#243247] rounded-xl p-3 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-amber-400 transition resize-none"
                ></textarea>
            </div>

            <div class="text-[11px] text-slate-500 flex items-center justify-between">
                <span>Sending as <strong class="text-slate-400"><?= $cuUsername ?></strong></span>
                <span class="truncate max-w-[180px]"><?= $cuEmail ?></span>
            </div>

            <button
                type="submit"
                id="wallyFeedbackSubmitBtn"
                class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-2 shadow-lg"
            >
                <span>Send to Wally &rarr;</span>
            </button>
        </form>

        <!-- Feedback Success Screen -->
        <div id="wallyFeedbackSuccess" class="hidden py-8 text-center space-y-4">
            <div class="w-14 h-14 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 text-2xl flex items-center justify-center mx-auto animate-bounce">
                ✓
            </div>
            <div>
                <h3 class="text-base font-bold text-white">Thank You, <?= $cuUsername ?>!</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto">
                    Your feedback was delivered straight to Wally's inbox. We appreciate your support!
                </p>
            </div>
            <button
                id="wallyFeedbackDoneBtn"
                type="button"
                class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-xl text-xs transition"
            >
                Back to Options
            </button>
        </div>
    </div>

    <!-- VIEW 3: Live Chat Stream -->
    <div id="wallyViewChat" class="hidden flex-1 flex flex-col overflow-hidden bg-[#0B1626]">
        <!-- Waiting State Banner & Countdown -->
        <div id="wallyChatWaitingBanner" class="p-4 bg-[#162235]/90 border-b border-[#243247] flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                </span>
                <div>
                    <div class="text-xs font-bold text-white">Pinging Wally's Phone & Email...</div>
                    <div class="text-[10px] text-slate-400">Waiting for Wally to connect</div>
                </div>
            </div>
            <div class="text-right">
                <span id="wallyChatCountdown" class="text-sm font-mono font-bold text-amber-400 tabular-nums">02:00</span>
            </div>
        </div>

        <!-- Unavailable Fallback Notice -->
        <div id="wallyChatUnavailableNotice" class="hidden p-3 bg-rose-950/30 border-b border-rose-800/40 text-xs space-y-2">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="font-bold text-rose-300">Wally is currently away from his desk</div>
                    <div class="text-[11px] text-slate-300 mt-0.5">
                        Leave your message below! It will be sent straight to his personal email with your chat transcript, and he'll reply to <strong><?= $cuEmail ?></strong>.
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 pt-1">
                <button
                    id="wallyChatTryAgainBtn"
                    type="button"
                    class="px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-[11px] font-bold transition flex items-center gap-1"
                >
                    <span>Try Again (Ping Wally Again)</span>
                </button>
            </div>
        </div>

        <!-- Chat Message Transcript -->
        <div id="wallyChatMessages" class="flex-1 p-4 overflow-y-auto space-y-3 text-xs scroll-smooth">
            <!-- Messages dynamically added here -->
        </div>

        <!-- Chat Input Form -->
        <form id="wallyChatInputForm" class="p-3 bg-[#162235] border-t border-[#243247] flex items-center gap-2">
            <input
                type="text"
                id="wallyChatInput"
                placeholder="Type your message to Wally..."
                autocomplete="off"
                class="flex-1 bg-[#0B1626] border border-[#243247] rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 transition"
            >
            <button
                type="submit"
                id="wallyChatSendBtn"
                class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition flex items-center gap-1 shadow-md"
            >
                <span>Send</span>
            </button>
        </form>

        <!-- End Session Action Bar -->
        <div class="px-3 py-1.5 bg-[#0B1626] border-t border-[#243247]/60 flex items-center justify-between text-[11px]">
            <span id="wallyChatSessionStatus" class="text-slate-500 font-mono text-[10px]">Session Active</span>
            <button id="wallyChatEndBtn" type="button" class="text-slate-400 hover:text-rose-400 transition font-mono text-[10px]">
                End Chat
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const fabBtn = document.getElementById('wallyChatFabBtn');
    const fabPulse = document.getElementById('wallyFabPulse');
    const widget = document.getElementById('wallyChatWidget');
    const closeBtn = document.getElementById('wallyWidgetCloseBtn');
    const backBtn = document.getElementById('wallyWidgetBackBtn');
    const headerDot = document.getElementById('wallyHeaderDot');
    const titleEl = document.getElementById('wallyWidgetTitle');
    const subtitleEl = document.getElementById('wallyWidgetSubtitle');

    const viewMenu = document.getElementById('wallyViewMenu');
    const viewFeedback = document.getElementById('wallyViewFeedback');
    const viewChat = document.getElementById('wallyViewChat');

    const btnStartChat = document.getElementById('wallyBtnStartChat');
    const btnStartFeedback = document.getElementById('wallyBtnStartFeedback');

    const feedbackForm = document.getElementById('wallyFeedbackForm');
    const feedbackText = document.getElementById('wallyFeedbackText');
    const feedbackSuccess = document.getElementById('wallyFeedbackSuccess');
    const feedbackDoneBtn = document.getElementById('wallyFeedbackDoneBtn');

    const chatWaitingBanner = document.getElementById('wallyChatWaitingBanner');
    const chatCountdown = document.getElementById('wallyChatCountdown');
    const chatUnavailableNotice = document.getElementById('wallyChatUnavailableNotice');
    const chatTryAgainBtn = document.getElementById('wallyChatTryAgainBtn');
    const chatMessages = document.getElementById('wallyChatMessages');
    const chatInputForm = document.getElementById('wallyChatInputForm');
    const chatInput = document.getElementById('wallyChatInput');
    const chatSessionStatus = document.getElementById('wallyChatSessionStatus');
    const chatEndBtn = document.getElementById('wallyChatEndBtn');

    let currentView = 'menu'; // 'menu' | 'feedback' | 'chat'
    let activeSession = null;
    let pollInterval = null;
    let countdownInterval = null;
    let countdownSeconds = 120; // 2 minutes countdown
    let hasResent = false;
    let renderedMsgIds = new Set();

    // Check for existing saved session in sessionStorage
    try {
        const saved = sessionStorage.getItem('wally_football_chat_session');
        if (saved) {
            activeSession = JSON.parse(saved);
            if (activeSession && activeSession.session_id) {
                fabPulse.classList.remove('hidden');
            }
        }
    } catch(e) {}

    function switchView(view) {
        currentView = view;
        viewMenu.classList.add('hidden');
        viewFeedback.classList.add('hidden');
        viewChat.classList.add('hidden');
        backBtn.classList.add('hidden');

        if (view === 'menu') {
            viewMenu.classList.remove('hidden');
            titleEl.textContent = "Wally's Desk";
            subtitleEl.textContent = "Commissioner Support & Chat";
            headerDot.className = "w-2.5 h-2.5 rounded-full bg-amber-400 inline-block";
        } else if (view === 'feedback') {
            viewFeedback.classList.remove('hidden');
            backBtn.classList.remove('hidden');
            feedbackForm.classList.remove('hidden');
            feedbackSuccess.classList.add('hidden');
            titleEl.textContent = "Send Feedback";
            subtitleEl.textContent = "Bugs, ideas & thoughts";
            headerDot.className = "w-2.5 h-2.5 rounded-full bg-sky-400 inline-block";
            feedbackText.focus();
        } else if (view === 'chat') {
            viewChat.classList.remove('hidden');
            backBtn.classList.remove('hidden');
            titleEl.textContent = "Live Chat with Wally";
            subtitleEl.textContent = "Direct 1-on-1";
            headerDot.className = "w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block";
            chatInput.focus();
        }
    }

    function toggleWidget() {
        const isHidden = widget.classList.contains('hidden');
        if (isHidden) {
            widget.classList.remove('hidden');
            if (activeSession && activeSession.session_id) {
                switchView('chat');
                startChatPolling();
            } else {
                switchView('menu');
            }
        } else {
            widget.classList.add('hidden');
        }
    }

    fabBtn.addEventListener('click', toggleWidget);
    closeBtn.addEventListener('click', () => widget.classList.add('hidden'));
    backBtn.addEventListener('click', () => switchView('menu'));

    btnStartFeedback.addEventListener('click', () => switchView('feedback'));
    feedbackDoneBtn.addEventListener('click', () => switchView('menu'));

    // Submit Feedback
    feedbackForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const cat = feedbackForm.querySelector('input[name="feedback_cat"]:checked')?.value || 'general';
        const msg = feedbackText.value.trim();
        if (!msg) return;

        const submitBtn = document.getElementById('wallyFeedbackSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        try {
            const formData = new FormData();
            formData.append('category', cat);
            formData.append('message', msg);

            const res = await fetch('/api/feedback/submit', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                feedbackForm.classList.add('hidden');
                feedbackSuccess.classList.remove('hidden');
                feedbackText.value = '';
            } else {
                alert(data.message || 'Error sending feedback');
            }
        } catch (err) {
            console.error('Feedback error', err);
            alert('Failed to send feedback. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Send to Wally &rarr;</span>';
        }
    });

    // Start or Resume Live Chat
    btnStartChat.addEventListener('click', async function() {
        switchView('chat');
        if (!activeSession || !activeSession.session_id) {
            await initNewChatSession();
        } else {
            startChatPolling();
        }
    });

    async function initNewChatSession() {
        renderedMsgIds.clear();
        chatMessages.innerHTML = '';
        chatWaitingBanner.classList.remove('hidden');
        chatUnavailableNotice.classList.add('hidden');
        countdownSeconds = 120;
        updateCountdownDisplay();
        startCountdown();

        try {
            const res = await fetch('/api/chat/start', { method: 'POST' });
            const data = await res.json();
            if (data.status === 'success') {
                activeSession = {
                    session_id: data.session_id,
                    token: data.user_token
                };
                sessionStorage.setItem('wally_football_chat_session', JSON.stringify(activeSession));
                fabPulse.classList.remove('hidden');
                startChatPolling();
            } else {
                alert(data.message || 'Failed to start chat session');
                switchView('menu');
            }
        } catch (err) {
            console.error('Chat start error', err);
            alert('Unable to initialize chat.');
            switchView('menu');
        }
    }

    function updateCountdownDisplay() {
        const mins = Math.floor(countdownSeconds / 60);
        const secs = countdownSeconds % 60;
        chatCountdown.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    function startCountdown() {
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(() => {
            if (countdownSeconds > 0) {
                countdownSeconds--;
                updateCountdownDisplay();
            } else {
                clearInterval(countdownInterval);
                // Countdown reached 0 without admin online -> Switch to unavailable message mode
                chatWaitingBanner.classList.add('hidden');
                chatUnavailableNotice.classList.remove('hidden');
                headerDot.className = "w-2.5 h-2.5 rounded-full bg-slate-500 inline-block";
                chatSessionStatus.textContent = "Wally Away &bull; Leave a Message";
            }
        }, 1000);
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function renderChatMessage(msg) {
        if (renderedMsgIds.has(msg.id)) return;
        renderedMsgIds.add(msg.id);

        const isUser = msg.sender === 'user';
        const isSystem = msg.sender === 'system';

        const row = document.createElement('div');
        row.className = isSystem ? 'text-center my-1.5' : (isUser ? 'flex justify-end' : 'flex justify-start');

        if (isSystem) {
            row.innerHTML = `
                <span class="inline-block text-[10px] font-mono text-slate-400 bg-[#162235] px-2.5 py-0.5 rounded-full border border-[#243247]">
                    ${escapeHtml(msg.message)}
                </span>
            `;
        } else {
            const bubbleBg = isUser ? 'bg-emerald-600 text-white' : 'bg-[#162235] text-slate-100 border border-[#243247]';
            const label = isUser ? 'You' : 'Wally Atkins';
            row.innerHTML = `
                <div class="max-w-[80%] space-y-0.5">
                    <div class="text-[9px] font-mono text-slate-400 px-1 ${isUser ? 'text-right' : 'text-left'}">
                        ${escapeHtml(label)} &bull; ${escapeHtml(msg.time_formatted || '')}
                    </div>
                    <div class="p-2.5 rounded-xl ${bubbleBg} text-xs leading-relaxed break-words shadow-sm">
                        ${escapeHtml(msg.message)}
                    </div>
                </div>
            `;
        }

        chatMessages.appendChild(row);
        scrollToBottom();
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    async function pollChat() {
        if (!activeSession || !activeSession.session_id) return;

        try {
            const res = await fetch(`/api/chat/poll?session_id=${encodeURIComponent(activeSession.session_id)}&token=${encodeURIComponent(activeSession.token)}`);
            if (!res.ok) {
                if (res.status === 404) {
                    endChatLocal();
                    return;
                }
                return;
            }
            const data = await res.json();
            if (data.status === 'success') {
                if (data.other_online || data.session_status === 'active') {
                    // Wally is connected!
                    chatWaitingBanner.classList.add('hidden');
                    chatUnavailableNotice.classList.add('hidden');
                    headerDot.className = "w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block animate-pulse";
                    subtitleEl.textContent = "🟢 Connected with Wally";
                    chatSessionStatus.textContent = "Wally Online";
                    if (countdownInterval) clearInterval(countdownInterval);
                }

                if (Array.isArray(data.messages)) {
                    data.messages.forEach(renderChatMessage);
                }
            }
        } catch (err) {
            console.error('Chat polling error', err);
        }
    }

    function startChatPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollChat();
        pollInterval = setInterval(pollChat, 2000);
    }

    // Send Message Form
    chatInputForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text || !activeSession) return;

        chatInput.value = '';
        try {
            const formData = new FormData();
            formData.append('session_id', activeSession.session_id);
            formData.append('token', activeSession.token);
            formData.append('message', text);

            const res = await fetch('/api/chat/send', { method: 'POST', body: formData });
            if (res.ok) {
                pollChat();
            }
        } catch (err) {
            console.error('Send message error', err);
        }
    });

    // Try Again (Resend Ping)
    chatTryAgainBtn.addEventListener('click', async function() {
        if (!activeSession) return;
        chatTryAgainBtn.disabled = true;
        chatTryAgainBtn.textContent = 'Pinging Wally again...';

        try {
            const formData = new FormData();
            formData.append('session_id', activeSession.session_id);
            formData.append('token', activeSession.token);

            await fetch('/api/chat/resend', { method: 'POST', body: formData });
            chatUnavailableNotice.classList.add('hidden');
            chatWaitingBanner.classList.remove('hidden');
            countdownSeconds = 120;
            updateCountdownDisplay();
            startCountdown();
            pollChat();
        } catch (err) {
            console.error('Resend error', err);
        } finally {
            chatTryAgainBtn.disabled = false;
            chatTryAgainBtn.textContent = 'Try Again (Ping Wally Again)';
        }
    });

    // End Chat
    chatEndBtn.addEventListener('click', async function() {
        if (confirm('End this chat session?')) {
            if (activeSession) {
                try {
                    const formData = new FormData();
                    formData.append('session_id', activeSession.session_id);
                    formData.append('token', activeSession.token);
                    await fetch('/api/chat/end', { method: 'POST', body: formData });
                } catch(e) {}
            }
            endChatLocal();
            switchView('menu');
        }
    });

    function endChatLocal() {
        if (pollInterval) clearInterval(pollInterval);
        if (countdownInterval) clearInterval(countdownInterval);
        activeSession = null;
        sessionStorage.removeItem('wally_football_chat_session');
        fabPulse.classList.add('hidden');
    }
})();
</script>
