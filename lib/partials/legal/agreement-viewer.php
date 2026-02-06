<?php
/**
 * Agreement Viewer Partial
 * 
 * Securely presents an agreement and provides a signing interface.
 * 
 * Expected variables:
 * - $template: array (id, title, body, version, etc.)
 * - $userId: int
 * - $isSigned: bool (optional)
 * - $onSignRedirect: string (optional)
 */

if (empty($template)) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded'>Agreement template missing.</div>";
    return;
}

$isSigned = $isSigned ?? false;
$onSignRedirect = $onSignRedirect ?? $_SERVER['REQUEST_URI'];
?>

<div class="agreement-viewer bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl overflow-hidden max-w-4xl mx-auto my-8">
    <!-- Header -->
    <div class="px-8 py-6 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-black tracking-tight text-zinc-900 dark:text-white"><?= htmlspecialchars($template['title']) ?></h2>
            <p class="text-xs font-bold text-zinc-500 uppercase tracking-widest mt-1">Version: <?= htmlspecialchars($template['version']) ?></p>
        </div>
        <?php if ($isSigned): ?>
            <div class="flex items-center gap-2 px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 rounded-full text-xs font-black uppercase tracking-widest border border-emerald-200 dark:border-emerald-800">
                <i class="bi bi-check-circle-fill"></i>
                Signed
            </div>
        <?php endif; ?>
    </div>

    <!-- Content -->
    <div class="px-8 py-10 max-h-[500px] overflow-y-auto prose dark:prose-invert prose-zinc max-w-none">
        <?= $template['body'] ?>
    </div>

    <!-- Signature Footer -->
    <div class="px-8 py-8 bg-zinc-50 dark:bg-zinc-900/50 border-t border-zinc-100 dark:border-zinc-800">
        <?php if (!$isSigned): ?>
            <form id="agreement-sign-form" action="/api/v1/legal/sign-agreement.php" method="POST" class="space-y-6">
                <input type="hidden" name="template_slug" value="<?= htmlspecialchars($template['slug']) ?>">
                <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($onSignRedirect) ?>">
                
                <div class="flex items-start gap-4">
                    <div class="pt-1">
                        <input type="checkbox" id="confirm_agreement" name="confirm_agreement" required 
                               class="w-5 h-5 rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand dark:bg-zinc-800 transition-all cursor-pointer">
                    </div>
                    <label for="confirm_agreement" class="text-sm font-bold text-zinc-600 dark:text-zinc-400 leading-relaxed cursor-pointer select-none">
                        I have read and understood the terms of the <span class="text-zinc-900 dark:text-white"><?= htmlspecialchars($template['title']) ?></span>. 
                        By clicking "Sign & Accept", I agree that this constitutes a binding digital signature and I accept all terms and conditions outlined above.
                    </label>
                </div>

                <div class="flex items-center justify-between gap-6 pt-2">
                    <div class="text-xs text-zinc-500 font-bold uppercase tracking-widest">
                        Digital Signature ID: <span class="text-zinc-400 font-mono"><?= strtoupper(substr(md5(uniqid()), 0, 12)) ?></span>
                    </div>
                    <button type="submit" id="sign-btn" disabled
                            class="px-10 py-4 bg-brand hover:bg-brand-dark text-black font-black uppercase tracking-[0.2em] text-sm rounded-full transition-all shadow-lg shadow-brand/20 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed">
                        Sign & Accept
                    </button>
                </div>
            </form>

            <script>
                document.getElementById('confirm_agreement').addEventListener('change', function(e) {
                    document.getElementById('sign-btn').disabled = !e.target.checked;
                });

                document.getElementById('agreement-sign-form').addEventListener('submit', function() {
                    const btn = document.getElementById('sign-btn');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin mr-2"></i> Signing...';
                });
            </script>
        <?php else: ?>
            <div class="flex flex-col items-center text-center py-4">
                <i class="bi bi-shield-check text-4xl text-emerald-500 mb-4"></i>
                <p class="text-zinc-600 dark:text-zinc-400 font-bold">You have already signed this agreement.</p>
                <p class="text-xs text-zinc-500 mt-2">A record of your signature is stored in our secure audit log.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
