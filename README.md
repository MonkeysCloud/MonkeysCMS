# MonkeysCMS

A modern, code-first CMS built on the MonkeysLegion framework. MonkeysCMS combines the best features of Drupal (entity/field system, modularity) and WordPress (ease of use) while eliminating their weaknesses.

## Key Features

- **Code-First Entities**: Define content types as PHP classes with attributes - no YAML, no UI configuration
- **Auto-Sync Schema**: Enable a module and database tables are created automatically - no migrations, no CLI commands
- **True Modularity**: Isolated modules with proper namespaces, dependencies, and lifecycle hooks
- **Normalized Database**: Proper relational tables with foreign keys - no EAV (Entity-Attribute-Value) anti-pattern
- **MLC Configuration**: Human-readable `.mlc` config files with caching, validation, and environment interpolation
- **Theme System**: Separate contrib and custom themes with ML View template engine
- **API-First**: Full REST API for all content operations
- **Modern PHP**: Built with PHP 8.4+ using strict types, attributes, and modern patterns

## Requirements

- PHP 8.4+
- MySQL 8.0+ or SQLite 3
- Composer 2.x

## Installation

### Via Composer (Recommended)

```bash
composer create-project monkeyscloud/monkeyscms my-project
cd my-project
./monkeys serve
```

### Manual Installation (Git)

```bash
# Clone the repository
git clone https://github.com/MonkeysCloud/MonkeysCMS.git
cd monkeyscms

# Install dependencies
composer install

# Copy environment file and configure (Managed automatically in create-project)
cp .env.example .env

# Edit .env with your database credentials
# DB_HOST=127.0.0.1
# DB_DATABASE=monkeyscms
# DB_USERNAME=root
# DB_PASSWORD=

# Start the development server
./monkeys serve
```

## Quick Start

### 1. Enable a Module

Via CLI:

```bash
./monkeys cms:module:enable Custom/Ecommerce
```

Via API:

```bash
curl -X POST http://localhost:8000/admin/modules/Custom/Ecommerce/enable
```

When you enable a module, MonkeysCMS:

1. Discovers all entity classes in the module
2. Reads their attributes (ContentType, Field, Relation)
3. Generates SQL schema
4. Executes CREATE TABLE statements immediately
5. Registers the module as active

### 2. Work with Content

**Create a product:**

```bash
curl -X POST http://localhost:8000/admin/content/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Premium Widget",
    "sku": "WDG-001",
    "price": 29.99,
    "stock_quantity": 100,
    "status": "published"
  }'
```

**List products with pagination:**

```bash
curl "http://localhost:8000/admin/content/products?page=1&per_page=20&sort=created_at&direction=DESC"
```

**Search products:**

```bash
curl "http://localhost:8000/admin/content/products/search?q=widget"
```

**Get a single product:**

```bash
curl http://localhost:8000/admin/content/products/1
```

**Update a product:**

```bash
curl -X PUT http://localhost:8000/admin/content/products/1 \
  -H "Content-Type: application/json" \
  -d '{"price": 34.99}'
```

**Delete a product:**

```bash
curl -X DELETE http://localhost:8000/admin/content/products/1
```

## Creating Your Own Module

### 1. Create the Module Structure

```
app/Modules/Custom/YourModule/
├── Entities/
│   └── YourEntity.php
├── Controllers/
│   └── YourController.php
├── Loader.php
└── module.json
```

### 2. Define an Entity

```php
<?php

declare(strict_types=1);

namespace App\Modules\Custom\YourModule\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

#[ContentType(
    tableName: 'your_entities',
    label: 'Your Entity',
    description: 'Description of your entity',
    publishable: true
)]
class YourEntity extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(
        type: 'string',
        label: 'Title',
        required: true,
        length: 255,
        searchable: true
    )]
    public string $title = '';

    #[Field(
        type: 'text',
        label: 'Content',
        widget: 'wysiwyg'
    )]
    public string $content = '';

    #[Field(
        type: 'boolean',
        label: 'Published',
        default: false
    )]
    public bool $is_published = false;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;
}
```

### 3. Create module.mlc

MonkeysCMS uses the `.mlc` (MonkeysLegion Config) format for configuration files:

