<?php

/**
 * admin/templates/artist/qa-validation-widget.php
 * Widget for QA validation of artist status.
 * Assumes $artistData is passed, containing:
 *  - artist: { Id, Name }
 *  - is_claimed: Boolean indicating if the profile is claimed.
 *  - label_association_status: String indicating label association (e.g., 'associated', 'not_associated').
 *  - score: The artist's current NGN score.
 */

// Placeholder data if $artistData is not provided externally
if (!isset($artistData)) {
    $artistData = [
        'artist' => ['Id' => 1, 'Name' => 'Test Artist'],
        'is_claimed' => true,
        'label_association_status' => 'associated',
        'score' => 1250,
    ];
}

$artistName = $artistData['artist']['Name'] ?? 'Unknown Artist';
$isClaimed = $artistData['is_claimed'] ?? false;
$labelAssociation = $artistData['label_association_status'] ?? 'not_associated';
$currentScore = $artistData['score'] ?? '--';

$claimStatusClass = $isClaimed ? 'text-emerald-500' : 'text-rose-500';
$associationStatusClass = ($labelAssociation === 'associated') ? 'text-emerald-500' : 'text-rose-500';

?>

<div class="admin-widget">
    <div class="widget-header">
        <h3 class="widget-title">QA Validation</h3>
    </div>
    <div class="widget-body p-4">
        <div class="space-y-3">
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-500 dark:text-gray-400">Profile Claimed</span>
                <span class="font-medium <?= $claimStatusClass ?>">
                    <?= $isClaimed ? 'Claimed' : 'Unclaimed' ?>
                </span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-500 dark:text-gray-400">Label Association</span>
                <span class="font-medium <?= $associationStatusClass ?>">
                    <?= ucfirst($labelAssociation) ?>
                </span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-gray-200 dark:border-white/10">
                <span class="text-sm text-gray-500 dark:text-gray-400">Current NGN Score</span>
                <span class="font-bold text-lg sk-text-primary"><?= htmlspecialchars($currentScore) ?></span>
            </div>
        </div>
    </div>
</div>
