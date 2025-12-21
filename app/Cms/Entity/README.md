# MonkeysCMS Entity System

The Entity System provides the foundation for all CMS data management with a clean ORM-like API.

## Quick Start

```php
use App\Cms\Entity\EntityServiceProvider;
use App\Cms\Entity\EntityManager;
use App\Cms\Content\Node;
use App\Cms\Content\NodeManager;

// Initialize with database
EntityServiceProvider::createFromConfig([
    'host' => 'localhost',
    'database' => 'monkeyscms',
    'username' => 'root',
    'password' => '',
]);

// Get entity manager
$em = EntityServiceProvider::getEntityManager();

// Create a node
$node = new Node([
    'type' => 'article',
    'title' => 'Hello World',
    'status' => 'draft',
]);
$em->save($node);

// Query nodes
$articles = $em->query(Node::class)
    ->where('type', 'article')
    ->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

## Core Components

### EntityManager

The central class for all entity operations.

```php
$em = new EntityManager($pdo);

// Create/Update
$em->save($entity);

// Find
$entity = $em->find(Node::class, 1);
$entity = $em->findOrFail(Node::class, 1);
$entities = $em->findMany(Node::class, [1, 2, 3]);

// Query
$results = $em->query(Node::class)
    ->where('status', 'published')
    ->get();

// Delete
$em->delete($entity);
$em->forceDelete($entity);

// Transactions
$em->transaction(function() use ($em) {
    $em->save($entity1);
    $em->save($entity2);
});
```

### EntityQuery

Fluent query builder for entities.

```php
$query = $em->query(Node::class);

// Where clauses
$query->where('status', 'published');
$query->where('score', '>', 100);
$query->orWhere('featured', true);
$query->whereIn('type', ['article', 'page']);
$query->whereNotIn('status', ['archived']);
$query->whereNull('deleted_at');
$query->whereNotNull('published_at');
$query->whereBetween('score', 50, 100);
$query->whereLike('title', '%hello%');

// Ordering
$query->orderBy('created_at', 'DESC');
$query->latest(); // created_at DESC
$query->oldest(); // created_at ASC

// Limit & Offset
$query->limit(10);
$query->offset(20);
$query->forPage(2, 15); // Page 2, 15 per page

// Execution
$results = $query->get();
$first = $query->first();
$count = $query->count();
$exists = $query->exists();

// Pagination
$paginated = $query->paginate(15, 1);
// Returns: ['data' => [...], 'total' => 100, 'page' => 1, 'per_page' => 15, 'last_page' => 7]

// Aggregates
$sum = $query->sum('score');
$avg = $query->avg('score');
$max = $query->max('score');
$min = $query->min('score');

// Pluck values
$titles = $query->pluck('title');
$titleById = $query->pluck('title', 'id');
```

### BaseEntity

Abstract base class for all entities.

```php
class Article extends BaseEntity
{
    protected ?int $id = null;
    protected string $title = '';
    protected string $content = '';
    protected string $status = 'draft';

    public static function getTableName(): string
    {
        return 'articles';
    }

    public static function getFillable(): array
    {
        return ['title', 'content', 'status'];
    }

    public static function getHidden(): array
    {
        return ['password'];
    }

    public static function getCasts(): array
    {
        return [
            'id' => 'int',
            'created_at' => 'datetime',
        ];
    }
}
```

### EntityRepository

Generic repository for entity operations.

```php
$repo = new EntityRepository($em, Node::class);

// Find
$entity = $repo->find(1);
$entity = $repo->findOrFail(1);
$entities = $repo->findBy(['status' => 'published']);
$entity = $repo->findOneBy(['slug' => 'hello-world']);

// CRUD
$repo->save($entity);
$repo->delete($entity);
$repo->deleteById(1);

// Count
$count = $repo->count(['status' => 'published']);
$exists = $repo->exists(['email' => 'test@example.com']);

// Pagination
$result = $repo->paginate(1, 15, ['status' => 'published']);
```

## Node System

### Node Entity

```php
use App\Cms\Content\Node;
use App\Cms\Content\NodeStatus;

$node = new Node([
    'type' => 'article',
    'title' => 'Hello World',
]);

// Status workflow
$node->publish();      // Sets status to published, sets published_at
$node->unpublish();    // Sets status to draft
$node->archive();      // Sets status to archived

// Check status
$node->isPublished();
$node->isDraft();
$node->isArchived();

// Fields
$node->setField('body', 'Content here');
$node->getField('body');
$node->hasField('body');

