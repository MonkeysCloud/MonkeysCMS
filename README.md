# MonkeysCMS v1.0

[![PHP Version](https://img.shields.io/badge/php-8.4%2B-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Framework](https://img.shields.io/badge/framework-MonkeysLegion%20v2-6366f1.svg)](https://github.com/MonkeysCloud/monkeyslegion)

**A modern, headless CMS built on PHP 8.4 and the MonkeysLegion framework.**

MonkeysCMS is a content management system with a theme engine, visual page builder (Mosaic), decoupled JSON:API, and MLC-driven database migrations — designed for both traditional server-rendered sites and headless/decoupled architectures.

---

## ✨ Key Features

| Feature | Description |
|---------|-------------|
| **Content Management** | Nodes, content types, dynamic fields, revisions, taxonomy, menus |
| **Mosaic Page Builder** | Visual block-based editor with drag-drop layout composition |
| **Theme Engine** | Tiered themes (core/contrib/custom), full inheritance chain, global libraries |
| **JSON:API** | Decoupled JSON:API 1.1 endpoint with filtering, pagination, relationships |
| **MLC Migrations** | Declarative schema in `.mlc` config files — no raw SQL |
| **Admin UI** | Full admin interface with dashboard, content CRUD, media, settings, appearance |
| **Installer Wizard** | 6-step web-based installer for zero-config bootstrap |

---

## 🚀 Quick Start

```bash
git clone git@github.com:MonkeysCloud/MonkeysCMS.git
cd MonkeysCMS

composer install

cp .env.example .env
php ml key:generate

composer serve
# → http://127.0.0.1:8000/install
```

---

## 📁 Project Structure

```
MonkeysCMS/
├── app/
│   ├── Cms/                       # CMS core (App\Cms namespace)
│   │   ├── Block/                 # Mosaic block types & registry
│   │   ├── Content/               # Nodes, content types, fields, revisions
│   │   ├── Controller/            # Admin, Frontend, API, Installer controllers
│   │   ├── Database/              # MigrationManager, SchemaBuilder, MigrationConfig
│   │   ├── JsonApi/               # JSON:API 1.1 formatter & response builder
│   │   ├── Media/                 # Media entity, repository, service
│   │   ├── Menu/                  # Menu entity, repository, builder
│   │   ├── Middleware/             # CORS, admin access, API auth
│   │   ├── Mosaic/                # Page builder renderer & services
│   │   ├── Provider/              # DI service providers
│   │   ├── Settings/              # Site settings entity & repository
│   │   ├── Taxonomy/              # Vocabularies, terms, tagging
│   │   ├── Theme/                 # ThemeManager, ThemeInfo, ThemeLibrary
│   │   └── User/                  # CMS user entity & repository
│   ├── Controller/                # Framework-level controllers
│   ├── Dto/                       # Request DTOs with validation
│   ├── Entity/                    # Framework entities
│   └── Service/                   # Business logic
├── config/
│   ├── app.mlc                    # Application settings
│   ├── database.mlc               # Database connection
│   ├── libraries.mlc              # Global CSS/JS libraries
│   ├── middleware.mlc             # Middleware pipeline
│   └── *.mlc                      # Other config (auth, cache, cors, etc.)
├── database/
│   └── migrations.mlc             # Migration registry
├── resources/
│   ├── css/base/                  # Global base CSS (variables, reset, grid, typography)
│   ├── css/components/            # Shared component CSS (forms, buttons, cards, etc.)
│   ├── migrations/                # MLC schema definitions
│   └── views/                     # Fallback templates
├── themes/
│   ├── core/                      # Built-in themes (shipped with CMS)
│   │   ├── admin/                 # Admin base theme
│   │   └── front/                 # Frontend base theme
│   ├── contrib/                   # Community/third-party themes
│   │   ├── admin_starter/         # Example admin child theme
│   │   └── starter/               # Example frontend child theme
│   └── custom/                    # User-created themes
├── public/
│   └── index.php                  # Entry point
└── tests/                         # PHPUnit test suites
```

---

## 🎨 Theme System

MonkeysCMS uses a tiered, inheritable theme system inspired by modern CMS architecture. Themes are auto-discovered from three directories:

```
themes/
├── core/       ← Base themes shipped with CMS (lowest priority)
├── contrib/    ← Community/installable themes
└── custom/     ← User-created themes (highest priority)
```

### How Themes Work

Each theme contains a `theme.mlc` configuration:

```mlc
theme {
    name        = "my_theme"
    label       = "My Custom Theme"
    version     = "1.0.0"
    type        = "frontend"         # "frontend" or "admin"
    base_theme  = "front"            # Inherit from parent theme

    # Global libraries to attach (from config/libraries.mlc)
    libraries = ["core/modals", "core/alerts"]

    # Theme-specific assets (loaded after parent's)
    assets {
        css = ["css/my_theme.css"]
        js  = ["js/my_theme.js"]
    }
}
```

### Theme Inheritance

Child themes inherit **everything** from their parent:

- **Views & components** — resolved through full chain (child → parent → grandparent → base)
- **CSS/JS assets** — parent loaded first, child loaded last (can override via CSS variables)
- **Regions** — inherited if not redefined
- **Libraries** — accumulated from the entire chain

```
my_theme → front (base)
         ↓ inherits views, components, regions, libraries
         ↓ only overrides what it changes
```

### Global Libraries

Shared CSS/JS defined in `config/libraries.mlc` — themes attach them without reproducing:

```mlc
library "core/base" {
    css = ["resources/css/base/variables.css", "resources/css/base/reset.css", ...]
    weight = -100
    required = true   # Always loaded
}

library "core/forms" {
    css = ["resources/css/components/forms.css"]
    weight = -50      # Loaded when a theme requests it
}
```

**14 built-in libraries**: core/base, core/monkeysjs, core/forms, core/buttons, core/cards, core/tables, core/alerts, core/modals, core/navigation, core/media, admin/toolbar, admin/editor, front/responsive, front/animations.

### Installing a Theme

1. Place theme folder in `themes/contrib/` or `themes/custom/`
2. Add a `theme.mlc` config file
3. Set `base_theme` to inherit from an existing theme
4. Activate it in **Admin → Appearance**

### Admin Theme Selection

Users choose both frontend and admin themes independently via **Admin → Appearance**. Admin child themes work identically — set `type = "admin"` and `base_theme = "admin"`.

---

## 🧱 Mosaic Page Builder

Mosaic is a visual, block-based page builder. Content editors compose pages using draggable blocks:

| Block Type | Description |
|------------|-------------|
| `hero` | Full-width hero section with background, title, CTA |
| `richtext` | WYSIWYG rich text content |
| `image` | Single image with caption and alt text |
| `gallery` | Image gallery with grid/carousel layout |
| `cta` | Call-to-action with heading, text, buttons |
| `columns` | Multi-column layout (2/3/4 columns) |
| `video` | Embedded video (YouTube, Vimeo, self-hosted) |
| `accordion` | Collapsible FAQ/content sections |
| `form` | Embedded form builder |

Blocks are stored as JSON and rendered through the theme's component system.

---

## 🗄️ Database Migrations

MonkeysCMS uses **MLC-based declarative schema** instead of raw SQL:

```mlc
# resources/migrations/core_schema.mlc
schema {
    table "cms_roles" {
        id   { type = "unsignedBigInt", autoIncrement = true, primary = true }
        machine_name { type = "string", length = 64, notNull = true }
        label { type = "string", length = 128, notNull = true }
        permissions { type = "json", notNull = true, default = "[]" }
    }

    seed "cms_roles" {
        row { machine_name = "admin", label = "Administrator", permissions = "[\"*\"]" }
        row { machine_name = "editor", label = "Editor" }
        row { machine_name = "viewer", label = "Viewer" }
    }
}
```

**Migration registry** (`database/migrations.mlc`) manages ordering and dependencies:

```mlc
migration "core_schema" {
    file     = "resources/migrations/core_schema.mlc"
    module   = "core"
    version  = "1.0.0"
}
```

**16 core tables**: roles, users, user_roles, content_types, field_definitions, nodes, node_fields, node_revisions, media, taxonomy_vocabularies, taxonomy_terms, node_terms, menus, menu_items, blocks, settings.

---

## 🔌 JSON:API

Decoupled JSON:API 1.1 endpoint for headless architectures:

```
GET  /api/v1/nodes?filter[content_type]=article&page[limit]=10
GET  /api/v1/nodes/{id}
POST /api/v1/nodes
PUT  /api/v1/nodes/{id}
DELETE /api/v1/nodes/{id}
```

Response format:

```json
{
  "jsonapi": { "version": "1.1" },
  "data": [{
    "type": "nodes",
    "id": "1",
    "attributes": {
      "title": "Hello World",
      "slug": "hello-world",
      "status": "published"
    },
    "relationships": {
      "content_type": { "data": { "type": "content_types", "id": "article" } }
    }
  }],
  "meta": { "total": 42 },
  "links": { "self": "/api/v1/nodes?page[offset]=0", "next": "..." }
}
```

---

## 🛠️ Admin Interface

The admin theme provides a complete management UI:

| Page | Description |
|------|-------------|
| **Dashboard** | Stats cards, content overview, quick actions, recent content |
| **Content** | List, filter, search, bulk actions, create/edit with dynamic fields |
| **Media** | Grid/list view, drag-drop upload, file management |
| **Menus** | Menu builder with drag-drop ordering |
| **Taxonomy** | Vocabularies and terms management |
| **Blocks** | Block type registry |
| **Appearance** | Theme selector (frontend + admin), global libraries overview |
| **Settings** | General, content, media, API, and cache settings with tabs |
| **Content Types** | Define content types and field definitions |
| **Users** | User management with roles |

---

## ⚙️ Configuration

All config uses `.mlc` format (MonkeysLegion Config):

```mlc
# config/app.mlc
app {
    name     = ${APP_NAME:"MonkeysCMS"}
    env      = ${APP_ENV:production}
    debug    = ${APP_DEBUG:false}
    url      = ${APP_URL:http://localhost:8000}
    key      = ${APP_KEY}
    timezone = "UTC"
}
```

---

## 📦 Built on MonkeysLegion v2

MonkeysCMS is built on the [MonkeysLegion](https://github.com/MonkeysCloud/monkeyslegion) PHP 8.4 framework:

- **Attribute routing** — `#[Route]`, `#[RoutePrefix]`, `#[Middleware]`
- **PHP 8.4 property hooks** — entities with validation on set
- **PSR-7/PSR-15** — standards-compliant HTTP pipeline
- **PSR-11 DI container** — `#[Singleton]`, auto-discovery
- **Template engine** — `.ml.php` templates with Blade-like syntax
- **MLC config** — type-safe configuration with environment interpolation

---

## 🧪 Testing

```bash
composer test              # All tests
composer test:unit         # Unit tests
composer test:feature      # Feature tests
```

---

## 📜 License

MIT License — see [LICENSE](LICENSE) for details.

---

**MonkeysCMS** — Built with 🐒 by [MonkeysCloud](https://github.com/MonkeysCloud)