```mlc
# YourModule Configuration

name = "YourModule"
version = "1.0.0"
description = "Description of your module"
namespace = "App\\Modules\\Custom\\YourModule"

# Module entities
entities = [
    "App\\Modules\\Custom\\YourModule\\Entities\\YourEntity"
]

# Dependencies (other modules required)
dependencies = []

# Module permissions
[permissions]
view_your_entities = "View your entities"
create_your_entities = "Create your entities"
edit_your_entities = "Edit your entities"
delete_your_entities = "Delete your entities"

# Module configuration defaults
[config]
items_per_page = 20
```

### 4. Create Loader.php (Optional)

```php
<?php

declare(strict_types=1);

namespace App\Modules\Custom\YourModule;

class Loader
{
    public function onEnable(): void
    {
        // Called after schema sync
        // Register routes, events, etc.
    }

    public function onDisable(): void
    {
        // Cleanup when module is disabled
    }

    public function getDependencies(): array
    {
        return [];
    }
}
```

### 5. Enable Your Module

```bash
./monkeys cms:module:enable Custom/YourModule
```

## Architecture

### Attributes

- **`#[ContentType]`**: Marks a class as a CMS content type with table name, label, and features (revisionable, publishable, translatable)
- **`#[Field]`**: Defines a database field with type, label, constraints, and UI hints (widget, options)
- **`#[Relation]`**: Defines relationships between entities (ManyToOne, OneToMany, ManyToMany)
- **`#[Id]`**: Defines the primary key strategy (auto, uuid, ulid)

### Field Types

| Type     | SQL Mapping               | PHP Type          |
| -------- | ------------------------- | ----------------- |
| string   | VARCHAR(length)           | string            |
| text     | LONGTEXT                  | string            |
| int      | INT                       | int               |
| decimal  | DECIMAL(precision, scale) | string            |
| boolean  | TINYINT(1)                | bool              |
| datetime | DATETIME                  | DateTimeImmutable |
| json     | JSON                      | array             |

### Core Components

- **BaseEntity**: Abstract base class providing hydration, serialization, and dirty tracking
- **SchemaGenerator**: Reflection-based SQL generator that reads attributes and creates DDL
- **ModuleManager**: Handles module lifecycle - discovery, enabling, disabling, schema sync
- **CmsRepository**: Generic CRUD repository that works with any entity

## API Endpoints

### Modules

| Method | Endpoint                               | Description          |
| ------ | -------------------------------------- | -------------------- |
| GET    | /admin/modules                         | List all modules     |
| GET    | /admin/modules/enabled                 | List enabled modules |
| GET    | /admin/modules/{module}/details        | Get module details   |
| POST   | /admin/modules/{module}/enable         | Enable a module      |
| POST   | /admin/modules/{module}/disable        | Disable a module     |
| POST   | /admin/modules/{module}/sync           | Re-sync schema       |
| GET    | /admin/modules/{module}/schema-preview | Preview SQL          |

### Content

| Method | Endpoint                        | Description                  |
| ------ | ------------------------------- | ---------------------------- |
| GET    | /admin/content/types            | List all content types       |
| GET    | /admin/content/{type}           | List content with pagination |
| GET    | /admin/content/{type}/{id}      | Get single item              |
| POST   | /admin/content/{type}           | Create new item              |
| PUT    | /admin/content/{type}/{id}      | Update item                  |
| DELETE | /admin/content/{type}/{id}      | Delete item                  |
| GET    | /admin/content/{type}/search?q= | Search content               |

### Users & Authentication

| Method | Endpoint                      | Description                |
| ------ | ----------------------------- | -------------------------- |
| GET    | /admin/users                  | List users with pagination |
| GET    | /admin/users/{id}             | Get user details           |
| POST   | /admin/users                  | Create user                |
| PUT    | /admin/users/{id}             | Update user                |
| DELETE | /admin/users/{id}             | Delete user                |
| GET    | /admin/users/{id}/roles       | Get user's roles           |
| PUT    | /admin/users/{id}/roles       | Set user's roles           |
| GET    | /admin/users/{id}/permissions | Get user's permissions     |

### Roles & Permissions

