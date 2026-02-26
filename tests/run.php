<?php
declare(strict_types=1);

/**
 * CIVISTROM ID Test Runner — Point d'entrée CLI
 *
 * Usage :
 *   php tests/run.php              # Tous les tests
 *   php tests/run.php Health       # Filtre par nom de fichier
 */

if (php_sapi_name() !== 'cli') {
    echo 'Les tests doivent être lancés en CLI.';
    exit(1);
}

require_once __DIR__ . '/framework/bootstrap.php';
require_once __DIR__ . '/framework/TestCase.php';
require_once __DIR__ . '/framework/TestRunner.php';

$filter = $argv[1] ?? null;
$runner = new TestRunner(__DIR__, $filter);
$exitCode = $runner->run();
exit($exitCode);
