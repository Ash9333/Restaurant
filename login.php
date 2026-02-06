<?php
session_start();
require_once 'config.php';

$error = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, name, email, phone, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_phone'] = $user['phone'];
                $_SESSION['logged_in'] = true;
                
                // Redirect to reservation page
                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gourmet Reserve</title>
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 2rem;
        }

        .login-container {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            background: rgba(201,162,39,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .login-icon i {
            font-size: 2.5rem;
            color: var(--primary);
        }

        .login-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: rgba(255,255,255,0.6);
        }

        .form-group {
            margin-bottom: 1.5rem;
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

        .form-input:focus {
            border-color: var(--primary);
            background: rgba(255,255,255,0.08);
            box-shadow: 0 0 20px rgba(201,162,39,0.2);
        }

        .submit-btn {
            width: 100%;
            padding: 1.25rem;
            margin-top: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
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

        .signup-link {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255,255,255,0.6);
        }

        .signup-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .guest-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .guest-link:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }

        .guest-link i {
            margin-right: 0.5rem;
        }

        /* Responsive Styles */
        @media (max-width: 576px) {
            body {
                padding: 1rem;
            }

            .login-container {
                padding: 2rem 1.5rem;
                border-radius: 16px;
            }

            .login-icon {
                width: 60px;
                height: 60px;
            }

            .login-icon i {
                font-size: 2rem;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .login-header p {
                font-size: 0.875rem;
            }

            .form-input {
                padding: 0.875rem 1rem;
                font-size: 16px;
            }

            .submit-btn {
                padding: 1rem;
                font-size: 1rem;
            }

            .guest-link {
                padding: 0.875rem;
                font-size: 0.875rem;
            }

            .signup-link {
                font-size: 0.875rem;
            }
        }

        @media (min-width: 577px) and (max-width: 768px) {
            .login-container {
                padding: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="bi bi-person-circle"></i>
            </div>
            <h1>Welcome Back</h1>
            <p>Log in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" placeholder="john@example.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
            </div>

            <button type="submit" name="login" class="submit-btn">
                <i class="bi bi-box-arrow-in-right"></i> Log In
            </button>
        </form>

        <a href="index.php" class="guest-link">
            <i class="bi bi-arrow-right-circle"></i> Continue as Guest
        </a>

        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
