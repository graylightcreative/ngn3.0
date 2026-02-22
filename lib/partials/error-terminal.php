<?php
/**
 * Sovereign Error Terminal Partial - NGN 3.0
 * Provides a real-time HUD for system-wide prioritized alerting.
 */

use NGN\Lib\Services\Reporting\ErrorReportingService;

$errorSvc = new ErrorReportingService($config);
$alerts = $errorSvc->getActiveAlerts(20);
?>

<div class="space-y-8">
    <!-- Terminal Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-black text-white italic uppercase tracking-tighter">Error_Terminal</h2>
            <p class="text-zinc-500 font-mono text-[10px] uppercase tracking-widest mt-1">Real-time System Integrity Monitoring</p>
        </div>
        <div class="px-4 py-2 glass-panel rounded-full border-brand/20 flex items-center gap-3">
            <span class="w-2 h-2 bg-brand rounded-full animate-pulse shadow-[0_0_10px_var(--primary)]"></span>
            <span class="text-[10px] font-black text-brand uppercase tracking-widest">Live Feed Active</span>
        </div>
    </div>

    <!-- Alert List -->
    <div class="glass-panel rounded-[2rem] border-white/5 overflow-hidden shadow-2xl">
        <?php if (empty($alerts)): ?>
            <div class="p-20 text-center">
                <i class="bi bi-shield-check text-6xl text-emerald-500/20 mb-6 block"></i>
                <h3 class="text-xl font-black text-white uppercase italic">All Systems Nominal</h3>
                <p class="text-zinc-500 font-mono text-xs mt-2">Zero active high-priority alerts detected.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left font-mono text-xs">
                    <thead>
                        <tr class="border-b border-white/5 bg-white/5">
                            <th class="p-6 font-black uppercase text-zinc-500 tracking-widest">Severity</th>
                            <th class="p-6 font-black uppercase text-zinc-500 tracking-widest">Message</th>
                            <th class="p-6 font-black uppercase text-zinc-500 tracking-widest text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($alerts as $alert): 
                            $sevColor = match($alert['severity']) {
                                'CRITICAL' => 'text-rose-500',
                                'ERROR' => 'text-brand',
                                'WARNING' => 'text-amber-500',
                                default => 'text-blue-500'
                            };
                            $sevBg = match($alert['severity']) {
                                'CRITICAL' => 'bg-rose-500/10 border-rose-500/20',
                                'ERROR' => 'bg-brand/10 border-brand/20',
                                'WARNING' => 'bg-amber-500/10 border-amber-500/20',
                                default => 'bg-blue-500/10 border-blue-500/20'
                            };
                        ?>
                        <tr class="hover:bg-white/5 transition-colors group cursor-pointer">
                            <td class="p-6">
                                <span class="px-3 py-1 rounded-full border <?= $sevBg ?> <?= $sevColor ?> font-black text-[9px] tracking-widest uppercase">
                                    <?= htmlspecialchars($alert['severity']) ?>
                                </span>
                            </td>
                            <td class="p-6">
                                <div class="text-white font-bold group-hover:text-brand transition-colors"><?= htmlspecialchars($alert['message']) ?></div>
                                <div class="text-[9px] text-zinc-600 mt-1 uppercase tracking-wider">Payload Captured</div>
                            </td>
                            <td class="p-6 text-right text-zinc-500 font-bold whitespace-nowrap">
                                <?= date('H:i:s // M j', strtotime($alert['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
