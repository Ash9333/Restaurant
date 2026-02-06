<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

require_once 'config.php';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php");
    exit;
}

// Handle status update
if (isset($_GET['status']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = in_array($_GET['status'], ['pending', 'confirmed', 'cancelled']) ? $_GET['status'] : 'pending';
    $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php");
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_login.php");
    exit;
}

// Fetch all reservations
$result = $conn->query("SELECT * FROM reservations ORDER BY reservation_date DESC, reservation_time DESC");
$reservations = [];
while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reservations - Gourmet Reserve</title>
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
        }

        .header {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--primary);
            color: #1a1a2e;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(233,69,96,0.2);
            border: 1px solid rgba(233,69,96,0.3);
            border-radius: 50px;
            color: var(--accent);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: var(--accent);
            color: #fff;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
        }

        .stats {
            display: flex;
            gap: 1.5rem;
        }

        .stat-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem 2rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.6);
        }

        .reservations-grid {
            display: grid;
            gap: 1rem;
        }

        .reservation-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.5rem;
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 1.5rem;
            align-items: center;
            transition: all 0.3s ease;
        }

        .reservation-card:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(201,162,39,0.3);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(245,158,11,0.2);
            color: var(--warning);
        }

        .status-confirmed {
            background: rgba(34,197,94,0.2);
            color: var(--success);
        }

        .status-cancelled {
            background: rgba(233,69,96,0.2);
            color: var(--accent);
        }

        .guest-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .guest-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a2e;
        }

        .guest-details h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .guest-details p {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.6);
        }

        .reservation-details {
            display: flex;
            gap: 2rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .detail-item i {
            color: var(--primary);
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .btn-confirm {
            background: rgba(34,197,94,0.2);
            color: var(--success);
        }

        .btn-confirm:hover {
            background: var(--success);
            color: #fff;
        }

        .btn-cancel {
            background: rgba(233,69,96,0.2);
            color: var(--accent);
        }

        .btn-cancel:hover {
            background: var(--accent);
            color: #fff;
        }

        .btn-delete {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.7);
        }

        .btn-delete:hover {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: rgba(255,255,255,0.3);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: rgba(255,255,255,0.6);
        }

        /* Responsive Styles */
        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
            }

            .back-btn, .logout-btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .stats {
                flex-wrap: wrap;
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem 1.5rem;
                flex: 1;
                min-width: 100px;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .reservation-card {
                grid-template-columns: 1fr;
                padding: 1rem;
                text-align: center;
            }

            .guest-info {
                flex-direction: column;
            }

            .guest-details h3 {
                font-size: 1rem;
            }

            .guest-details p {
                font-size: 0.8rem;
            }

            .reservation-details {
                flex-direction: column;
                gap: 0.5rem;
                font-size: 0.875rem;
            }

            .actions {
                justify-content: center;
                gap: 0.75rem;
            }

            .action-btn {
                width: 44px;
                height: 44px;
            }

            .empty-state {
                padding: 2rem 1rem;
            }

            .empty-state i {
                font-size: 3rem;
            }

            .empty-state h3 {
                font-size: 1.25rem;
            }
        }

        @media (min-width: 577px) and (max-width: 768px) {
            .header {
                padding: 1rem 1.5rem;
            }

            .reservation-card {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .reservation-details {
                flex-wrap: wrap;
                justify-content: center;
            }

            .container {
                padding: 1.5rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .stats {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (min-width: 769px) and (max-width: 991px) {
            .reservation-card {
                grid-template-columns: auto 1fr auto;
                gap: 1rem;
            }

            .reservation-details {
                grid-column: 1 / -1;
                justify-content: center;
                flex-wrap: wrap;
            }

            .actions {
                grid-column: 1 / -1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">Gourmet Reserve</div>
        <div class="header-actions">
            <a href="index.php" class="back-btn">
                <i class="bi bi-arrow-left"></i>
                Back to Reservation
            </a>
            <a href="?logout=1" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Reservations</h1>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($reservations); ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($reservations, fn($r) => $r['status'] === 'confirmed')); ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($reservations, fn($r) => $r['status'] === 'pending')); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>

        <div class="reservations-grid">
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h3>No Reservations Yet</h3>
                    <p>Start by making your first reservation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($reservations as $reservation): ?>
                    <div class="reservation-card">
                        <span class="status-badge status-<?php echo $reservation['status']; ?>">
                            <?php echo ucfirst($reservation['status']); ?>
                        </span>

                        <div class="guest-info">
                            <div class="guest-avatar">
                                <?php echo strtoupper(substr($reservation['name'], 0, 1)); ?>
                            </div>
                            <div class="guest-details">
                                <h3><?php echo htmlspecialchars($reservation['name']); ?></h3>
                                <p><?php echo htmlspecialchars($reservation['email']); ?> • <?php echo htmlspecialchars($reservation['phone']); ?></p>
                            </div>
                        </div>

                        <div class="reservation-details">
                            <div class="detail-item">
                                <i class="bi bi-calendar-event"></i>
                                <span><?php echo date('M j, Y', strtotime($reservation['reservation_date'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-clock"></i>
                                <span><?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-people"></i>
                                <span><?php echo $reservation['guests']; ?> guests</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-<?php echo $reservation['table_type'] === 'indoor' ? 'house-door' : ($reservation['table_type'] === 'outdoor' ? 'tree' : 'shield-lock'); ?>"></i>
                                <span><?php echo ucfirst($reservation['table_type']); ?></span>
                            </div>
                        </div>

                        <div class="actions">
                            <?php if ($reservation['status'] !== 'confirmed'): ?>
                                <a href="?status=confirmed&id=<?php echo $reservation['id']; ?>" class="action-btn btn-confirm" title="Confirm">
                                    <i class="bi bi-check-lg"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($reservation['status'] !== 'cancelled'): ?>
                                <a href="?status=cancelled&id=<?php echo $reservation['id']; ?>" class="action-btn btn-cancel" title="Cancel">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $reservation['id']; ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this reservation?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
