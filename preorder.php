<?php
session_start();
require_once 'config.php';

// Check if reservation_id is provided
if (!isset($_GET['reservation_id'])) {
    header('Location: index.php');
    exit;
}

$reservation_id = intval($_GET['reservation_id']);

// Fetch reservation details
$sql = "SELECT * FROM reservations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$reservation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reservation) {
    header('Location: index.php');
    exit;
}

// Check if order is already paid
$is_paid = false;
if ($reservation['special_requests'] && strpos($reservation['special_requests'], '[PRE-ORDER PAID:') !== false) {
    $is_paid = true;
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add item to order
    if (isset($_POST['add_to_order'])) {
        $menu_item_id = intval($_POST['menu_item_id']);
        $quantity = intval($_POST['quantity']);
        $special_instructions = mysqli_real_escape_string($conn, trim($_POST['special_instructions'] ?? ''));
        
        if ($quantity < 1) {
            $message = 'Please select at least 1 item';
            $message_type = 'error';
        } else {
            // Get menu item price
            $sql = "SELECT price FROM menu_items WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $menu_item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($item) {
                $price = $item['price'] * $quantity;
                
                $sql = "INSERT INTO preorder_items (reservation_id, menu_item_id, quantity, price, special_instructions) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiids", $reservation_id, $menu_item_id, $quantity, $price, $special_instructions);
                
                if ($stmt->execute()) {
                    $message = 'Item added to your order!';
                    $message_type = 'success';
                } else {
                    $message = 'Error adding item: ' . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
    
    // Update item quantity
    if (isset($_POST['update_item'])) {
        $item_id = intval($_POST['item_id']);
        $new_quantity = intval($_POST['new_quantity']);
        
        if ($new_quantity < 1) {
            // Remove item if quantity is 0
            $sql = "DELETE FROM preorder_items WHERE id = ? AND reservation_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $item_id, $reservation_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Item removed from order';
            $message_type = 'success';
        } else {
            // Update price based on new quantity
            $sql = "UPDATE preorder_items p 
                    JOIN menu_items m ON p.menu_item_id = m.id 
                    SET p.quantity = ?, p.price = m.price * ? 
                    WHERE p.id = ? AND p.reservation_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idii", $new_quantity, $new_quantity, $item_id, $reservation_id);
            
            if ($stmt->execute()) {
                $message = 'Quantity updated!';
                $message_type = 'success';
            } else {
                $message = 'Error updating item';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
    
    // Remove item
    if (isset($_POST['remove_item'])) {
        $item_id = intval($_POST['item_id']);
        
        $sql = "DELETE FROM preorder_items WHERE id = ? AND reservation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $item_id, $reservation_id);
        
        if ($stmt->execute()) {
            $message = 'Item removed from order';
            $message_type = 'success';
        } else {
            $message = 'Error removing item';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Fetch menu items grouped by category
$sql = "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY category, name";
$result = $conn->query($sql);
$menu_items = [];
while ($row = $result->fetch_assoc()) {
    $menu_items[$row['category']][] = $row;
}

// Fetch current preorder items
$sql = "SELECT p.*, m.name, m.description, m.price as unit_price FROM preorder_items p 
        JOIN menu_items m ON p.menu_item_id = m.id 
        WHERE p.reservation_id = ? ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$preorder_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = array_sum(array_column($preorder_items, 'price'));

$conn->close();

// Check if this is an AJAX request
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $message_type === 'success',
        'message' => $message,
        'cart_html' => getCartHtml($preorder_items, $total, $is_paid, $reservation_id)
    ]);
    exit;
}

function getCartHtml($items, $total, $is_paid, $reservation_id) {
    ob_start();
    if (empty($items)): ?>
        <div class="empty-order">
            <i class="bi bi-cart3" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
            Your order is empty
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="order-item" data-item-id="<?php echo $item['id']; ?>">
                <div class="order-item-info">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <?php if ($item['special_instructions']): ?>
                        <p><?php echo htmlspecialchars($item['special_instructions']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="order-item-actions">
                    <?php if (!$is_paid): ?>
                        <form method="POST" class="quantity-form" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" name="update_item" value="1" class="qty-btn" onclick="this.form.new_quantity.value=parseInt(this.form.new_quantity.value)-1">-</button>
                            <input type="number" name="new_quantity" value="<?php echo $item['quantity']; ?>" min="0" max="20" class="qty-input" style="width: 50px; text-align: center;" onchange="this.form.submit()">
                            <button type="submit" name="update_item" value="1" class="qty-btn" onclick="this.form.new_quantity.value=parseInt(this.form.new_quantity.value)+1">+</button>
                        </form>
                    <?php else: ?>
                        <span class="quantity-display">x<?php echo $item['quantity']; ?></span>
                    <?php endif; ?>
                    <div class="order-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                    <?php if (!$is_paid): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" name="remove_item" class="remove-btn" title="Remove item">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="order-total">
            <span>Total</span>
            <span>$<?php echo number_format($total, 2); ?></span>
        </div>
        
        <?php if ($is_paid): ?>
            <div class="paid-notice">
                <i class="bi bi-check-circle-fill"></i>
                <span>Pre-order already paid</span>
            </div>
            <a href="receipt.php?reservation_id=<?php echo $reservation_id; ?>" class="checkout-btn" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                <i class="bi bi-receipt"></i> View Receipt
            </a>
        <?php else: ?>
            <a href="checkout.php?reservation_id=<?php echo $reservation_id; ?>" class="checkout-btn">
                <i class="bi bi-credit-card"></i> Proceed to Checkout
            </a>
        <?php endif; ?>
    <?php endif;
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-order Food - Gourmet Reserve</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #c9a227;
            --primary-dark: #a88420;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --accent: #e94560;
            --success: #22c55e;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            min-height: 100vh;
            color: #fff;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .header p {
            color: rgba(255,255,255,0.7);
        }

        .reservation-info {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .reservation-details h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .reservation-details p {
            color: rgba(255,255,255,0.6);
            font-size: 0.875rem;
        }

        .back-btn {
            padding: 0.75rem 1.5rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            background: var(--primary);
            color: #1a1a2e;
        }

        .content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .menu-section {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 2rem;
        }

        .menu-category {
            margin-bottom: 2rem;
        }

        .menu-category:last-child {
            margin-bottom: 0;
        }

        .menu-category h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(201,162,39,0.3);
        }

        .menu-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .menu-item:last-child {
            margin-bottom: 0;
        }

        .menu-item-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .menu-item-info p {
            color: rgba(255,255,255,0.6);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .menu-item-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .add-form {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 150px;
        }

        .add-form input[type="number"] {
            width: 60px;
            padding: 0.5rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            text-align: center;
        }

        .add-form input[type="text"] {
            padding: 0.5rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            font-size: 0.8rem;
        }

        .add-form input::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .add-btn {
            padding: 0.5rem 1rem;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            color: #1a1a2e;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .add-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .add-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .order-summary {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 2rem;
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .order-summary h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            gap: 0.5rem;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-info {
            flex: 1;
        }

        .order-item-info h4 {
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .order-item-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .quantity-display {
            background: rgba(255,255,255,0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            background: var(--primary);
            color: #1a1a2e;
        }

        .qty-input {
            width: 45px;
            padding: 0.25rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            color: #fff;
            text-align: center;
            font-size: 0.875rem;
        }

        .remove-btn {
            width: 28px;
            height: 28px;
            background: rgba(233,69,96,0.2);
            border: 1px solid rgba(233,69,96,0.3);
            border-radius: 6px;
            color: var(--accent);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .remove-btn:hover {
            background: var(--accent);
            color: #fff;
        }

        .order-item-info p {
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
        }

        .order-item-price {
            font-weight: 600;
            color: var(--primary);
            white-space: nowrap;
        }

        .order-total {
            border-top: 2px solid var(--primary);
            margin-top: 1rem;
            padding-top: 1rem;
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .order-total span:last-child {
            color: var(--primary);
        }

        .checkout-btn {
            width: 100%;
            padding: 1.25rem;
            margin-top: 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(201,162,39,0.3);
        }

        .paid-notice {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            background: rgba(34,197,94,0.15);
            border: 1px solid var(--success);
            border-radius: 12px;
            margin-top: 1rem;
            color: var(--success);
            font-weight: 600;
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            color: #fff;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(150%);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--accent);
        }

        .empty-order {
            text-align: center;
            padding: 2rem 0;
            color: rgba(255,255,255,0.5);
        }

        .message {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .success-message {
            background: rgba(34,197,94,0.15);
            border: 1px solid var(--success);
            color: #4ade80;
        }

        .error-message {
            background: rgba(233,69,96,0.15);
            border: 1px solid var(--accent);
            color: #ff6b7a;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .content {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.75rem;
            }

            .reservation-info {
                flex-direction: column;
                text-align: center;
            }

            .menu-section {
                padding: 1.5rem;
            }

            .menu-item {
                flex-direction: column;
            }

            .add-form {
                flex-direction: row;
                align-items: center;
                width: 100%;
            }

            .add-form input[type="text"] {
                flex: 1;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-item-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="bi bi-cart-plus"></i> Pre-order Your Meal</h1>
            <p>Pre-order food for your upcoming reservation and we'll have it ready when you arrive</p>
        </div>

        <div class="reservation-info">
            <div class="reservation-details">
                <h3>Reservation #<?php echo $reservation_id; ?></h3>
                <p>
                    <i class="bi bi-calendar"></i> <?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?> 
                    <i class="bi bi-clock" style="margin-left: 1rem;"></i> <?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?>
                    <i class="bi bi-people" style="margin-left: 1rem;"></i> <?php echo $reservation['guests']; ?> guests
                </p>
            </div>
            <a href="index.php" class="back-btn">
                <i class="bi bi-arrow-left"></i> Back to Home
            </a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="content">
            <div class="menu-section">
                <?php foreach ($menu_items as $category => $items): ?>
                    <div class="menu-category">
                        <h2><i class="bi bi-circle-fill" style="font-size: 0.5rem; margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($category); ?></h2>
                        <?php foreach ($items as $item): ?>
                            <div class="menu-item" id="menu-item-<?php echo $item['id']; ?>">
                                <div class="menu-item-info">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                                    <div class="menu-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                <?php if (!$is_paid): ?>
                                    <form method="POST" class="add-form" onsubmit="return addToCart(event, this);">
                                        <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                                        <input type="number" name="quantity" value="1" min="1" max="10">
                                        <input type="text" name="special_instructions" placeholder="Special requests...">
                                        <button type="submit" name="add_to_order" class="add-btn">
                                            <i class="bi bi-plus-lg"></i> Add
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div style="color: rgba(255,255,255,0.5); font-size: 0.875rem;">
                                        <i class="bi bi-lock"></i> Order locked
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="order-summary" id="cart-container">
                <h2><i class="bi bi-receipt"></i> Your Order</h2>
                <?php echo getCartHtml($preorder_items, $total, $is_paid, $reservation_id); ?>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        function addToCart(event, form) {
            event.preventDefault();
            
            const btn = form.querySelector('.add-btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';
            
            const formData = new FormData(form);
            formData.append('add_to_order', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart HTML without scrolling
                    document.getElementById('cart-container').innerHTML = '<h2><i class="bi bi-receipt"></i> Your Order</h2>' + data.cart_html;
                    showToast(data.message, 'success');
                    // Reset quantity to 1
                    form.querySelector('input[name="quantity"]').value = 1;
                    form.querySelector('input[name="special_instructions"]').value = '';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error adding item', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
            
            return false;
        }
        
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.className = 'toast ' + type;
            toast.innerHTML = type === 'success' 
                ? '<i class="bi bi-check-circle"></i> ' + message
                : '<i class="bi bi-exclamation-circle"></i> ' + message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Handle quantity form submissions without page reload
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('qty-btn')) {
                e.preventDefault();
                const form = e.target.closest('form');
                const input = form.querySelector('input[name="new_quantity"]');
                let val = parseInt(input.value);
                
                if (e.target.textContent === '-') {
                    val = Math.max(0, val - 1);
                } else {
                    val = Math.min(20, val + 1);
                }
                input.value = val;
                
                // Submit via AJAX
                submitCartForm(form);
            }
        });
        
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('qty-input')) {
                const form = e.target.closest('form');
                submitCartForm(form);
            }
        });
        
        function submitCartForm(form) {
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cart-container').innerHTML = '<h2><i class="bi bi-receipt"></i> Your Order</h2>' + data.cart_html;
                    if (data.message) showToast(data.message, 'success');
                }
            });
        }
        
        // Handle remove buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-btn')) {
                e.preventDefault();
                const form = e.target.closest('form');
                
                if (confirm('Remove this item from your order?')) {
                    const formData = new FormData(form);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('cart-container').innerHTML = '<h2><i class="bi bi-receipt"></i> Your Order</h2>' + data.cart_html;
                            showToast(data.message, 'success');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
