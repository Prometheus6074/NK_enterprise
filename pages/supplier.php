<?php
session_start();
include '../db/connect.php';
include '../functions/supplier_guard.php';
include '../functions/supplier_functions.php';

$user = $_SESSION['user'];

// ── Handle form submissions ──────────────────────────────────────────────────
if (isset($_POST['add_product'])) {
    addSupplierProduct(
        $connect2db,
        $user['id'],
        $_POST,
        $_FILES['product_image'] ?? null,
        $resultClass,
        $result
    );
}

if (isset($_POST['update_product'])) {
    updateSupplierProduct(
        $connect2db,
        (int)$_POST['product_id'],
        $user['id'],
        $_POST,
        $_FILES['product_image'] ?? null,
        $resultClass,
        $result
    );
}

if (isset($_POST['delete_product'])) {
    deleteSupplierProduct(
        $connect2db,
        (int)$_POST['product_id'],
        $user['id'],
        $resultClass,
        $result
    );
}

// ── Fetch data ───────────────────────────────────────────────────────────────
$products = getSupplierProducts($connect2db, $user['id']);
$stats    = getSupplierStats($connect2db, $user['id']);

// Re-open modal on error so the user doesn't lose context
$reopenAdd  = isset($_POST['add_product'])    && isset($resultClass) && $resultClass === 'error';
$reopenEdit = isset($_POST['update_product']) && isset($resultClass) && $resultClass === 'error';
$editId     = $reopenEdit ? (int)$_POST['product_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="supplier-container">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="supplier-header">
        <div class="header-left">
            <h1>Supplier Portal</h1>
            <span class="header-subtitle">
                <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                &nbsp;·&nbsp; Supplier Account
            </span>
        </div>
        <div class="nav-links">
            <a href="profile.php" class="profile-link">Profile</a>
            <a href="logout.php"  class="logout-link">Logout</a>
        </div>
    </div>

    <!-- ── Flash message ──────────────────────────────────────────────────── -->
    <?php if (isset($result)): ?>
    <div class="message <?php echo htmlspecialchars($resultClass); ?>">
        <?php echo htmlspecialchars($result); ?>
    </div>
    <?php endif; ?>

    <!-- ── Stats cards ────────────────────────────────────────────────────── -->
    <div class="stats-container">
        <div class="stat-card">
            <h3>Total Products</h3>
            <p><?php echo (int)$stats['total_products']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Qty Available</h3>
            <p><?php echo number_format((int)$stats['total_quantity']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Categories</h3>
            <p><?php echo (int)$stats['total_categories']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Avg. Unit Price</h3>
            <p>₱<?php echo number_format((float)$stats['avg_price'], 2); ?></p>
        </div>
    </div>

    <!-- ── Products section ───────────────────────────────────────────────── -->
    <div class="supplier-products-section">

        <div class="sp-section-header">
            <h2>My Products</h2>
            <button class="btn-primary" onclick="openAddModal()">+ Add Product</button>
        </div>

        <!-- Controls: search + sort -->
        <div class="sp-table-controls">
            <input
                type="text"
                id="spSearch"
                placeholder="Search by name, SKU or category…"
                oninput="applyTableFilters()"
                class="sp-search-input"
            >
            <div class="sp-sort-controls">
                <span class="sort-label">Sort:</span>
                <button class="sort-col-btn active" data-col="name"     onclick="setSort('name')">Name</button>
                <button class="sort-col-btn"         data-col="sku"      onclick="setSort('sku')">SKU</button>
                <button class="sort-col-btn"         data-col="category" onclick="setSort('category')">Category</button>
                <button class="sort-col-btn"         data-col="qty"      onclick="setSort('qty')">Qty</button>
                <button class="sort-col-btn"         data-col="price"    onclick="setSort('price')">Price</button>
                <button class="sort-dir-btn" id="sortDirBtn" onclick="toggleSortDir()" title="Toggle order">↑ ASC</button>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <div class="sp-empty-state">
                <p>🗂 No products yet.</p>
                <p>Click <strong>+ Add Product</strong> to list your first item.</p>
            </div>
        <?php else: ?>

        <div class="table-container">
            <table class="sp-table" id="spTable">
                <thead>
                    <tr>
                        <th style="width:64px;">Image</th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Qty Available</th>
                        <th>Unit Price</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr
                        data-sku="<?php      echo strtolower(htmlspecialchars($p['sku']));      ?>"
                        data-name="<?php     echo strtolower(htmlspecialchars($p['name']));     ?>"
                        data-category="<?php echo strtolower(htmlspecialchars($p['category'] ?? '')); ?>"
                        data-qty="<?php      echo (int)$p['quantity_available'];               ?>"
                        data-price="<?php    echo (float)$p['unit_price'];                     ?>"
                    >
                        <td>
                            <?php if ($p['image_path']): ?>
                                <img
                                    src="../<?php echo htmlspecialchars($p['image_path']); ?>"
                                    alt="<?php echo htmlspecialchars($p['name']); ?>"
                                    class="sp-thumb"
                                >
                            <?php else: ?>
                                <div class="sp-no-img">—</div>
                            <?php endif; ?>
                        </td>
                        <td class="mono"><?php echo htmlspecialchars($p['sku']); ?></td>
                        <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['category'] ?: '—'); ?></td>
                        <td><?php echo number_format((int)$p['quantity_available']); ?></td>
                        <td>₱<?php echo number_format((float)$p['unit_price'], 2); ?></td>
                        <td>
                            <button
                                class="btn-edit"
                                onclick='openEditModal(<?php echo json_encode([
                                    "id"                 => (int)$p['id'],
                                    "sku"                => $p['sku'],
                                    "name"               => $p['name'],
                                    "description"        => $p['description'] ?? '',
                                    "category"           => $p['category'] ?? '',
                                    "quantity_available" => (int)$p['quantity_available'],
                                    "unit_price"         => (float)$p['unit_price'],
                                    "image_path"         => $p['image_path'] ?? '',
                                ]); ?>)'
                            >Edit</button>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this product?')">
                                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                <button type="submit" name="delete_product" class="btn-delete">Del</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="sp-row-count" id="spRowCount"></p>

        <?php endif; ?>
    </div><!-- /supplier-products-section -->

