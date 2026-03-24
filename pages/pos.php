<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';

$user       = $_SESSION['user'];
$activePage = 'pos';

if (isset($_POST['process_sale'])) {
    $cartItems     = json_decode($_POST['cart_items'], true);
    $paymentMethod = $_POST['payment_method'];
    $customerName  = $_POST['customer_name'] ?: null;
    $notes         = $_POST['notes'] ?: null;
    if (!empty($cartItems)) {
        $saleId = processSale($connect2db, $user['id'], $cartItems, $paymentMethod, $resultClass, $result, $customerName, $notes);
        if ($saleId) unset($_SESSION['cart']);
    } else {
        $resultClass = "error";
        $result      = "Cart is empty";
    }
}

if (isset($_POST['add_to_cart'])) {
    $productId = $_POST['product_id'];
    $quantity  = (int)$_POST['quantity'];
    $product   = getProduct($connect2db, $productId);
    if ($product && $product['quantity'] >= $quantity) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $productId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        unset($item); // critical: break the reference after foreach
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $productId,
                'sku'        => $product['sku'],
                'name'       => $product['name'],
                'quantity'   => $quantity,
                'unit_price' => $product['unit_price']
            ];
        }
        $resultClass = "success";
        $result      = "Added to cart";
    } else {
        $resultClass = "error";
        $result      = "Insufficient stock";
    }
}

if (isset($_POST['update_cart'])) {
    $productId = $_POST['product_id'];
    $quantity  = (int)$_POST['quantity'];
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $productId) {
                $product = getProduct($connect2db, $productId);
                if ($product && $product['quantity'] >= $quantity) {
                    $item['quantity'] = $quantity;
                    $resultClass = "success";
                    $result      = "Cart updated";
                } else {
                    $resultClass = "error";
                    $result      = "Insufficient stock";
                }
                break;
            }
        }
        unset($item); // critical: break the reference after foreach
    }
}

if (isset($_POST['remove_from_cart'])) {
    $productId = $_POST['product_id'];
    $_SESSION['cart'] = array_values(
        array_filter($_SESSION['cart'] ?? [], fn($i) => $i['product_id'] != $productId)
    ); // array_values re-indexes so keys stay sequential
    $resultClass = "success";
    $result      = "Removed from cart";
}

if (isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']); $resultClass = "success"; $result = "Cart cleared";
}

$products    = getProducts($connect2db);
$recentSales = getSales($connect2db, 10);
$cart        = $_SESSION['cart'] ?? [];
$cartTotal   = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $cart));

$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) $products = searchProducts($connect2db, $searchTerm);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../css/style.css" />
  <title>Point of Sale — NK Ent</title>
</head>
<body>

<div class="app-layout">
  <?php include '../components/sidebar.php'; ?>

  <div class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <h1>Point of Sale</h1>
        <span class="page-header-breadcrumb">Process transactions</span>
      </div>
    </div>

    <div class="page-body">

      <?php if (isset($result)): ?>
      <div class="message <?php echo $resultClass; ?>"><?php echo htmlspecialchars($result); ?></div>
      <?php endif; ?>

      <div class="pos-layout">

        <!-- Products panel -->
        <div>
          <div class="search-container">
            <form method="GET" action="">
              <input type="text" name="search" placeholder="Search products by name or SKU..."
                     value="<?php echo htmlspecialchars($searchTerm); ?>">
              <button type="submit">Search</button>
            </form>
          </div>

          <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
              <div>
                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                <p class="sku"><?php echo htmlspecialchars($product['sku']); ?></p>
                <p class="price">₱<?php echo number_format($product['unit_price'], 2); ?></p>
                <p class="stock <?php echo $product['quantity'] <= $product['min_quantity'] ? 'low-stock' : ''; ?>">
                  Stock: <?php echo $product['quantity']; ?>
                </p>
              </div>
              <form method="POST" action="" class="add-to-cart-form">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <div class="quantity-input">
                  <input type="number" name="quantity" value="1" min="1"
                         max="<?php echo $product['quantity']; ?>">
                </div>
                <button type="submit" name="add_to_cart" class="btn-add-cart"
                        <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                  Add to Cart
                </button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Cart Panel -->
        <div class="cart-panel">
          <div class="cart-header">
            <h2>Cart</h2>
            <?php if (!empty($cart)): ?>
            <form method="POST" action="" style="display:inline;">
              <button type="submit" name="clear_cart" class="btn-clear"
                      onclick="return confirm('Clear entire cart?')">Clear all</button>
            </form>
            <?php endif; ?>
          </div>

          <?php if (empty($cart)): ?>
          <div class="empty-cart">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <p>Your cart is empty</p>
          </div>

          <?php else: ?>
          <div class="cart-items">
            <?php foreach ($cart as $item): ?>
            <div class="cart-item">
              <div>
                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                <p class="sku"><?php echo htmlspecialchars($item['sku']); ?></p>
                <p class="unit-price">₱<?php echo number_format($item['unit_price'], 2); ?> each</p>
              </div>
              <div class="item-actions">
                <form method="POST" action="" class="update-quantity-form">
                  <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                  <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>"
                         min="1" class="quantity-field">
                  <button type="submit" name="update_cart" class="btn-update">Update</button>
                </form>
                <form method="POST" action="" style="display:inline;">
                  <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                  <button type="submit" name="remove_from_cart" class="btn-remove">Remove</button>
                </form>
              </div>
              <div class="item-total">₱<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="cart-summary">
            <div class="cart-total">Total: ₱<?php echo number_format($cartTotal, 2); ?></div>
          </div>

          <div class="checkout-form">
            <h3>Checkout</h3>
            <form method="POST" action="">
              <input type="hidden" name="cart_items" value='<?php echo htmlspecialchars(json_encode($cart)); ?>'>
              <div class="form-row">
                <input type="text" name="customer_name" placeholder="Customer name (optional)">
              </div>
              <div class="form-row">
                <select name="payment_method" required>
                  <option value="cash">Cash</option>
                  <option value="card">Card</option>
                  <option value="credit">Credit</option>
                </select>
              </div>
              <div class="form-row">
                <textarea name="notes" placeholder="Notes (optional)" style="margin-bottom:0;"></textarea>
              </div>
              <button type="submit" name="process_sale" class="btn-checkout" style="margin-top:12px;">
                Process Sale — ₱<?php echo number_format($cartTotal, 2); ?>
              </button>
            </form>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- /pos-layout -->

      <!-- Recent Sales -->
      <div class="recent-sales-section" style="margin-top:28px;">
        <h2>Recent Sales</h2>
        <?php if (empty($recentSales)): ?>
          <p class="text-muted">No sales yet.</p>
        <?php else: ?>
        <div class="table-container">
          <table class="sales-table">
            <thead>
              <tr>
                <th>ID</th><th>Amount</th><th>Payment</th>
                <th>Cashier</th><th>Time</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentSales as $sale): ?>
              <tr>
                <td class="mono">#<?php echo $sale['id']; ?></td>
                <td>₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                <td><?php echo ucfirst($sale['payment_method']); ?></td>
                <td><?php echo htmlspecialchars($sale['firstname'] . ' ' . $sale['lastname']); ?></td>
                <td><?php echo date('M j, g:i A', strtotime($sale['created_at'])); ?></td>
                <td>
                  <button class="btn-view" onclick="viewSaleDetails(<?php echo $sale['id']; ?>)">View</button>
                  <?php if ($user['role'] === 'admin'): ?>
                  <button class="btn-refund" onclick="refundSale(<?php echo $sale['id']; ?>)">Refund</button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

