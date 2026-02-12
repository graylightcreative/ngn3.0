<?php
/**
 * Dispute Modal Partial
 * 
 * Included globally to handle profile ownership disputes.
 */
?>
<div id="dispute-modal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
    <div class="bg-zinc-900 border border-white/10 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
        <!-- Header -->
        <div class="p-8 border-b border-white/5 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-black text-white uppercase tracking-tight">Dispute Profile Claim</h3>
                <p class="text-xs text-zinc-500 font-bold uppercase tracking-widest mt-1">Formal Ownership Challenge</p>
            </div>
            <button onclick="closeDisputeModal()" class="text-zinc-500 hover:text-white transition-colors"><i class="bi bi-x-lg text-2xl"></i></button>
        </div>

        <!-- Form -->
        <form id="dispute-form" onsubmit="submitDispute(event)" class="p-8 space-y-6">
            <input type="hidden" id="dispute-entity-type" name="entity_type">
            <input type="hidden" id="dispute-entity-id" name="entity_id">

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1">Your Name</label>
                    <input type="text" name="disputant_name" required class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-brand outline-none transition-all">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1">Contact Email</label>
                    <input type="email" name="disputant_email" required class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-brand outline-none transition-all">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1">Your Relationship to Entity</label>
                <select name="relationship" required class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-brand outline-none transition-all appearance-none">
                    <option value="">Select Relationship...</option>
                    <option value="owner">Legal Owner</option>
                    <option value="manager">Professional Manager</option>
                    <option value="member">Artist / Member</option>
                    <option value="agent">Authorized Agent</option>
                </select>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black text-zinc-500 uppercase tracking-widest px-1">Reason for Dispute</label>
                <textarea name="reason" required rows="4" placeholder="Explain why you are challenging the current ownership..." class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-brand outline-none transition-all resize-none"></textarea>
            </div>

            <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-xl">
                <p class="text-[10px] text-red-400 font-bold leading-relaxed">
                    <i class="bi bi-exclamation-triangle-fill mr-1"></i>
                    NOTICE: False or malicious disputes may result in permanent account suspension. All challenges are manually reviewed by NextGenNoise compliance.
                </p>
            </div>

            <button type="submit" class="w-full py-4 bg-brand text-black font-black uppercase tracking-widest text-sm rounded-full hover:scale-[1.02] active:scale-95 transition-all shadow-xl shadow-brand/20">
                Submit Challenge
            </button>
        </form>

        <!-- Success State -->
        <div id="dispute-success" class="hidden p-12 text-center space-y-6">
            <div class="w-20 h-20 bg-brand/20 text-brand rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-check-lg text-5xl"></i>
            </div>
            <h3 class="text-2xl font-black text-white">Dispute Filed</h3>
            <p class="text-zinc-400 text-sm">Your challenge has been recorded and anchored to the Graylight Vault. Our compliance team will review your evidence within 48 hours.</p>
            <button onclick="closeDisputeModal()" class="px-8 py-3 bg-white/5 hover:bg-white/10 text-white font-black uppercase tracking-widest text-xs rounded-full border border-white/10 transition-all">Close Window</button>
        </div>
    </div>
</div>

<script>
function openDisputeModal(type, id) {
    document.getElementById('dispute-entity-type').value = type;
    document.getElementById('dispute-entity-id').value = id;
    document.getElementById('dispute-modal').classList.remove('hidden');
    document.getElementById('dispute-modal').classList.add('flex');
}

function closeDisputeModal() {
    document.getElementById('dispute-modal').classList.add('hidden');
    document.getElementById('dispute-modal').classList.remove('flex');
    document.getElementById('dispute-form').classList.remove('hidden');
    document.getElementById('dispute-success').classList.add('hidden');
    document.getElementById('dispute-form').reset();
}

async function submitDispute(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerText;
    
    submitBtn.disabled = true;
    submitBtn.innerText = 'Anchoring...';

    const formData = new FormData(form);
    const data = Object.from_entries(formData.entries());

    try {
        const response = await fetch('/api/v1/disputes/file', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            form.classList.add('hidden');
            document.getElementById('dispute-success').classList.remove('hidden');
        } else {
            alert('Error: ' + result.message);
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    } catch (error) {
        alert('A network error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerText = originalText;
    }
}
</script>
