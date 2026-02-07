<?php
/**
 * Pricing & Commission Documentation Tab - Partial Template
 *
 * Comprehensive documentation for the pricing and commission management system.
 */
?>

<div class="max-w-5xl mx-auto space-y-8">
    <!-- Overview -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-2xl font-bold text-white mb-4">System Overview</h2>
        <p class="text-gray-300 mb-4">
            The Pricing & Commission Management system provides centralized control over merchandise pricing
            and platform fees to ensure NextGenNoise maintains its <strong class="text-green-400">60%+ net margin KPI</strong> (Bible Ch. 17, I.6).
        </p>
        <div class="bg-blue-900/20 border border-blue-700/50 rounded-lg p-4">
            <div class="font-semibold text-blue-400 mb-2">Critical KPI: 60%+ Net Margin</div>
            <p class="text-sm text-gray-300">
                All pricing must be configured to achieve at least 60% net margin after deducting product costs
                and platform commission. This ensures sustainable profitability and scalability.
            </p>
        </div>
    </div>

    <!-- Pricing Rules -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-2xl font-bold text-white mb-4">Pricing Rules</h2>

        <h3 class="text-lg font-semibold text-white mb-3">Rule Types</h3>
        <div class="space-y-3 mb-6">
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Global</div>
                <p class="text-sm text-gray-400">
                    Applies to all products across the platform. Lowest priority by default.
                    Use for platform-wide defaults.
                </p>
            </div>
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Product Category</div>
                <p class="text-sm text-gray-400">
                    Applies to specific product categories (e.g., "apparel", "vinyl", "posters").
                    Overrides global rules.
                </p>
            </div>
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Entity Type</div>
                <p class="text-sm text-gray-400">
                    Applies to all products from a specific entity type (Artist, Label, Venue, Station).
                    Useful for different margins per seller type.
                </p>
            </div>
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Product Specific</div>
                <p class="text-sm text-gray-400">
                    Applies to a single product. Highest priority. Use for special promotions or exceptions.
                </p>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-white mb-3">Pricing Strategies</h3>
        <div class="space-y-3">
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Margin % (Recommended)</div>
                <p class="text-sm text-gray-400 mb-2">
                    Sets target net margin percentage. Price calculated as: <code class="bg-gray-800 px-2 py-0.5 rounded">Cost / (1 - Margin% - Commission%)</code>
                </p>
                <p class="text-xs text-gray-500">
                    Example: $10 cost, 60% margin, 30% commission → $100 sale price
                </p>
            </div>
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Markup %</div>
                <p class="text-sm text-gray-400 mb-2">
                    Multiplies cost by markup factor. Price calculated as: <code class="bg-gray-800 px-2 py-0.5 rounded">Cost × (1 + Markup%/100)</code>
                </p>
                <p class="text-xs text-gray-500">
                    Example: $10 cost, 150% markup → $25 sale price (2.5x multiplier)
                </p>
            </div>
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Fixed Price</div>
                <p class="text-sm text-gray-400 mb-2">
                    Overrides calculation with fixed price in cents.
                </p>
                <p class="text-xs text-gray-500">
                    Example: 2999 cents → $29.99 sale price (regardless of cost)
                </p>
            </div>
        </div>
    </div>

    <!-- Commission Rules -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-2xl font-bold text-white mb-4">Commission Rules</h2>

        <p class="text-gray-300 mb-4">
            Commission rules determine the platform fee charged to sellers for each transaction.
            These fees fund platform operations and ensure sustainability.
        </p>

        <h3 class="text-lg font-semibold text-white mb-3">Commission Types</h3>
        <div class="space-y-3">
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Percentage of Sale</div>
                <p class="text-sm text-gray-400">
                    Most common. Fee = Sale Price × Commission%. Example: 30% of $100 = $30 fee.
                </p>
            </div>
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Fixed Per Item</div>
                <p class="text-sm text-gray-400">
                    Flat fee per item sold. Example: $5 fee per item, regardless of price.
                </p>
            </div>
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <div class="font-semibold text-white mb-1">Fixed Per Order</div>
                <p class="text-sm text-gray-400">
                    Flat fee per order. Example: $10 fee per order, regardless of number of items.
                </p>
            </div>
        </div>
    </div>

    <!-- Rule Priority & Conflicts -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-2xl font-bold text-white mb-4">Rule Priority & Conflict Resolution</h2>

        <p class="text-gray-300 mb-4">
            When multiple rules apply to a product, the system uses <strong>priority ranking</strong> to resolve conflicts:
        </p>

        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4 mb-4">
            <ol class="space-y-2 text-sm text-gray-300">
                <li>1. <strong>Lowest priority number wins</strong> (priority 1 beats priority 100)</li>
                <li>2. <strong>More specific rules override general rules</strong> (product-specific > category > entity type > global)</li>
                <li>3. <strong>Inactive rules are ignored</strong> regardless of priority</li>
                <li>4. <strong>Ties</strong> are resolved by creation date (newest wins)</li>
            </ol>
        </div>

        <div class="bg-yellow-900/20 border border-yellow-700/50 rounded-lg p-4">
            <div class="font-semibold text-yellow-400 mb-2">Best Practice</div>
            <p class="text-sm text-gray-300">
                Set your global default to priority 1000, category rules to 500, entity rules to 250,
                and product-specific rules to 100. This creates clear hierarchy and room for exceptions.
            </p>
        </div>
    </div>

    <!-- Margin Analysis -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-2xl font-bold text-white mb-4">Margin Analysis</h2>

        <p class="text-gray-300 mb-4">
            The system automatically analyzes product margins and flags products that don't meet the 60% KPI:
        </p>

        <div class="space-y-3">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-24 px-2 py-1 bg-green-900/30 border border-green-700 rounded text-center">
                    <span class="text-green-400 font-semibold text-xs">EXCELLENT</span>
                </div>
                <p class="text-sm text-gray-300">Net margin ≥ 70% (exceeds target)</p>
            </div>
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-24 px-2 py-1 bg-blue-900/30 border border-blue-700 rounded text-center">
                    <span class="text-blue-400 font-semibold text-xs">GOOD</span>
                </div>
                <p class="text-sm text-gray-300">Net margin 65-70% (above target)</p>
            </div>
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-24 px-2 py-1 bg-gray-700 border border-gray-600 rounded text-center">
                    <span class="text-gray-300 font-semibold text-xs">ACCEPTABLE</span>
                </div>
                <p class="text-sm text-gray-300">Net margin 60-65% (meets target)</p>
            </div>
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-24 px-2 py-1 bg-yellow-900/30 border border-yellow-700 rounded text-center">
                    <span class="text-yellow-400 font-semibold text-xs">LOW</span>
                </div>
                <p class="text-sm text-gray-300">Net margin 0-60% (below target)</p>
            </div>
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-24 px-2 py-1 bg-red-900/30 border border-red-700 rounded text-center">
                    <span class="text-red-400 font-semibold text-xs">NEGATIVE</span>
                </div>
                <p class="text-sm text-gray-300">Net margin < 0% (losing money!)</p>
            </div>
        </div>
    </div>

    <!-- Related Documentation -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-2xl font-bold text-white mb-4">Related Documentation</h2>

        <ul class="space-y-2 text-sm text-gray-300">
            <li>• <strong>Bible Ch. 17:</strong> Transparency and Integrity (Financial integrity requirements)</li>
            <li>• <strong>I.6:</strong> NGN Margin Integrity KPI (60%+ net margin target)</li>
            <li>• <strong>I.9:</strong> COGS Validation (True cost tracking implementation)</li>
            <li>• <strong>Schema:</strong> migrations/sql/schema/17_pricing_commission_rules.sql</li>
            <li>• <strong>Admin UI:</strong> admin/pricing.php (this page)</li>
        </ul>
    </div>
</div>
