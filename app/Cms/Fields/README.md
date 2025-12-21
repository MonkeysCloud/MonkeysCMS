# MonkeysCMS Field Widget System

A comprehensive, object-oriented field widget system for building dynamic forms and content management interfaces.

## Features

- **35+ Field Widgets**: Text, selection, number, date, media, reference, composite, rich text, and location widgets
- **Fluent Field Definition API**: Chain methods to configure fields easily
- **Comprehensive Validation**: Required, type-based, pattern, range, and custom validators
- **Form Building**: Automatic form generation with grouping and error handling
- **Display Rendering**: Separate rendering for editable forms and display views
- **Asset Management**: Automatic CSS/JS collection for used widgets
- **Database Storage**: EAV pattern with revision support
- **CLI Tools**: Field management from the command line
- **HTTP API**: RESTful endpoints for field operations

## Quick Start

### Using the Field Manager (Recommended)

```php
use App\Cms\Fields\FieldManager;

// Create manager with database
$manager = FieldManager::create($pdo);

// Define a field
$titleField = $manager->defineField('title', 'string')
    ->name('Title')
    ->required()
    ->searchable()
    ->save();

// Store a value
$manager->setValue($titleField, 'node', 1, 'My First Post');

// Render a form
$html = $manager->renderField($titleField, 'My First Post');

// Validate
$result = $manager->validateField($titleField, '');
if (!$result->isValid()) {
    echo $result->getErrors()[0];
}
```

### Using the Service Provider

```php
use App\Cms\Fields\FieldServiceProvider;

// Create components
$registry = FieldServiceProvider::createWidgetRegistry();
$formBuilder = FieldServiceProvider::createFormBuilder($registry);

// Or create all at once
$services = FieldServiceProvider::createAll();
$registry = $services['registry'];
$formBuilder = $services['form_builder'];
$validator = $services['validator'];
```

### Manual Setup

```php
use App\Cms\Fields\Widget\WidgetFactory;
use App\Cms\Fields\Form\FormBuilder;
use App\Cms\Fields\Validation\FieldValidator;

// Create widget registry with all widgets
$registry = WidgetFactory::create();

// Create form builder
$formBuilder = new FormBuilder($registry);

// Create validator
$validator = new FieldValidator();
```

## Field Definition

### Create Fields Programmatically

```php
use App\Cms\Fields\FieldDefinition;

// From array
$field = new FieldDefinition([
    'name' => 'Email',
    'machine_name' => 'field_email',
    'field_type' => 'email',
    'required' => true,
    'description' => 'Contact email address',
]);

// Using fluent API
$field = (new FieldDefinition([
    'name' => 'Price',
    'machine_name' => 'field_price',
    'field_type' => 'decimal',
]))
->required()
->withWidget('decimal')
->withSettings(['precision' => 2, 'currency' => 'USD']);
```

### Using Field Manager Builder

```php
$field = $manager->defineField('category', 'taxonomy_reference')
    ->name('Category')
    ->required()
    ->widgetSettings([
        'vocabulary' => 'categories',
        'display_style' => 'select',
    ])
    ->save();
```

## Available Widgets

### Text Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Text Input | `text_input` | string |
| Textarea | `textarea` | text, textarea |
| Email | `email` | email |
| URL | `url` | url |
| Phone | `phone` | phone, tel |

### Selection Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Select | `select` | select |
| Checkbox | `checkbox` | boolean |
| Checkboxes | `checkboxes` | multiselect |
| Radio | `radio` | radio |
| Switch | `switch` | boolean |

### Number Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Number | `number` | integer, float |
| Decimal | `decimal` | decimal, currency |
| Range | `range` | range, slider |

### Date Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Date | `date` | date |
| DateTime | `datetime` | datetime |
| Time | `time` | time |

### Special Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Color | `color` | color |
| Hidden | `hidden` | hidden |
| Slug | `slug` | slug |
| JSON | `json` | json, object |
| Password | `password` | password |

### Media Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Image | `image` | image |
| File | `file` | file, document |
| Gallery | `gallery` | gallery, images |
| Video | `video` | video |

### Reference Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Entity Reference | `entity_reference` | reference |
| Taxonomy | `taxonomy` | taxonomy_reference |
| User Reference | `user_reference` | user_reference |

