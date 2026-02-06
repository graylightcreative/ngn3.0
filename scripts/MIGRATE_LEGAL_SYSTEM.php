<?php
/**
 * Migration: Setup Legal Agreements
 * 
 * Seeds initial agreement templates.
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Services\Legal\AgreementService;

$config = new Config();
$db = ConnectionFactory::write($config);
$service = new AgreementService($db);

// 1. Artist Onboarding Agreement
$artistAgreement = "
<h3>NGN Artist Distribution & Performance Agreement</h3>
<p>This agreement ('Agreement') is made between NextGen Noise LLC ('NGN') and the undersigned Artist ('Artist').</p>

<h4>1. Rights & Distribution</h4>
<p>Artist grants NGN a non-exclusive right to stream, promote, and distribute Artist's music through the NGN platform. Artist retains 100% ownership of their master recordings and publishing.</p>

<h4>2. Revenue Splits</h4>
<p>NGN agrees to distribute 95% of 'Spark' tips and platform revenue pool allocations directly to Artist, after third-party processing fees (e.g., Stripe).</p>

<h4>3. Meritocratic Ranking</h4>
<p>Artist acknowledges that NGN's 'Heat Score' and 'EQS' are proprietary algorithms based on organic engagement signals. NGN reserves the right to audit and remove bot-driven or fraudulent signals.</p>

<h4>4. Termination</h4>
<p>This Agreement is 'At Will'. Either party may terminate this agreement at any time by removing content or closing the account.</p>
";

$service->upsertTemplate(
    'artist-onboarding',
    'Artist Onboarding Agreement',
    $artistAgreement,
    '1.0.0'
);

// 2. Terms of Service (Site-wide)
$tosAgreement = "
<h3>NextGen Noise Terms of Service</h3>
<p>By using NextGen Noise, you agree to the following terms...</p>
<p>(Simplified for Beta Implementation)</p>
<ul>
    <li>Be organic. No bots.</li>
    <li>Respect IP. Only upload what you own.</li>
    <li>Support the scene.</li>
</ul>
";

$service->upsertTemplate(
    'terms-of-service',
    'NGN Terms of Service',
    $tosAgreement,
    '1.0.0'
);

echo "Legal system migration complete. Templates seeded.
";
