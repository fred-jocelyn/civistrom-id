<?php
declare(strict_types=1);

/**
 * TestCase — Classe de base pour tous les tests CIVISTROM ID
 */
class TestCase
{
    public int $assertionCount = 0;

    protected function setUp(): void {}
    protected function tearDown(): void {}

    public function resetStaticState(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_HOST'] = 'CIVISTROMID';
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected !== $actual) {
            throw new AssertionFailure(
                $message ?: sprintf("Expected %s, got %s", $this->export($expected), $this->export($actual))
            );
        }
    }

    protected function assertTrue(mixed $value, string $message = ''): void
    {
        $this->assertionCount++;
        if ($value !== true) {
            throw new AssertionFailure($message ?: "Expected true, got " . $this->export($value));
        }
    }

    protected function assertFalse(mixed $value, string $message = ''): void
    {
        $this->assertionCount++;
        if ($value !== false) {
            throw new AssertionFailure($message ?: "Expected false, got " . $this->export($value));
        }
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->assertionCount++;
        if ($value !== null) {
            throw new AssertionFailure($message ?: "Expected null, got " . $this->export($value));
        }
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        $this->assertionCount++;
        if ($value === null) {
            throw new AssertionFailure($message ?: "Expected non-null value");
        }
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertionCount++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailure($message ?: "'{$needle}' not found in '{$haystack}'");
        }
    }

    protected function assertCount(int $expected, array|Countable $collection, string $message = ''): void
    {
        $this->assertionCount++;
        $actual = count($collection);
        if ($actual !== $expected) {
            throw new AssertionFailure($message ?: "Expected count {$expected}, got {$actual}");
        }
    }

    protected function assertNotEmpty(mixed $value, string $message = ''): void
    {
        $this->assertionCount++;
        if (empty($value)) {
            throw new AssertionFailure($message ?: "Expected non-empty value");
        }
    }

    protected function assertMatchesRegex(string $pattern, string $subject, string $message = ''): void
    {
        $this->assertionCount++;
        if (!preg_match($pattern, $subject)) {
            throw new AssertionFailure($message ?: "'{$subject}' does not match pattern '{$pattern}'");
        }
    }

    protected function assertGreaterThan(int|float $expected, int|float $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($actual <= $expected) {
            throw new AssertionFailure($message ?: "Expected {$actual} > {$expected}");
        }
    }

    protected function assertFileExists(string $path, string $message = ''): void
    {
        $this->assertionCount++;
        if (!file_exists($path)) {
            throw new AssertionFailure($message ?: "File '{$path}' does not exist");
        }
    }

    private function export(mixed $value): string
    {
        if (is_string($value)) {
            $display = strlen($value) > 80 ? substr($value, 0, 80) . '...' : $value;
            return "'{$display}'";
        }
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_null($value)) return 'null';
        if (is_array($value)) return 'array(' . count($value) . ')';
        return (string)$value;
    }
}

class AssertionFailure extends RuntimeException {}
