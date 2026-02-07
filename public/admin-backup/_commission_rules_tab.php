<?php
/**
 * Commission Rules Tab - Partial Template
 *
 * Manages platform commission/fee rules for different sellers and product categories.
 */
?>

<div class="mb-6">
    <button onclick="document.getElementById('new-commission-rule-form').classList.toggle('hidden')"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition">
        + Create New Commission Rule
    </button>
</div>

<!-- New Rule Form (Hidden by default) -->
<div id="new-commission-rule-form" class="hidden mb-6 bg-gray-800 border border-gray-700 rounded-lg p-6">
    <h3 class="text-xl font-bold text-white mb-4">Create New Commission Rule</h3>
    <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="save_commission_rule">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Rule Name *</label>
                <input type="text" name="name" required
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Rule Type *</label>
                <select name="rule_type" required
                        class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                    <option value="global">Global (All Sellers)</option>
                    <option value="entity_type">Entity Type (Artist/Label/Venue)</option>
                    <option value="product_category">Product Category</option>
                    <option value="seller_specific">Specific Seller</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Entity Type</label>
                <select name="entity_type"
                        class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                    <option value="">-- Not Applicable --</option>
                    <option value="artist">Artist</option>
                    <option value="label">Label</option>
                    <option value="venue">Venue</option>
                    <option value="station">Station</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Commission Type *</label>
                <select name="commission_type" required
                        class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
                    <option value="percent">Percentage of Sale</option>
                    <option value="fixed_per_item">Fixed Amount Per Item</option>
                    <option value="fixed_per_order">Fixed Amount Per Order</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Commission % (e.g., 30 for 30%)</label>
                <input type="number" name="commission_percent" step="0.01" min="0" max="100"
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Priority (lower = higher priority)</label>
                <input type="number" name="priority" value="100" min="1"
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
            <textarea name="description" rows="2"
                      class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500"
                      placeholder="Optional description of this commission rule..."></textarea>
        </div>

        <div class="flex items-center">
            <input type="checkbox" name="active" id="new_comm_active" checked class="mr-2">
            <label for="new_comm_active" class="text-sm text-gray-300">Active</label>
        </div>

        <div class="flex space-x-3">
            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition">
                Create Rule
            </button>
            <button type="button" onclick="document.getElementById('new-commission-rule-form').classList.add('hidden')"
                    class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
                Cancel
            </button>
        </div>
    </form>
</div>

<!-- Existing Rules Table -->
<div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-900 border-b border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Priority</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Scope</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Commission</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                <?php if (empty($commissionRules)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No commission rules found. Create your first rule above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($commissionRules as $rule): ?>
                        <tr class="hover:bg-gray-750">
                            <td class="px-4 py-3 text-sm text-white font-medium"><?= $rule['priority'] ?></td>
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium text-white"><?= htmlspecialchars($rule['name']) ?></div>
                                <?php if ($rule['description']): ?>
                                    <div class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($rule['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                <?php if ($rule['entity_type']): ?>
                                    <?= ucfirst($rule['entity_type']) ?>
                                <?php else: ?>
                                    <?= ucfirst(str_replace('_', ' ', $rule['rule_type'])) ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300"><?= ucfirst(str_replace('_', ' ', $rule['commission_type'])) ?></td>
                            <td class="px-4 py-3 text-sm text-white">
                                <?php if ($rule['commission_type'] === 'percent' && $rule['commission_percent']): ?>
                                    <?= number_format($rule['commission_percent'], 1) ?>%
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?php if ($rule['active']): ?>
                                    <span class="inline-block px-2 py-1 rounded border text-xs font-medium bg-green-900/30 text-green-400 border-green-700">
                                        ACTIVE
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-1 rounded border text-xs font-medium bg-gray-700 text-gray-400 border-gray-600">
                                        INACTIVE
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this rule?')">
                                    <input type="hidden" name="action" value="delete_rule">
                                    <input type="hidden" name="rule_table" value="commission_rules">
                                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-300 text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