| Method | Endpoint                      | Description                  |
| ------ | ----------------------------- | ---------------------------- |
| GET    | /admin/roles                  | List all roles               |
| GET    | /admin/roles/{id}             | Get role with permissions    |
| POST   | /admin/roles                  | Create role                  |
| PUT    | /admin/roles/{id}             | Update role                  |
| DELETE | /admin/roles/{id}             | Delete role                  |
| PUT    | /admin/roles/{id}/permissions | Set role permissions         |
| GET    | /admin/permissions            | List all permissions         |
| GET    | /admin/permissions/grouped    | List permissions by group    |
| GET    | /admin/permissions/matrix     | Get permission matrix for UI |
| PUT    | /admin/permissions/matrix     | Batch update permissions     |

### Taxonomy (Categories & Tags)

| Method | Endpoint                                | Description                 |
| ------ | --------------------------------------- | --------------------------- |
| GET    | /admin/taxonomies                       | List all taxonomies         |
| GET    | /admin/taxonomies/{id}                  | Get taxonomy with terms     |
| POST   | /admin/taxonomies                       | Create taxonomy             |
| PUT    | /admin/taxonomies/{id}                  | Update taxonomy             |
| DELETE | /admin/taxonomies/{id}                  | Delete taxonomy             |
| GET    | /admin/taxonomies/{id}/terms            | Get terms (flat or tree)    |
| GET    | /admin/taxonomies/{id}/options          | Get terms for select widget |
| POST   | /admin/taxonomies/{id}/terms            | Create term                 |
| PUT    | /admin/terms/{id}                       | Update term                 |
| DELETE | /admin/terms/{id}                       | Delete term                 |
| GET    | /admin/entity-terms/{type}/{id}         | Get entity's terms          |
| PUT    | /admin/entity-terms/{type}/{id}/{vocab} | Set entity terms            |

### Menus

| Method | Endpoint                         | Description         |
| ------ | -------------------------------- | ------------------- |
| GET    | /admin/menus                     | List all menus      |
| GET    | /admin/menus/{id}                | Get menu with items |
| POST   | /admin/menus                     | Create menu         |
| PUT    | /admin/menus/{id}                | Update menu         |
| DELETE | /admin/menus/{id}                | Delete menu         |
| POST   | /admin/menus/{id}/items          | Add menu item       |
| PUT    | /admin/menus/{id}/items/{itemId} | Update menu item    |
| DELETE | /admin/menus/{id}/items/{itemId} | Delete menu item    |
| PUT    | /admin/menus/{id}/items/reorder  | Reorder items       |

### Settings

| Method | Endpoint                      | Description           |
| ------ | ----------------------------- | --------------------- |
| GET    | /admin/settings               | List all settings     |
| GET    | /admin/settings/group/{group} | Get settings by group |
| GET    | /admin/settings/{key}         | Get single setting    |
| PUT    | /admin/settings/{key}         | Update setting        |
| PUT    | /admin/settings               | Batch update settings |

## Role-Based Access Control (RBAC)

MonkeysCMS includes a comprehensive permission system inspired by Drupal but simplified.

### System Roles

- **Super Admin**: Bypasses all permission checks (full access)
- **Administrator**: Site administration with most permissions
- **Editor**: Can create and edit all content
- **Author**: Can create and edit own content
- **Authenticated User**: Default role for logged-in users

### Permission Types

Permissions follow the pattern `{action}_{entity_type}`:

- `view_products` - View any product
- `view_own_products` - View only own products
- `create_products` - Create new products
- `edit_products` - Edit any product
- `edit_own_products` - Edit only own products
- `delete_products` - Delete any product
- `delete_own_products` - Delete only own products
- `administer_products` - Full control over products

### Using Permissions

```php
// In a controller
public function edit(int $id): JsonResponse
{
    $product = $this->repository->find(Product::class, $id);

    // Check permission
    $this->permissions->authorizeEntity('edit', $product);

    // Or check manually
    if (!$this->permissions->canOnEntity('edit', $product)) {
        return new JsonResponse(['error' => 'Access denied'], 403);
    }

    // ...
}
```

### Auto-Registering Entity Permissions

When enabling a module, permissions are automatically created for each entity:

