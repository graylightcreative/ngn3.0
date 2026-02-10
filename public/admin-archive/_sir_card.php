<?php
/**
 * SIR Card Partial Template
 *
 * Reusable component for displaying SIR cards in the governance board.
 * Expected variable: $sir (array with SIR data)
 */

$priorityColors = [
    'low' => 'bg-gray-700 text-gray-300 border-gray-600',
    'normal' => 'bg-blue-900/30 text-blue-400 border-blue-700',
    'high' => 'bg-yellow-900/30 text-yellow-400 border-yellow-700',
    'critical' => 'bg-red-900/30 text-red-400 border-red-700'
];

$priorityColor = $priorityColors[$sir['priority']] ?? $priorityColors['normal'];
?>

<div class="bg-gray-900/50 border border-gray-700 rounded-lg p-3 hover:border-gray-600 transition cursor-pointer"
     onclick="toggleSirDetails(<?= $sir['id'] ?>)">
    <div class="flex items-start justify-between mb-2">
        <div class="flex-1">
            <div class="font-medium text-white text-sm mb-1"><?= htmlspecialchars($sir['title']) ?></div>
            <div class="flex items-center space-x-2 text-xs text-gray-400">
                <span class="inline-block px-2 py-0.5 rounded border text-xs font-medium <?= $priorityColor ?>">
                    <?= strtoupper($sir['priority']) ?>
                </span>
                <span><?= ucfirst($sir['category']) ?></span>
            </div>
        </div>
        <div id="sir-arrow-<?= $sir['id'] ?>" class="text-gray-500 transition-transform" style="transition: transform 0.2s;">
            ▶
        </div>
    </div>

    <div id="sir-details-<?= $sir['id'] ?>" class="hidden mt-3 pt-3 border-t border-gray-700">
        <div class="text-xs text-gray-400 mb-3 whitespace-pre-wrap"><?= htmlspecialchars($sir['description']) ?></div>

        <div class="text-xs text-gray-500 space-y-1 mb-3">
            <div>Submitted: <?= date('M j, Y', strtotime($sir['submitted_at'])) ?></div>
            <div>By: <?= htmlspecialchars($sir['submitted_by_name'] ?? 'Unknown') ?></div>
            <div>Updated: <?= date('M j, Y g:ia', strtotime($sir['updated_at'])) ?></div>
        </div>

        <!-- Status Change Form -->
        <form method="POST" class="space-y-2" onclick="event.stopPropagation()">
            <input type="hidden" name="action" value="update_sir_status">
            <input type="hidden" name="sir_id" value="<?= $sir['id'] ?>">

            <select name="status" required
                    class="w-full px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:outline-none focus:border-blue-500">
                <option value="">-- Move to Status --</option>
                <option value="open" <?= $sir['status'] === 'open' ? 'disabled' : '' ?>>OPEN</option>
                <option value="in_review" <?= $sir['status'] === 'in_review' ? 'disabled' : '' ?>>IN REVIEW</option>
                <option value="rant_phase" <?= $sir['status'] === 'rant_phase' ? 'disabled' : '' ?>>RANT PHASE</option>
                <option value="verified" <?= $sir['status'] === 'verified' ? 'disabled' : '' ?>>VERIFIED</option>
                <option value="closed" <?= $sir['status'] === 'closed' ? 'disabled' : '' ?>>CLOSED</option>
            </select>

            <textarea name="notes" rows="2" placeholder="Notes about this status change..."
                      class="w-full px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-xs focus:outline-none focus:border-blue-500"></textarea>

            <button type="submit" class="w-full px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs rounded transition">
                Update Status
            </button>
        </form>
    </div>
</div>
