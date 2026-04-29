<?php

declare(strict_types=1);

namespace App\Cms\Controller;

use App\Cms\Database\MigrationManager;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * InstallerController — Web-based CMS installation wizard.
 *
 * Steps:
 *   1. Requirements check (PHP version, extensions, write permissions)
 *   2. Database configuration
 *   3. Run migrations
 *   4. Create admin account
 *   5. Site configuration
 *   6. Complete
 */
#[RoutePrefix('/install')]
final class InstallerController
{
    public function __construct(
        private readonly Renderer $renderer,
    ) {}

    #[Route('GET', '/', name: 'install.index')]
    public function index(ServerRequestInterface $request): Response
    {
        if ($this->isInstalled()) {
            return Response::redirect('/admin');
        }

        return Response::html($this->renderer->render('install.index', [
            'title' => 'Install MonkeysCMS',
            'step' => 1,
            'requirements' => $this->checkRequirements(),
        ]));
    }

    #[Route('POST', '/check', name: 'install.check')]
    public function check(ServerRequestInterface $request): Response
    {
        $reqs = $this->checkRequirements();
        $allPassed = !in_array(false, array_column($reqs, 'passed'), true);

        return Response::json([
            'requirements' => $reqs,
            'all_passed' => $allPassed,
        ]);
    }

    #[Route('POST', '/database', name: 'install.database')]
    public function database(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        $host = $body['db_host'] ?? '127.0.0.1';
        $port = $body['db_port'] ?? '3306';
        $name = $body['db_name'] ?? '';
        $user = $body['db_user'] ?? '';
        $pass = $body['db_pass'] ?? '';

        if (!$name || !$user) {
            return Response::json(['error' => 'Database name and user are required.'], 422);
        }

        // Test connection
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('SELECT 1');
        } catch (\PDOException $e) {
            return Response::json([
                'error' => 'Database connection failed: ' . $e->getMessage(),
            ], 422);
        }

        // Write to .env
        $this->writeEnvValues([
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $name,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => $pass,
        ]);

        return Response::json(['success' => true, 'message' => 'Database connected successfully.']);
    }

    #[Route('POST', '/migrate', name: 'install.migrate')]
    public function migrate(ServerRequestInterface $request): Response
    {
        try {
            $pdo = $this->createPdoFromEnv();
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
            $manager = new MigrationManager($pdo, $basePath);

            $result = $manager->migrate();

            return Response::json([
                'success' => empty($result['errors']),
                'executed' => $result['executed'],
                'errors' => $result['errors'],
                'batch' => $result['batch'],
            ]);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('POST', '/admin-user', name: 'install.admin')]
    public function adminUser(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$name || !$email || strlen($password) < 8) {
            return Response::json([
                'error' => 'Name, email, and password (min 8 chars) are required.',
            ], 422);
        }

        try {
            $pdo = $this->createPdoFromEnv();

            // Ensure admin role exists
            $stmt = $pdo->prepare("SELECT id FROM cms_roles WHERE machine_name = 'admin' LIMIT 1");
            $stmt->execute();
            $roleId = $stmt->fetchColumn();

            if (!$roleId) {
                $pdo->exec("INSERT INTO cms_roles (machine_name, label, permissions, is_super_admin, is_system) VALUES ('admin', 'Administrator', '[\"admin.*\"]', 1, 1)");
                $roleId = $pdo->lastInsertId();
            }

            // Create or update admin user
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $check = $pdo->prepare("SELECT id FROM cms_users WHERE email = :email");
            $check->execute(['email' => $email]);

            if ($check->fetchColumn()) {
                $pdo->prepare("UPDATE cms_users SET name = :name, password = :pass, role_id = :role WHERE email = :email")
                    ->execute(['name' => $name, 'pass' => $hash, 'role' => $roleId, 'email' => $email]);
            } else {
                $pdo->prepare("INSERT INTO cms_users (name, email, password, role_id, active) VALUES (:name, :email, :pass, :role, 1)")
                    ->execute(['name' => $name, 'email' => $email, 'pass' => $hash, 'role' => $roleId]);
            }

            return Response::json(['success' => true]);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('POST', '/configure', name: 'install.configure')]
    public function configure(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);

        try {
            $pdo = $this->createPdoFromEnv();

            $upsert = $pdo->prepare(
                "INSERT INTO settings (`group`, `key`, `value`, `type`) VALUES (:g, :k, :v, 'string')
                 ON DUPLICATE KEY UPDATE `value` = :v2"
            );

            $settings = [
                'site_name' => $body['site_name'] ?? 'MonkeysCMS',
                'site_tagline' => $body['site_tagline'] ?? '',
                'site_email' => $body['site_email'] ?? '',
                'timezone' => $body['timezone'] ?? 'UTC',
            ];

            foreach ($settings as $key => $value) {
                $upsert->execute(['g' => 'general', 'k' => $key, 'v' => $value, 'v2' => $value]);
            }

            // Write APP_URL to .env
            if (!empty($body['site_url'])) {
                $this->writeEnvValues(['APP_URL' => $body['site_url']]);
            }

            // Mark as installed
            $this->markInstalled();

            return Response::json(['success' => true]);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function checkRequirements(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);

        return [
            ['name' => 'PHP >= 8.4', 'passed' => version_compare(PHP_VERSION, '8.4.0', '>='), 'value' => PHP_VERSION],
            ['name' => 'PDO MySQL', 'passed' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? 'Loaded' : 'Missing'],
            ['name' => 'JSON', 'passed' => extension_loaded('json'), 'value' => 'Loaded'],
            ['name' => 'mbstring', 'passed' => extension_loaded('mbstring'), 'value' => extension_loaded('mbstring') ? 'Loaded' : 'Missing'],
            ['name' => 'OpenSSL', 'passed' => extension_loaded('openssl'), 'value' => extension_loaded('openssl') ? 'Loaded' : 'Missing'],
            ['name' => 'GD or Imagick', 'passed' => extension_loaded('gd') || extension_loaded('imagick'), 'value' => extension_loaded('gd') ? 'GD' : (extension_loaded('imagick') ? 'Imagick' : 'Missing')],
            ['name' => 'storage/ writable', 'passed' => is_writable($basePath . '/storage'), 'value' => is_writable($basePath . '/storage') ? 'Writable' : 'Not writable'],
            ['name' => '.env writable', 'passed' => is_writable($basePath . '/.env') || is_writable($basePath), 'value' => 'OK'],
        ];
    }

    private function isInstalled(): bool
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        return file_exists($basePath . '/storage/.installed');
    }

    private function markInstalled(): void
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $dir = $basePath . '/storage';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/.installed', date('c'));
    }

    private function writeEnvValues(array $values): void
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $envPath = $basePath . '/.env';

        $content = file_exists($envPath) ? file_get_contents($envPath) : '';

        foreach ($values as $key => $value) {
            $escaped = str_contains($value, ' ') ? "\"{$value}\"" : $value;
            if (preg_match('/^' . preg_quote($key) . '=/m', $content)) {
                $content = preg_replace('/^' . preg_quote($key) . '=.*/m', "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }

        file_put_contents($envPath, $content);
    }

    private function createPdoFromEnv(): PDO
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $envPath = $basePath . '/.env';

        $env = [];
        if (file_exists($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $env[trim($k)] = trim(trim($v), '"');
                }
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_DATABASE'] ?? '',
        );

        return new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