<!-- Sale Details Modal -->
<div id="saleDetailsModal" class="modal" style="display:none;">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Sale Details</h3>
      <span class="close" onclick="closeModal()">&times;</span>
    </div>
    <div id="saleDetailsContent"></div>
  </div>
</div>

<script>
function viewSaleDetails(saleId) {
  const content = document.getElementById('saleDetailsContent');
  content.innerHTML = '<p style="padding:20px;color:#6b7280;">Loading...</p>';
  document.getElementById('saleDetailsModal').style.display = 'flex';

  fetch('get_sale_details.php?id=' + saleId)
    .then(r => r.json())
    .then(data => {
      if (!data || data.error) {
        content.innerHTML = '<p style="padding:20px;color:#ef4444;">Could not load sale details.</p>';
        return;
      }
      let rows = (data.items || []).map(i => `
        <tr>
          <td style="font-family:monospace;font-size:12px;">${i.sku}</td>
          <td>${i.name}</td>
          <td style="text-align:center;">${i.quantity}</td>
          <td style="text-align:right;">₱${parseFloat(i.unit_price).toFixed(2)}</td>
          <td style="text-align:right;font-weight:700;">₱${parseFloat(i.total_price).toFixed(2)}</td>
        </tr>`).join('');
      content.innerHTML = `
        <div style="font-size:13.5px;line-height:1.6;padding:4px 0 12px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #e5e7eb;">
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Sale ID</span><br><strong>#${data.id}</strong></div>
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Date</span><br>${data.created_at}</div>
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Cashier</span><br>${data.cashier}</div>
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Payment</span><br>${data.payment_method}</div>
            ${data.customer_name ? `<div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Customer</span><br>${data.customer_name}</div>` : ''}
            ${data.notes ? `<div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Notes</span><br>${data.notes}</div>` : ''}
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
              <tr style="background:#f8fafc;">
                <th style="padding:8px 10px;text-align:left;font-size:11.5px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">SKU</th>
                <th style="padding:8px 10px;text-align:left;font-size:11.5px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">Product</th>
                <th style="padding:8px 10px;text-align:center;font-size:11.5px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">Qty</th>
                <th style="padding:8px 10px;text-align:right;font-size:11.5px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">Unit</th>
                <th style="padding:8px 10px;text-align:right;font-size:11.5px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">Total</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
            <tfoot>
              <tr style="border-top:2px solid #e5e7eb;">
                <td colspan="4" style="padding:10px 10px 4px;text-align:right;font-weight:700;">Grand Total:</td>
                <td style="padding:10px 10px 4px;text-align:right;font-weight:700;color:#FFDE42;">₱${parseFloat(data.total_amount).toFixed(2)}</td>
              </tr>
            </tfoot>
          </table>
        </div>`;
    })
    .catch(() => {
      content.innerHTML = '<p style="padding:20px;color:#ef4444;">Failed to load. Please try again.</p>';
    });
}

function closeModal() {
  document.getElementById('saleDetailsModal').style.display = 'none';
}

function refundSale(saleId) {
  if (confirm('Refund sale #' + saleId + '? This will restock all items and cannot be undone.')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="refund_sale" value="' + saleId + '">';
    document.body.appendChild(form);
    form.submit();
  }
}

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.quantity-field').forEach(input => {
    input.addEventListener('change', function() { this.closest('form').submit(); });
  });

  // Close modal on backdrop click
  document.getElementById('saleDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
});
</script>

</body>
</html>
