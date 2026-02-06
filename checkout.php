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

// Fetch preorder items
$sql = "SELECT p.*, m.name, m.description FROM preorder_items p 
        JOIN menu_items m ON p.menu_item_id = m.id 
        WHERE p.reservation_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$preorder_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($preorder_items)) {
    header('Location: preorder.php?reservation_id=' . $reservation_id);
    exit;
}

$subtotal = array_sum(array_column($preorder_items, 'price'));
$tax_rate = 0.08; // 8% tax
$tax = $subtotal * $tax_rate;
$total = $subtotal + $tax;

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $card_name = trim($_POST['card_name'] ?? '');
    $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $expiry = trim($_POST['expiry'] ?? '');
    $cvv = trim($_POST['cvv'] ?? '');
    
    // Basic validation (in production, use a payment gateway)
    if (empty($card_name) || empty($card_number) || empty($expiry) || empty($cvv)) {
        $error = 'Please fill in all payment details';
    } elseif (strlen($card_number) < 13 || strlen($card_number) > 19) {
        $error = 'Please enter a valid card number';
    } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
        $error = 'Please enter a valid CVV';
    } elseif (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
        $error = 'Please enter expiry date in MM/YY format';
    } else {
        // In production, integrate with Stripe/PayPal here
        // For demo, we just mark as successful
        $success = true;
        
        // Store payment info (in production, store payment intent ID, not card details!)
        $sql = "UPDATE reservations SET status = 'confirmed', special_requests = CONCAT(IFNULL(special_requests, ''), '\n[PRE-ORDER PAID: $" . number_format($total, 2) . "]') WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Gourmet Reserve</title>
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
            max-width: 900px;
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

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
            margin-bottom: 2rem;
        }

        .back-btn:hover {
            background: var(--primary);
            color: #1a1a2e;
        }

        .checkout-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 2.5rem;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .order-summary {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .order-item:last-of-type {
            border-bottom: none;
        }

        .order-item-info h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .order-item-info p {
            color: rgba(255,255,255,0.5);
            font-size: 0.875rem;
        }

        .order-item-price {
            font-weight: 600;
            color: var(--primary);
        }

        .order-totals {
            border-top: 2px solid rgba(255,255,255,0.1);
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            color: rgba(255,255,255,0.7);
        }

        .total-row.final {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 0.5rem;
            padding-top: 1rem;
        }

        .total-row.final span:last-child {
            color: var(--primary);
        }

        .payment-form {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: rgba(255,255,255,0.8);
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input::placeholder {
            color: rgba(255,255,255,0.4);
        }

        .form-input:focus {
            border-color: var(--primary);
            background: rgba(255,255,255,0.08);
            box-shadow: 0 0 20px rgba(201,162,39,0.2);
        }

        .card-icons {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .card-icon {
            width: 40px;
            height: 26px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
        }

        .submit-btn {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(201,162,39,0.3);
        }

        .error-message {
            background: rgba(233,69,96,0.15);
            border: 1px solid var(--accent);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #ff6b7a;
            text-align: center;
        }

        .success-message {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 3rem;
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
        }

        .success-icon i {
            font-size: 3rem;
            color: white;
        }

        .success-message h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .success-message p {
            color: rgba(255,255,255,0.7);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .home-btn {
            padding: 1rem 2rem;
            background: var(--primary);
            border: none;
            border-radius: 12px;
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .home-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: rgba(255,255,255,0.5);
            font-size: 0.875rem;
            margin-top: 1.5rem;
        }

        .secure-badge i {
            color: var(--success);
        }

        /* Responsive */
        @media (max-width: 576px) {
            body {
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.75rem;
            }

            .checkout-card {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="preorder.php?reservation_id=<?php echo $reservation_id; ?>" class="back-btn">
            <i class="bi bi-arrow-left"></i> Back to Order
        </a>

        <?php if ($success): ?>
            <div class="success-message">
                <div class="success-icon">
                    <i class="bi bi-check-lg"></i>
                </div>
                <h2>Payment Successful!</h2>
                <p>
                    Thank you for your order. Your pre-order has been confirmed and will be ready<br>
                    when you arrive for your reservation on <?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?> at <?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?>.
                </p>
                <p style="font-size: 1.25rem; color: var(--primary); margin-bottom: 1.5rem;">
                    Total Paid: $<?php echo number_format($total, 2); ?>
                </p>
                <a href="index.php" class="home-btn">
                    <i class="bi bi-house"></i> Return to Home
                </a>
            </div>
        <?php else: ?>
            <div class="header">
                <h1><i class="bi bi-credit-card"></i> Checkout</h1>
                <p>Complete your pre-order payment</p>
            </div>

            <div class="checkout-card">
                <h2 class="section-title"><i class="bi bi-receipt"></i> Order Summary</h2>
                
                <div class="order-summary">
                    <?php foreach ($preorder_items as $item): ?>
                        <div class="order-item">
                            <div class="order-item-info">
                                <h4><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></h4>
                                <?php if ($item['special_instructions']): ?>
                                    <p><?php echo htmlspecialchars($item['special_instructions']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="order-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span>$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Tax (8%)</span>
                            <span>$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="total-row final">
                            <span>Total</span>
                            <span>$<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" class="payment-form">
                    <h2 class="section-title"><i class="bi bi-shield-lock"></i> Payment Details</h2>
                    
                    <div class="form-group">
                        <label class="form-label">Cardholder Name</label>
                        <input type="text" name="card_name" class="form-input" placeholder="John Doe" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Card Number</label>
                        <input type="text" name="card_number" class="form-input" placeholder="1234 5678 9012 3456" maxlength="19" required>
                        <div class="card-icons">
                            <div class="card-icon">VISA</div>
                            <div class="card-icon">MC</div>
                            <div class="card-icon">AMEX</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input type="text" name="expiry" class="form-input" placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">CVV</label>
                            <input type="text" name="cvv" class="form-input" placeholder="123" maxlength="4" required>
                        </div>
                    </div>

                    <button type="submit" name="place_order" class="submit-btn">
                        <i class="bi bi-lock"></i> Pay $<?php echo number_format($total, 2); ?>
                    </button>

                    <div class="secure-badge">
                        <i class="bi bi-shield-check"></i>
                        <span>Secure 256-bit SSL encrypted payment</span>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
