<?php
declare(strict_types=1);

/**
 * Tests du framework core
 */
class CoreTest extends TestCase
{
    public function testEnvReturnsDefault(): void
    {
        $val = env('NONEXISTENT_KEY_12345', 'fallback');
        $this->assertEquals('fallback', $val);
    }

    public function testEnvReturnsValue(): void
    {
        $_ENV['TEST_KEY'] = 'hello';
        $this->assertEquals('hello', env('TEST_KEY', ''));
        unset($_ENV['TEST_KEY']);
    }

    public function testEscapesHtml(): void
    {
        $this->assertEquals('&lt;script&gt;', e('<script>'));
        $this->assertEquals('a&amp;b', e('a&b'));
        $this->assertEquals('', e(null));
    }

    public function testUrlGeneration(): void
    {
        $base = env('APP_URL');
        $this->assertEquals($base . '/health', url('health'));
        $this->assertEquals($base . '/health', url('/health'));
    }

    public function testConfigReturnsValues(): void
    {
        $this->assertEquals('CIVISTROM ID', config('app.name'));
        $this->assertEquals('#6366F1', config('app.color'));
        $this->assertEquals('1.0.0', config('app.version'));
    }

    public function testConfigReturnsDefault(): void
    {
        $this->assertNull(config('app.nonexistent'));
        $this->assertEquals('default', config('app.nonexistent', 'default'));
    }

    public function testToJson(): void
    {
        $json = to_json(['status' => 'ok']);
        $this->assertEquals('{"status":"ok"}', $json);
    }

    public function testIdLogCreatesFile(): void
    {
        $logFile = BASE_PATH . '/storage/logs/id.log';
        @unlink($logFile); // clean

        id_log('test message', 'info');

        $this->assertFileExists($logFile);
        $content = file_get_contents($logFile);
        $this->assertContains('test message', $content);
        $this->assertContains('[INFO]', $content);

        @unlink($logFile);
    }

    public function testAppSingleton(): void
    {
        $app1 = App::getInstance();
        $app2 = App::getInstance();
        $this->assertTrue($app1 === $app2);
    }

    public function testAppHasNoDatabase(): void
    {
        $app = App::getInstance();
        $ref = new ReflectionClass($app);
        $this->assertFalse($ref->hasProperty('database'), 'App ne doit pas avoir de propriété database');
    }

    public function testAppHasNoSession(): void
    {
        $app = App::getInstance();
        $ref = new ReflectionClass($app);
        $this->assertFalse($ref->hasProperty('session'), 'App ne doit pas avoir de propriété session');
    }

    public function testRouterExists(): void
    {
        $router = App::getInstance()->getRouter();
        $this->assertNotNull($router);
    }

    public function testRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $this->assertEquals('POST', $request->method());
    }

    public function testRequestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/health?check=1';
        $request = new Request();
        $this->assertEquals('/health', $request->uri());
    }

    public function testControllerDefaultLayout(): void
    {
        $ref = new ReflectionMethod(Controller::class, 'render');
        $params = $ref->getParameters();
        $layoutParam = $params[2]; // $layout
        $this->assertEquals('id', $layoutParam->getDefaultValue(), 'Default layout doit être "id"');
    }
}
