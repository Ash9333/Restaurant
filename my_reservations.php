<?php
session_start();
require_once 'config.php';

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$reservations = [];

// Handle AJAX pre-order requests
$ajax_response = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $reservation_id = intval($_POST['reservation_id'] ?? 0);
    
    if (isset($_POST['add_to_order'])) {
        $menu_item_id = intval($_POST['menu_item_id']);
        $quantity = intval($_POST['quantity']);
        $special_instructions = mysqli_real_escape_string($conn, trim($_POST['special_instructions'] ?? ''));
        
        if ($quantity >= 1) {
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
                $stmt->execute();
                $stmt->close();
                $ajax_response = ['success' => true, 'message' => 'Item added!'];
            }
        }
    }
    
    if (isset($_POST['update_item'])) {
        $item_id = intval($_POST['item_id']);
        $new_quantity = intval($_POST['new_quantity']);
        
        if ($new_quantity < 1) {
            $sql = "DELETE FROM preorder_items WHERE id = ? AND reservation_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $item_id, $reservation_id);
            $stmt->execute();
            $stmt->close();
            $ajax_response = ['success' => true, 'message' => 'Item removed'];
        } else {
            $sql = "UPDATE preorder_items p 
                    JOIN menu_items m ON p.menu_item_id = m.id 
                    SET p.quantity = ?, p.price = m.price * ? 
                    WHERE p.id = ? AND p.reservation_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idii", $new_quantity, $new_quantity, $item_id, $reservation_id);
            $stmt->execute();
            $stmt->close();
            $ajax_response = ['success' => true, 'message' => 'Updated'];
        }
    }
    
    if (isset($_POST['remove_item'])) {
        $item_id = intval($_POST['item_id']);
        $sql = "DELETE FROM preorder_items WHERE id = ? AND reservation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $item_id, $reservation_id);
        $stmt->execute();
        $stmt->close();
        $ajax_response = ['success' => true, 'message' => 'Item removed'];
    }
    
    // Return updated cart HTML
    if ($ajax_response) {
        $sql = "SELECT p.*, m.name, m.description, m.price as unit_price FROM preorder_items p 
                JOIN menu_items m ON p.menu_item_id = m.id 
                WHERE p.reservation_id = ? ORDER BY p.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $sql = "SELECT special_requests FROM reservations WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $is_paid = $res && $res['special_requests'] && strpos($res['special_requests'], '[PRE-ORDER PAID:') !== false;
        
        $total = array_sum(array_column($items, 'price'));
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $ajax_response['success'],
            'message' => $ajax_response['message'],
            'cart_html' => getCartHtml($items, $total, $is_paid, $reservation_id)
        ]);
        exit;
    }
}

