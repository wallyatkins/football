<?php
/** @var array $session */
/** @var string $sessionId */
/** @var string $activeToken */
/** @var bool $isAdmin */
/** @var array $playerStats */

ob_start();
$userName = htmlspecialchars($session['user_name']);
$userEmail = htmlspecialchars($session['user_email']);
?>

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header Breadcrumb & Status -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-[#162235] border border-[#243247] p-4 sm:p-6 rounded-2xl shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-300 font-black text-lg">
                <?= strtoupper(substr($session['user_name'], 0, 2)) ?>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-white"><?= $userName ?></h1>
                    <span id="chatStatusPill" class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold <?= $session['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-400 border border-amber-500/40' ?>">
                        <?= ucfirst($session['status']) ?>
                    </span>
                    <span id="otherOnlineIndicator" class="text-xs text-slate-400 flex items-center gap-1.5 font-mono">
                        <span class="w-2 h-2 rounded-full bg-slate-500 inline-block" id="otherDot"></span>
                        <span id="otherText">Checking status...</span>
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                    <a href="mailto:<?= $userEmail ?>" class="hover:text-amber-400 transition underline"><?= $userEmail ?></a>
                    &bull; Session ID: <code class="text-slate-500"><?= htmlspecialchars($sessionId) ?></code>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <?php if ($isAdmin): ?>
                <a href="/chat" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition">
                    &larr; All Chats
                </a>
            <?php endif; ?>
            <button id="btnEndChat" type="button" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 border border-rose-500/40 transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                <span>End Session</span>
            </button>
        </div>
    </div>

    <!-- Main Grid: Chat Area + User Context -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Chat Column -->
        <div class="lg:col-span-2 bg-[#162235] border border-[#243247] rounded-2xl shadow-xl flex flex-col h-[650px] overflow-hidden">
            <!-- Messages Container -->
            <div id="messagesContainer" class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-3.5 scroll-smooth bg-[#0B1626]/60">
                <div class="text-center py-6">
                    <span class="text-xs font-mono text-slate-500 bg-[#0B1626] px-3 py-1 rounded-full border border-[#243247]">
                        Chat started &bull; <?= htmlspecialchars($session['created_at']) ?>
                    </span>
                </div>
                <!-- Dynamic messages injected here -->
            </div>

            <!-- Quick Replies (for Admin) -->
            <?php if ($isAdmin): ?>
                <div class="px-4 py-2 bg-[#0B1626] border-t border-[#243247] flex items-center gap-2 overflow-x-auto text-xs whitespace-nowrap">
                    <span class="text-slate-500 font-mono text-[11px] uppercase tracking-wider">Quick:</span>
                    <button type="button" class="quick-reply px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition text-[11px]" data-text="Hey! Thanks for reaching out. How can I help?">Hey! How can I help?</button>
                    <button type="button" class="quick-reply px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition text-[11px]" data-text="Looking into that right now for you!">Looking into that now</button>
                    <button type="button" class="quick-reply px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition text-[11px]" data-text="All set! Fixed in the database.">All set & fixed!</button>
                    <button type="button" class="quick-reply px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition text-[11px]" data-text="Good luck this week!">Good luck this week!</button>
                </div>
            <?php endif; ?>

            <!-- Input Form -->
            <form id="chatForm" class="p-3 sm:p-4 bg-[#162235] border-t border-[#243247] flex items-center gap-2">
                <input
                    type="text"
                    id="chatInput"
                    placeholder="Type your reply to <?= $userName ?>..."
                    autocomplete="off"
                    class="flex-1 bg-[#0B1626] border border-[#243247] rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 transition"
                >
                <button
                    type="submit"
                    id="btnSend"
                    class="px-5 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-sm transition flex items-center gap-1.5 shadow-lg"
                >
                    <span>Send</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </form>
        </div>

        <!-- Sidebar: Player League Profile -->
        <div class="space-y-6">
            <div class="bg-[#162235] border border-[#243247] rounded-2xl p-5 shadow-xl">
                <h2 class="text-xs font-mono font-bold uppercase tracking-wider text-amber-400 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span>Player Snapshot</span>
                </h2>

                <div class="space-y-4 text-sm">
                    <div class="p-3 bg-[#0B1626] rounded-xl border border-[#243247]">
                        <div class="text-xs text-slate-400">Username</div>
                        <div class="font-bold text-white"><?= $userName ?></div>
                    </div>

                    <div class="p-3 bg-[#0B1626] rounded-xl border border-[#243247]">
                        <div class="text-xs text-slate-400">Pick'em Entry</div>
                        <div class="font-bold <?= !empty($playerStats['pickem']['has_entry']) ? 'text-emerald-400' : 'text-slate-500' ?>">
                            <?= !empty($playerStats['pickem']['has_entry']) ? 'Active Entry Locked In' : 'No Entry Yet' ?>
                        </div>
                        <?php if (!empty($playerStats['pickem']['predicted_mnf'])): ?>
                            <div class="text-xs text-slate-400 mt-1">
                                MNF Tiebreaker: <strong class="text-amber-300"><?= $playerStats['pickem']['predicted_mnf'] ?> pts</strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-3 bg-[#0B1626] rounded-xl border border-[#243247]">
                        <div class="text-xs text-slate-400">Survivor Pick</div>
                        <?php if (!empty($playerStats['survivor']['is_eliminated'])): ?>
                            <div class="font-bold text-rose-400">Eliminated (Week <?= $playerStats['survivor']['elimination_week'] ?? '?' ?>)</div>
                        <?php elseif (!empty($playerStats['survivor']['pick'])): ?>
                            <div class="font-bold text-emerald-400"><?= htmlspecialchars($playerStats['survivor']['pick']) ?></div>
                        <?php else: ?>
                            <div class="font-bold text-slate-500">No Pick Yet</div>
                        <?php endif; ?>
                    </div>

                    <div class="p-3 bg-[#0B1626] rounded-xl border border-[#243247]">
                        <div class="text-xs text-slate-400">Direct Contact</div>
                        <a href="mailto:<?= $userEmail ?>" class="text-xs text-sky-400 hover:underline break-all"><?= $userEmail ?></a>
                    </div>
                </div>
            </div>

            <!-- League Shortcuts -->
            <div class="bg-[#162235] border border-[#243247] rounded-2xl p-5 shadow-xl space-y-2.5">
                <h3 class="text-xs font-mono font-bold uppercase tracking-wider text-slate-400 mb-2">Quick Navigation</h3>
                <a href="/pickem/standings" target="_blank" class="block px-3.5 py-2.5 bg-[#0B1626] hover:bg-slate-800 rounded-xl text-xs font-bold text-slate-200 border border-[#243247] transition">
                    🏆 View Live Standings &rarr;
                </a>
                <a href="/survivor/standings" target="_blank" class="block px-3.5 py-2.5 bg-[#0B1626] hover:bg-slate-800 rounded-xl text-xs font-bold text-slate-200 border border-[#243247] transition">
                    🛡️ Survivor Board &rarr;
                </a>
                <?php if ($isAdmin): ?>
                    <a href="/admin/payments" target="_blank" class="block px-3.5 py-2.5 bg-[#0B1626] hover:bg-slate-800 rounded-xl text-xs font-bold text-purple-300 border border-purple-500/30 transition">
                        ⚡ Commissioner Command &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const sessionId = <?= json_encode($sessionId) ?>;
    const token = <?= json_encode($activeToken) ?>;
    const isAdmin = <?= json_encode($isAdmin) ?>;

    const messagesContainer = document.getElementById('messagesContainer');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const otherDot = document.getElementById('otherDot');
    const otherText = document.getElementById('otherText');
    const chatStatusPill = document.getElementById('chatStatusPill');
    const btnEndChat = document.getElementById('btnEndChat');

    let renderedIds = new Set();

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function renderMessage(msg) {
        if (renderedIds.has(msg.id)) return;
        renderedIds.add(msg.id);

        const isSystem = msg.sender === 'system';
        const isSelf = (isAdmin && msg.sender === 'admin') || (!isAdmin && msg.sender === 'user');

        const wrapper = document.createElement('div');
        wrapper.className = isSystem ? 'text-center my-2' : (isSelf ? 'flex justify-end' : 'flex justify-start');

        if (isSystem) {
            wrapper.innerHTML = `
                <span class="inline-block text-[11px] font-mono text-slate-400 bg-[#0B1626] px-3 py-1 rounded-full border border-[#243247]">
                    ${escapeHtml(msg.message)}
                </span>
            `;
        } else {
            const bubbleBg = isSelf 
                ? (isAdmin ? 'bg-amber-500 text-slate-950 font-medium' : 'bg-emerald-600 text-white font-medium')
                : 'bg-[#1e2d42] text-slate-100 border border-[#2d405a]';
            const timeColor = isSelf ? (isAdmin ? 'text-slate-800' : 'text-emerald-100') : 'text-slate-400';
            const senderLabel = isSelf ? 'You' : (msg.sender_name || (msg.sender === 'admin' ? 'Wally Atkins' : 'Player'));

            wrapper.innerHTML = `
                <div class="max-w-[75%] space-y-1">
                    <div class="text-[10px] font-mono text-slate-400 px-1 ${isSelf ? 'text-right' : 'text-left'}">
                        ${escapeHtml(senderLabel)} &bull; ${escapeHtml(msg.time_formatted || '')}
                    </div>
                    <div class="p-3.5 rounded-2xl ${bubbleBg} text-sm leading-relaxed shadow-md whitespace-pre-wrap break-words">
                        ${escapeHtml(msg.message)}
                    </div>
                </div>
            `;
        }

        messagesContainer.appendChild(wrapper);
        scrollToBottom();
    }

    function escapeHtml(str) {
        if (!str) return '';
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
    }

    async function poll() {
        try {
            const res = await fetch(`/api/chat/poll?session_id=${encodeURIComponent(sessionId)}&token=${encodeURIComponent(token)}`);
            if (!res.ok) {
                if (res.status === 404) {
                    otherDot.className = 'w-2 h-2 rounded-full bg-rose-500 inline-block';
                    otherText.textContent = 'Session ended';
                    chatStatusPill.className = 'px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-rose-500/20 text-rose-400 border border-rose-500/40';
                    chatStatusPill.textContent = 'Ended';
                    return;
                }
                return;
            }
            const data = await res.json();
            if (data.status === 'success') {
                // Update session status pill
                chatStatusPill.textContent = data.session_status ? data.session_status.charAt(0).toUpperCase() + data.session_status.slice(1) : 'Active';
                if (data.session_status === 'active') {
                    chatStatusPill.className = 'px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40';
                }

                // Update online status
                if (data.other_online) {
                    otherDot.className = 'w-2 h-2 rounded-full bg-emerald-400 inline-block animate-pulse';
                    otherText.textContent = (isAdmin ? data.user_name : 'Wally') + ' is online';
                } else {
                    otherDot.className = 'w-2 h-2 rounded-full bg-slate-500 inline-block';
                    otherText.textContent = (isAdmin ? data.user_name : 'Wally') + ' away';
                }

                // Render messages
                if (Array.isArray(data.messages)) {
                    data.messages.forEach(renderMessage);
                }
            }
        } catch (e) {
            console.error('Chat poll error', e);
        }
    }

    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text) return;

        chatInput.value = '';
        try {
            const formData = new FormData();
            formData.append('session_id', sessionId);
            formData.append('token', token);
            formData.append('message', text);

            const res = await fetch('/api/chat/send', {
                method: 'POST',
                body: formData
            });
            if (res.ok) {
                poll();
            }
        } catch (e) {
            console.error('Send error', e);
        }
    });

    // Quick replies for Admin
    document.querySelectorAll('.quick-reply').forEach(btn => {
        btn.addEventListener('click', function() {
            const text = this.getAttribute('data-text');
            if (text) {
                chatInput.value = text;
                chatInput.focus();
            }
        });
    });

    btnEndChat.addEventListener('click', async function() {
        if (confirm('Are you sure you want to conclude this chat session?')) {
            try {
                const formData = new FormData();
                formData.append('session_id', sessionId);
                formData.append('token', token);
                await fetch('/api/chat/end', { method: 'POST', body: formData });
                window.location.reload();
            } catch (e) {
                console.error('End session error', e);
            }
        }
    });

    // Initial poll and recurring timer
    poll();
    const interval = setInterval(poll, 2000);
    window.addEventListener('beforeunload', () => clearInterval(interval));
})();
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
