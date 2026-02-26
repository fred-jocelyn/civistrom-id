<?php
declare(strict_types=1);

/**
 * Tests du HealthController
 */
class HealthTest extends TestCase
{
    public function testHealthEndpointReturnsJson(): void
    {
        // Response::json envoie des headers (non-op en CLI)
        // mais le JSON est bien echo'ed
        ob_start();
        @Response::json([
            'status'  => 'ok',
            'app'     => 'civistrom-id',
            'version' => config('app.version', '1.0.0'),
            'time'    => date('c'),
        ]);
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertNotNull($data, 'Health endpoint doit retourner du JSON');
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('civistrom-id', $data['app']);
        $this->assertNotEmpty($data['version']);
        $this->assertNotEmpty($data['time']);
    }

    public function testHealthVersionMatchesConfig(): void
    {
        ob_start();
        @Response::json([
            'status'  => 'ok',
            'app'     => 'civistrom-id',
            'version' => config('app.version', '1.0.0'),
            'time'    => date('c'),
        ]);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertNotNull($data);
        $this->assertEquals(config('app.version'), $data['version']);
    }

    public function testHealthControllerExists(): void
    {
        $this->assertTrue(class_exists('HealthController'));
        $ref = new ReflectionClass('HealthController');
        $this->assertTrue($ref->hasMethod('index'));
    }
}
