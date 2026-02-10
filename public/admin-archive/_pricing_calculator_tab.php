<?php
/**
 * Pricing Calculator Tab - Partial Template
 *
 * Interactive calculator for testing pricing scenarios and margin calculations.
 */
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-8">
        <h2 class="text-2xl font-bold text-white mb-6">Pricing Calculator</h2>
        <p class="text-gray-400 mb-8">
            Test pricing scenarios to ensure you hit the 60%+ net margin KPI target. This calculator shows
            how cost, margin target, and platform commission interact to determine the final sale price.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div>
                <label for="calc_cost" class="block text-sm font-medium text-gray-300 mb-2">
                    Product Cost (USD)
                </label>
                <input type="number" id="calc_cost" value="10.00" step="0.01" min="0"
                       oninput="calculatePrice()"
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Base + Print + Shipping cost</p>
            </div>

            <div>
                <label for="calc_margin" class="block text-sm font-medium text-gray-300 mb-2">
                    Target Net Margin (%)
                </label>
                <input type="number" id="calc_margin" value="60" step="1" min="0" max="100"
                       oninput="calculatePrice()"
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Profit after all costs</p>
            </div>

            <div>
                <label for="calc_commission" class="block text-sm font-medium text-gray-300 mb-2">
                    Platform Commission (%)
                </label>
                <input type="number" id="calc_commission" value="30" step="1" min="0" max="100"
                       oninput="calculatePrice()"
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">NGN platform fee</p>
            </div>
        </div>

        <div id="calc_result" class="bg-gray-900/50 border border-gray-700 rounded-lg p-6">
            <div class="text-gray-500 text-center">Enter values above to calculate pricing</div>
        </div>

        <!-- Formula Explanation -->
        <div class="mt-8 bg-blue-900/20 border border-blue-700/50 rounded-lg p-6">
            <h3 class="text-lg font-bold text-blue-400 mb-4">Formula Explanation</h3>
            <div class="space-y-4 text-sm">
                <div>
                    <div class="font-semibold text-white mb-1">Sale Price Calculation:</div>
                    <code class="text-gray-300 bg-gray-900 px-2 py-1 rounded">
                        Price = Cost / (1 - Margin% - Commission%)
                    </code>
                </div>

                <div>
                    <div class="font-semibold text-white mb-1">Margin Breakdown:</div>
                    <ul class="text-gray-400 space-y-1 ml-4">
                        <li>• <strong>Gross Profit:</strong> Sale Price - Cost</li>
                        <li>• <strong>Platform Fee:</strong> Sale Price × Commission%</li>
                        <li>• <strong>Net Profit:</strong> Gross Profit - Platform Fee</li>
                        <li>• <strong>Net Margin:</strong> Net Profit / Sale Price × 100</li>
                    </ul>
                </div>

                <div>
                    <div class="font-semibold text-white mb-1">Example (Cost=$10, Margin=60%, Commission=30%):</div>
                    <div class="text-gray-400 space-y-1 ml-4">
                        <div>1. Price = $10 / (1 - 0.60 - 0.30) = $10 / 0.10 = <strong class="text-white">$100.00</strong></div>
                        <div>2. Gross Profit = $100 - $10 = <strong class="text-white">$90.00</strong></div>
                        <div>3. Platform Fee = $100 × 30% = <strong class="text-white">$30.00</strong></div>
                        <div>4. Net Profit = $90 - $30 = <strong class="text-white">$60.00</strong></div>
                        <div>5. Net Margin = $60 / $100 = <strong class="text-green-400">60.0%</strong> ✓</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Common Scenarios -->
        <div class="mt-8">
            <h3 class="text-lg font-bold text-white mb-4">Common Pricing Scenarios</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button onclick="document.getElementById('calc_cost').value='10'; document.getElementById('calc_margin').value='60'; document.getElementById('calc_commission').value='30'; calculatePrice();"
                        class="px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-left transition">
                    <div class="font-semibold text-white">Standard Merch ($10 cost)</div>
                    <div class="text-xs text-gray-400">60% margin, 30% commission</div>
                </button>

                <button onclick="document.getElementById('calc_cost').value='25'; document.getElementById('calc_margin').value='60'; document.getElementById('calc_commission').value='30'; calculatePrice();"
                        class="px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-left transition">
                    <div class="font-semibold text-white">Premium Item ($25 cost)</div>
                    <div class="text-xs text-gray-400">60% margin, 30% commission</div>
                </button>

                <button onclick="document.getElementById('calc_cost').value='5'; document.getElementById('calc_margin').value='70'; document.getElementById('calc_commission').value='20'; calculatePrice();"
                        class="px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-left transition">
                    <div class="font-semibold text-white">Budget Item ($5 cost)</div>
                    <div class="text-xs text-gray-400">70% margin, 20% commission</div>
                </button>

                <button onclick="document.getElementById('calc_cost').value='15'; document.getElementById('calc_margin').value='50'; document.getElementById('calc_commission').value='30'; calculatePrice();"
                        class="px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-left transition">
                    <div class="font-semibold text-white">Lower Margin ($15 cost)</div>
                    <div class="text-xs text-gray-400 text-red-400">50% margin (below KPI!), 30% commission</div>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-calculate on page load
document.addEventListener('DOMContentLoaded', function() {
    calculatePrice();
});
</script>
