<?php
session_start();
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_name = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gourmet Reserve - Table Reservations</title>
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
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            min-height: 100vh;
            color: #fff;
        }

        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(26,26,46,0.95) 0%, rgba(22,33,62,0.95) 100%),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="%23c9a227" stroke-width="0.5" opacity="0.3"/></svg>');
            background-size: cover, 200px 200px;
            padding: 2rem;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at top, rgba(201,162,39,0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .container {
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.8);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .features {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: rgba(201,162,39,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .feature-icon i {
            font-size: 1.5rem;
        }

        .reservation-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .card-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .card-header p {
            color: rgba(255,255,255,0.6);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
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
            transition: color 0.3s;
        }

        .form-group:focus-within .form-label {
            color: var(--primary);
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

        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23c9a227' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 3rem;
        }

        select.form-input option {
            background: var(--dark);
            color: #fff;
            padding: 0.5rem;
        }

        .table-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .table-option {
            position: relative;
        }

        .table-option input {
            position: absolute;
            opacity: 0;
        }

        .table-option label {
            display: block;
            padding: 1rem;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .table-option label:hover {
            background: rgba(255,255,255,0.1);
        }

        .table-option input:checked + label {
            border-color: var(--primary);
            background: rgba(201,162,39,0.15);
            color: var(--primary);
        }

        .table-icon {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .table-icon i {
            display: block;
        }

        .table-name {
            font-size: 0.75rem;
            font-weight: 500;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 100px;
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
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(201,162,39,0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .view-reservations {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: rgba(255,255,255,0.1);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .view-reservations i {
            margin-right: 0.5rem;
        }

        /* Success Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal {
            background: var(--dark);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 3rem;
            text-align: center;
            max-width: 400px;
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .modal-overlay.active .modal {
            transform: scale(1);
        }

        .view-reservations:hover {
            background: var(--primary);
            color: #1a1a2e;
        }

        /* User Account Section */
        .user-section {
            position: absolute;
            top: 2rem;
            right: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255,255,255,0.05);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #1a1a2e;
            font-size: 0.875rem;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .auth-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .auth-btn {
            padding: 0.625rem 1.25rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .auth-btn-login {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
        }

        .auth-btn-login:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.5);
        }

        .auth-btn-signup {
            background: var(--primary);
            border: 1px solid var(--primary);
            color: #1a1a2e;
        }

        .auth-btn-signup:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .logout-btn {
            padding: 0.5rem 1rem;
            background: rgba(233,69,96,0.2);
            border: 1px solid rgba(233,69,96,0.3);
            border-radius: 50px;
            color: var(--accent);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: var(--accent);
            color: #fff;
        }

        .my-reservations-btn {
            padding: 0.5rem 1rem;
            background: rgba(34,197,94,0.2);
            border: 1px solid rgba(34,197,94,0.3);
            border-radius: 50px;
            color: var(--success);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .my-reservations-btn:hover {
            background: var(--success);
            color: #fff;
        }

        /* View Menu Button */
        .view-menu-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            background: rgba(201,162,39,0.2);
            border: 2px solid var(--primary);
            border-radius: 50px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .view-menu-btn:hover {
            background: var(--primary);
            color: #1a1a2e;
            transform: translateY(-2px);
        }

        /* Menu Modal */
        .menu-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
            padding: 2rem;
        }

        .menu-modal.active {
            display: flex;
            opacity: 1;
        }

        .menu-content {
            background: var(--dark);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .menu-modal.active .menu-content {
            transform: scale(1);
        }

        .menu-header {
            padding: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--dark);
            z-index: 10;
        }

        .menu-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--primary);
        }

        .close-menu {
            background: rgba(255,255,255,0.1);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-menu:hover {
            background: var(--accent);
        }

        .menu-body {
            padding: 2rem;
        }

        .menu-category {
            margin-bottom: 2rem;
        }

        .menu-category h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(201,162,39,0.3);
        }

        .menu-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .menu-item:last-child {
            border-bottom: none;
        }

        .menu-item-info h4 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .menu-item-info p {
            color: rgba(255,255,255,0.6);
            font-size: 0.875rem;
        }

        .menu-item-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            white-space: nowrap;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .modal-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .modal h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .modal p {
            color: rgba(255,255,255,0.7);
            margin-bottom: 2rem;
        }

        .modal-btn {
            padding: 1rem 2rem;
            background: var(--primary);
            border: none;
            border-radius: 12px;
            color: #1a1a2e;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-btn:hover {
            background: var(--primary-dark);
        }

        /* Loading State */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s linear infinite;
            margin-left: 10px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Error Message */
        .error-message {
            background: rgba(233,69,96,0.15);
            border: 1px solid var(--accent);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #ff6b7a;
            display: none;
        }

        .error-message.show {
            display: block;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        /* Extra Small Devices (phones, 576px and down) */
        @media (max-width: 576px) {
            .hero {
                padding: 1rem;
                min-height: auto;
            }

            .hero-content h1 {
                font-size: 1.75rem;
                text-align: center;
            }

            .hero-content p {
                font-size: 1rem;
                text-align: center;
            }

            .features {
                flex-direction: column;
                gap: 1rem;
                align-items: center;
            }

            .feature {
                font-size: 0.875rem;
            }

            .feature-icon {
                width: 36px;
                height: 36px;
            }

            .feature-icon i {
                font-size: 1.25rem;
            }

            .user-section {
                position: relative;
                top: auto;
                right: auto;
                justify-content: center;
                flex-wrap: wrap;
                margin-bottom: 1rem;
                gap: 0.5rem;
            }

            .auth-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .auth-btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .reservation-card {
                padding: 1.25rem;
                border-radius: 16px;
            }

            .card-header h2 {
                font-size: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-input {
                padding: 0.875rem 1rem;
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .table-options {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .table-option label {
                padding: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .table-icon {
                margin-bottom: 0;
                font-size: 1.25rem;
            }

            .table-name {
                font-size: 0.875rem;
            }

            .submit-btn {
                padding: 1rem;
                font-size: 1rem;
            }

            .modal {
                padding: 2rem;
                margin: 1rem;
            }

            .modal h3 {
                font-size: 1.5rem;
            }

            .modal p {
                font-size: 0.9rem;
            }
        }

        /* Small Devices (landscape phones, 577px to 768px) */
        @media (min-width: 577px) and (max-width: 768px) {
            .hero {
                padding: 1.5rem;
            }

            .hero-content h1 {
                font-size: 2rem;
                text-align: center;
            }

            .hero-content p {
                text-align: center;
            }

            .features {
                justify-content: center;
                flex-wrap: wrap;
                gap: 1.5rem;
            }

            .container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .user-section {
                position: relative;
                top: auto;
                right: auto;
                justify-content: center;
                margin-bottom: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }

            .table-options {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Medium Devices (tablets, 769px to 991px) */
        @media (min-width: 769px) and (max-width: 991px) {
            .container {
                grid-template-columns: 1fr;
                gap: 3rem;
                max-width: 600px;
            }

            .hero-content {
                text-align: center;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .features {
                justify-content: center;
            }

            .user-section {
                position: relative;
                top: auto;
                right: auto;
                justify-content: center;
                margin-bottom: 1rem;
            }
        }

        /* Large Devices (desktops, 992px to 1199px) */
        @media (min-width: 992px) and (max-width: 1199px) {
            .container {
                gap: 3rem;
                max-width: 1000px;
            }

            .hero-content h1 {
                font-size: 3rem;
            }
        }

        /* Extra Large Devices (large desktops, 1200px and up) */
        @media (min-width: 1200px) {
            .container {
                max-width: 1200px;
            }
        }

        /* Handle very small height screens */
        @media (max-height: 700px) {
            .hero {
                min-height: auto;
                padding: 4rem 1rem;
            }
        }

        /* Handle landscape orientation on mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .hero {
                min-height: auto;
                padding: 2rem 1rem;
            }

            .container {
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .features {
                flex-wrap: wrap;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="user-section">
            <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                </div>
                <a href="my_reservations.php" class="my-reservations-btn">
                    <i class="bi bi-calendar-check"></i> My Reservations
                </a>
                <a href="logout.php" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</a>
            <?php else: ?>
                <div class="auth-buttons">
                    <a href="my_reservations.php" class="auth-btn auth-btn-login">
                        <i class="bi bi-calendar-check"></i> My Reservations
                    </a>
                    <a href="login.php" class="auth-btn auth-btn-login"><i class="bi bi-person"></i> Login</a>
                    <a href="signup.php" class="auth-btn auth-btn-signup"><i class="bi bi-person-plus"></i> Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="container">
            <div class="hero-content">
                <h1>Reserve Your Table</h1>
                <p>Experience culinary excellence at Gourmet Reserve. Book your table now for an unforgettable dining experience.</p>
                <div class="features">
                    <div class="feature">
                        <div class="feature-icon"><i class="bi bi-cup-hot-fill"></i></div>
                        <span>Fine Dining</span>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="bi bi-star-fill"></i></div>
                        <span>5-Star Service</span>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="bi bi-wine"></i></div>
                        <span>Premium Selection</span>
                    </div>
                </div>
                <a href="#menu" class="view-menu-btn" onclick="showMenu(); return false;">
                    <i class="bi bi-book-open"></i> View Our Menu
                </a>
            </div>

            <div class="reservation-card">
                <div class="card-header">
                    <h2>Make a Reservation</h2>
                    <p>Fill in your details below</p>
                </div>

                <div class="error-message" id="errorMessage"></div>

                <form id="reservationForm" action="submit.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-input" placeholder="John Doe" required 
                                value="<?php echo $is_logged_in ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" placeholder="+1 234 567 890" required
                                value="<?php echo $is_logged_in ? htmlspecialchars($_SESSION['user_phone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" placeholder="john@example.com" required
                            value="<?php echo $is_logged_in ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-input" required min="">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time</label>
                            <select name="time" class="form-input" required>
                                <option value="">Select time...</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="11:30">11:30 AM</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="12:30">12:30 PM</option>
                                <option value="13:00">1:00 PM</option>
                                <option value="13:30">1:30 PM</option>
                                <option value="17:00">5:00 PM</option>
                                <option value="17:30">5:30 PM</option>
                                <option value="18:00">6:00 PM</option>
                                <option value="18:30">6:30 PM</option>
                                <option value="19:00">7:00 PM</option>
                                <option value="19:30">7:30 PM</option>
                                <option value="20:00">8:00 PM</option>
                                <option value="20:30">8:30 PM</option>
                                <option value="21:00">9:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Number of Guests</label>
                        <input type="number" name="guests" class="form-input" placeholder="2" min="1" max="20" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Table Preference</label>
                        <div class="table-options">
                            <div class="table-option">
                                <input type="radio" name="table_type" id="indoor" value="indoor" checked>
                                <label for="indoor">
                                    <div class="table-icon"><i class="bi bi-house-door-fill"></i></div>
                                    <div class="table-name">Indoor</div>
                                </label>
                            </div>
                            <div class="table-option">
                                <input type="radio" name="table_type" id="outdoor" value="outdoor">
                                <label for="outdoor">
                                    <div class="table-icon"><i class="bi bi-tree-fill"></i></div>
                                    <div class="table-name">Outdoor</div>
                                </label>
                            </div>
                            <div class="table-option">
                                <input type="radio" name="table_type" id="private" value="private">
                                <label for="private">
                                    <div class="table-icon"><i class="bi bi-shield-lock-fill"></i></div>
                                    <div class="table-name">Private</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Special Requests (Optional)</label>
                        <textarea name="requests" class="form-input" placeholder="Any dietary restrictions, special occasions, seating preferences..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        Confirm Reservation
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal">
            <div class="modal-icon"><i class="bi bi-check-lg"></i></div>
            <h3>Reservation Confirmed!</h3>
            <p>Thank you for your reservation. We've sent a confirmation email with all the details.</p>
            <div id="reservationIdDisplay" style="display: none;"></div>
            <button class="modal-btn" onclick="startPreorder()" style="margin-bottom: 0.5rem;">
                <i class="bi bi-cart-plus"></i> Pre-order Food
            </button>
            <button class="modal-btn" onclick="closeModal()" style="background: transparent; border: 1px solid rgba(255,255,255,0.3); color: #fff;">
                Maybe Later
            </button>
        </div>
    </div>

    <!-- Menu Modal -->
    <div class="menu-modal" id="menuModal">
        <div class="menu-content">
            <div class="menu-header">
                <h2><i class="bi bi-journal-text"></i> Our Menu</h2>
                <button class="close-menu" onclick="closeMenu()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="menu-body">
                <div class="menu-category">
                    <h3><i class="bi bi-egg-fried"></i> Appetizers</h3>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Truffle Arancini</h4>
                            <p>Crispy risotto balls infused with black truffle, served with garlic aioli</p>
                        </div>
                        <div class="menu-item-price">$14</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Burrata Salad</h4>
                            <p>Fresh burrata with heirloom tomatoes, basil, and balsamic glaze</p>
                        </div>
                        <div class="menu-item-price">$16</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Crispy Calamari</h4>
                            <p>Tender calamari rings, lightly fried with lemon herb seasoning</p>
                        </div>
                        <div class="menu-item-price">$15</div>
                    </div>
                </div>

                <div class="menu-category">
                    <h3><i class="bi bi-shop"></i> Main Courses</h3>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Wagyu Beef Burger</h4>
                            <p>Premium wagyu patty with caramelized onions, aged cheddar, brioche bun</p>
                        </div>
                        <div class="menu-item-price">$28</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Pan-Seared Salmon</h4>
                            <p>Atlantic salmon with lemon butter sauce, seasonal vegetables</p>
                        </div>
                        <div class="menu-item-price">$32</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Truffle Pasta</h4>
                            <p>House-made fettuccine with black truffle cream and parmesan</p>
                        </div>
                        <div class="menu-item-price">$26</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Ribeye Steak</h4>
                            <p>12oz prime ribeye with herb butter, roasted garlic mashed potatoes</p>
                        </div>
                        <div class="menu-item-price">$45</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Chicken Marsala</h4>
                            <p>Free-range chicken with wild mushrooms and marsala wine sauce</p>
                        </div>
                        <div class="menu-item-price">$24</div>
                    </div>
                </div>

                <div class="menu-category">
                    <h3><i class="bi bi-cake2"></i> Desserts</h3>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Tiramisu</h4>
                            <p>Classic Italian dessert with espresso-soaked ladyfingers and mascarpone</p>
                        </div>
                        <div class="menu-item-price">$12</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Chocolate Lava Cake</h4>
                            <p>Warm chocolate cake with molten center, vanilla ice cream</p>
                        </div>
                        <div class="menu-item-price">$14</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Crème Brûlée</h4>
                            <p>Vanilla bean custard with caramelized sugar crust</p>
                        </div>
                        <div class="menu-item-price">$11</div>
                    </div>
                </div>

                <div class="menu-category">
                    <h3><i class="bi bi-cup-straw"></i> Beverages</h3>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>House Wine (Glass)</h4>
                            <p>Selection of red or white wine</p>
                        </div>
                        <div class="menu-item-price">$12</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Craft Cocktail</h4>
                            <p>Signature cocktails made with premium spirits</p>
                        </div>
                        <div class="menu-item-price">$15</div>
                    </div>
                    <div class="menu-item">
                        <div class="menu-item-info">
                            <h4>Sparkling Water</h4>
                            <p>Premium Italian sparkling water</p>
                        </div>
                        <div class="menu-item-price">$6</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set minimum date to today
        document.querySelector('input[type="date"]').min = new Date().toISOString().split('T')[0];

        // Form submission
        const form = document.getElementById('reservationForm');
        const submitBtn = document.getElementById('submitBtn');
        const errorMessage = document.getElementById('errorMessage');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Reset error
            errorMessage.classList.remove('show');
            errorMessage.textContent = '';

            // Show loading
            submitBtn.innerHTML = 'Processing<span class="loading"></span>';
            submitBtn.disabled = true;

            try {
                const formData = new FormData(form);
                const response = await fetch('submit.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    currentReservationId = result.reservation_id;
                    document.getElementById('successModal').classList.add('active');
                    form.reset();
                } else {
                    errorMessage.textContent = result.message || 'Something went wrong. Please try again.';
                    errorMessage.classList.add('show');
                }
            } catch (error) {
                errorMessage.textContent = 'Network error. Please check your connection and try again.';
                errorMessage.classList.add('show');
            } finally {
                submitBtn.innerHTML = 'Confirm Reservation';
                submitBtn.disabled = false;
            }
        });

        function closeModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        let currentReservationId = null;

        function startPreorder() {
            if (currentReservationId) {
                window.location.href = 'preorder.php?reservation_id=' + currentReservationId;
            }
        }

        function showMenu() {
            document.getElementById('menuModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            document.getElementById('menuModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        // Close menu on overlay click
        document.getElementById('menuModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMenu();
            }
        });

        // Input animations
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>
