<?php
/** @var array $sessions */
/** @var array $feedback */

ob_start();

$activeSessionsCount = 0;
foreach ($sessions as $s) {
    if ($s['status'] === 'active' || $s['status'] === 'waiting') {
        $activeSessionsCount++;
    }
}
?>

<div class="space-y-8 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-purple-500/15 text-purple-300 border border-purple-500/30 uppercase tracking-wider">Commissioner Portal</span>
                <span class="text-xs text-slate-400">Live Support & Feedback</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                <span>Wally's Chat & Feedback Desk</span>
                <?php if ($activeSessionsCount > 0): ?>
                    <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 animate-pulse">
                        <?= $activeSessionsCount ?> Active
                    </span>
                <?php endif; ?>
            </h1>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="/admin/payments" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-900 border border-slate-700 text-slate-200 hover:bg-slate-800 transition">
                &larr; Commissioner Portal
            </a>
            <button onclick="window.location.reload()" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 transition flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-[#162235] border border-[#243247] p-4 rounded-xl">
            <div class="text-xs text-slate-400 font-mono uppercase tracking-wider">Active Chats</div>
            <div class="text-2xl font-black <?= $activeSessionsCount > 0 ? 'text-emerald-400' : 'text-white' ?> mt-1">
                <?= $activeSessionsCount ?>
            </div>
        </div>
        <div class="bg-[#162235] border border-[#243247] p-4 rounded-xl">
            <div class="text-xs text-slate-400 font-mono uppercase tracking-wider">Total Chat Sessions</div>
            <div class="text-2xl font-black text-white mt-1"><?= count($sessions) ?></div>
        </div>
        <div class="bg-[#162235] border border-[#243247] p-4 rounded-xl">
            <div class="text-xs text-slate-400 font-mono uppercase tracking-wider">Total Feedback</div>
            <div class="text-2xl font-black text-amber-400 mt-1"><?= count($feedback) ?></div>
        </div>
        <div class="bg-[#162235] border border-[#243247] p-4 rounded-xl">
            <div class="text-xs text-slate-400 font-mono uppercase tracking-wider">Channel Status</div>
            <div class="text-sm font-bold text-emerald-400 mt-2 flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                <span>Live & Listening</span>
            </div>
        </div>
    </div>

    <!-- Two Columns: Chat Sessions (Left) and Feedback Submissions (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Live & Recent Chats -->
        <div class="bg-[#162235] border border-[#243247] rounded-2xl p-5 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-[#243247] pb-3">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>💬 Live Chat Sessions</span>
                    <span class="text-xs font-mono text-slate-400">(<?= count($sessions) ?>)</span>
                </h2>
            </div>

            <?php if (empty($sessions)): ?>
                <div class="text-center py-12 text-slate-500 text-sm">
                    No chat sessions initiated yet.
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-[650px] overflow-y-auto pr-1">
                    <?php foreach ($sessions as $s): ?>
                        <?php
                            $status = $s['status'];
                            $pillClass = match ($status) {
                                'active' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40',
                                'waiting' => 'bg-amber-500/20 text-amber-400 border-amber-500/40 animate-pulse',
                                default => 'bg-slate-800 text-slate-400 border-slate-700',
                            };
                        ?>
                        <div class="p-4 rounded-xl bg-[#0B1626] border border-[#243247] hover:border-slate-600 transition flex flex-col justify-between gap-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-white text-sm"><?= htmlspecialchars($s['user_name']) ?></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold border <?= $pillClass ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        <?= htmlspecialchars($s['user_email']) ?>
                                    </div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-500 whitespace-nowrap">
                                    <?= date('M j, g:i A', strtotime($s['created_at'])) ?>
                                </span>
                            </div>

                            <?php if (!empty($s['last_message'])): ?>
                                <div class="text-xs text-slate-300 bg-[#162235] p-2.5 rounded-lg border border-[#243247] line-clamp-2">
                                    &ldquo;<?= htmlspecialchars($s['last_message']) ?>&rdquo;
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center justify-between pt-1 border-t border-[#243247]/60">
                                <span class="text-[11px] text-slate-400 font-mono">
                                    <?= (int) ($s['message_count'] ?? 0) ?> messages
                                </span>
                                <a href="/chat?session_id=<?= urlencode($s['id']) ?>" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                                    Enter Chat Room &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Feedback Submissions -->
        <div class="bg-[#162235] border border-[#243247] rounded-2xl p-5 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-[#243247] pb-3">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span>📝 Feedback & Ideas</span>
                    <span class="text-xs font-mono text-slate-400">(<?= count($feedback) ?>)</span>
                </h2>
            </div>

            <?php if (empty($feedback)): ?>
                <div class="text-center py-12 text-slate-500 text-sm">
                    No feedback messages submitted yet.
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-[650px] overflow-y-auto pr-1">
                    <?php foreach ($feedback as $f): ?>
                        <?php
                            $cat = $f['category'];
                            $catBadge = match ($cat) {
                                'bug' => 'bg-rose-500/20 text-rose-300 border-rose-500/40',
                                'idea' => 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                                'scoring' => 'bg-purple-500/20 text-purple-300 border-purple-500/40',
                                default => 'bg-blue-500/20 text-blue-300 border-blue-500/40',
                            };
                            $catLabel = match ($cat) {
                                'bug' => '🐞 Bug',
                                'idea' => '💡 Idea',
                                'scoring' => '🏈 Scoring',
                                default => '💬 General',
                            };
                        ?>
                        <div class="p-4 rounded-xl bg-[#0B1626] border border-[#243247] space-y-2.5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-white text-sm"><?= htmlspecialchars($f['username']) ?></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold border <?= $catBadge ?>">
                                            <?= $catLabel ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        <a href="mailto:<?= htmlspecialchars($f['email']) ?>" class="hover:text-sky-400 transition underline"><?= htmlspecialchars($f['email']) ?></a>
                                    </div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-500 whitespace-nowrap">
                                    <?= date('M j, g:i A', strtotime($f['created_at'])) ?>
                                </span>
                            </div>

                            <div class="text-sm text-slate-200 bg-[#162235] p-3 rounded-xl border border-[#243247] whitespace-pre-wrap leading-relaxed">
                                <?= htmlspecialchars($f['message']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