```php
// In your module's Loader.php
public function onEnable(): void
{
    $this->permissions->registerEntityPermissions(
        entityType: 'products',
        entityLabel: 'Products',
        module: 'Ecommerce',
        actions: ['view', 'view_own', 'create', 'edit', 'edit_own', 'delete', 'delete_own']
    );
}
```

## Taxonomy System

MonkeysCMS provides a Drupal-like taxonomy system for categorizing content.

### Vocabularies

A vocabulary is a container for related terms (e.g., "Categories", "Tags", "Genres"):

```php
// Create a vocabulary
$vocabulary = new Vocabulary();
$vocabulary->name = 'Product Categories';
$vocabulary->machine_name = 'product_categories';
$vocabulary->hierarchical = true;  // Allow nested terms
$vocabulary->multiple = true;      // Allow multiple selections
$vocabulary->entity_types = ['products'];  // Restrict to products

$taxonomyService->saveVocabulary($vocabulary);
```

### Terms

Terms are the individual items within a vocabulary:

```php
// Create a term
$term = new Term();
$term->vocabulary_id = $vocabulary->id;
$term->name = 'Electronics';
$term->slug = 'electronics';
$term->parent_id = null;  // Root term

$taxonomyService->saveTerm($term);

// Create child term
$childTerm = new Term();
$childTerm->vocabulary_id = $vocabulary->id;
$childTerm->name = 'Laptops';
$childTerm->parent_id = $term->id;

$taxonomyService->saveTerm($childTerm);
```

### Assigning Terms to Content

```php
// Add term to entity
$taxonomyService->addTermToEntity('products', $product->id, $term->id);

// Set multiple terms (replaces existing)
$taxonomyService->setEntityTerms('products', $product->id, $vocabulary->id, [1, 2, 3]);

// Get entity's terms
$terms = $taxonomyService->getEntityTerms('products', $product->id);
```

## Comparison with Other CMS

### vs Drupal

| Drupal                          | MonkeysCMS              |
| ------------------------------- | ----------------------- |
| hook_install(), hook_update_N() | Auto-sync on enable     |
| drush updb                      | Not needed              |
| Field configuration tables      | Single normalized table |
| Entity API complexity           | Simple BaseEntity       |
| YAML configuration              | PHP attributes          |

### vs WordPress

| WordPress                | MonkeysCMS               |
| ------------------------ | ------------------------ |
| wp_postmeta EAV          | Proper normalized tables |
| wp_insert_post arrays    | Typed entity classes     |
| No foreign keys          | Real relationships       |
| Global functions         | Namespaced modules       |
| No dependency management | Module dependencies      |

## CLI Commands

MonkeysCMS includes a unified CLI tool `./monkeys` (or `bin/cms`) that provides access to all framework and application commands.

```bash
# List all available commands
./monkeys list

# Core CMS Commands
./monkeys cms:module:list           # List all modules
./monkeys cms:module:enable <name>  # Enable a module
./monkeys cms:module:disable <name> # Disable a module
./monkeys menu:seed                 # Seed default admin menus (Admin Dashboard)

# Cache Management
./monkeys cache:clear               # Clear default cache store
./monkeys cache:clear --store=redis # Clear specific store
./monkeys cache:config              # Display cache configuration
./monkeys cache:flush-all           # Flush all configured cache stores
./monkeys cache:set key val         # Set a cache value
./monkeys cache:get key             # Get a cache value
./monkeys cache:forget key          # Remove a cache key
./monkeys cache:monitor             # Monitor cache performance

# Database & Schema
./monkeys migrate                   # Run pending migrations
./monkeys rollback                  # Rollback migrations
./monkeys db:seed                   # Run database seeders
./monkeys schema:update             # Update database schema from entities

# Generators (Make)
./monkeys make:controller Name      # Create a new controller
./monkeys make:entity Name          # Create a new entity
./monkeys make:migration            # Create a migration from entities
```

## Admin Interface

MonkeysCMS now features a fully functional Admin UI with a dynamic menu system.

- **Dashboard**: Overview of system status and modules (`/admin/dashboard`)
- **Menu Management**: Manage Admin and Frontend menus via UI (`/admin/menus`)
- **Admin Theme**: Custom admin theme located in `themes/custom/admin`

