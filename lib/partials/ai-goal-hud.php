<?php
/**
 * Sovereign Activation HUD v2.4
 * Displays the "Road to Activation" progress for all four pillars.
 */
$policy = new \NGN\Lib\AI\SovereignAIPolicy($config);
$traffic = new \NGN\Lib\Http\SovereignTrafficPolicy($config);
$expansion = new \NGN\Lib\Sovereign\SovereignExpansionPolicy($config);
$ops = new \NGN\Lib\Sovereign\SovereignOperationsPolicy($config);

$aiGoal = $policy->getActivationGoal();
$lbGoal = $traffic->getLBGoal();
$exGoal = $expansion->getExpansionGoal();
$opGoal = $ops->getOperationsGoal();

$currentAI = $policy->getCurrentProgress();
$currentLB = $traffic->getCurrentProgress();
$currentEX = $expansion->getCurrentProgress();
$currentOP = $ops->getCurrentProgress();
$contributors = $policy->getContributorCount();

$aiPercent = $policy->getProgressPercentage();
$lbPercent = min(100, ($currentLB / $lbGoal) * 100);
$exPercent = min(100, ($currentEX / $exGoal) * 100);
$opPercent = min(100, ($currentOP / $opGoal) * 100);

$aiEnabled = $policy->isAIEnabled();
$lbEnabled = $traffic->isBadassEnabled();
?>

<section class="mb-16">
    <div class="glass-panel p-8 md:p-12 rounded-[3rem] border <?= $aiEnabled ? 'border-emerald-500/30' : 'border-brand/30' ?> relative overflow-hidden shadow-[0_20px_100px_rgba(0,0,0,0.8)]">
        <!-- Background Signal -->
        <div class="absolute inset-0 bg-gradient-to-br <?= $aiEnabled ? 'from-emerald-500/5' : 'from-brand/5' ?> to-transparent pointer-events-none"></div>
        
        <div class="relative z-10">
            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-12">
                <div class="lg:w-1/2">
                    <div class="flex items-center gap-6 mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 <?= $aiEnabled ? 'bg-emerald-500' : 'bg-brand' ?> rounded-full <?= !$aiEnabled ? 'animate-pulse' : '' ?>"></div>
                            <h4 class="text-[10px] font-black <?= $aiEnabled ? 'text-emerald-500' : 'text-brand' ?> uppercase tracking-[0.4em] font-mono">Sovereign_Alliance_Policy_v2.4</h4>
                        </div>
                        <div class="h-4 w-px bg-white/10"></div>
                        <div class="text-[10px] font-black text-white/40 uppercase tracking-widest font-mono">
                            Vanguard_Alliance: <span class="text-white"><?= number_format($contributors) ?></span>
                        </div>
                    </div>
                    
                    <h2 class="text-4xl md:text-6xl font-black text-white uppercase italic tracking-tighter mb-6 leading-none">
                        Road_To_Activation
                    </h2>
                    
                    <p class="text-zinc-400 font-medium text-sm md:text-base leading-relaxed mb-8 max-w-xl">
                        NextGenNoise is human-powered until our collective infrastructure is fully funded. Join the <span class="text-white font-bold">NGN Vanguard</span> to unlock NIKO, Badass Load Balancing, Global Foundry, and <span class="text-white">Sovereign Payroll</span>. <span class="text-brand">Donations and Ad Signal Fuel</span> directly empower the Sovereign Alliance.
                    </p>
                    
                    <div class="flex flex-wrap gap-4">
                        <a href="/activation" style="background-color: var(--primary);" class="px-8 py-4 text-black font-black uppercase tracking-widest text-[11px] rounded-full hover:bg-white transition-all shadow-[0_10px_30px_rgba(255,95,31,0.3)] hover:scale-105 active:scale-95">AMPLIFY_THE_SIGNAL</a>
                        <div class="px-8 py-4 bg-zinc-900 border border-white/5 text-white/60 font-black uppercase tracking-widest text-[10px] rounded-full">
                            Status: <?= ($aiEnabled && $lbEnabled) ? 'FULLY ARMED' : 'RESTRICTED SIGNAL' ?>
                        </div>
                    </div>
                </div>

                <div class="lg:w-1/3 space-y-8">
                    <!-- AI Activation Goal -->
                    <div>
                        <div class="mb-2 flex justify-between items-end">
                            <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest block">01 AI_Activation</span>
                            <span class="text-lg font-black text-white font-mono"><?= number_format($aiPercent, 1) ?>%</span>
                        </div>
                        <div class="h-1.5 bg-white/5 rounded-full overflow-hidden p-0.5 border border-white/5">
                            <div class="h-full <?= $aiEnabled ? 'bg-emerald-500' : 'bg-brand' ?> rounded-full" style="width: <?= $aiPercent ?>%"></div>
                        </div>
                    </div>

                    <!-- Load Balancing Goal -->
                    <div>
                        <div class="mb-2 flex justify-between items-end">
                            <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest block">02 Signal_LB</span>
                            <span class="text-lg font-black text-white font-mono"><?= number_format($lbPercent, 1) ?>%</span>
                        </div>
                        <div class="h-1.5 bg-white/5 rounded-full overflow-hidden p-0.5 border border-white/5">
                            <div class="h-full <?= $lbEnabled ? 'bg-emerald-500' : 'bg-indigo-500' ?> rounded-full" style="width: <?= $lbPercent ?>%"></div>
                        </div>
                    </div>

                    <!-- Foundry Expansion Goal -->
                    <div>
                        <div class="mb-2 flex justify-between items-end">
                            <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest block">03 Foundry_Ex</span>
                            <span class="text-lg font-black text-white font-mono"><?= number_format($exPercent, 1) ?>%</span>
                        </div>
                        <div class="h-1.5 bg-white/5 rounded-full overflow-hidden p-0.5 border border-white/5">
                            <div class="h-full bg-emerald-600 rounded-full" style="width: <?= $exPercent ?>%"></div>
                        </div>
                    </div>

                    <!-- Operations & Payroll -->
                    <div>
                        <div class="mb-2 flex justify-between items-end">
                            <span class="text-[9px] font-black text-zinc-500 uppercase tracking-widest block">04 Sovereign_OP</span>
                            <span class="text-lg font-black text-white font-mono"><?= number_format($opPercent, 1) ?>%</span>
                        </div>
                        <div class="h-1.5 bg-white/5 rounded-full overflow-hidden p-0.5 border border-white/5">
                            <div class="h-full bg-amber-500 rounded-full" style="width: <?= $opPercent ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
