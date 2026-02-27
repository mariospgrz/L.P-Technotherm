<?php
// Backend/logout.php
// Destroys the user session and clears the JWT cookie (if used), then redirects to login.

session_start();

// 1. Remove all session variables
$_SESSION = [];

// 2. Destroy the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Clear the JWT cookie if it was set (cookie name must match wherever you set it)
if (isset($_COOKIE['jwt_token'])) {
    setcookie('jwt_token', '', time() - 3600, '/', '', true, true);
}

// 5. Prevent caching of the page after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// 6. Redirect to login page
header('Location: /login/login.html');
exit;
