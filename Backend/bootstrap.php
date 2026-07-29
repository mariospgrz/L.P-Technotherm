<?php
/**
 * Backend/bootstrap.php
 * Shared config loader, URL helpers, CSRF, and cron guards.
 */

if (!defined('LP_BOOTSTRAP_LOADED')) {
    define('LP_BOOTSTRAP_LOADED', true);

    $__lp_config_file = __DIR__ . '/config.php';
    if (file_exists($__lp_config_file)) {
        $GLOBALS['app_config'] = require $__lp_config_file;
    } else {
        $GLOBALS['app_config'] = [
            'base_path' => '',
            'debug_mode' => false,
        ];
    }
}

if (!function_exists('app_config')) {
    function app_config(?string $key = null, $default = null)
    {
        $config = $GLOBALS['app_config'] ?? [];
        if ($key === null) {
            return $config;
        }
        return $config[$key] ?? $default;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $base = rtrim((string) app_config('base_path', ''), '/');
        $path = ltrim($path, '/');
        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }
        return ($base === '' ? '' : $base) . '/' . $path;
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path): void
    {
        header('Location: ' . app_url($path));
        exit();
    }
}

if (!function_exists('csrf_token_from_request')) {
    function csrf_token_from_request(?array $jsonBody = null): string
    {
        if (isset($_POST['csrf_token'])) {
            return (string) $_POST['csrf_token'];
        }
        if ($jsonBody !== null && isset($jsonBody['csrf_token'])) {
            return (string) $jsonBody['csrf_token'];
        }
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['csrf_token'])) {
                return (string) $decoded['csrf_token'];
            }
        }
        return '';
    }
}

if (!function_exists('require_csrf')) {
    /**
     * Validate CSRF token. Exits with redirect (HTML) or JSON error.
     */
    function require_csrf(?string $token = null, bool $json = false, ?string $redirectPath = null): void
    {
        $token = $token ?? csrf_token_from_request();
        $valid = hash_equals($_SESSION['csrf_token'] ?? '', $token);
        if ($valid) {
            return;
        }

        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Άκυρο αίτημα (CSRF).']);
            exit();
        }

        $target = $redirectPath ?? 'login/login.html?error=' . urlencode('Άκυρο αίτημα. Ανανεώστε τη σελίδα.');
        redirect_to($target);
    }
}

if (!function_exists('require_cron_access')) {
    /**
     * Allow CLI, or HTTP with matching cron_secret (query ?key= or X-Cron-Key header).
     */
    function require_cron_access(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $secret = (string) app_config('cron_secret', '');
        $provided = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');

        if ($secret === '' || !hash_equals($secret, $provided)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Forbidden\n";
            exit();
        }
    }
}

if (!function_exists('load_vapid_keys')) {
    /**
     * Load VAPID keys from vapid.json (preferred) or config.php.
     * @return array{publicKey: string, privateKey: string}
     */
    function load_vapid_keys(): array
    {
        $vapidFile = __DIR__ . '/Notifications/vapid.json';
        if (file_exists($vapidFile)) {
            $keys = json_decode((string) file_get_contents($vapidFile), true);
            if (!empty($keys['publicKey']) && !empty($keys['privateKey'])) {
                return [
                    'publicKey' => (string) $keys['publicKey'],
                    'privateKey' => (string) $keys['privateKey'],
                ];
            }
        }

        $public = (string) app_config('vapid_public_key', '');
        $private = (string) app_config('vapid_private_key', '');
        if ($public !== '' && $private !== '') {
            return ['publicKey' => $public, 'privateKey' => $private];
        }

        throw new RuntimeException('VAPID keys missing. Run generate_vapid.php or set vapid_*. in config.php.');
    }
}

if (!function_exists('invoice_proxy_url')) {
    function invoice_proxy_url(int $invoiceId): string
    {
        return app_url('Backend/invoice_file.php?id=' . $invoiceId);
    }
}

if (!function_exists('user_assigned_to_project')) {
    function user_assigned_to_project(mysqli $conn, int $userId, int $projectId): bool
    {
        $stmt = $conn->prepare(
            'SELECT 1 FROM project_assignments WHERE project_id = ? AND user_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $projectId, $userId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }
}