</div><!-- /supplier-container -->


<!-- ══════════════════════════════════════════════════════════════════════════
     ADD / EDIT PRODUCT MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div id="productModal" class="sp-modal-overlay" style="display:none;" onclick="overlayClick(event)">
    <div class="sp-modal-card">

        <!-- Modal header -->
        <div class="sp-modal-header">
            <h2 id="spModalTitle">Add New Product</h2>
            <button class="sp-modal-close" onclick="closeModal()" title="Close">×</button>
        </div>

        <!-- Form — enctype required for file upload -->
        <form
            method="POST"
            action=""
            enctype="multipart/form-data"
            id="spModalForm"
        >
            <input type="hidden" name="product_id" id="spProductId">

            <div class="sp-modal-body">

                <!-- LEFT: Image upload -->
                <div class="sp-modal-image-col">
                    <div class="sp-upload-area" id="spUploadArea" onclick="document.getElementById('spImageInput').click()" title="Click to upload image">
                        <img id="spImgPreview" src="" alt="" style="display:none;">
                        <div id="spImgPlaceholder">
                            <span class="sp-upload-icon">🖼</span>
                            <p>Click to upload</p>
                            <small>JPEG · PNG · GIF · WEBP<br>Max 5 MB</small>
                        </div>
                    </div>
                    <input
                        type="file"
                        id="spImageInput"
                        name="product_image"
                        accept="image/*"
                        style="display:none;"
                        onchange="previewImage(this)"
                    >
                    <p class="sp-img-note" id="spImgNote"></p>
                </div>

                <!-- RIGHT: Fields -->
                <div class="sp-modal-fields">

                    <div class="sp-field-row-two">
                        <div class="sp-field">
                            <label>SKU <span class="req">*</span></label>
                            <input type="text"   name="sku"  id="spSku"  placeholder="e.g. SCREW001" required>
                        </div>
                        <div class="sp-field">
                            <label>Category</label>
                            <input type="text"   name="category" id="spCategory" placeholder="e.g. Fasteners">
                        </div>
                    </div>

                    <div class="sp-field">
                        <label>Product Name <span class="req">*</span></label>
                        <input type="text" name="name" id="spName" placeholder="e.g. Wood Screws #8" required>
                    </div>

                    <div class="sp-field-row-two">
                        <div class="sp-field">
                            <label>Qty Available <span class="req">*</span></label>
                            <input type="number" name="quantity_available" id="spQty" placeholder="0" min="0" required>
                        </div>
                        <div class="sp-field">
                            <label>Unit Price (₱) <span class="req">*</span></label>
                            <input type="number" name="unit_price" id="spPrice" placeholder="0.00" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="sp-field">
                        <label>Description</label>
                        <textarea name="description" id="spDescription" placeholder="Optional product details…" rows="4"></textarea>
                    </div>

                </div><!-- /sp-modal-fields -->
            </div><!-- /sp-modal-body -->

            <div class="sp-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" id="spSubmitBtn" name="add_product" class="btn-primary">Save Product</button>
            </div>
        </form>
    </div><!-- /sp-modal-card -->
</div><!-- /sp-modal-overlay -->


<script>
// ── Modal state ───────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('spModalTitle').textContent   = 'Add New Product';
    document.getElementById('spProductId').value          = '';
    document.getElementById('spSku').value                = '';
    document.getElementById('spName').value               = '';
    document.getElementById('spCategory').value           = '';
    document.getElementById('spQty').value                = '';
    document.getElementById('spPrice').value              = '';
    document.getElementById('spDescription').value        = '';
    document.getElementById('spImgPreview').style.display = 'none';
    document.getElementById('spImgPlaceholder').style.display = 'flex';
    document.getElementById('spImgNote').textContent      = '';
    document.getElementById('spImageInput').value         = '';
    document.getElementById('spSubmitBtn').name           = 'add_product';
    showModal();
}

