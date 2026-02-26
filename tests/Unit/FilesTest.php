<?php
declare(strict_types=1);

/**
 * Tests de structure — vérifie que tous les fichiers essentiels existent
 */
class FilesTest extends TestCase
{
    public function testCoreFilesExist(): void
    {
        $files = ['App', 'Router', 'Request', 'Response', 'Controller', 'View'];
        foreach ($files as $file) {
            $this->assertFileExists(BASE_PATH . "/core/{$file}.php");
        }
    }

    public function testControllersExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/app/Controllers/AppController.php');
        $this->assertFileExists(BASE_PATH . '/app/Controllers/HealthController.php');
    }

    public function testViewsExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/views/layouts/id.php');
        $this->assertFileExists(BASE_PATH . '/views/app.php');
        $this->assertFileExists(BASE_PATH . '/views/errors/404.php');
        $this->assertFileExists(BASE_PATH . '/views/errors/500.php');
    }

    public function testJsFilesExist(): void
    {
        $files = ['totp.js', 'crypto.js', 'storage.js', 'scanner.js', 'app.js'];
        foreach ($files as $file) {
            $this->assertFileExists(BASE_PATH . "/public/assets/js/{$file}");
        }
    }

    public function testJsQRVendored(): void
    {
        $this->assertFileExists(BASE_PATH . '/public/assets/js/vendor/jsqr.min.js');
    }

    public function testPwaFilesExist(): void
    {
        $this->assertFileExists(BASE_PATH . '/public/manifest.json');
        $this->assertFileExists(BASE_PATH . '/public/sw.js');
    }

    public function testIconsExist(): void
    {
        $icons = ['icon-192.png', 'icon-512.png', 'maskable-192.png', 'maskable-512.png', 'apple-touch-icon.png'];
        foreach ($icons as $icon) {
            $this->assertFileExists(BASE_PATH . "/public/assets/img/{$icon}");
        }
    }

    public function testManifestIsValidJson(): void
    {
        $content = file_get_contents(BASE_PATH . '/public/manifest.json');
        $data = json_decode($content, true);
        $this->assertNotNull($data, 'manifest.json doit être du JSON valide');
        $this->assertEquals('CIVISTROM ID', $data['name']);
        $this->assertEquals('standalone', $data['display']);
        $this->assertCount(4, $data['icons']);
    }

    public function testConfigFiles(): void
    {
        $this->assertFileExists(BASE_PATH . '/config/app.php');
        $this->assertFileExists(BASE_PATH . '/config/routes.php');
    }

    public function testDeployFiles(): void
    {
        $this->assertFileExists(BASE_PATH . '/deploy/nginx/id.conf');
        $this->assertFileExists(BASE_PATH . '/deploy/php/id-fpm.conf');
        $this->assertFileExists(BASE_PATH . '/deploy/setup-production.sh');
    }
}
