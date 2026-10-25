<?php
// Shared security helpers: headers, session hardening and same-origin check

function send_security_headers(): void
{
    if (headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
         . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
         . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header_remove('X-Powered-By');
}

function start_secure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Log the user out after 30 minutes of inactivity
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > 1800) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = $now;
}

// If the browser sends an Origin header on a POST, it must match this site
function verify_same_origin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return;

    $parts      = parse_url($origin);
    $originHost = strtolower(($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    $serverHost = strtolower($_SERVER['HTTP_HOST'] ?? '');

    if (!hash_equals($serverHost, $originHost)) {
        http_response_code(403);
        message_page('Request blocked', 'This request came from a different website.');
    }
}