The Admin Sidebar is dynamically populated from the 'admin' menu, which can be seeded using `./monkeys menu:seed`.

## Caching (MonkeysLegion-Cache)

MonkeysCMS uses [MonkeysLegion-Cache](https://github.com/MonkeysCloud/MonkeysLegion-Cache) for high-performance caching with multiple drivers.

### Configuration

Edit `.env` to configure caching:

```env
# Cache driver: file, redis, memcached, array
CACHE_DRIVER=file
CACHE_PREFIX=monkeyscms
CACHE_TTL=3600

# Redis (if using redis driver)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_CACHE_DB=1

# Memcached (if using memcached driver)
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
```

### Using the Cache Facade

```php
use MonkeysLegion\Cache\Cache;

// Store items
Cache::set('key', 'value', 3600);       // Store for 1 hour
Cache::forever('key', 'value');          // Store forever
Cache::add('key', 'value', 3600);        // Store only if doesn't exist

// Retrieve items
$value = Cache::get('key');              // Get value
$value = Cache::get('key', 'default');   // Get with default
$value = Cache::pull('key');             // Get and delete

// Remember pattern (get or compute)
$users = Cache::remember('users', 3600, function() {
    return User::all();
});

// Check existence
if (Cache::has('key')) {
    // Key exists
}

// Delete items
Cache::delete('key');                    // Delete single
Cache::deleteMultiple(['key1', 'key2']); // Delete multiple
Cache::clear();                          // Clear all

// Increment/Decrement
Cache::increment('counter');
Cache::decrement('counter', 5);
```

### Cache Tagging

```php
// Store with tags
Cache::tags(['users', 'premium'])->set('user:1', $user, 3600);

// Retrieve tagged items
$user = Cache::tags(['users', 'premium'])->get('user:1');

// Flush all items with specific tags
Cache::tags(['users'])->clear();
```

### Helper Functions

```php
// Quick access helpers
cache('key');                           // Get
cache('key', 'value');                  // Set
cache(['key1' => 'val1']);              // Set multiple

cache_remember('key', 3600, fn() => expensiveQuery());
cache_forever('key', $value);
cache_forget('key');
cache_flush();
cache_has('key');
cache_pull('key');
```

### Using Different Stores

```php
// Use specific store
Cache::store('redis')->set('key', 'value');
Cache::store('file')->get('key');

// Chain with tags
Cache::store('redis')->tags(['api'])->set('key', 'value', 3600);
```

### CMS Cache Service

MonkeysCMS provides a specialized cache service for CMS-specific caching:

```php
use App\Cms\Cache\CmsCacheService;

// Entity caching
$user = $cmsCache->entity('user', 123, fn() => $this->loadUser(123));

// Query caching
$articles = $cmsCache->query('articles', ['status' => 'published'], fn() => $this->getArticles());

// Menu caching
$menu = $cmsCache->menu('main', fn() => $this->loadMenu('main'));

// Settings caching
$settings = $cmsCache->settings('general', fn() => $this->loadSettings());

// Invalidation
$cmsCache->invalidateEntity('user');
$cmsCache->invalidateMenu('main');
$cmsCache->invalidateSettings();
$cmsCache->invalidateAll();  // Clear all CMS caches
```

## Directory Structure

```
monkeyscms/
├── app/
│   ├── Cms/                    # CMS Core Engine
│   │   ├── Attributes/         # PHP Attributes (ContentType, Field, etc.)
│   │   ├── Core/               # BaseEntity, SchemaGenerator
│   │   ├── Modules/            # ModuleManager
│   │   ├── Repository/         # CmsRepository
│   │   ├── Security/           # PermissionService
│   │   └── Themes/             # ThemeManager, ThemeInfo
│   ├── Modules/
│   │   ├── Core/               # Core module (users, roles, taxonomy)
│   │   ├── Contrib/            # Community modules
│   │   └── Custom/             # Your business logic
│   │       └── Ecommerce/      # Example module
│   ├── Controllers/Admin/      # Admin API controllers
│   ├── Middleware/             # Auth & permission middleware
│   ├── Views/                  # Fallback views
│   └── Cli/Command/            # CLI commands
├── config/                     # MLC configuration files
│   ├── app.mlc                 # Application config
│   ├── database.mlc            # Database config
│   ├── cache.mlc               # Cache/session config
│   └── schema.php              # Config validation schema
├── themes/
│   ├── contrib/                # Community themes
│   │   ├── default/            # Default frontend theme
│   │   │   └── theme.mlc       # Theme configuration
│   │   └── admin-default/      # Default admin theme
│   │       └── theme.mlc       # Theme configuration
│   └── custom/                 # Your custom themes
├── public/                     # Web entry point
├── storage/                    # Logs, cache, uploads
└── var/                        # Framework cache, migrations
```

## Theme Configuration

Themes use `.mlc` files for configuration. Example `theme.mlc`:

```mlc
# My Custom Theme

name = "my-theme"
version = "1.0.0"
description = "A beautiful custom theme"
author = "Your Name"
type = "frontend"  # or "admin"

# Parent theme for inheritance
parent = null

# Theme regions for block placement
[regions]
header = "Header"
navigation = "Main Navigation"
content = "Main Content"
sidebar = "Sidebar"
footer = "Footer"

# Theme configuration options
[config]
primary_color = "#3b82f6"
secondary_color = "#64748b"
show_sidebar = true

# Asset files
[assets]
css = ["assets/css/theme.css"]
js = ["assets/js/theme.js"]

# Menu locations
[menus]
main = "Main Navigation"
footer = "Footer Menu"

# Feature support
supports = ["custom-logo", "custom-background", "menus", "widgets"]
```

## Configuration with MLC

MonkeysCMS uses the MLC (MonkeysLegion Config) format - a human-readable configuration format with powerful features.

### Example: config/app.mlc

```
app {
    name = ${APP_NAME:MonkeysCMS}
    env = ${APP_ENV:production}
    debug = ${APP_DEBUG:false}
}

themes {
    active = ${CMS_THEME:default}

    paths {
        contrib = "themes/contrib"
        custom = "themes/custom"
    }
}

view {
    paths = [
        "themes/custom/${CMS_THEME}/views",
        "themes/contrib/${CMS_THEME}/views",
        "app/Views"
    ]

    cache {
        enabled = ${VIEW_CACHE:true}
        path = "var/cache/views"
    }
}
```

### MLC Features

- **Environment Variables**: `${VAR_NAME:default_value}` syntax
- **Nested Structures**: Clean block-based organization
- **Arrays**: `[item1, item2, item3]` syntax
- **Type-Safe Access**: `$config->getString()`, `$config->getBool()`, etc.
- **Caching**: File-based caching with TTL
- **Validation**: Schema-based validation at startup

## Theme System

MonkeysCMS separates themes into two directories:

- **themes/contrib**: Community/third-party themes
- **themes/custom**: Site-specific custom themes

Custom themes override contrib themes with the same name.

### Theme Structure

```
themes/contrib/default/
├── theme.json          # Theme metadata
├── views/
│   ├── layouts/        # Base layouts (base.ml.php)
│   └── partials/       # Reusable partials
├── components/         # ML View components
└── assets/
    ├── css/
    ├── js/
    └── images/
```

### theme.json

```json
{
  "name": "default",
  "version": "1.0.0",
  "description": "MonkeysCMS Default Theme",
  "parent": null,
  "regions": {
    "header": "Header",
    "content": "Main Content",
    "sidebar": "Sidebar",
    "footer": "Footer"
  },
  "config": {
    "primary_color": "#3b82f6",
    "show_sidebar": true
  }
}
```

### Theme API Endpoints

| Method | Endpoint                       | Description              |
| ------ | ------------------------------ | ------------------------ |
| GET    | /admin/themes                  | List all themes          |
| GET    | /admin/themes/contrib          | List contrib themes      |
| GET    | /admin/themes/custom           | List custom themes       |
| GET    | /admin/themes/{theme}          | Get theme details        |
| POST   | /admin/themes/{theme}/activate | Activate a theme         |
| POST   | /admin/themes                  | Create new custom theme  |
| GET    | /admin/themes/{theme}/validate | Validate theme structure |
| POST   | /admin/themes/cache/clear      | Clear theme cache        |

### Creating a Custom Theme

```bash
# Via API
curl -X POST http://localhost:8000/admin/themes \
  -H "Content-Type: application/json" \
  -d '{
    "name": "my-theme",
    "parent": "default",
    "description": "My custom theme"
  }'
```

### ML View Template Syntax

Templates use the `.ml.php` extension with blade-like syntax:

```php
@extends('layouts.base')

@section('content')
    <h1>{{ $title }}</h1>

    @foreach($items as $item)
        <x-card title="{{ $item->name }}">
            {{ $item->description }}
        </x-card>
    @endforeach
@endsection

@push('scripts')
    <script src="/js/page.js"></script>
@endpush
```

### Using Components

```html
{{-- Basic component --}}
<x-button variant="primary">Click Me</x-button>

{{-- Component with slots --}}
<x-card>
  <x-slot name="header">Card Title</x-slot>
  Card content goes here
</x-card>

{{-- Passing attributes --}}
<x-input type="email" name="email" class="w-full" required />
```

## Block System

MonkeysCMS features a comprehensive block system for reusable content blocks and widgets. Blocks can be placed in theme regions and support both code-defined and database-defined types with custom fields.

### Block Type Sources

1. **Code-Defined Types**: PHP classes implementing `BlockTypeInterface`
2. **Database-Defined Types**: Created via admin UI with custom fields

### Built-in Block Types

| Type      | Category   | Description                         |
| --------- | ---------- | ----------------------------------- |
| `html`    | Basic      | Raw HTML content                    |
| `text`    | Basic      | WYSIWYG rich text                   |
| `image`   | Media      | Single image with caption           |
| `gallery` | Media      | Image gallery with multiple layouts |
| `video`   | Media      | YouTube/Vimeo/uploaded videos       |
| `menu`    | Navigation | Display a navigation menu           |
| `views`   | Content    | Dynamic content queries             |

### Creating a Code-Defined Block Type

```php
<?php
namespace App\Cms\Blocks\Types;

class HeroBlock extends AbstractBlockType
{
    protected const ID = 'hero';
    protected const LABEL = 'Hero Block';
    protected const ICON = '🎯';
    protected const CATEGORY = 'Layout';

    public static function getFields(): array
    {
        return [
            'heading' => ['type' => 'string', 'label' => 'Heading', 'required' => true],
            'subheading' => ['type' => 'text', 'label' => 'Subheading'],
            'background' => ['type' => 'image', 'label' => 'Background Image'],
            'button_text' => ['type' => 'string', 'label' => 'Button Text'],
            'button_url' => ['type' => 'url', 'label' => 'Button URL'],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $heading = $this->getFieldValue($block, 'heading', '');
        $subheading = $this->getFieldValue($block, 'subheading', '');

        return <<<HTML
            <section class="hero">
                <h1>{$this->escape($heading)}</h1>
                <p>{$this->escape($subheading)}</p>
            </section>
        HTML;
    }
}
```

### Block Type API

```bash
# List all block types
curl http://localhost:8000/admin/block-types

# Get types grouped by category
curl http://localhost:8000/admin/block-types/grouped

# Get available field types
curl http://localhost:8000/admin/block-types/field-types

# Create a database block type
curl -X POST http://localhost:8000/admin/block-types \
  -H "Content-Type: application/json" \
  -d '{
    "label": "FAQ Block",
    "description": "Frequently asked questions",
    "icon": "❓",
    "category": "Content",
    "fields": [
      {"name": "Questions", "type": "json"},
      {"name": "Collapsed", "type": "boolean", "default": true}
    ]
  }'

# Add field to block type
curl -X POST http://localhost:8000/admin/block-types/faq/fields \
  -H "Content-Type: application/json" \
  -d '{"name": "Show Icons", "type": "boolean", "default": false}'
```

### Using Blocks in Templates

```php
// Render a single block
echo $blockService->render('homepage-hero');

// Render all blocks in a region
echo $blockService->renderRegion('sidebar');

// Render multiple regions
$regions = $blockService->renderRegions(['header', 'sidebar', 'footer']);
```

## Content Type System

MonkeysCMS supports dynamic content types that can be defined either in code (PHP entities) or in the database (via admin UI). Database-defined types can have custom fields added dynamically.

### Content Type Sources

1. **Code-Defined**: PHP Entity classes with `#[ContentType]` attribute
2. **Database-Defined**: Created via admin with dynamic schema generation

### Content Type API

```bash
# List all content types
curl http://localhost:8000/admin/content-types

# Get content type details
curl http://localhost:8000/admin/content-types/article

# Create a database content type
curl -X POST http://localhost:8000/admin/content-types \
  -H "Content-Type: application/json" \
  -d '{
    "label": "Product",
    "label_plural": "Products",
    "description": "E-commerce products",
    "icon": "🛒",
    "publishable": true,
    "revisionable": true,
    "fields": [
      {"name": "Price", "type": "decimal", "required": true},
      {"name": "SKU", "type": "string", "required": true},
      {"name": "Stock", "type": "integer", "default": 0},
      {"name": "Images", "type": "gallery"}
    ]
  }'

# Add field to content type
curl -X POST http://localhost:8000/admin/content-types/product/fields \
  -H "Content-Type: application/json" \
  -d '{"name": "Weight", "type": "decimal", "settings": {"suffix": "kg"}}'

# Content CRUD
curl http://localhost:8000/admin/content-types/product/content
curl -X POST http://localhost:8000/admin/content-types/product/content \
  -d '{"title": "Widget", "field_price": 29.99, "field_sku": "WDG-001"}'
```

### Available Field Types

| Category  | Types                                                |
| --------- | ---------------------------------------------------- |
| Text      | string, text, textarea, html, markdown               |
| Number    | integer, float, decimal                              |
| Date/Time | date, datetime, time                                 |
| Selection | boolean, select, radio, checkbox, multiselect        |
| Media     | image, file, gallery, video                          |
| Reference | entity_reference, taxonomy_reference, user_reference |
| Special   | email, url, phone, color, slug, json, code           |

## Taxonomy System

MonkeysCMS provides a flexible taxonomy system for organizing content with vocabularies and terms. Like block types and content types, taxonomies support both code-defined and database-defined vocabularies with custom fields.

### Vocabulary Features

- Hierarchical or flat structure
- Multiple or single selection
- Custom fields on terms
- Content type restrictions

### Taxonomy API

```bash
# List all vocabularies
curl http://localhost:8000/admin/taxonomies

# Create a vocabulary
curl -X POST http://localhost:8000/admin/taxonomies \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Product Categories",
    "hierarchical": true,
    "multiple": true,
    "fields": [
      {"name": "Image", "type": "image"},
      {"name": "Color", "type": "color"}
    ]
  }'

# List terms (as tree)
curl "http://localhost:8000/admin/taxonomies/categories/terms?format=tree"

# Create a term
curl -X POST http://localhost:8000/admin/taxonomies/categories/terms \
  -H "Content-Type: application/json" \
  -d '{"name": "Electronics", "parent_id": null}'

# Import terms
curl -X POST http://localhost:8000/admin/taxonomies/categories/terms/import \
  -H "Content-Type: application/json" \
  -d '{"terms": [
    {"name": "Computers"},
    {"name": "Phones"},
    {"name": "Tablets"}
  ]}'

# Export terms
curl "http://localhost:8000/admin/taxonomies/categories/terms/export?format=csv"
```

### Using Taxonomies

```php
// Get terms for a vocabulary
$categories = $taxonomyManager->getTerms('categories');

// Get hierarchical tree
$tree = $taxonomyManager->getTermTree('categories');

// Get terms for content
$tags = $taxonomyManager->getContentTerms($contentId, 'article');

// Assign terms to content
$taxonomyManager->setContentTerms($contentId, 'article', [1, 5, 12]);
```

## Structure Admin Section

MonkeysCMS provides a unified "Structure" admin section for managing all structural elements:

| Path                           | Description             |
| ------------------------------ | ----------------------- |
| /admin/structure/block-types   | Manage block types      |
| /admin/structure/content-types | Manage content types    |
| /admin/structure/taxonomies    | Manage vocabularies     |
| /admin/structure/menus         | Manage navigation menus |

## License

MIT License

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Credits

Built on the [MonkeysLegion Framework](https://github.com/MonkeysCloud/MonkeysLegion)
