<?php
declare(strict_types=1);

/**
 * TestRunner — Découvre, exécute et affiche les résultats des tests
 */
class TestRunner
{
    private string $testsDir;
    private ?string $filter;
    private int $passed = 0;
    private int $failed = 0;
    private int $errors = 0;
    private int $totalAssertions = 0;
    private float $startTime;

    private const GREEN  = "\033[32m";
    private const RED    = "\033[31m";
    private const YELLOW = "\033[33m";
    private const CYAN   = "\033[36m";
    private const BOLD   = "\033[1m";
    private const DIM    = "\033[2m";
    private const RESET  = "\033[0m";

    public function __construct(string $testsDir, ?string $filter = null)
    {
        $this->testsDir = $testsDir;
        $this->filter = $filter;
    }

    public function run(): int
    {
        $this->startTime = microtime(true);
        echo PHP_EOL . self::BOLD . '  CIVISTROM ID Test Runner' . self::RESET . PHP_EOL;
        echo self::DIM . '  ' . str_repeat("\xe2\x94\x80", 50) . self::RESET . PHP_EOL;

        $testFiles = $this->discoverTests();

        if (empty($testFiles)) {
            echo self::YELLOW . "  Aucun fichier de test trouvé." . self::RESET . PHP_EOL;
            return 1;
        }

        foreach ($testFiles as $file) {
            $this->runTestFile($file);
        }

        $this->printSummary();
        return ($this->failed + $this->errors) > 0 ? 1 : 0;
    }

    private function discoverTests(): array
    {
        $files = [];
        $dirs = ['Unit'];

        foreach ($dirs as $dir) {
            $path = $this->testsDir . '/' . $dir;
            if (!is_dir($path)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                    if ($this->filter === null ||
                        stripos($file->getFilename(), $this->filter) !== false) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        sort($files);
        return $files;
    }

    private function runTestFile(string $filePath): void
    {
        require_once $filePath;
        $className = pathinfo($filePath, PATHINFO_FILENAME);

        if (!class_exists($className)) return;

        $ref = new ReflectionClass($className);
        if ($ref->isAbstract()) return;

        echo PHP_EOL . self::BOLD . self::CYAN . "  {$ref->getShortName()}" . self::RESET . PHP_EOL;

        $testMethods = array_filter(
            $ref->getMethods(ReflectionMethod::IS_PUBLIC),
            fn(ReflectionMethod $m) => str_starts_with($m->getName(), 'test')
        );

        if ($this->filter !== null) {
            $methodFiltered = array_filter(
                $testMethods,
                fn(ReflectionMethod $m) => stripos($m->getName(), $this->filter) !== false
            );
            if (!empty($methodFiltered)) {
                $testMethods = $methodFiltered;
            }
        }

        foreach ($testMethods as $method) {
            $this->runTestMethod($className, $method->getName());
        }
    }

    private function runTestMethod(string $className, string $methodName): void
    {
        $instance = new $className();
        $instance->resetStaticState();
        $testStart = microtime(true);

        try {
            $ref = new ReflectionMethod($instance, 'setUp');
            $ref->setAccessible(true);
            $ref->invoke($instance);

            $instance->$methodName();

            try { (new ReflectionMethod($instance, 'tearDown'))->invoke($instance); } catch (Throwable) {}

            $elapsed = round((microtime(true) - $testStart) * 1000, 1);
            $assertions = $instance->assertionCount;
            $this->totalAssertions += $assertions;
            $this->passed++;

            $name = strtolower(ltrim(preg_replace('/([A-Z])/', ' $1', substr($methodName, 4))));
            echo sprintf("    %s\xe2\x9c\x93 PASS%s %s %s(%d assertions, %.1fms)%s" . PHP_EOL,
                self::GREEN, self::RESET, $name, self::DIM, $assertions, $elapsed, self::RESET);

        } catch (AssertionFailure $e) {
            $this->failed++;
            $name = strtolower(ltrim(preg_replace('/([A-Z])/', ' $1', substr($methodName, 4))));
            echo sprintf("    %s\xe2\x9c\x97 FAIL%s %s" . PHP_EOL, self::RED, self::RESET, $name);
            echo sprintf("         %s%s%s" . PHP_EOL, self::RED, $e->getMessage(), self::RESET);

        } catch (Throwable $e) {
            $this->errors++;
            $name = strtolower(ltrim(preg_replace('/([A-Z])/', ' $1', substr($methodName, 4))));
            echo sprintf("    %s\xe2\x9a\xa0 ERROR%s %s" . PHP_EOL, self::YELLOW, self::RESET, $name);
            echo sprintf("         %s%s: %s%s" . PHP_EOL, self::YELLOW, get_class($e), $e->getMessage(), self::RESET);
        }
    }

    private function printSummary(): void
    {
        $elapsed = round(microtime(true) - $this->startTime, 2);
        $total = $this->passed + $this->failed + $this->errors;
        $statusColor = ($this->failed + $this->errors) > 0 ? self::RED : self::GREEN;
        $statusText = ($this->failed + $this->errors) > 0 ? 'FAILED' : 'PASSED';

        echo PHP_EOL . self::DIM . '  ' . str_repeat("\xe2\x94\x80", 50) . self::RESET . PHP_EOL;
        echo sprintf(PHP_EOL . "  %s%s%s%s  %d tests, %d assertions, %d passed, %d failed, %d errors%s" . PHP_EOL,
            $statusColor, self::BOLD, $statusText, self::RESET,
            $total, $this->totalAssertions, $this->passed, $this->failed, $this->errors, self::RESET);
        echo sprintf("  %sTemps : %.2fs%s" . PHP_EOL . PHP_EOL, self::DIM, $elapsed, self::RESET);
    }
}
