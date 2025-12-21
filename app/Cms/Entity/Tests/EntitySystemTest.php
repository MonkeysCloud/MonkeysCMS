<?php

declare(strict_types=1);

namespace App\Cms\Entity\Tests;

use App\Cms\Entity\BaseEntity;
use App\Cms\Entity\EntityInterface;
use App\Cms\Entity\EntityManager;
use App\Cms\Entity\EntityQuery;
use App\Cms\Entity\EntityRepository;
use App\Cms\Entity\EntityServiceProvider;
use App\Cms\Content\Node;
use App\Cms\Content\NodeStatus;
use App\Cms\Content\NodeManager;
use App\Cms\User\User;
use App\Cms\User\UserStatus;
use App\Cms\User\UserManager;

/**
 * EntitySystemTest - Comprehensive tests for the entity system
 * 
 * Run with: php EntitySystemTest.php
 */
class EntitySystemTest
{
    private \PDO $db;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->db = $this->createTestDatabase();
        EntityServiceProvider::setConnection($this->db);
    }

    /**
     * Run all tests
     */
    public function runAll(): void
    {
        $this->header("Entity System Tests");

        // Base Entity Tests
        $this->group("BaseEntity Tests");
        $this->testEntityCreation();
        $this->testEntityFill();
        $this->testEntityCasting();
        $this->testEntityDirtyTracking();
        $this->testEntitySerialization();

        // Node Tests
        $this->group("Node Tests");
        $this->testNodeCreation();
        $this->testNodeStatus();
        $this->testNodeSlug();
        $this->testNodeFields();

        // User Tests
        $this->group("User Tests");
        $this->testUserCreation();
        $this->testUserPassword();
        $this->testUserStatus();
        $this->testUserRoles();

        // Entity Query Tests
        $this->group("EntityQuery Tests");
        $this->testBasicQuery();
        $this->testWhereClause();
        $this->testOrderBy();
        $this->testPagination();
        $this->testAggregates();

        // Entity Manager Tests
        $this->group("EntityManager Tests");
        $this->testEntityManagerCrud();
        $this->testEntityManagerFind();
        $this->testEntityManagerQuery();
        $this->testEntityManagerTransactions();
        $this->testEntityManagerEvents();

        // Repository Tests
        $this->group("Repository Tests");
        $this->testRepositoryBasic();
        $this->testRepositoryPagination();

        // Summary
        $this->summary();
    }

    // =========================================================================
    // BaseEntity Tests
    // =========================================================================

    private function testEntityCreation(): void
    {
        $node = new Node();
        $this->assert($node->getId() === null, "New entity has null ID");
        $this->assert(!$node->exists(), "New entity does not exist");
    }

    private function testEntityFill(): void
    {
        $node = new Node([
            'title' => 'Test Title',
            'type' => 'article',
            'status' => 'draft',
        ]);

        $this->assert($node->getTitle() === 'Test Title', "Entity fill sets title");
        $this->assert($node->getType() === 'article', "Entity fill sets type");
        $this->assert($node->getStatus() === 'draft', "Entity fill sets status");
    }

    private function testEntityCasting(): void
    {
        $node = new Node([
            'author_id' => '123',
            'revision_id' => '1',
        ]);

        $this->assert($node->getAuthorId() === 123, "Integer casting works");
        $this->assert(is_int($node->getRevisionId()), "Revision ID is integer");
    }

    private function testEntityDirtyTracking(): void
    {
        $node = new Node(['title' => 'Original']);
        $node->syncOriginal();

        $this->assert(!$node->isDirty(), "Entity is clean after sync");

        $node->setTitle('Changed');
        $this->assert($node->isDirty(), "Entity is dirty after change");

        $dirty = $node->getDirty();
        $this->assert(isset($dirty['title']), "Dirty tracking includes changed field");
    }

    private function testEntitySerialization(): void
    {
        $node = new Node([
            'title' => 'Test',
            'type' => 'article',
        ]);

        $array = $node->toArray();
        $this->assert(isset($array['title']), "toArray includes title");
        $this->assert(isset($array['type']), "toArray includes type");

        $dbArray = $node->toDatabase();
        $this->assert(is_array($dbArray), "toDatabase returns array");
    }

    // =========================================================================
    // Node Tests
    // =========================================================================

    private function testNodeCreation(): void
    {
        $node = new Node([
            'title' => 'Hello World',
            'type' => 'article',
        ]);

        $this->assert($node->getTitle() === 'Hello World', "Node title is set");
        $this->assert($node->getType() === 'article', "Node type is set");
        $this->assert($node->getStatus() === NodeStatus::DRAFT, "Node default status is draft");
    }

    private function testNodeStatus(): void
    {
        $node = new Node(['title' => 'Test']);

        $this->assert($node->isDraft(), "New node is draft");
        $this->assert(!$node->isPublished(), "New node is not published");

        $node->publish();
        $this->assert($node->isPublished(), "Published node is published");
        $this->assert($node->getPublishedAt() !== null, "Published node has published_at");

        $node->unpublish();
        $this->assert($node->isDraft(), "Unpublished node is draft");

        $node->archive();
        $this->assert($node->isArchived(), "Archived node is archived");
    }

    private function testNodeSlug(): void
    {
        $node = new Node(['title' => 'Hello World Post!']);
        $slug = $node->generateSlug();

        $this->assert($slug === 'hello-world-post', "Slug is generated correctly");

        $node->ensureSlug();
        $this->assert($node->getSlug() === 'hello-world-post', "ensureSlug sets slug");
    }

    private function testNodeFields(): void
    {
        $node = new Node(['title' => 'Test']);
        
        $node->setField('body', 'Content here');
        $node->setField('tags', ['news', 'featured']);

        $this->assert($node->getField('body') === 'Content here', "Field value is stored");
        $this->assert($node->hasField('body'), "hasField returns true");
        $this->assert(!$node->hasField('nonexistent'), "hasField returns false for missing");
        $this->assert($node->getField('nonexistent', 'default') === 'default', "getField returns default");

        $node->removeField('body');
        $this->assert(!$node->hasField('body'), "removeField removes field");
    }

    // =========================================================================
    // User Tests
    // =========================================================================

    private function testUserCreation(): void
    {
        $user = new User([
            'email' => 'Test@Example.com',
            'username' => 'testuser',
        ]);

        $this->assert($user->getEmail() === 'test@example.com', "Email is lowercased");
        $this->assert($user->getUsername() === 'testuser', "Username is set");
        $this->assert($user->getStatus() === UserStatus::PENDING, "Default status is pending");
    }

    private function testUserPassword(): void
    {
        $user = new User();
        $user->setPassword('secret123');

        $this->assert($user->verifyPassword('secret123'), "Correct password verifies");
        $this->assert(!$user->verifyPassword('wrong'), "Wrong password fails");
        $this->assert(strlen($user->getPasswordHash()) > 0, "Password hash is set");
    }

    private function testUserStatus(): void
    {
        $user = new User();

        $this->assert($user->isPending(), "New user is pending");

        $user->activate();
        $this->assert($user->isActive(), "Activated user is active");

        $user->block();
        $this->assert($user->isBlocked(), "Blocked user is blocked");
    }

    private function testUserRoles(): void
    {
        $user = new User();
        $user->setRoles(['authenticated', 'editor']);

        $this->assert($user->hasRole('editor'), "hasRole returns true");
        $this->assert(!$user->hasRole('admin'), "hasRole returns false");
        $this->assert($user->hasAnyRole(['admin', 'editor']), "hasAnyRole works");
        $this->assert($user->hasAllRoles(['authenticated', 'editor']), "hasAllRoles works");
        $this->assert(!$user->isAdmin(), "Non-admin is not admin");
    }

    // =========================================================================
    // EntityQuery Tests
    // =========================================================================

    private function testBasicQuery(): void
    {
        $query = new EntityQuery($this->db, TestEntity::class);
        $sql = $query->toSql();

        $this->assert(str_contains($sql, 'SELECT'), "Query contains SELECT");
        $this->assert(str_contains($sql, 'test_entities'), "Query contains table name");
    }

    private function testWhereClause(): void
    {
        $query = new EntityQuery($this->db, TestEntity::class);
        $query->where('status', 'active');
        $sql = $query->toSql();

        $this->assert(str_contains($sql, 'WHERE'), "Query has WHERE clause");
        $this->assert(str_contains($sql, 'status'), "Query filters by status");

        // Test multiple conditions
        $query2 = new EntityQuery($this->db, TestEntity::class);
        $query2->where('status', 'active')
               ->where('type', 'article');
        $sql2 = $query2->toSql();

        $this->assert(str_contains($sql2, 'AND'), "Multiple conditions use AND");
    }

    private function testOrderBy(): void
    {
        $query = new EntityQuery($this->db, TestEntity::class);
        $query->orderBy('created_at', 'DESC');
        $sql = $query->toSql();

        $this->assert(str_contains($sql, 'ORDER BY'), "Query has ORDER BY");
        $this->assert(str_contains($sql, 'DESC'), "Query has DESC direction");
    }

    private function testPagination(): void
    {
        $query = new EntityQuery($this->db, TestEntity::class);
        $query->forPage(2, 10);
        $sql = $query->toSql();

        $this->assert(str_contains($sql, 'LIMIT 10'), "Query has LIMIT");
        $this->assert(str_contains($sql, 'OFFSET 10'), "Query has OFFSET for page 2");
    }

    private function testAggregates(): void
    {
        // Create some test data first
        $this->createTestEntities();

        $query = new EntityQuery($this->db, TestEntity::class);
        $count = $query->count();

        $this->assert($count >= 0, "Count returns number");
    }

    // =========================================================================
    // EntityManager Tests
    // =========================================================================

    private function testEntityManagerCrud(): void
    {
        $em = EntityServiceProvider::getEntityManager();

        // Create
        $entity = new TestEntity(['name' => 'Test', 'status' => 'active']);
        $em->save($entity);

        $this->assert($entity->getId() !== null, "Entity gets ID after save");
        $this->assert($entity->exists(), "Entity exists after save");

        // Update
        $entity->name = 'Updated';
        $em->save($entity);

        // Find
        $found = $em->find(TestEntity::class, $entity->getId());
        $this->assert($found !== null, "Entity can be found");
        $this->assert($found->name === 'Updated', "Entity is updated");

        // Delete
        $em->delete($entity);
        $deleted = $em->find(TestEntity::class, $entity->getId());
        // Note: If soft delete, entity still exists but is marked deleted
    }

    private function testEntityManagerFind(): void
    {
        $em = EntityServiceProvider::getEntityManager();

        // Create test entity
        $entity = new TestEntity(['name' => 'FindTest', 'status' => 'active']);
        $em->save($entity);

        // Find by ID
        $found = $em->find(TestEntity::class, $entity->getId());
        $this->assert($found !== null, "find() returns entity");

        // Find by criteria
        $found2 = $em->findOneBy(TestEntity::class, ['name' => 'FindTest']);
        $this->assert($found2 !== null, "findOneBy() returns entity");

        // Find many
        $all = $em->findBy(TestEntity::class, ['status' => 'active']);
        $this->assert(is_array($all), "findBy() returns array");
    }

    private function testEntityManagerQuery(): void
    {
        $em = EntityServiceProvider::getEntityManager();
        $query = $em->query(TestEntity::class);

        $this->assert($query instanceof EntityQuery, "query() returns EntityQuery");
    }

    private function testEntityManagerTransactions(): void
    {
        $em = EntityServiceProvider::getEntityManager();

        $result = $em->transaction(function() use ($em) {
            $entity = new TestEntity(['name' => 'Transaction', 'status' => 'active']);
            $em->save($entity);
            return $entity->getId();
        });

        $this->assert($result !== null, "Transaction returns result");
    }

    private function testEntityManagerEvents(): void
    {
        $em = EntityServiceProvider::getEntityManager();
        $called = false;

        $em->on('postSave', function($event) use (&$called) {
            $called = true;
        });

        $entity = new TestEntity(['name' => 'Event', 'status' => 'active']);
        $em->save($entity);

        $this->assert($called, "Event listener is called");
    }

    // =========================================================================
    // Repository Tests
    // =========================================================================

    private function testRepositoryBasic(): void
    {
        $em = EntityServiceProvider::getEntityManager();
        $repo = new EntityRepository($em, TestEntity::class);

        // Create
        $entity = $repo->createAndSave(['name' => 'Repo Test', 'status' => 'active']);
        $this->assert($entity->getId() !== null, "Repository creates entity");

        // Find
        $found = $repo->find($entity->getId());
        $this->assert($found !== null, "Repository finds entity");

        // All
        $all = $repo->all();
        $this->assert(is_array($all), "Repository returns all");

        // Count
        $count = $repo->count();
        $this->assert($count > 0, "Repository counts entities");
    }

    private function testRepositoryPagination(): void
    {
        $em = EntityServiceProvider::getEntityManager();
        $repo = new EntityRepository($em, TestEntity::class);

        // Create some entities
        for ($i = 0; $i < 5; $i++) {
            $repo->createAndSave(['name' => "Page Test {$i}", 'status' => 'active']);
        }

        $result = $repo->paginate(1, 3);

        $this->assert(isset($result['data']), "Pagination has data");
        $this->assert(isset($result['total']), "Pagination has total");
        $this->assert(isset($result['last_page']), "Pagination has last_page");
        $this->assert(count($result['data']) <= 3, "Pagination respects per_page");
    }

    // =========================================================================
    // Test Helpers
    // =========================================================================

    private function createTestDatabase(): \PDO
    {
        $db = new \PDO('sqlite::memory:');
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create test table
        $db->exec("
            CREATE TABLE test_entities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255),
                status VARCHAR(50),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        return $db;
    }

    private function createTestEntities(): void
    {
        $this->db->exec("INSERT INTO test_entities (name, status) VALUES ('Test 1', 'active')");
        $this->db->exec("INSERT INTO test_entities (name, status) VALUES ('Test 2', 'draft')");
        $this->db->exec("INSERT INTO test_entities (name, status) VALUES ('Test 3', 'active')");
    }

    // =========================================================================
    // Test Framework
    // =========================================================================

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  \033[32m✓\033[0m {$message}\n";
        } else {
            $this->failed++;
            echo "  \033[31m✗\033[0m {$message}\n";
        }
    }

    private function header(string $title): void
    {
        echo "\n\033[1;34m{$title}\033[0m\n";
        echo str_repeat("=", strlen($title)) . "\n";
    }

    private function group(string $name): void
    {
        echo "\n\033[1m{$name}\033[0m\n";
    }

    private function summary(): void
    {
        $total = $this->passed + $this->failed;
        echo "\n" . str_repeat("-", 50) . "\n";
        echo "Total: {$total} tests, ";
        echo "\033[32m{$this->passed} passed\033[0m, ";
        echo "\033[31m{$this->failed} failed\033[0m\n";
    }
}

/**
 * TestEntity - Simple entity for testing
 */
class TestEntity extends BaseEntity
{
    protected ?int $id = null;
    protected string $name = '';
    protected string $status = '';
    protected ?\DateTimeImmutable $created_at = null;
    protected ?\DateTimeImmutable $updated_at = null;

    public static function getTableName(): string
    {
        return 'test_entities';
    }

    public static function getFillable(): array
    {
        return ['name', 'status'];
    }
}

// =========================================================================
// CLI Runner
// =========================================================================

if (php_sapi_name() === 'cli' && realpath($argv[0]) === __FILE__) {
    require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

    // Or manual requires if no autoloader
    $basePath = dirname(__DIR__, 2);
    require_once $basePath . '/Entity/EntityInterface.php';
    require_once $basePath . '/Entity/BaseEntity.php';
    require_once $basePath . '/Entity/EntityQuery.php';
    require_once $basePath . '/Entity/EntityManager.php';
    require_once $basePath . '/Entity/EntityRepository.php';
    require_once $basePath . '/Entity/EntityServiceProvider.php';
    require_once $basePath . '/Content/Node.php';
    require_once $basePath . '/User/User.php';

    $test = new EntitySystemTest();
    $test->runAll();
}