### Rich Text Widgets
| Widget | ID | Types |
|--------|-----|-------|
| WYSIWYG | `wysiwyg` | html, richtext |
| Markdown | `markdown` | markdown |
| Code | `code` | code |

### Location Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Address | `address` | address |
| Geolocation | `geolocation` | geolocation |
| Link | `link` | link, url |

### Composite Widgets
| Widget | ID | Types |
|--------|-----|-------|
| Repeater | `repeater` | repeater, collection |

## Rendering

### Render Individual Fields

```php
use App\Cms\Fields\Rendering\RenderContext;

$context = RenderContext::create()
    ->withFormId('edit_post')
    ->withIndex(0);

// Editable form
$result = $registry->get('text_input')->render($field, $value, $context);
echo $result->getHtml();

// Display only
$result = $registry->get('text_input')->renderDisplay($field, $value, $context);
echo $result->getHtml();
```

### Build Complete Forms

```php
$fields = [
    new FieldDefinition([...]),
    new FieldDefinition([...]),
];

$values = [
    'field_title' => 'Hello World',
    'field_body' => 'Content here',
];

$errors = [
    'field_title' => ['Title is required'],
];

$html = $formBuilder->build($fields, $values, $errors, [
    'title' => 'Edit Post',
    'action' => '/posts/1',
    'method' => 'PUT',
    'submit_label' => 'Update',
    'cancel_url' => '/posts',
]);
```

## Validation

### Single Field Validation

```php
$result = $validator->validate($field, $value);

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        echo $error;
    }
}
```

### Multiple Fields

```php
$results = $validator->validateMultiple($fields, $values);

foreach ($results as $fieldName => $result) {
    if (!$result->isValid()) {
        echo "{$fieldName}: " . implode(', ', $result->getErrors());
    }
}
```

### Custom Validation Rules

```php
$field = new FieldDefinition([
    'name' => 'Username',
    'machine_name' => 'field_username',
    'field_type' => 'string',
    'validation' => [
        'pattern' => '/^[a-z0-9_]+$/',
        'min_length' => 3,
        'max_length' => 20,
    ],
]);
```

## Database

### Run Migrations

```php
use App\Cms\Fields\Database\FieldMigration;

$migration = new FieldMigration($pdo);
$migration->up();     // Create tables
$migration->seed();   // Seed default fields
$migration->down();   // Drop tables
```

### Using Repository

```php
use App\Cms\Fields\FieldRepository;

$repo = new FieldRepository($pdo);

// CRUD operations
$repo->save($field);
$field = $repo->find(1);
$field = $repo->findByMachineName('field_title');
$fields = $repo->findAll();
$repo->delete($field);

// Attach to entity type
$repo->attachToEntity($field, 'node', $bundleId, $weight);
$fields = $repo->findByEntityType('node', $bundleId);
```

### Value Storage

```php
use App\Cms\Fields\Storage\FieldValueStorage;

$storage = new FieldValueStorage($pdo);

// Store values
$storage->setValue($fieldId, 'node', 1, 'Hello World');
$storage->setValues('node', 1, [
    1 => 'Title',
    2 => 'Body content',
]);

// Retrieve values
$value = $storage->getValue($fieldId, 'node', 1);
$values = $storage->getEntityValues('node', 1);

// Revisions
$storage->createRevision('node', 1, $revisionId);
$oldValues = $storage->getRevisionValues('node', 1, $revisionId);
$storage->restoreRevision('node', 1, $revisionId);
```

## CLI Commands

```bash
# Run migrations
php FieldCommands.php migrate --action=up
php FieldCommands.php migrate --action=down
php FieldCommands.php migrate --action=fresh

# List fields
php FieldCommands.php list
php FieldCommands.php list --format=json

# Create field
php FieldCommands.php create --name="Title" --type=string --required

# Show field
php FieldCommands.php show --id=1
php FieldCommands.php show --machine_name=field_title

# Delete field
php FieldCommands.php delete --id=1

# List widgets
php FieldCommands.php widgets
php FieldCommands.php widgets --type=string
php FieldCommands.php widgets --category=Text

# Export/Import
php FieldCommands.php export --file=fields.json
php FieldCommands.php import --file=fields.json --force
```

