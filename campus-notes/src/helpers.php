<?php
declare(strict_types=1);

/** Escape for HTML text and quoted attribute contexts. */
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function redirect(string $path = '/'): never
{
    header('Location: ' . $path, true, 303); // 303: follow-up request is a GET
    exit;
}

function send_html_security_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}

function send_api_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('Vary: Origin, Cookie');
    // Deliberately NO Access-Control-Allow-* headers: the API is same-origin only.
}

function json_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function render(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/../templates/' . basename($template) . '.php';
}
