<?php
declare(strict_types=1);

/**
 * CIVISTROM ID — CLI (commandes utilitaires)
 *
 * Usage :
 *   php cli.php test [filter]  — Lance les tests unitaires
 */

define('BASE_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('ID_CLI', true);

require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/core/autoload.php';

// Charger .env manuellement (pas de session HTTP)
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (preg_match('/^(["\'])(.*)(\\1)$/', $value, $m)) {
                $value = $m[2];
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

$command = $argv[1] ?? 'help';
$arg2 = $argv[2] ?? null;

echo "\n  ╔═══════════════════════════════════════╗\n";
echo "  ║        CIVISTROM ID — CLI              ║\n";
echo "  ╚═══════════════════════════════════════╝\n\n";

try {
    switch ($command) {
        // ─── Tests ─────────────────────────────
        case 'test':
            echo "  Lancement des tests...\n\n";
            $filter = $arg2 ?? '';
            $filterArg = $filter !== '' ? " {$filter}" : '';
            $php = PHP_BINARY;
            passthru("{$php} " . BASE_PATH . "/tests/run.php{$filterArg}", $exitCode);
            exit($exitCode);

        // ─── Help ───────────────────────────────
        default:
            echo "  Commandes disponibles :\n";
            echo "    test [filter]  — Lancer les tests unitaires\n";
            break;
    }
} catch (\Throwable $e) {
    echo "  ✗ Erreur : {$e->getMessage()}\n";
    if (env('APP_DEBUG', 'false') === 'true') {
        echo "  {$e->getFile()}:{$e->getLine()}\n";
    }
}

echo "\n";
