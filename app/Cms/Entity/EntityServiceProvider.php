<?php

declare(strict_types=1);

namespace App\Cms\Entity;

use App\Cms\Cache\CmsCacheService;
use App\Cms\Content\Node;
use App\Cms\Content\NodeManager;
use App\Cms\Content\NodeRepository;
use App\Cms\ContentTypes\ContentType;
use App\Cms\ContentTypes\ContentTypeManager;
use App\Cms\Fields\Storage\FieldValueStorage;
use App\Cms\User\User;
use App\Cms\User\UserManager;

/**
 * EntityServiceProvider - Dependency injection wiring for entity system
 * 
 * Provides factory methods for creating entity system components.
 * Can be integrated with any DI container or used standalone.
 */
class EntityServiceProvider
{
    private static ?\PDO $connection = null;
    private static ?EntityManager $entityManager = null;
    private static ?NodeManager $nodeManager = null;
    private static ?UserManager $userManager = null;
    private static ?ContentTypeManager $contentTypeManager = null;

    /**
     * Set the database connection
     */
    public static function setConnection(\PDO $connection): void
    {
        self::$connection = $connection;
        // Reset instances when connection changes
        self::$entityManager = null;
        self::$nodeManager = null;
        self::$userManager = null;
        self::$contentTypeManager = null;
    }

    /**
     * Get the database connection
     */
    public static function getConnection(): \PDO
    {
        if (self::$connection === null) {
            throw new \RuntimeException("Database connection not set. Call EntityServiceProvider::setConnection() first.");
        }

        return self::$connection;
    }

    /**
     * Create connection from config
     */
    public static function createConnection(array $config): \PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? 'monkeyscms',
            $config['charset'] ?? 'utf8mb4'
        );

        $pdo = new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        self::setConnection($pdo);

        return $pdo;
    }

    // =========================================================================
    // Core Services
    // =========================================================================

    /**
     * Get the entity manager
     */
    public static function getEntityManager(?CmsCacheService $cache = null): EntityManager
    {
        if (self::$entityManager === null) {
            self::$entityManager = new EntityManager(self::getConnection(), $cache);
        }

        return self::$entityManager;
    }

    /**
     * Get the node manager
     */
    public static function getNodeManager(?CmsCacheService $cache = null): NodeManager
    {
        if (self::$nodeManager === null) {
            $fieldStorage = new FieldValueStorage(self::getConnection());
            self::$nodeManager = new NodeManager(
                self::getEntityManager($cache),
                $fieldStorage
            );
        }

        return self::$nodeManager;
    }

    /**
     * Get the user manager
     */
    public static function getUserManager(?CmsCacheService $cache = null): UserManager
    {
        if (self::$userManager === null) {
            self::$userManager = new UserManager(self::getEntityManager($cache));
        }

        return self::$userManager;
    }

    /**
     * Get the content type manager
     */
    public static function getContentTypeManager(): ContentTypeManager
    {
        if (self::$contentTypeManager === null) {
            self::$contentTypeManager = new ContentTypeManager(self::getConnection());
        }

        return self::$contentTypeManager;
    }

    // =========================================================================
    // Repositories
    // =========================================================================

    /**
     * Get node repository
     */
    public static function getNodeRepository(?CmsCacheService $cache = null): NodeRepository
    {
        return new NodeRepository(self::getEntityManager($cache));
    }

    /**
     * Get a generic repository for any entity class
     * 
     * @template T of EntityInterface
     * @param class-string<T> $entityClass
     * @return EntityRepository<T>
     */
    public static function getRepository(string $entityClass, ?CmsCacheService $cache = null): EntityRepository
    {
        return self::getEntityManager($cache)->getRepository($entityClass);
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a new node
     */
    public static function createNode(string $type, array $data = []): Node
    {
        $node = new Node($data);
        $node->setType($type);
        return $node;
    }

    /**
     * Create a new user
     */
    public static function createUser(array $data = []): User
    {
        return new User($data);
    }

    /**
     * Create a new content type
     */
    public static function createContentType(array $data = []): ContentType
    {
        return new ContentType($data);
    }

    // =========================================================================
    // Query Shortcuts
    // =========================================================================

    /**
     * Query nodes
     */
    public static function queryNodes(): EntityQuery
    {
        return self::getEntityManager()->query(Node::class);
    }

    /**
     * Query users
     */
    public static function queryUsers(): EntityQuery
    {
        return self::getEntityManager()->query(User::class);
    }

    // =========================================================================
    // Container Registration
    // =========================================================================

    /**
     * Get service definitions for DI container
     * 
     * Returns an array of service definitions that can be used with
     * any PSR-11 compatible container.
     */
    public static function getDefinitions(): array
    {
        return [
            \PDO::class => function() {
                return self::getConnection();
            },
            EntityManager::class => function() {
                return self::getEntityManager();
            },
            NodeManager::class => function() {
                return self::getNodeManager();
            },
            UserManager::class => function() {
                return self::getUserManager();
            },
            ContentTypeManager::class => function() {
                return self::getContentTypeManager();
            },
            NodeRepository::class => function() {
                return self::getNodeRepository();
            },
        ];
    }

    /**
     * Register services with a container
     */
    public static function register(object $container): void
    {
        $definitions = self::getDefinitions();

        // Handle different container types
        foreach ($definitions as $id => $factory) {
            if (method_exists($container, 'set')) {
                $container->set($id, $factory);
            } elseif (method_exists($container, 'bind')) {
                $container->bind($id, $factory);
            } elseif (method_exists($container, 'register')) {
                $container->register($id, $factory);
            }
        }
    }

    // =========================================================================
    // Testing Support
    // =========================================================================

    /**
     * Create an in-memory SQLite connection for testing
     */
    public static function createTestConnection(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        self::setConnection($pdo);

        return $pdo;
    }

    /**
     * Reset all static instances (useful for testing)
     */
    public static function reset(): void
    {
        self::$connection = null;
        self::$entityManager = null;
        self::$nodeManager = null;
        self::$userManager = null;
        self::$contentTypeManager = null;
    }
}
