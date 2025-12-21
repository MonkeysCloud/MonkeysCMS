<?php

declare(strict_types=1);

namespace App\Installer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use PDO;

class InstallerRequestHandler implements RequestHandlerInterface
{
    private string $basePath;
    private $renderer;

    /**
     * @param string $basePath
     * @param \MonkeysLegion\Template\Renderer|null $renderer
     */
    public function __construct(string $basePath, $renderer = null)
    {
        $this->basePath = $basePath;
        $this->renderer = $renderer;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($request->getMethod() === 'POST' && $path === '/install/test') {
            return $this->handleTestConnection($request);
        }
        
        if ($request->getMethod() === 'POST' && $path === '/install/create-db') {
            return $this->handleCreateDatabase($request);
        }
        
        if ($request->getMethod() === 'POST' && $path === '/install/save') {
            return $this->handleSaveConfig($request);
        }

        if ($path !== '/install') {
            return new RedirectResponse('/install');
        }

        return $this->showWizard();
    }

    private function showWizard(): ResponseInterface
    {
        if ($this->renderer) {
            $html = $this->renderer->render('installer/index');
            return new HtmlResponse($html);
        }

        // Fallback for safety if renderer not provided
        return new HtmlResponse('<h1>Installer Template Error</h1><p>Renderer not initialized.</p>', 500);
    }

    private function getDsn(array $data, bool $withDb = true): string
    {
        $driver = $data['DB_CONNECTION'] ?? 'mysql';

        // Manual handling for unsupported drivers in the library
        if ($driver === 'sqlsrv') {
             return sprintf(
                'sqlsrv:Server=%s,%s;Database=%s',
                $data['DB_HOST'] ?? '127.0.0.1',
                $data['DB_PORT'] ?? '1433',
                $withDb ? ($data['DB_DATABASE'] ?? 'master') : 'master'
            );
        }

        try {
            $builder = \MonkeysLegion\Database\DSN\DsnBuilderFactory::createByString($driver);
        } catch (\InvalidArgumentException $e) {
             throw new \InvalidArgumentException("Unsupported database driver: $driver");
        }

        if ($builder instanceof \MonkeysLegion\Database\DSN\MySQLDsnBuilder) {
            $builder->host($data['DB_HOST'] ?? '127.0.0.1')
                    ->port((int)($data['DB_PORT'] ?? 3306))
                    ->charset($data['DB_CHARSET'] ?? 'utf8mb4');
            
            if ($withDb && !empty($data['DB_DATABASE'])) {
                $builder->database($data['DB_DATABASE']);
            }
        } elseif ($builder instanceof \MonkeysLegion\Database\DSN\PostgreSQLDsnBuilder) {
            $builder->host($data['DB_HOST'] ?? '127.0.0.1')
                    ->port((int)($data['DB_PORT'] ?? 5432));
            
            $dbName = $withDb ? ($data['DB_DATABASE'] ?? 'postgres') : 'postgres';
            $builder->database($dbName);
        } elseif ($builder instanceof \MonkeysLegion\Database\DSN\SQLiteDsnBuilder) {
            $builder->file($data['DB_DATABASE'] ?? '');
        }

        return $builder->build();
    }

    private function handleTestConnection(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        try {
            $dsn = $this->getDsn($data);
            $user = $data['DB_USERNAME'] ?? null;
            $pass = $data['DB_PASSWORD'] ?? null;
            
            new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            return new JsonResponse(['success' => true]);
        } catch (\PDOException $e) {
            
            // Check for "Unknown database" error code (1049 for MySQL)
            // Postgres: 3D000 - invalid_catalog_name
            // SQL Server: 4060 - cannot open database
            
            $canCreate = false;
            $code = (string) $e->getCode();
            $msg = $e->getMessage();

            // Detect database missing
            if (
                str_contains($msg, 'Unknown database') || $code === '1049' ||
                str_contains($msg, 'database "' . ($data['DB_DATABASE']??'') . '" does not exist') || $code === '3D000' ||
                str_contains($msg, 'Cannot open database')
            ) {
                 // Try connecting WITHOUT database name to verify credentials
                 try {
                     $dsnNoDb = $this->getDsn($data, false);
                     new PDO($dsnNoDb, $data['DB_USERNAME'] ?? null, $data['DB_PASSWORD'] ?? null);
                     $canCreate = true;
                 } catch (\Throwable $t) {
                     // If we can't connect even without DB, then credentials are wrong too
                     $canCreate = false; 
                 }
            }

            return new JsonResponse([
                'success' => false, 
                'error' => $e->getMessage(),
                'can_create' => $canCreate
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function handleCreateDatabase(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $dbName = $data['DB_DATABASE'] ?? '';
        
        if (empty($dbName)) {
             return new JsonResponse(['success' => false, 'error' => 'Database name is required']);
        }

        try {
            // Connect without selecting the specific database
            $dsn = $this->getDsn($data, false);
            $user = $data['DB_USERNAME'] ?? null;
            $pass = $data['DB_PASSWORD'] ?? null;

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $driver = $data['DB_CONNECTION'] ?? 'mysql';
            
            // Sanitize DB name (basic) - only allow certain chars to prevent injection if not parameterized
            // Although CREATE DATABASE usually cannot use prepared statements for the identifier
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
                 throw new \RuntimeException("Invalid database name");
            }

            // Create command
            // Postgres requires quotes for some cases, MySQL backticks
            $sql = match($driver) {
                'pgsql' => "CREATE DATABASE \"$dbName\"",
                'sqlsrv' => "CREATE DATABASE [$dbName]",
                default => "CREATE DATABASE `$dbName`"
            };

            $pdo->exec($sql);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => 'Failed to create database: ' . $e->getMessage()]);
        }
    }

    private function handleSaveConfig(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $envPath = $this->basePath . '/.env';

        try {
            $envContent = '';
            foreach ($data as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $envContent .= "{$key}={$value}\n";
                }
            }

            if (!str_contains($envContent, 'APP_ENV=')) {
                $envContent .= "APP_ENV=local\n";
            }

            file_put_contents($envPath, trim($envContent));
            
            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => 'Failed to write .env file: ' . $e->getMessage()]);
        }
    }
}