## HTTP API

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/fields` | List all fields |
| POST | `/api/fields` | Create field |
| GET | `/api/fields/{id}` | Get field |
| PUT | `/api/fields/{id}` | Update field |
| DELETE | `/api/fields/{id}` | Delete field |
| GET | `/api/fields/widgets` | List widgets |
| GET | `/api/fields/widgets/{id}` | Get widget |
| GET | `/api/fields/types/{type}/widgets` | Widgets for type |
| POST | `/api/fields/{id}/render` | Render field |
| POST | `/api/fields/{id}/validate` | Validate value |

## Custom Widgets

### Create a Custom Widget

```php
use App\Cms\Fields\Widget\AbstractWidget;
use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;

class StarRatingWidget extends AbstractWidget
{
    public function getId(): string { return 'star_rating'; }
    public function getLabel(): string { return 'Star Rating'; }
    public function getCategory(): string { return 'Custom'; }
    public function getIcon(): string { return '⭐'; }
    public function getSupportedTypes(): array { return ['integer', 'rating']; }

    protected function buildInput(
        FieldDefinition $field, 
        mixed $value, 
        RenderContext $context
    ): HtmlBuilder|string {
        $maxStars = $this->getSettings($field)->getInt('max_stars', 5);
        
        // Build star rating HTML
        $html = '<div class="star-rating">';
        for ($i = 1; $i <= $maxStars; $i++) {
            $checked = $i <= ($value ?? 0) ? 'checked' : '';
            $html .= "<input type='radio' name='{$this->getFieldName($field, $context)}' value='{$i}' {$checked}>";
        }
        $html .= '</div>';
        
        return $html;
    }

    public function getSettingsSchema(): array
    {
        return [
            'max_stars' => ['type' => 'integer', 'default' => 5],
        ];
    }
}

// Register
$registry->register(new StarRatingWidget());
```

## Directory Structure

```
app/Cms/Fields/
├── Console/
│   └── FieldCommands.php        # CLI commands
├── Database/
│   └── FieldMigration.php       # Database migrations
├── Definition/
│   └── Field.php                # Field definition class
├── Examples/
│   └── usage.php                # Usage examples
├── Form/
│   └── FormBuilder.php          # Form generation
├── Http/
│   ├── FieldController.php      # HTTP API
│   └── FieldRoutes.php          # Route definitions
├── Rendering/
│   ├── AssetCollection.php      # CSS/JS management
│   ├── HtmlBuilder.php          # HTML generation
│   ├── RenderContext.php        # Render configuration
│   └── RenderResult.php         # Render output
├── Settings/
│   └── FieldSettings.php        # Settings value object
├── Storage/
│   └── FieldValueStorage.php    # Value persistence
├── Tests/
│   └── FieldWidgetSystemTest.php # Test suite
├── Validation/
│   ├── FieldValidator.php       # Validation logic
│   └── ValidationRules.php      # Rule definitions
├── Value/
│   ├── FieldValue.php           # Value wrapper
│   └── ValueTransformer.php     # Value transformation
├── Widget/
│   ├── AbstractWidget.php       # Base widget class
│   ├── Composite/               # Repeater widget
│   ├── Date/                    # Date widgets
│   ├── Location/                # Location widgets
│   ├── Media/                   # Media widgets
│   ├── Number/                  # Number widgets
│   ├── Reference/               # Reference widgets
│   ├── RichText/                # Rich text widgets
│   ├── Selection/               # Selection widgets
│   ├── Special/                 # Special widgets
│   ├── Text/                    # Text widgets
│   ├── WidgetFactory.php        # Factory class
│   ├── WidgetInterface.php      # Widget contract
│   └── WidgetRegistry.php       # Widget management
├── FieldDefinition.php          # Field definition
├── FieldManager.php             # Main orchestrator
├── FieldRepository.php          # Database repository
├── FieldServiceProvider.php     # DI definitions
└── FieldType.php                # Type enumeration

public/
├── css/fields/
│   └── widgets.css              # Widget styles
└── js/fields/
    └── widgets.js               # Widget JavaScript
```

## Testing

```php
use App\Cms\Fields\Tests\FieldWidgetSystemTest;

$test = new FieldWidgetSystemTest();
$result = $test->run();  // Returns 0 on success, 1 on failure
```

## License

Part of MonkeysCMS. MIT License.