if ($is_logged_in) {
    // Get reservations for logged-in user
    $user_id = intval($_SESSION['user_id']);
    $sql = "SELECT r.*, 
            (SELECT SUM(price) FROM preorder_items WHERE reservation_id = r.id) as preorder_total
            FROM reservations r 
            WHERE r.user_id = ? 
            ORDER BY r.reservation_date DESC, r.reservation_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // Get guest reservations from session
    if (isset($_SESSION['guest_reservation_ids']) && !empty($_SESSION['guest_reservation_ids'])) {
        $ids = array_map('intval', $_SESSION['guest_reservation_ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql = "SELECT r.*, 
                (SELECT SUM(price) FROM preorder_items WHERE reservation_id = r.id) as preorder_total
                FROM reservations r 
                WHERE r.id IN ($placeholders)
                ORDER BY r.reservation_date DESC, r.reservation_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Fetch preorder items for each reservation
$menu_items_all = [];
$sql = "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY category, name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $menu_items_all[$row['category']][] = $row;
}

foreach ($reservations as &$reservation) {
    $sql = "SELECT p.*, m.name, m.description, m.price as unit_price FROM preorder_items p 
            JOIN menu_items m ON p.menu_item_id = m.id 
            WHERE p.reservation_id = ? ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reservation['id']);
    $stmt->execute();
    $reservation['preorders'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $reservation['is_paid'] = $reservation['special_requests'] && strpos($reservation['special_requests'], '[PRE-ORDER PAID:') !== false;
}
unset($reservation);

$conn->close();

function getCartHtml($items, $total, $is_paid, $reservation_id) {
    ob_start();
    if (empty($items)): ?>
        <div class="empty-order-mini">
            <i class="bi bi-cart3"></i> No items yet
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="preorder-item-mini" data-item-id="<?php echo $item['id']; ?>">
                <div class="preorder-item-info">
                    <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                    <?php if ($item['special_instructions']): ?>
                        <small><?php echo htmlspecialchars($item['special_instructions']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="preorder-item-actions">
                    <?php if (!$is_paid): ?>
                        <form class="qty-form" data-reservation="<?php echo $reservation_id; ?>" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                            <button type="button" class="qty-btn-mini" onclick="updateQty(this, -1)">-</button>
                            <input type="number" name="new_quantity" value="<?php echo $item['quantity']; ?>" min="0" max="20" class="qty-input-mini" onchange="submitQty(this)">
                            <button type="button" class="qty-btn-mini" onclick="updateQty(this, 1)">+</button>
                        </form>
                    <?php else: ?>
                        <span class="qty-display">x<?php echo $item['quantity']; ?></span>
                    <?php endif; ?>
                    <span class="item-price">$<?php echo number_format($item['price'], 2); ?></span>
                    <?php if (!$is_paid): ?>
                        <button type="button" class="remove-btn-mini" onclick="removeItem(<?php echo $item['id']; ?>, <?php echo $reservation_id; ?>)" title="Remove">
                            <i class="bi bi-trash"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="mini-total">
            <span>Total: $<?php echo number_format($total, 2); ?></span>
            <?php if (!$is_paid): ?>
                <a href="checkout.php?reservation_id=<?php echo $reservation_id; ?>" class="checkout-mini">
                    <i class="bi bi-credit-card"></i> Pay
                </a>
            <?php else: ?>
                <span class="paid-badge"><i class="bi bi-check-circle"></i> Paid</span>
            <?php endif; ?>
        </div>
    <?php endif;
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Gourmet Reserve</title>
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
            --warning: #f59e0b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            min-height: 100vh;
            color: #fff;
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .back-btn, .new-reservation-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        .new-reservation-btn {
            background: var(--primary);
            border: 1px solid var(--primary);
            color: #1a1a2e;
        }

        .new-reservation-btn:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .welcome-msg {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .welcome-msg h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .welcome-msg p {
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }

        .reservations-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .reservation-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            overflow: hidden;
        }

        .reservation-header {
            padding: 1.5rem;
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .reservation-info h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .reservation-info p {
            color: rgba(255,255,255,0.6);
            font-size: 0.875rem;
        }

        .reservation-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }

        .meta-item i {
            color: var(--primary);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(245,158,11,0.2);
            color: var(--warning);
            border: 1px solid rgba(245,158,11,0.3);
        }

        .status-confirmed {
            background: rgba(34,197,94,0.2);
            color: var(--success);
            border: 1px solid rgba(34,197,94,0.3);
        }

        .status-cancelled {
            background: rgba(233,69,96,0.2);
            color: var(--accent);
            border: 1px solid rgba(233,69,96,0.3);
        }

        .reservation-body {
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1rem;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preorder-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .preorder-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }

        .preorder-item:last-child {
            border-bottom: none;
        }

        .preorder-item-info h4 {
            font-size: 0.95rem;
            font-weight: 500;
        }

        .preorder-item-info p {
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
        }

        .preorder-item-price {
            color: var(--primary);
            font-weight: 600;
        }

        .preorder-total {
            display: flex;
            justify-content: space-between;
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 2px solid rgba(201,162,39,0.3);
            font-weight: 600;
        }

        .preorder-total span:last-child {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .manage-preorder-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1.25rem; background: rgba(201,162,39,0.2);
            border: 1px solid var(--primary); border-radius: 8px;
            color: var(--primary); text-decoration: none; font-weight: 500;
            cursor: pointer; transition: all 0.3s; margin-top: 1rem;
        }
        .manage-preorder-btn:hover { background: var(--primary); color: #1a1a2e; }
        .preorder-panel {
            display: none; margin-top: 1.5rem; padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .preorder-panel.active { display: block; }
        .panel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .menu-list { max-height: 400px; overflow-y: auto; padding-right: 0.5rem; }
        .menu-list::-webkit-scrollbar { width: 6px; }
        .menu-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }
        .menu-category-mini { margin-bottom: 1.5rem; }
        .menu-category-mini:last-child { margin-bottom: 0; }
        .menu-category-mini h4 {
            font-family: 'Playfair Display', serif; font-size: 1rem;
            color: var(--primary); margin-bottom: 0.75rem;
            padding-bottom: 0.25rem; border-bottom: 1px solid rgba(201,162,39,0.3);
        }
        .menu-item-mini {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.75rem; background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05); border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .menu-item-mini h5 { font-size: 0.9rem; margin-bottom: 0.25rem; }
        .menu-item-mini small { color: rgba(255,255,255,0.5); font-size: 0.75rem; display: block; }
        .menu-item-mini .price { color: var(--primary); font-weight: 600; font-size: 0.9rem; }
        .add-form-mini { display: flex; gap: 0.5rem; align-items: center; }
        .add-form-mini input[type="number"] {
            width: 45px; padding: 0.25rem; background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 4px;
            color: #fff; text-align: center; font-size: 0.875rem;
        }
        .add-form-mini input[type="text"] {
            width: 80px; padding: 0.25rem; background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 4px;
            color: #fff; font-size: 0.75rem;
        }
        .add-form-mini input::placeholder { color: rgba(255,255,255,0.5); }
        .add-btn-mini {
            padding: 0.4rem 0.75rem; background: var(--primary); border: none;
            border-radius: 6px; color: #1a1a2e; font-weight: 600; font-size: 0.8rem;
            cursor: pointer; transition: all 0.3s;
        }
        .add-btn-mini:hover { background: var(--primary-dark); }
        .add-btn-mini:disabled { opacity: 0.5; cursor: not-allowed; }
        .cart-panel {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px; padding: 1rem;
        }
        .cart-panel h4 { font-size: 1rem; margin-bottom: 1rem; color: var(--primary); }
        .empty-order-mini {
            text-align: center; padding: 2rem 0; color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
        }
        .preorder-item-mini {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .preorder-item-mini:last-child { border-bottom: none; }
        .preorder-item-mini h5 { font-size: 0.85rem; font-weight: 500; }
        .preorder-item-mini small { color: rgba(255,255,255,0.4); font-size: 0.7rem; display: block; }
        .preorder-item-actions { display: flex; align-items: center; gap: 0.5rem; }
        .qty-btn-mini {
            width: 22px; height: 22px; background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 4px;
            color: #fff; cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 0.75rem; transition: all 0.3s;
        }
        .qty-btn-mini:hover { background: var(--primary); color: #1a1a2e; }
        .qty-input-mini {
            width: 35px; padding: 0.2rem; background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 4px;
            color: #fff; text-align: center; font-size: 0.75rem;
        }
        .qty-display { background: rgba(255,255,255,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; }
        .item-price { color: var(--primary); font-weight: 600; font-size: 0.85rem; white-space: nowrap; }
        .remove-btn-mini {
            width: 22px; height: 22px; background: rgba(233,69,96,0.2);
            border: 1px solid rgba(233,69,96,0.3); border-radius: 4px;
            color: var(--accent); cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: 0.7rem;
            transition: all 0.3s;
        }
        .remove-btn-mini:hover { background: var(--accent); color: #fff; }
        .mini-total {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 0.75rem; margin-top: 0.75rem;
            border-top: 1px solid rgba(201,162,39,0.3); font-weight: 600;
        }
        .mini-total span:first-child { color: var(--primary); }
        .checkout-mini {
            padding: 0.5rem 1rem; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none; border-radius: 6px; color: #1a1a2e;
            font-size: 0.8rem; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.25rem;
        }
        .paid-badge { color: var(--success); font-size: 0.8rem; }
        .toast {
            position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem;
            border-radius: 12px; color: #fff; font-weight: 500; z-index: 1000;
            transform: translateX(150%); transition: transform 0.3s ease;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .toast.show { transform: translateX(0); }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--accent); }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: rgba(255,255,255,0.2);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: rgba(255,255,255,0.6);
            margin-bottom: 2rem;
        }

        .guest-notice {
            background: rgba(201,162,39,0.1);
            border: 1px solid rgba(201,162,39,0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .guest-notice i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .guest-notice p {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
        }

        .guest-notice a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .header { flex-direction: column; text-align: center; }
            .header h1 { font-size: 1.75rem; }
            .header-actions { width: 100%; justify-content: center; }
            .reservation-header { flex-direction: column; }
            .reservation-meta { width: 100%; }
            .panel-grid { grid-template-columns: 1fr; }
            .menu-list { max-height: 300px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="bi bi-calendar-check"></i> My Reservations</h1>
            <div class="header-actions">
                <a href="index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <a href="index.php" class="new-reservation-btn">
                    <i class="bi bi-plus-lg"></i> New Reservation
                </a>
            </div>
        </div>

        <?php if (!$is_logged_in): ?>
            <div class="guest-notice">
                <i class="bi bi-info-circle"></i>
                <p>
                    You're viewing reservations as a guest. 
                    <a href="login.php">Log in</a> or <a href="signup.php">sign up</a> 
                    to save your reservations permanently and manage them anytime.
                </p>
            </div>
        <?php else: ?>
            <div class="welcome-msg">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                <p>Here are all your reservations and pre-orders.</p>
            </div>
        <?php endif; ?>

        <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h3>No Reservations Found</h3>
                <p>You don't have any reservations yet. Make your first reservation now!</p>
                <a href="index.php" class="new-reservation-btn">
                    <i class="bi bi-calendar-plus"></i> Make a Reservation
                </a>
            </div>
        <?php else: ?>
            <div class="reservations-list">
                <?php foreach ($reservations as $reservation): ?>
                    <div class="reservation-card">
                        <div class="reservation-header">
                            <div class="reservation-info">
                                <h3>
                                    <i class="bi bi-shop"></i>
                                    Table for <?php echo $reservation['guests']; ?> 
                                    (<?php echo ucfirst($reservation['table_type']); ?>)
                                </h3>
                                <p>Reservation #<?php echo $reservation['id']; ?> • Booked on <?php echo date('M j, Y', strtotime($reservation['created_at'])); ?></p>
                            </div>
                            <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                <?php echo ucfirst($reservation['status']); ?>
                            </span>
                        </div>
                        
                        <div class="reservation-body">
                            <div class="reservation-meta">
                                <div class="meta-item">
                                    <i class="bi bi-calendar-event"></i>
                                    <span><?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="bi bi-person"></i>
                                    <span><?php echo htmlspecialchars($reservation['name']); ?></span>
                                </div>
                            </div>

                            <?php if (!empty($reservation['preorders']) || !$reservation['is_paid']): ?>
                                <button type="button" class="manage-preorder-btn" onclick="togglePreorderPanel(<?php echo $reservation['id']; ?>)">
                                    <i class="bi bi-cart-plus"></i>
                                    <?php echo empty($reservation['preorders']) ? 'Add Pre-order' : 'Manage Pre-order'; ?>
                                    (<?php echo count($reservation['preorders']); ?> items)
                                </button>
                                
                                <div class="preorder-panel" id="preorder-panel-<?php echo $reservation['id']; ?>">
                                    <div class="panel-grid">
                                        <div class="menu-section-mini">
                                            <h4 class="section-title"><i class="bi bi-journal-text"></i> Menu</h4>
                                            <div class="menu-list">
                                                <?php foreach ($menu_items_all as $category => $items): ?>
                                                    <div class="menu-category-mini">
                                                        <h4><?php echo htmlspecialchars($category); ?></h4>
                                                        <?php foreach ($items as $item): ?>
                                                            <div class="menu-item-mini">
                                                                <div>
                                                                    <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                                                                    <small><?php echo htmlspecialchars($item['description']); ?></small>
                                                                    <div class="price">$<?php echo number_format($item['price'], 2); ?></div>
                                                                </div>
                                                                <?php if (!$reservation['is_paid']): ?>
                                                                    <form class="add-form-mini" onsubmit="return addItem(event, <?php echo $reservation['id']; ?>);">
                                                                        <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                                                                        <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                                        <input type="number" name="quantity" value="1" min="1" max="10">
                                                                        <input type="text" name="special_instructions" placeholder="Note...">
                                                                        <button type="submit" class="add-btn-mini">Add</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="cart-panel">
                                            <h4><i class="bi bi-receipt"></i> Your Order</h4>
                                            <div class="cart-items" id="cart-<?php echo $reservation['id']; ?>">
                                                <?php echo getCartHtml($reservation['preorders'], $reservation['preorder_total'] ?? 0, $reservation['is_paid'], $reservation['id']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="preorder-section">
                                    <h4 class="section-title"><i class="bi bi-cart-check"></i> Pre-ordered Items</h4>
                                    <?php foreach ($reservation['preorders'] as $item): ?>
                                        <div class="preorder-item">
                                            <div class="preorder-item-info">
                                                <h4><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></h4>
                                                <?php if ($item['special_instructions']): ?>
                                                    <p><?php echo htmlspecialchars($item['special_instructions']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="preorder-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="preorder-total">
                                        <span>Pre-order Total</span>
                                        <span>$<?php echo number_format($reservation['preorder_total'] ?? 0, 2); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        function togglePreorderPanel(reservationId) {
            const panel = document.getElementById('preorder-panel-' + reservationId);
            panel.classList.toggle('active');
        }
        
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.className = 'toast ' + type;
            toast.innerHTML = type === 'success' 
                ? '<i class="bi bi-check-circle"></i> ' + message
                : '<i class="bi bi-exclamation-circle"></i> ' + message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        function addItem(event, reservationId) {
            event.preventDefault();
            const form = event.target;
            const btn = form.querySelector('.add-btn-mini');
            btn.disabled = true;
            btn.textContent = '...';
            
            const formData = new FormData(form);
            formData.append('add_to_order', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cart-' + reservationId).innerHTML = data.cart_html;
                    showToast(data.message, 'success');
                    form.querySelector('input[name="quantity"]').value = 1;
                    form.querySelector('input[name="special_instructions"]').value = '';
                    updateButtonCount(reservationId, data.cart_html);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => showToast('Error adding item', 'error'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Add'; });
            
            return false;
        }
        
        function updateQty(btn, delta) {
            const form = btn.closest('form');
            const input = form.querySelector('input[name="new_quantity"]');
            let val = parseInt(input.value) + delta;
            val = Math.max(0, Math.min(20, val));
            input.value = val;
            submitQty(input);
        }
        
        function submitQty(input) {
            const form = input.closest('form');
            const reservationId = form.dataset.reservation;
            const formData = new FormData(form);
            formData.append('update_item', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cart-' + reservationId).innerHTML = data.cart_html;
                    updateButtonCount(reservationId, data.cart_html);
                }
            });
        }
        
        function removeItem(itemId, reservationId) {
            if (!confirm('Remove this item?')) return;
            
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('reservation_id', reservationId);
            formData.append('remove_item', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cart-' + reservationId).innerHTML = data.cart_html;
                    showToast(data.message, 'success');
                    updateButtonCount(reservationId, data.cart_html);
                }
            });
        }
        
        function updateButtonCount(reservationId, cartHtml) {
            const btn = document.querySelector(`button[onclick="togglePreorderPanel(${reservationId})"]`);
            if (btn) {
                const count = (cartHtml.match(/preorder-item-mini/g) || []).length;
                btn.innerHTML = count > 0 
                    ? `<i class="bi bi-cart-plus"></i> Manage Pre-order (${count} items)`
                    : `<i class="bi bi-cart-plus"></i> Add Pre-order (0 items)`;
            }
        }
    </script>
</body>
</html>