// Slug
$node->generateSlug();  // From title
$node->ensureSlug();    // Generate if empty
```

### NodeManager

High-level operations for nodes.

```php
$manager = new NodeManager($em, $fieldStorage);

// Create
$node = $manager->create('article', [
    'title' => 'Hello World',
    'body' => 'Content here',
], $authorId);

// Update
$manager->update($node, ['title' => 'New Title']);

// Publish
$manager->publish($node);
$manager->unpublish($node);
$manager->archive($node);

// Schedule
$manager->schedule($node, new DateTimeImmutable('+1 week'));
$manager->processScheduled(); // Called by cron

// Revisions
$manager->createRevision($node, 'Updated content');
$revisions = $manager->getRevisions($node);
$manager->restoreRevision($node, $revisionId);

// Find
$node = $manager->find(1);
$node = $manager->findBySlug('hello-world');
$nodes = $manager->findPublished('article', 10);
$nodes = $manager->findByAuthor($authorId);

// Search
$results = $manager->search('hello', 'article', 20);

// Paginate
$result = $manager->paginate(1, 15, 'article', 'published');

// Clone
$copy = $manager->duplicate($node, 'Copy of Hello World');
```

## Migrations

### Running Migrations

```bash
# Run all pending migrations
php cms migrate

# Rollback last batch
php cms migrate:rollback

# Reset all migrations
php cms migrate:reset

# Drop all and re-run
php cms migrate:fresh

# Show status
php cms migrate:status
```

### Creating Migrations

```php
// app/Cms/Database/migrations/011_custom.php
return new class {
    public function up(\PDO $db): void
    {
        $db->exec("
            CREATE TABLE custom (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS custom");
    }
};
```

## Events

```php
$em = new EntityManager($db);

// Register listeners
$em->on('preSave', function($event) {
    echo "Saving: " . get_class($event->entity);
});

$em->on('postSave', function($event) {
    // Clear cache, send notifications, etc.
});

$em->on('preDelete', function($event) {
    // Prevent deletion, cleanup, etc.
});

$em->on('postDelete', function($event) {
    // Log deletion, etc.
});
```

## Soft Deletes

```php
class Article extends BaseEntity implements SoftDeleteInterface
{
    use SoftDeleteTrait;
    
    // ...
}

// Delete (soft)
$em->delete($article);

// Restore
$em->restore($article);

// Force delete
$em->forceDelete($article);

// Query including deleted
$query->withTrashed()->get();

// Query only deleted
$query->onlyTrashed()->get();
```

## Revisions

```php
class Article extends BaseEntity implements RevisionInterface
{
    use RevisionTrait;
    
    // ...
}

// Revision auto-increments on update
$article->getRevisionId();  // 1
$em->save($article);
$article->getRevisionId();  // 2
```

## Database Schema

### Core Tables

| Table | Description |
|-------|-------------|
| `users` | User accounts |
| `roles` | Role definitions |
| `permissions` | Permission definitions |
| `user_roles` | User-role assignments |
| `role_permissions` | Role-permission assignments |
| `content_types` | Content type definitions |
| `nodes` | Content nodes |
| `node_revisions` | Node revision history |
| `field_definitions` | Field definitions |
| `field_values` | Field values (EAV) |
| `vocabularies` | Taxonomy vocabularies |
| `terms` | Taxonomy terms |
| `media` | Media files |
| `menus` | Menu definitions |
| `menu_items` | Menu items |
| `blocks` | Block instances |

## Testing

```bash
# Run entity system tests
php app/Cms/Entity/Tests/EntitySystemTest.php
```

## File Structure

```
app/Cms/
├── Entity/
│   ├── EntityInterface.php    # Entity contracts
│   ├── BaseEntity.php         # Base entity class
│   ├── EntityManager.php      # Central CRUD operations
│   ├── EntityQuery.php        # Query builder
│   ├── EntityRepository.php   # Repository pattern
│   └── EntityServiceProvider.php
├── Content/
│   ├── Node.php              # Content entity
│   ├── NodeManager.php       # Content operations
│   └── NodeRepository.php    # Content queries
├── User/
│   ├── User.php              # User entity
│   ├── UserManager.php       # User operations
│   └── UserRepository.php    # User queries
└── Database/
    ├── MigrationRunner.php   # Migration execution
    ├── Console/
    │   └── MigrationCommands.php
    └── migrations/
        ├── 001_users.php
        ├── 002_roles_permissions.php
        ├── 003_content_types.php
        ├── 004_nodes.php
        ├── 005_fields.php
        └── ...
```
