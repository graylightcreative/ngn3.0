<?php
/**
 * Venue Dashboard - Shop & Merch Management
 */
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Commerce\ProductService;

dashboard_require_auth();
dashboard_require_entity_type('venue');

$user = dashboard_get_user();
$entity = dashboard_get_entity('venue');
$pageTitle = 'Shop';
$currentPage = 'shop';

$config = new Config();
$productService = new ProductService($config);

$action = $_GET['action'] ?? 'list';
$productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$success = $error = null;
$products = [];
$editProduct = null;

// Fetch products
if ($entity) {
    $result = $productService->list('venue', (int)$entity['id'], null, '', false);
    $products = $result['items'];
    
    if ($productId && $action === 'edit') {
        $editProduct = $productService->get($productId);
        // Security check: ensure product belongs to this entity
        if ($editProduct && ($editProduct['owner_type'] !== 'venue' || (int)$editProduct['owner_id'] !== (int)$entity['id'])) {
            $editProduct = null;
            $error = 'Unauthorized access to product.';
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $entity) {
    if (!dashboard_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $priceFloat = (float)($_POST['price'] ?? 0);
        $data = [
            'owner_type' => 'venue',
            'owner_id' => $entity['id'],
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'type' => $_POST['type'] ?? 'physical',
            'price_cents' => (int)round($priceFloat * 100),
            'status' => isset($_POST['is_active']) ? 'active' : 'draft',
            'track_inventory' => isset($_POST['track_inventory']) ? 1 : 0,
            'inventory_count' => (int)($_POST['inventory_count'] ?? 0),
            'sku' => trim($_POST['sku'] ?? ''),
        ];
        
        if (empty($data['name'])) {
            $error = 'Product name is required.';
        } elseif ($data['price_cents'] <= 0) {
            $error = 'Price must be greater than zero.';
        } else {
            if ($action === 'edit' && $editProduct) {
                $res = $productService->update($productId, $data);
                if ($res['success']) {
                    $success = 'Product updated!';
                    $action = 'list';
                } else {
                    $error = 'Error updating product: ' . ($res['error'] ?? 'Unknown error');
                }
            } else {
                $res = $productService->create($data);
                if ($res['success']) {
                    $success = 'Product added!';
                    $action = 'list';
                } else {
                    $error = 'Error creating product: ' . ($res['error'] ?? 'Unknown error');
                }
            }
            
            // Refresh list
            if (empty($error)) {
                $result = $productService->list('venue', (int)$entity['id'], null, '', false);
                $products = $result['items'];
            }
        }
    }
}

// Handle delete (Archive)
if ($action === 'delete' && $productId && $entity) {
    $res = $productService->update($productId, ['status' => 'archived']);
    if ($res['success']) {
        $success = 'Product archived.';
    } else {
        $error = 'Error archiving product.';
    }
    header('Location: shop.php?success=' . urlencode($success));
    exit;
}

if (isset($_GET['success'])) $success = $_GET['success'];

$csrf = dashboard_csrf_token();

include dirname(__DIR__) . '/lib/partials/head.php';
include dirname(__DIR__) . '/lib/partials/sidebar.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1 class="page-title">Shop & Merch</h1>
        <p class="page-subtitle">Manage your merchandise and digital products</p>
    </header>
    
    <div class="page-content">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$entity): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Your venue profile needs to be set up before you can manage your shop.
            <a href="profile.php">Set up profile →</a>
        </div>
        <?php elseif ($action === 'add' || ($action === 'edit' && $editProduct)): ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $action === 'edit' ? 'Edit Product' : 'Add New Product' ?></h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-input" required
                               value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>"
                               placeholder="e.g. Limited Edition T-Shirt">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SKU (Internal ID)</label>
                        <input type="text" name="sku" class="form-input"
                               value="<?= htmlspecialchars($editProduct['sku'] ?? '') ?>"
                               placeholder="e.g. TSHIRT-BLK-L">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="4" placeholder="Describe your product..."><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Product Type</label>
                        <select name="type" class="form-select">
                            <option value="physical" <?= ($editProduct['type'] ?? '') === 'physical' ? 'selected' : '' ?>>Physical Good (Merch)</option>
                            <option value="digital" <?= ($editProduct['type'] ?? '') === 'digital' ? 'selected' : '' ?>>Digital Download</option>
                            <option value="ticket" <?= ($editProduct['type'] ?? '') === 'ticket' ? 'selected' : '' ?>>Event Ticket</option>
                            <option value="donation" <?= ($editProduct['type'] ?? '') === 'donation' ? 'selected' : '' ?>>Donation/Tip</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" name="price" class="form-input" step="0.01" min="0" required
                               value="<?= htmlspecialchars($editProduct['price'] ?? '0.00') ?>">
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="track_inventory" <?= ($editProduct['track_inventory'] ?? false) ? 'checked' : '' ?>>
                            Track Inventory
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity in Stock</label>
                        <input type="number" name="inventory_count" class="form-input" min="0"
                               value="<?= htmlspecialchars($editProduct['inventory_count'] ?? '0') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" <?= ($editProduct['status'] ?? 'active') === 'active' ? 'checked' : '' ?>>
                        Product is active (visible in shop)
                    </label>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="shop.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Product' : 'Add Product' ?>
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        
        <!-- Products List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Products (<?= count($products) ?>)</h2>
                <a href="shop.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Product
                </a>
            </div>
            
            <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
                <i class="bi bi-bag" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                <p>No products yet. Start selling your merch!</p>
                <a href="shop.php?action=add" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="bi bi-plus-lg"></i> Add Product
                </a>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 4px; background: var(--border); overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                        <i class="bi bi-bag" style="color: var(--text-muted);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($product['name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($product['sku'] ?? 'No SKU') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-secondary"><?= ucfirst(htmlspecialchars($product['type'])) ?></span></td>
                            <td>
                                <?= htmlspecialchars($product['currency']) ?> <?= number_format($product['price'], 2) ?>
                            </td>
                            <td>
                                <?php if ($product['track_inventory']): ?>
                                    <span style="color: <?= $product['inventory_count'] <= 5 ? 'var(--danger)' : 'inherit' ?>;">
                                        <?= $product['inventory_count'] ?> in stock
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Infinite</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product['status'] === 'active'): ?>
                                <span class="badge badge-success">Active</span>
                                <?php elseif ($product['status'] === 'archived'): ?>
                                <span class="badge badge-danger">Archived</span>
                                <?php else: ?>
                                <span class="badge badge-muted">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="shop.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($product['status'] !== 'archived'): ?>
                                    <a href="shop.php?action=delete&id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm" style="color: var(--danger);" title="Archive" onclick="return confirm('Archive this product?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
</div>

</body>
</html>