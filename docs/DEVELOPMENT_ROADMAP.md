# MonkeysCMS Development Roadmap

**Version:** 1.0  
**Last Updated:** December 2024  
**Status:** Active Development

---

## Table of Contents

1. [Current State Summary](#current-state-summary)
2. [Phase 1: Core Entity System](#phase-1-core-entity-system-foundation)
3. [Phase 2: Authentication & Users](#phase-2-authentication--users)
4. [Phase 3: Content Management UI](#phase-3-content-management-ui)
5. [Phase 4: Frontend Rendering](#phase-4-frontend-rendering)
6. [Phase 5: Advanced Features](#phase-5-advanced-features)
7. [Phase 6: Internationalization](#phase-6-internationalization)
8. [Phase 7: Performance & DevOps](#phase-7-performance--devops)
9. [Phase 8: SEO & Marketing](#phase-8-seo--marketing)
10. [Phase 9: Installation & Admin Polish](#phase-9-installation--admin-polish)
11. [Implementation Priority Matrix](#implementation-priority-matrix)
12. [Recommended Next Steps](#recommended-next-steps)

---

## Current State Summary

### ✅ Completed Components

| Component | Description | Location |
|-----------|-------------|----------|
| **Field Widget System** | 35+ widgets, validation, rendering, storage, CLI | `app/Cms/Fields/` |
| **Content Types** | Entity definitions, manager (basic) | `app/Cms/ContentTypes/` |
| **Block System** | Block types, renderer, manager | `app/Cms/Blocks/` |
| **Theme System** | Theme info, manager, admin/default themes | `app/Cms/Themes/` |
| **Module System** | Loader, manager, enable/disable | `app/Cms/Modules/` |
| **Taxonomy** | Vocabulary, terms, manager | `app/Cms/Taxonomy/` |
| **Security** | Role, Permission, UserRole entities | `app/Cms/Security/` |
| **Cache System** | Service provider, CLI commands | `app/Cms/Cache/` |
| **Admin Controllers** | Scaffolding for all areas | `app/Controllers/Admin/` |
| **CLI Framework** | Module, cache, install commands | `app/Cli/` |

### Project Structure
```
monkeyscms/
├── app/
│   ├── Cms/                    # Core CMS functionality
│   │   ├── Fields/             # ✅ Field widget system
│   │   ├── Blocks/             # ✅ Block management
│   │   ├── Themes/             # ✅ Theme system
│   │   ├── ContentTypes/       # ✅ Content type manager
│   │   ├── Taxonomy/           # ✅ Taxonomy system
│   │   ├── Security/           # ✅ Permissions
│   │   ├── Cache/              # ✅ Caching
│   │   └── Modules/            # ✅ Module system
│   ├── Controllers/            # ✅ Admin controllers
│   ├── Modules/                # ✅ Core/Custom modules
│   └── Cli/                    # ✅ CLI commands
├── public/                     # ✅ Frontend assets
├── themes/                     # ✅ Theme files
├── config/                     # ✅ Configuration
└── storage/                    # ✅ Files, cache, logs
```

---

## Phase 1: Core Entity System (Foundation)

**Priority:** 🔴 Critical  
**Estimated Time:** 1-2 weeks  
**Dependencies:** None

### 1.1 Entity Manager & ORM

The Entity Manager is the foundation of the entire CMS. It provides generic CRUD operations for all entity types.

#### File Structure
```
app/Cms/Entity/
├── EntityManager.php          # Central CRUD operations
├── EntityInterface.php        # Contract for all entities
├── EntityRepository.php       # Base repository with query builder
├── EntityStorage.php          # Database abstraction
├── EntityQuery.php            # Fluent query builder
└── EntityEvent.php            # Pre/post save, delete hooks
```

#### EntityManager Features
- **Generic CRUD** for any entity type
- **Eager/lazy loading** of relationships
- **Query builder** with filters, sorting, pagination
- **Event hooks** (preSave, postSave, preDelete, postDelete)
- **Soft deletes** support
- **Entity caching** integration

#### Example Usage
```php
// Get entity manager
$em = $container->get(EntityManager::class);

// Create
$node = new Node(['title' => 'Hello World', 'type' => 'article']);
$em->save($node);

// Query
$articles = $em->query(Node::class)
    ->where('type', 'article')
    ->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Update
$node->title = 'Updated Title';
$em->save($node);

// Delete
$em->delete($node);
```

### 1.2 Content/Node System

The Node system is the core content storage, representing all content entities in the CMS.

#### File Structure
```
app/Cms/Content/
├── Node.php                   # Base content entity
├── NodeStorage.php            # Node-specific storage
├── NodeManager.php            # Content operations
├── NodeRevision.php           # Revision tracking
├── NodeType.php               # Content type definition
└── NodeAccess.php             # Content-level permissions
```

#### Node Features
- **Node entity** with fields from content type
- **Create, edit, delete, publish** workflow
- **Revision history** with diff viewing
- **Content type inheritance**
- **Field value storage** integration
- **Author tracking**
- **Timestamps** (created, updated, published)

#### Node Entity Structure
```php
class Node extends BaseEntity
{
    public ?int $id;
    public string $type;           // Content type machine name
    public string $title;
    public string $status;         // draft, published, archived
    public ?int $author_id;
    public ?string $slug;
    public ?DateTimeImmutable $created_at;
    public ?DateTimeImmutable $updated_at;
    public ?DateTimeImmutable $published_at;
    public int $revision_id;
    public array $fields = [];     // Field values
}
```

### 1.3 Database Migrations

Complete database schema for all CMS components.

#### File Structure
```
app/Cms/Database/
├── MigrationRunner.php        # Execute migrations
├── MigrationGenerator.php     # Generate from entities
├── Schema.php                 # Schema builder
└── migrations/
    ├── 001_users.php
    ├── 002_roles_permissions.php
    ├── 003_content_types.php
    ├── 004_nodes.php
    ├── 005_node_revisions.php
    ├── 006_fields.php
    ├── 007_field_values.php
    ├── 008_taxonomies.php
    ├── 009_media.php
    ├── 010_menus.php
    ├── 011_blocks.php
    └── 012_settings.php
```

#### Core Tables Schema

**users**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'blocked', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_status (status)
);
```

**nodes**
```sql
CREATE TABLE nodes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    author_id INT,
    revision_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_author (author_id),
    INDEX idx_published (published_at)
);
```

**node_revisions**
```sql
CREATE TABLE node_revisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    node_id INT NOT NULL,
    revision_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    data JSON,
    author_id INT,
    log_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (node_id) REFERENCES nodes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_revision (node_id, revision_id)
);
```

---

## Phase 2: Authentication & Users

**Priority:** 🔴 Critical  
**Estimated Time:** 1 week  
**Dependencies:** Entity Manager

### 2.1 Authentication System

#### File Structure
```
app/Cms/Auth/
├── AuthManager.php            # Login, logout, session
├── PasswordHasher.php         # Bcrypt/Argon2
├── SessionManager.php         # Session handling
├── RememberToken.php          # "Remember me" tokens
├── TwoFactorAuth.php          # 2FA support (TOTP)
├── LoginAttempt.php           # Brute force protection
└── OAuth/
    ├── OAuthManager.php       # OAuth orchestration
    ├── OAuthProvider.php      # Base provider
    ├── GoogleProvider.php     # Google login
    └── GithubProvider.php     # GitHub login
```

#### Authentication Features
- **Password hashing** with Argon2id (fallback to bcrypt)
- **Session management** with secure cookies
- **Remember me** functionality with rotating tokens
- **Two-factor authentication** using TOTP
- **Brute force protection** with exponential backoff
- **OAuth integration** for social login
- **Password policies** (min length, complexity)

#### Example Usage
```php
$auth = $container->get(AuthManager::class);

// Login
$result = $auth->attempt($email, $password, $remember);
if ($result->success) {
    redirect('/dashboard');
}

// Check authentication
if ($auth->check()) {
    $user = $auth->user();
}

// Logout
$auth->logout();

// 2FA
$auth->enableTwoFactor($user);
$auth->verifyTwoFactor($user, $code);
```

### 2.2 User Management

#### File Structure
```
app/Cms/User/
├── UserManager.php            # User CRUD
├── UserProfile.php            # Profile fields
├── PasswordReset.php          # Reset flow
├── EmailVerification.php      # Email confirmation
├── UserSession.php            # Active sessions
└── UserPreferences.php        # User settings
```

#### User Management Features
- **User CRUD** operations
- **Profile management** with custom fields
- **Password reset** via email
- **Email verification** on registration
- **Session management** (view/revoke active sessions)
- **User preferences** (language, timezone, notifications)

### 2.3 Auth Controllers

#### File Structure
```
app/Controllers/Auth/
├── LoginController.php        # Login form & processing
├── LogoutController.php       # Logout handling
├── RegisterController.php     # Registration
├── PasswordResetController.php # Forgot password
├── TwoFactorController.php    # 2FA setup & verify
└── ProfileController.php      # User profile
```

#### Routes
```
GET  /login              → LoginController@show
POST /login              → LoginController@login
POST /logout             → LogoutController@logout
GET  /register           → RegisterController@show
POST /register           → RegisterController@register
GET  /password/forgot    → PasswordResetController@showForgot
POST /password/forgot    → PasswordResetController@sendReset
GET  /password/reset     → PasswordResetController@showReset
POST /password/reset     → PasswordResetController@reset
GET  /profile            → ProfileController@show
PUT  /profile            → ProfileController@update
```

---

## Phase 3: Content Management UI

**Priority:** 🟠 High  
**Estimated Time:** 2 weeks  
**Dependencies:** Content System, Authentication

### 3.1 Admin Content Interface

#### File Structure
```
app/Views/admin/content/
├── index.ml.php               # Content listing with filters
├── create.ml.php              # Create content form
├── edit.ml.php                # Edit content form
├── revisions.ml.php           # Revision history
├── revision-diff.ml.php       # Compare revisions
├── preview.ml.php             # Content preview
└── delete.ml.php              # Delete confirmation
```

### 3.2 Content List Features

#### Listing Capabilities
- **DataTables** with server-side processing
- **Filters:**
  - Content type
  - Status (draft, published, archived)
  - Author
  - Date range (created, updated, published)
  - Custom field filters
- **Bulk actions:**
  - Publish selected
  - Unpublish selected
  - Delete selected
  - Change author
- **Quick edit** inline (title, status)
- **Search** across all searchable fields
- **Column sorting** (title, author, date, status)
- **Pagination** with configurable page size

#### List View Mockup
```
┌─────────────────────────────────────────────────────────────────────────┐
│ Content                                              [+ Add Content ▼]  │
├─────────────────────────────────────────────────────────────────────────┤
│ Type: [All Types ▼]  Status: [All ▼]  Author: [All ▼]  [Search...] [🔍] │
├─────────────────────────────────────────────────────────────────────────┤
│ □ │ Title              │ Type    │ Author  │ Status    │ Updated      │ │
├───┼────────────────────┼─────────┼─────────┼───────────┼──────────────┤ │
│ □ │ Welcome Post       │ Article │ Admin   │ Published │ Dec 15, 2024 │ │
│ □ │ About Us           │ Page    │ Admin   │ Published │ Dec 14, 2024 │ │
│ □ │ Draft Article      │ Article │ Editor  │ Draft     │ Dec 13, 2024 │ │
├─────────────────────────────────────────────────────────────────────────┤
│ With selected: [Publish ▼] [Apply]          Showing 1-10 of 50  [< 1 >] │
└─────────────────────────────────────────────────────────────────────────┘
```

### 3.3 Content Form Features

#### Form Capabilities
- **Dynamic field rendering** based on content type
- **Autosave drafts** every 60 seconds
- **Preview before publish** in new tab
- **Revision comparison** side-by-side diff
- **Publishing options:**
  - Save as draft
  - Publish immediately
  - Schedule for future date
- **SEO meta fields:**
  - Meta title
  - Meta description
  - OG image
  - Canonical URL
- **URL alias management** with auto-generation
- **Validation** with inline errors

#### Form Layout
```
┌─────────────────────────────────────────────────────────────────────────┐
│ Edit Article: Welcome Post                              [Preview] [Save]│
├─────────────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────┐ ┌─────────────────────────────────┐ │
│ │ Main Content                    │ │ Publishing                      │ │
│ │                                 │ │                                 │ │
│ │ Title *                         │ │ Status: [Published ▼]           │ │
│ │ ┌─────────────────────────────┐ │ │                                 │ │
│ │ │ Welcome Post                │ │ │ Published: Dec 15, 2024         │ │
│ │ └─────────────────────────────┘ │ │                                 │ │
│ │                                 │ │ Author: [Admin ▼]               │ │
│ │ Body *                          │ │                                 │ │
│ │ ┌─────────────────────────────┐ │ │ [Schedule Publishing]           │ │
│ │ │ [WYSIWYG Editor]            │ │ └─────────────────────────────────┘ │
│ │ │                             │ │                                   │
│ │ │                             │ │ ┌─────────────────────────────────┐ │
│ │ └─────────────────────────────┘ │ │ URL Alias                       │ │
│ │                                 │ │                                 │ │
│ │ Featured Image                  │ │ /welcome-post                   │ │
│ │ ┌─────────────────────────────┐ │ │ □ Generate automatically        │ │
│ │ │ [📷 Select Image]           │ │ └─────────────────────────────────┘ │
│ │ └─────────────────────────────┘ │                                   │
│ │                                 │ ┌─────────────────────────────────┐ │
│ │ Tags                            │ │ SEO                             │ │
│ │ ┌─────────────────────────────┐ │ │                                 │ │
│ │ │ [news] [welcome] [+]        │ │ │ Meta Title                      │ │
│ │ └─────────────────────────────┘ │ │ [                             ] │ │
│ └─────────────────────────────────┘ │                                 │ │
│                                     │ Meta Description                │ │
│                                     │ [                             ] │ │
│                                     └─────────────────────────────────┘ │
├─────────────────────────────────────────────────────────────────────────┤
│ Last saved: 2 minutes ago (autosaved)     [Delete] [Save Draft] [Save] │
└─────────────────────────────────────────────────────────────────────────┘
```

### 3.4 Media Library UI

#### File Structure
```
app/Views/admin/media/
├── index.ml.php               # Grid/list view
├── upload.ml.php              # Upload interface
├── edit.ml.php                # Edit metadata
├── folder.ml.php              # Folder management
└── browser.ml.php             # Modal for field integration
```

#### Media Library Features
- **Drag-drop upload** with progress
- **Image editing:**
  - Crop
  - Rotate
  - Resize
  - Focal point
- **Folder organization** with drag-drop
- **Search and filters:**
  - File type
  - Upload date
  - Size
  - Folder
- **Usage tracking** (where media is used)
- **Bulk operations:**
  - Delete
  - Move to folder
  - Download
- **View modes:** Grid / List

---

## Phase 4: Frontend Rendering

**Priority:** 🟠 High  
**Estimated Time:** 1-2 weeks  
**Dependencies:** Content System, Routing

### 4.1 Routing System

#### File Structure
```
app/Cms/Routing/
├── Router.php                 # Route matching
├── Route.php                  # Route definition
├── RouteCollection.php        # Route storage
├── UrlGenerator.php           # Generate URLs
├── PathAlias.php              # URL aliases
├── PathAliasManager.php       # Alias CRUD
└── RouteProvider.php          # Dynamic routes from content
```

#### Route Types

| Type | Example | Description |
|------|---------|-------------|
| Static | `/about` | Defined in code |
| Content | `/node/{id}` | Content by ID |
| Alias | `/welcome-post` | URL alias to content |
| Taxonomy | `/category/{term}` | Term listings |
| User | `/user/{id}` | User profiles |

#### URL Alias System
```php
// Automatic alias generation
$alias = $pathAlias->generate($node);
// Result: /blog/welcome-post

// Custom alias
$pathAlias->create('/my-custom-url', '/node/123');

// Resolve alias
$path = $pathAlias->resolve('/welcome-post');
// Result: /node/123
```

### 4.2 View/Display System

#### File Structure
```
app/Cms/Display/
├── DisplayManager.php         # View mode management
├── DisplayMode.php            # Full, teaser, card, etc.
├── FieldDisplay.php           # Field display config
├── FieldFormatter.php         # Field display formatting
├── ViewBuilder.php            # Build render arrays
└── DisplaySettings.php        # Per-field display config
```

#### Display Modes

| Mode | Description | Usage |
|------|-------------|-------|
| `full` | Complete content | Single content page |
| `teaser` | Summary with "read more" | Listing pages |
| `card` | Compact card format | Grid layouts |
| `search` | Search result format | Search results |
| `rss` | RSS feed format | Feed generation |

#### Field Formatters

| Field Type | Formatters |
|------------|------------|
| `text` | plain, trimmed, summary |
| `image` | thumbnail, medium, large, original |
| `date` | short, medium, long, relative |
| `taxonomy` | label, link, list |
| `entity_ref` | label, teaser, rendered |

### 4.3 Template Engine Integration

#### File Structure
```
app/Cms/Template/
├── TemplateEngine.php         # ML template wrapper
├── TemplateLoader.php         # Find templates
├── TemplateSuggestions.php    # Template suggestions
├── TemplateContext.php        # Variables for templates
└── TemplateHelper.php         # Template functions
```

#### Template Hierarchy (Node)
```
1. node--{type}--{view-mode}--{id}.ml.php
2. node--{type}--{view-mode}.ml.php
3. node--{type}--{id}.ml.php
4. node--{type}.ml.php
5. node--{view-mode}.ml.php
6. node.ml.php
```

#### Example
For an Article node (ID: 123) in full view mode:
```
1. node--article--full--123.ml.php
2. node--article--full.ml.php
3. node--article--123.ml.php
4. node--article.ml.php
5. node--full.ml.php
6. node.ml.php
```

### 4.4 Frontend Controllers

#### File Structure
```
app/Controllers/Frontend/
├── NodeController.php         # Content display
├── TaxonomyController.php     # Term listings
├── SearchController.php       # Search results
├── UserController.php         # User profiles
├── SitemapController.php      # XML sitemap
└── RssController.php          # RSS feeds
```

#### Frontend Routes
```
GET /                          → Home page
GET /node/{id}                 → Content by ID
GET /{alias}                   → Content by alias
GET /taxonomy/{vocab}/{term}   → Term listing
GET /search                    → Search results
GET /user/{id}                 → User profile
GET /sitemap.xml               → XML sitemap
GET /rss/{type}                → RSS feed
```

---

## Phase 5: Advanced Features

**Priority:** 🟡 Medium  
**Estimated Time:** 2-3 weeks  
**Dependencies:** Core Systems

### 5.1 Search System

#### File Structure
```
app/Cms/Search/
├── SearchManager.php          # Search orchestration
├── SearchIndex.php            # Index management
├── SearchQuery.php            # Query builder
├── SearchResult.php           # Result object
├── Indexer/
│   ├── IndexerInterface.php   # Indexer contract
│   ├── NodeIndexer.php        # Content indexer
│   ├── MediaIndexer.php       # Media indexer
│   └── TaxonomyIndexer.php    # Term indexer
└── Driver/
    ├── SearchDriverInterface.php
    ├── DatabaseDriver.php     # MySQL fulltext
    ├── MeilisearchDriver.php  # Meilisearch
    └── ElasticDriver.php      # Elasticsearch
```

#### Search Features
- **Full-text search** across content
- **Faceted search** with filters
- **Search suggestions** (autocomplete)
- **Highlighting** of matched terms
- **Relevance scoring**
- **Search analytics** (popular searches)

#### Example Usage
```php
$search = $container->get(SearchManager::class);

$results = $search->query('welcome')
    ->type('article')
    ->filter('status', 'published')
    ->filter('category', 'news')
    ->sort('relevance')
    ->limit(20)
    ->get();

foreach ($results as $result) {
    echo $result->title;
    echo $result->excerpt;  // Highlighted
    echo $result->score;
}
```

### 5.2 API Layer (Headless)

#### File Structure
```
app/Cms/Api/
├── ApiRouter.php              # /api/v1/* routes
├── ApiController.php          # Base controller
├── JsonResponse.php           # Standardized responses
├── ApiAuth.php                # API key / JWT auth
├── RateLimiter.php            # Rate limiting
├── ApiDocGenerator.php        # OpenAPI docs
└── Resources/
    ├── ResourceInterface.php  # Resource contract
    ├── NodeResource.php       # Content transformer
    ├── UserResource.php       # User transformer
    ├── MediaResource.php      # Media transformer
    └── TaxonomyResource.php   # Term transformer
```

#### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/content` | List content |
| GET | `/api/v1/content/{id}` | Get content |
| POST | `/api/v1/content` | Create content |
| PUT | `/api/v1/content/{id}` | Update content |
| DELETE | `/api/v1/content/{id}` | Delete content |
| GET | `/api/v1/content/type/{type}` | List by type |
| GET | `/api/v1/taxonomy/{vocab}` | List terms |
| GET | `/api/v1/taxonomy/{vocab}/{term}` | Get term |
| GET | `/api/v1/media` | List media |
| POST | `/api/v1/media/upload` | Upload media |
| GET | `/api/v1/menu/{name}` | Get menu |
| GET | `/api/v1/block/{region}` | Get blocks |

#### Response Format
```json
{
    "data": {
        "id": 1,
        "type": "article",
        "attributes": {
            "title": "Welcome Post",
            "body": "...",
            "created_at": "2024-12-15T10:30:00Z"
        },
        "relationships": {
            "author": {"id": 1, "name": "Admin"},
            "category": [{"id": 5, "name": "News"}]
        }
    },
    "meta": {
        "generated_at": "2024-12-15T10:35:00Z"
    }
}
```

### 5.3 Menu System

#### File Structure
```
app/Cms/Menu/
├── MenuManager.php            # Menu CRUD
├── Menu.php                   # Menu entity
├── MenuItem.php               # Menu item entity
├── MenuBuilder.php            # Build menu tree
├── MenuRenderer.php           # Render HTML
├── MenuLink.php               # Link types
└── MenuCache.php              # Menu caching
```

#### Menu Link Types
- **Content** - Link to node
- **Taxonomy** - Link to term
- **External** - External URL
- **Route** - Named route
- **Custom** - Custom path

### 5.4 Workflow & Publishing

#### File Structure
```
app/Cms/Workflow/
├── WorkflowManager.php        # State machine
├── Workflow.php               # Workflow definition
├── WorkflowState.php          # State entity
├── WorkflowTransition.php     # Transition entity
├── ScheduledPublish.php       # Future publishing
└── ContentModerator.php       # Approval workflow
```

#### Default Workflow States

```
┌─────────┐    ┌──────────┐    ┌───────────┐    ┌──────────┐
│  Draft  │───►│ In Review│───►│ Published │───►│ Archived │
└─────────┘    └──────────┘    └───────────┘    └──────────┘
     │              │                │
     └──────────────┴────────────────┘
              (reject back to draft)
```

#### Scheduled Publishing
```php
$scheduler = $container->get(ScheduledPublish::class);

// Schedule for future
$scheduler->schedule($node, new DateTime('2024-12-25 09:00:00'));

// Cron job processes scheduled content
$scheduler->processScheduled();
```

---

## Phase 6: Internationalization

**Priority:** 🟡 Medium  
**Estimated Time:** 1 week  
**Dependencies:** Content System

### 6.1 Translation System

#### File Structure
```
app/Cms/I18n/
├── TranslationManager.php     # Translation handling
├── LanguageManager.php        # Language config
├── ContentTranslation.php     # Content translation
├── InterfaceTranslation.php   # UI translation
├── LocaleNegotiator.php       # Detect user language
└── TranslationStorage.php     # Store translations
```

### 6.2 Multi-language Features

| Feature | Description |
|---------|-------------|
| **Translatable fields** | Mark fields as translatable per content type |
| **Language switcher** | UI component for language selection |
| **Fallback language** | Show fallback when translation missing |
| **RTL support** | Right-to-left language support |
| **URL strategies** | Prefix (`/en/about`), Domain, Query param |
| **Interface translation** | Translate admin UI strings |
| **Translation status** | Track translation completeness |

#### URL Strategies

| Strategy | Example |
|----------|---------|
| Prefix | `/en/about`, `/es/acerca` |
| Domain | `en.site.com`, `es.site.com` |
| Query | `/about?lang=en` |

---

## Phase 7: Performance & DevOps

**Priority:** 🟡 Medium  
**Estimated Time:** 1 week  
**Dependencies:** Core Systems

### 7.1 Caching Strategy

#### File Structure
```
app/Cms/Cache/
├── CacheManager.php           # Cache orchestration
├── PageCache.php              # Full page cache
├── RenderCache.php            # Block/view cache
├── EntityCache.php            # Entity cache
├── QueryCache.php             # Query result cache
├── CacheTag.php               # Cache tagging
└── CacheInvalidator.php       # Smart invalidation
```

#### Cache Layers

| Layer | TTL | Description |
|-------|-----|-------------|
| **Page Cache** | 1 hour | Full HTML pages |
| **Render Cache** | 30 min | Blocks, views |
| **Entity Cache** | 1 hour | Individual entities |
| **Query Cache** | 15 min | Query results |
| **Config Cache** | Forever | Configuration |

#### Cache Tags
```php
// Tag-based invalidation
$cache->tags(['node', 'node:123'])->set('key', $value);

// Invalidate all content
$cache->invalidateTags(['node']);

// Invalidate specific node
$cache->invalidateTags(['node:123']);
```

### 7.2 Queue System

#### File Structure
```
app/Cms/Queue/
├── QueueManager.php           # Queue operations
├── Job.php                    # Base job class
├── Worker.php                 # Job processor
├── FailedJob.php              # Failed job handling
├── Driver/
│   ├── QueueDriverInterface.php
│   ├── DatabaseDriver.php     # MySQL queue
│   ├── RedisDriver.php        # Redis queue
│   └── SyncDriver.php         # Synchronous
└── Jobs/
    ├── SendEmailJob.php
    ├── ProcessImageJob.php
    ├── IndexContentJob.php
    └── GenerateSitemapJob.php
```

#### Example Usage
```php
$queue = $container->get(QueueManager::class);

// Dispatch job
$queue->dispatch(new SendEmailJob($user, 'welcome'));

// Delayed job
$queue->dispatch(new ProcessImageJob($media))
    ->delay(60);  // 60 seconds

// Run worker (CLI)
$worker->run('default', ['timeout' => 60]);
```

### 7.3 Image Processing

#### File Structure
```
app/Cms/Image/
├── ImageProcessor.php         # Process images
├── ImageStyle.php             # Style definitions
├── ImageEffect.php            # Effects (resize, crop)
├── ImageDerivative.php        # Generated images
└── Effects/
    ├── ResizeEffect.php
    ├── CropEffect.php
    ├── ScaleEffect.php
    ├── RotateEffect.php
    └── WatermarkEffect.php
```

#### Built-in Image Styles

| Style | Dimensions | Effects |
|-------|------------|---------|
| `thumbnail` | 100x100 | Scale & crop |
| `small` | 200x200 | Scale |
| `medium` | 400x400 | Scale |
| `large` | 800x800 | Scale |
| `hero` | 1920x600 | Scale & crop |

---

## Phase 8: SEO & Marketing

**Priority:** 🟢 Low  
**Estimated Time:** 1 week  
**Dependencies:** Content System, Routing

### 8.1 SEO Tools

#### File Structure
```
app/Cms/Seo/
├── SeoManager.php             # SEO orchestration
├── MetaTagManager.php         # Meta tags
├── SitemapGenerator.php       # XML sitemap
├── RobotsManager.php          # robots.txt
├── SchemaOrg.php              # Structured data
├── OpenGraph.php              # Social sharing
├── TwitterCard.php            # Twitter cards
└── Canonical.php              # Canonical URLs
```

### 8.2 SEO Features

| Feature | Description |
|---------|-------------|
| **Meta tags** | Title, description, keywords |
| **Open Graph** | Facebook/LinkedIn sharing |
| **Twitter Cards** | Twitter sharing |
| **Schema.org** | Structured data (JSON-LD) |
| **XML Sitemap** | Auto-generated sitemap |
| **robots.txt** | Configurable robots.txt |
| **Canonical URLs** | Prevent duplicate content |
| **Redirects** | 301/302 redirect management |

### 8.3 Analytics Integration

- **Google Analytics 4** integration
- **Built-in page views** tracking
- **Popular content** reports
- **Search queries** analytics
- **404 error** tracking

---

## Phase 9: Installation & Admin Polish

**Priority:** 🟢 Low  
**Estimated Time:** 1 week  
**Dependencies:** All previous phases

### 9.1 Installation Wizard

#### File Structure
```
app/Cms/Install/
├── InstallWizard.php          # Step-by-step install
├── Requirements.php           # Check PHP, extensions
├── DatabaseSetup.php          # Create tables
├── AdminSetup.php             # Create admin user
├── SiteSetup.php              # Site configuration
└── SampleContent.php          # Demo content
```

#### Installation Steps

1. **Welcome** - Introduction
2. **Requirements** - Check PHP version, extensions
3. **Database** - Configure connection, create tables
4. **Admin User** - Create administrator account
5. **Site Info** - Site name, email, timezone
6. **Sample Content** - Optional demo content
7. **Complete** - Installation summary

### 9.2 Admin Dashboard

#### Dashboard Widgets

| Widget | Description |
|--------|-------------|
| **System Status** | PHP, database, cache status |
| **Recent Content** | Latest created/updated |
| **Content Stats** | Published, drafts, by type |
| **User Activity** | Recent logins |
| **Quick Actions** | Create content, clear cache |
| **Pending Moderation** | Content awaiting review |
| **Popular Content** | Most viewed |
| **Storage Usage** | Disk space, media count |

### 9.3 System Health

#### Health Checks

| Check | Description |
|-------|-------------|
| **PHP Info** | Version, extensions, limits |
| **Database** | Connection, table status |
| **Cache** | Driver, hit rate |
| **Queue** | Pending jobs, failed jobs |
| **Cron** | Last run, scheduled tasks |
| **Storage** | Disk space, permissions |
| **Error Logs** | Recent errors |

---

## Implementation Priority Matrix

| Phase | Component | Priority | Effort | Dependencies | Status |
|-------|-----------|----------|--------|--------------|--------|
| 1.1 | Entity Manager | 🔴 Critical | High | None | ⬜ Pending |
| 1.2 | Content/Node System | 🔴 Critical | High | Entity Manager | ⬜ Pending |
| 1.3 | Database Migrations | 🔴 Critical | Medium | None | ⬜ Pending |
| 2.1 | Authentication | 🔴 Critical | Medium | Users | ⬜ Pending |
| 2.2 | User Management | 🔴 Critical | Medium | Entity Manager | ⬜ Pending |
| 3.1 | Content List UI | 🟠 High | Medium | Content System | ⬜ Pending |
| 3.2 | Content Form UI | 🟠 High | High | Content System | ⬜ Pending |
| 3.3 | Media Library UI | 🟠 High | Medium | Entity Manager | ⬜ Pending |
| 4.1 | Routing System | 🟠 High | Medium | None | ⬜ Pending |
| 4.2 | Display System | 🟠 High | Medium | Routing | ⬜ Pending |
| 4.3 | Template Engine | 🟠 High | Low | None | ⬜ Pending |
| 5.1 | Search System | 🟡 Medium | Medium | Content System | ⬜ Pending |
| 5.2 | API Layer | 🟡 Medium | Medium | Entity Manager | ⬜ Pending |
| 5.3 | Menu System | 🟡 Medium | Low | Entity Manager | ⬜ Pending |
| 5.4 | Workflow | 🟡 Medium | Medium | Content System | ⬜ Pending |
| 6.1 | Translations | 🟡 Medium | High | Content System | ⬜ Pending |
| 7.1 | Advanced Caching | 🟡 Medium | Medium | None | ⬜ Pending |
| 7.2 | Queue System | 🟡 Medium | Medium | None | ⬜ Pending |
| 7.3 | Image Processing | 🟡 Medium | Medium | Media | ⬜ Pending |
| 8.1 | SEO Tools | 🟢 Low | Low | Content System | ⬜ Pending |
| 8.2 | Analytics | 🟢 Low | Low | None | ⬜ Pending |
| 9.1 | Install Wizard | 🟢 Low | Medium | All | ⬜ Pending |
| 9.2 | Admin Dashboard | 🟢 Low | Medium | All | ⬜ Pending |

---

## Recommended Next Steps

### Immediate (This Week)

1. **Entity Manager**
   - Build the core ORM that everything depends on
   - Implement EntityInterface, EntityManager, EntityQuery
   - Create base repository with CRUD operations

2. **Database Migrations**
   - Create MigrationRunner
   - Write all core schema migrations
   - Run migrations to set up database

3. **Node System**
   - Implement Node entity
   - Create NodeManager with CRUD
   - Integrate with Field Value Storage

### Short Term (Next 2 Weeks)

4. **Authentication**
   - AuthManager with login/logout
   - Session management
   - Password hashing

5. **User Management**
   - UserManager CRUD
   - Profile management
   - Password reset flow

6. **Content Admin UI**
   - Content listing page
   - Create/edit forms
   - Integration with field widgets

### Medium Term (Month 1-2)

7. **Frontend Routing**
   - Router implementation
   - Path alias system
   - Dynamic content routes

8. **Display System**
   - View modes
   - Field formatters
   - Template suggestions

9. **Media Library**
   - Upload interface
   - Image cropping
   - Folder management

10. **Search**
    - Full-text search
    - Search indexing
    - Search UI

### Long Term (Month 2-3)

11. **API Layer** - RESTful API for headless
12. **Workflow** - Publishing workflow
13. **I18n** - Multi-language support
14. **SEO** - Meta tags, sitemap
15. **Installation Wizard** - Setup experience

---

## Technical Guidelines

### Coding Standards
- **PSR-12** coding style
- **PHP 8.2+** features (typed properties, enums, attributes)
- **Strict types** in all files
- **DocBlocks** for all public methods

### Architecture Principles
- **Dependency Injection** via container
- **Interface-driven** design
- **Event-driven** for extensibility
- **Repository pattern** for data access
- **Service layer** for business logic

### Testing Requirements
- **Unit tests** for services
- **Integration tests** for repositories
- **Feature tests** for controllers
- **80%+ code coverage** target

---

## Appendix

### Database Schema Overview

```
users
├── roles (many-to-many via user_roles)
└── nodes (one-to-many, author)

nodes
├── node_type (many-to-one)
├── node_revisions (one-to-many)
├── field_values (one-to-many)
├── terms (many-to-many via node_terms)
└── media (many-to-many via node_media)

content_types
├── field_definitions (many-to-many via content_type_fields)
└── nodes (one-to-many)

taxonomies
├── vocabularies (one-to-many)
└── terms (one-to-many)

media
├── folder (many-to-one)
└── derivatives (one-to-many)

menus
└── menu_items (one-to-many, hierarchical)

blocks
├── block_type (many-to-one)
└── regions (placement)
```

### File Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Entity | `{Name}.php` | `Node.php` |
| Manager | `{Name}Manager.php` | `NodeManager.php` |
| Repository | `{Name}Repository.php` | `NodeRepository.php` |
| Controller | `{Name}Controller.php` | `NodeController.php` |
| Interface | `{Name}Interface.php` | `EntityInterface.php` |
| Migration | `{number}_{name}.php` | `001_users.php` |
| View | `{name}.ml.php` | `index.ml.php` |

---

*This document is a living roadmap and will be updated as development progresses.*