function openEditModal(p) {
    document.getElementById('spModalTitle').textContent   = 'Edit Product';
    document.getElementById('spProductId').value          = p.id;
    document.getElementById('spSku').value                = p.sku;
    document.getElementById('spName').value               = p.name;
    document.getElementById('spCategory').value           = p.category || '';
    document.getElementById('spQty').value                = p.quantity_available;
    document.getElementById('spPrice').value              = p.unit_price;
    document.getElementById('spDescription').value        = p.description || '';
    document.getElementById('spImageInput').value         = '';

    const preview     = document.getElementById('spImgPreview');
    const placeholder = document.getElementById('spImgPlaceholder');

    if (p.image_path) {
        preview.src           = '../' + p.image_path;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        document.getElementById('spImgNote').textContent = 'Upload a new file to replace the current image.';
    } else {
        preview.style.display     = 'none';
        placeholder.style.display = 'flex';
        document.getElementById('spImgNote').textContent = '';
    }

    document.getElementById('spSubmitBtn').name = 'update_product';
    showModal();
}

function showModal() {
    document.getElementById('productModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close when clicking the dark overlay (not the card)
function overlayClick(e) {
    if (e.target.id === 'productModal') closeModal();
}

// Image preview
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('spImgPreview');
        img.src           = e.target.result;
        img.style.display = 'block';
        document.getElementById('spImgPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

// ── Table sort + filter ───────────────────────────────────────────────────────
let sortCol = 'name', sortDir = 'asc';

function setSort(col) {
    if (sortCol === col) { toggleSortDir(); return; }
    sortCol = col;
    sortDir = 'asc';
    document.querySelectorAll('.sort-col-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.sort-col-btn[data-col="${col}"]`).classList.add('active');
    document.getElementById('sortDirBtn').textContent = '↑ ASC';
    applyTableFilters();
}

function toggleSortDir() {
    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    document.getElementById('sortDirBtn').textContent = sortDir === 'asc' ? '↑ ASC' : '↓ DESC';
    applyTableFilters();
}

function applyTableFilters() {
    const tbody  = document.querySelector('#spTable tbody');
    if (!tbody) return;
    const rows   = Array.from(tbody.querySelectorAll('tr'));
    const term   = document.getElementById('spSearch').value.toLowerCase();

    // Filter visibility
    rows.forEach(r => {
        const match =
            (r.dataset.name     || '').includes(term) ||
            (r.dataset.sku      || '').includes(term) ||
            (r.dataset.category || '').includes(term);
        r.style.display = match ? '' : 'none';
    });

    // Sort visible rows
    const visible = rows.filter(r => r.style.display !== 'none');
    visible.sort((a, b) => {
        let av = a.dataset[sortCol] || '';
        let bv = b.dataset[sortCol] || '';
        if (sortCol === 'qty' || sortCol === 'price') {
            av = parseFloat(av) || 0;
            bv = parseFloat(bv) || 0;
            return sortDir === 'asc' ? av - bv : bv - av;
        }
        return sortDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
    });

    visible.forEach(r => tbody.appendChild(r));

    // Row count
    const countEl = document.getElementById('spRowCount');
    if (countEl) {
        countEl.textContent = term
            ? `Showing ${visible.length} of ${rows.length} products`
            : `${rows.length} product${rows.length !== 1 ? 's' : ''}`;
    }
}

// ── Auto-open modal on page load if needed (error after submit) ───────────────
<?php if ($reopenAdd): ?>
openAddModal();
// Re-populate last posted values
document.getElementById('spSku').value          = <?php echo json_encode($_POST['sku']          ?? ''); ?>;
document.getElementById('spName').value         = <?php echo json_encode($_POST['name']         ?? ''); ?>;
document.getElementById('spCategory').value     = <?php echo json_encode($_POST['category']     ?? ''); ?>;
document.getElementById('spQty').value          = <?php echo json_encode($_POST['quantity_available'] ?? ''); ?>;
document.getElementById('spPrice').value        = <?php echo json_encode($_POST['unit_price']   ?? ''); ?>;
document.getElementById('spDescription').value  = <?php echo json_encode($_POST['description']  ?? ''); ?>;
<?php endif; ?>

<?php if ($reopenEdit): ?>
(function() {
    const p = {
        id: <?php echo $editId ?? 0; ?>,
        sku:                <?php echo json_encode($_POST['sku']          ?? ''); ?>,
        name:               <?php echo json_encode($_POST['name']         ?? ''); ?>,
        category:           <?php echo json_encode($_POST['category']     ?? ''); ?>,
        quantity_available: <?php echo json_encode($_POST['quantity_available'] ?? ''); ?>,
        unit_price:         <?php echo json_encode($_POST['unit_price']   ?? ''); ?>,
        description:        <?php echo json_encode($_POST['description']  ?? ''); ?>,
        image_path:         ''
    };
    openEditModal(p);
})();
<?php endif; ?>

// Initial count
document.addEventListener('DOMContentLoaded', () => applyTableFilters());
</script>

</body>
</html>
