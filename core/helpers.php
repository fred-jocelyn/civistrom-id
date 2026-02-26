<?php
declare(strict_types=1);

/**
 * Helpers globaux — Fonctions utilitaires CIVISTROM ID
 *
 * PWA Authenticator TOTP — helpers minimalistes.
 * Pas de i18n, pas de CSRF, pas de flash messages, pas de sessions.
 */

// ═══════════════════════════════════════════
// Environnement & Configuration
// ═══════════════════════════════════════════

/**
 * Récupère une variable d'environnement
 */
function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? (getenv($key) ?: $default);
}

/**
 * Récupère une valeur de configuration (notation pointée)
 */
function config(string $key, mixed $default = null): mixed
{
    return App::getInstance()->config($key, $default);
}

// ═══════════════════════════════════════════
// Sécurité & HTML
// ═══════════════════════════════════════════

/**
 * Échappe une valeur pour l'affichage HTML
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ═══════════════════════════════════════════
// URLs & Navigation
// ═══════════════════════════════════════════

/**
 * Génère une URL absolue
 */
function url(string $path = ''): string
{
    $base = rtrim(env('APP_URL', ''), '/');
    if ($path === '' || $path === '/') {
        return $base ?: '/';
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Génère l'URL d'un asset (CSS, JS, images)
 *
 * En dev MAMP (docroot = racine projet) → /public/assets/...
 * En prod Nginx (docroot = public/)     → /assets/...
 */
function asset(string $path): string
{
    $prefix = defined('PUBLIC_PREFIX') ? PUBLIC_PREFIX : '';
    return $prefix . '/assets/' . ltrim($path, '/');
}

// ═══════════════════════════════════════════
// Utilitaires
// ═══════════════════════════════════════════

/**
 * Dump and die — debug helper
 */
function dd(mixed ...$vars): never
{
    echo '<pre style="background:#0a0a0f;color:#EF4444;padding:15px;font-family:monospace;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit;
}

/**
 * Logger CIVISTROM ID
 */
function id_log(string $message, string $level = 'info'): void
{
    $logDir = defined('STORAGE_PATH')
        ? STORAGE_PATH . '/logs'
        : BASE_PATH . '/storage/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $date = date('Y-m-d H:i:s');
    $level = strtoupper($level);
    $line = "[{$date}] [{$level}] {$message}" . PHP_EOL;

    @file_put_contents($logDir . '/id.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Encode en JSON proprement
 */
function to_json(mixed $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
