<?php
session_start();


require_once 'Backend/Database.php';

// If user is already logged in, you can redirect them
if (isset($_SESSION['user_id'])) {
    // header('Location: dashboard.php');
    // exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // Redirect to a protected page
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'An error occurred. Please try again later.';
        }
    }
}
/*
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
        }
        .login-container {
            background: #ffffff;
            padding: 2.5rem 2.75rem;
            border-radius: 12px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            margin-bottom: 1.75rem;
            text-align: center;
        }
        .login-header h1 {
            font-size: 1.75rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        .login-header p {
            font-size: 0.95rem;
            color: #64748b;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border-radius: 8px;
            border: 1px solid #cbd5f0;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.25);
        }
        .error-message {
            margin-bottom: 1rem;
            padding: 0.75rem 0.9rem;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 0.9rem;
            border: 1px solid #fecaca;
        }
        .login-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.5rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            color: #475569;
        }
        .remember-me input[type="checkbox"] {
            width: 14px;
            height: 14px;
        }
        button[type="submit"] {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1.4rem;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 999px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
        }
        button[type="submit"]:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        button[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }
        .login-footer {
            margin-top: 1.6rem;
            text-align: center;
            font-size: 0.85rem;
            color: #64748b;
        }
        .login-footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .login-container {
                margin: 1.5rem;
                padding: 2rem 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome back</h1>
            <p>Sign in to access your L.P Technotherm account.</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    required
                    autocomplete="username"
                    value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''; ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="login-actions">
                <label class="remember-me">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
                <button type="submit">Log in</button>
            </div>
        </form>

        <div class="login-footer">
            <span>Forgot your password? Contact the system administrator.</span>
        </div>
    </div>
</body>
</html>

*/