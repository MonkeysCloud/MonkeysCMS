# MonkeysCMS Field Widgets System

The Field Widgets system provides a comprehensive framework for rendering form fields, handling user input, and managing field values. It supports 30+ field types with customizable widgets and allows developers to create custom widgets in modules.

## Table of Contents

- [Overview](#overview)
- [Built-in Field Types](#built-in-field-types)
- [Built-in Widgets](#built-in-widgets)
- [Using the FieldWidgetManager](#using-the-fieldwidgetmanager)
- [Using the FormBuilder](#using-the-formbuilder)
- [Creating Custom Widgets](#creating-custom-widgets)
- [Widget Settings](#widget-settings)
- [Validation](#validation)
- [API Endpoints](#api-endpoints)

## Overview

The field widgets system consists of several components:

- **FieldType** - Enum defining all supported field types
- **FieldDefinition** - Entity representing a field's configuration
- **FieldWidgetInterface** - Contract for widget implementations
- **AbstractFieldWidget** - Base class with common widget functionality
- **FieldWidgetManager** - Registry and resolver for widgets
- **FormBuilder** - Service for generating complete forms

## Built-in Field Types

### Text Types
| Type | Description | Default Widget |
|------|-------------|----------------|
| `string` | Single-line text | text_input |
| `text` | Multi-line text | textarea |
| `textarea` | Multi-line text | textarea |
| `html` | Rich text/HTML | wysiwyg |
| `markdown` | Markdown text | markdown |
| `code` | Code with syntax highlighting | code_editor |

### Number Types
| Type | Description | Default Widget |
|------|-------------|----------------|
| `integer` | Whole numbers | number |
| `float` | Floating point | number |
| `decimal` | Decimal with precision | decimal |

### Selection Types
| Type | Description | Default Widget |
|------|-------------|----------------|
| `boolean` | True/false | switch |
| `select` | Single selection | select |
| `radio` | Radio buttons | radio |
| `checkbox` | Multiple checkboxes | checkboxes |
| `multiselect` | Multiple selection | checkboxes |

### Date/Time Types
| Type | Description | Default Widget |
|------|-------------|----------------|
| `date` | Date only | date |
| `datetime` | Date and time | datetime |
| `time` | Time only | time |

### Media Types
| Type | Description | Default Widget |
|------|-------------|----------------|
| `image` | Single image | image |
| `file` | Single file | file |
| `gallery` | Multiple images | gallery |
| `video` | Video URL/embed | video |

### Reference Types
| Type | Description | Default Widget |
|------|-------------|----------------|
| `entity_reference` | Reference to content | entity_reference |
| `taxonomy_reference` | Reference to terms | taxonomy |
| `user_reference` | Reference to users | user_reference |

### Special Types
| Type | Description | Default Widget |
|------|-------------|----------------|
| `email` | Email address | email |
| `url` | URL | url |
| `phone` | Phone number | phone |
| `color` | Color picker | color |
| `slug` | URL slug | slug |
| `json` | JSON data | json |
| `link` | Link with title | link |
| `address` | Address fields | address |
| `geolocation` | Lat/lng coordinates | geolocation |

## Built-in Widgets

### Text Widgets
- `text_input` - Simple text input
- `textarea` - Multi-line textarea
- `wysiwyg` - Rich text editor (TipTap/ProseMirror)
- `markdown` - Markdown editor with preview
- `code_editor` - Syntax-highlighted code editor

### Number Widgets
- `number` - Number input with min/max/step
- `decimal` - Decimal with currency support
- `range` - Slider range input

### Selection Widgets
- `select` - Dropdown select
- `radio` - Radio button group
- `checkbox` - Single checkbox
- `checkboxes` - Multiple checkboxes
- `switch` - Toggle switch

### Date/Time Widgets
- `date` - Native date picker
- `datetime` - Native datetime picker
- `time` - Time picker

### Media Widgets
- `image` - Image picker with browser
- `file` - File picker
- `gallery` - Multiple image selector
- `video` - Video URL/embed

### Reference Widgets
- `entity_reference` - Autocomplete entity selector
- `taxonomy` - Term selector (tree/checkboxes/autocomplete)
- `user_reference` - User autocomplete

### Special Widgets
- `email` - Email input with validation
- `url` - URL input with preview
- `phone` - Phone input
- `color` - Color picker
- `slug` - URL slug with auto-generation
- `json` - JSON editor
- `link` - Link with title/target
- `address` - Address fields
- `geolocation` - Map with coordinates

### Complex Widgets
- `repeater` - Repeatable field groups

## Using the FieldWidgetManager

```php
use App\Cms\Fields\Widgets\FieldWidgetManager;
use App\Cms\Fields\FieldDefinition;

// Get manager from container
$manager = $container->get(FieldWidgetManager::class);

// Create a field definition
$field = new FieldDefinition();
$field->name = 'Title';
$field->machine_name = 'title';
$field->field_type = 'string';
$field->required = true;

// Render the field
$html = $manager->renderField($field, $currentValue, [
    'form_id' => 'content_form',
    'errors' => $validationErrors,
]);

// Render for display (non-editable)
$displayHtml = $manager->renderFieldDisplay($field, $value);

// Get widget for a field
$widget = $manager->getWidgetForField($field);

// Get all widgets for a field type
$widgets = $manager->getWidgetsForType('string');

// Validate values
$errors = $manager->validateValues($fields, $submittedValues);

// Prepare values for storage
$prepared = $manager->prepareValues($fields, $submittedValues);

// Get collected assets
$css = $manager->getCssAssets();
$js = $manager->getJsAssets();
$initScript = $manager->getInitScripts();
```

## Using the FormBuilder

```php
use App\Cms\Forms\FormBuilder;

$builder = $container->get(FormBuilder::class);

// Build a complete form
$html = $builder->build($fields, $values, [
    'action' => '/admin/content/save',
    'method' => 'POST',
    'id' => 'content_form',
    'submit_label' => 'Save',
    'cancel_url' => '/admin/content',
]);

// Render just the fields
$fieldsHtml = $builder->renderFields($fields, $values, [
    'form_id' => 'content_form',
    'errors' => $errors,
]);

// Validate submitted data
$errors = $builder->validate($fields, $_POST);

// Prepare values for storage
$prepared = $builder->prepareValues($fields, $_POST);
```

## Creating Custom Widgets

### Step 1: Create the Widget Class

```php
<?php
// app/Modules/MyModule/Widgets/MyWidget.php

namespace App\Modules\MyModule\Widgets;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widgets\AbstractFieldWidget;

class MyWidget extends AbstractFieldWidget
{
    protected const ID = 'my_widget';
    protected const LABEL = 'My Custom Widget';
    protected const CATEGORY = 'Custom';
    protected const ICON = '🎨';
    protected const PRIORITY = 100;

    public static function getSupportedTypes(): array
    {
        return ['string', 'text'];
    }

    public function render(FieldDefinition $field, mixed $value, array $context = []): string
    {
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        
        $inputHtml = '<div class="my-widget">';
        $inputHtml .= '<input type="text" id="' . $fieldId . '" name="' . $fieldName . '" value="' . $this->escape($value ?? '') . '">';
        $inputHtml .= '</div>';
        
        return $this->renderWrapper($field, $inputHtml, $context);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, array $context = []): string
    {
        if ($this->isEmpty($value)) {
            return '<span class="field-display field-display--empty">—</span>';
        }
        
        return '<span class="field-display">' . $this->escape($value) . '</span>';
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        // Transform value before storage
        return trim($value);
    }

    public function validate(FieldDefinition $field, mixed $value): array
    {
        $errors = [];
        // Add custom validation
        return $errors;
    }

    public static function getCssAssets(): array
    {
        return ['/css/fields/my-widget.css'];
    }

    public static function getJsAssets(): array
    {
        return ['/js/fields/my-widget.js'];
    }

    public static function getSettingsSchema(): array
    {
        return [
            'custom_option' => [
                'type' => 'string',
                'label' => 'Custom Option',
                'default' => '',
            ],
        ];
    }
}
```

### Step 2: Register the Widget

In your module's `module.php`:

```php
return [
    'name' => 'My Module',
    // ...
    
    'boot' => function ($container) {
        $widgetManager = $container->get(\App\Cms\Fields\Widgets\FieldWidgetManager::class);
        $widgetManager->register(new \App\Modules\MyModule\Widgets\MyWidget());
    },
];
```

Or use auto-discovery:

```php
// The manager can auto-discover widgets in a module
$widgetManager->registerModuleWidgets('/path/to/module');
```

## Widget Settings

Widgets can define settings that allow customization:

```php
public static function getSettingsSchema(): array
{
    return [
        'placeholder' => [
            'type' => 'string',
            'label' => 'Placeholder text',
            'default' => '',
        ],
        'max_length' => [
            'type' => 'integer',
            'label' => 'Maximum length',
            'min' => 1,
            'max' => 1000,
        ],
        'display_mode' => [
            'type' => 'select',
            'label' => 'Display mode',
            'options' => [
                'inline' => 'Inline',
                'block' => 'Block',
            ],
            'default' => 'inline',
        ],
        'allow_html' => [
            'type' => 'boolean',
            'label' => 'Allow HTML',
            'default' => false,
        ],
    ];
}
```

Access settings in the widget:

```php
public function render(FieldDefinition $field, mixed $value, array $context = []): string
{
    $placeholder = $field->getSetting('placeholder', '');
    $maxLength = $field->getSetting('max_length');
    // ...
}
```

## Validation

### Field-level Validation

Set validation rules on the FieldDefinition:

```php
$field->validation = [
    'min' => 0,
    'max' => 100,
    'minLength' => 5,
    'maxLength' => 255,
    'pattern' => '^[a-z]+$',
    'in' => ['option1', 'option2', 'option3'],
];
```

### Widget-level Validation

Override the `validate` method:

```php
public function validate(FieldDefinition $field, mixed $value): array
{
    $errors = [];
    
    if ($value !== null && !$this->isValidFormat($value)) {
        $errors[] = 'Invalid format';
    }
    
    return $errors;
}
```

### Using Validation

```php
// Validate using widget manager
$errors = $widgetManager->validateValues($fields, $submittedData);
// Returns: ['field_name' => ['Error message 1', 'Error message 2'], ...]

// Or using form builder
$errors = $formBuilder->validate($fields, $submittedData);

// Check if valid
if (empty($errors)) {
    // Save data
}
```

## API Endpoints

### Field Types

```
GET /admin/fields/types
GET /admin/fields/types/{type}
GET /admin/fields/types/grouped
```

### Widgets

```
GET /admin/fields/widgets
GET /admin/fields/widgets/grouped
GET /admin/fields/widgets/for-type/{type}
GET /admin/fields/widgets/{id}/schema
```

### Validation & Rendering

```
POST /admin/fields/validate
POST /admin/fields/validate-many
POST /admin/fields/render
POST /admin/fields/render-display
POST /admin/fields/prepare
```

### Helpers

```
GET /admin/fields/template?type={type}
POST /admin/fields/machine-name
```

## Example: Complete Field Form

```php
// Controller
public function editForm(int $contentId): Response
{
    $content = $this->repository->find($contentId);
    $fields = $this->contentTypeManager->getFieldsForType($content->type);
    
    $html = $this->formBuilder->build($fields, $content->getFieldValues(), [
        'action' => "/admin/content/{$contentId}",
        'method' => 'POST',
        'id' => 'content_edit_form',
    ]);
    
    return $this->view('admin/content/edit', [
        'form' => $html,
        'content' => $content,
    ]);
}

public function save(ServerRequestInterface $request, int $contentId): Response
{
    $content = $this->repository->find($contentId);
    $fields = $this->contentTypeManager->getFieldsForType($content->type);
    $data = $request->getParsedBody();
    
    // Validate
    $errors = $this->formBuilder->validate($fields, $data);
    
    if (!empty($errors)) {
        return $this->formBuilder->build($fields, $data, [
            'action' => "/admin/content/{$contentId}",
            'errors' => $errors,
        ]);
    }
    
    // Prepare and save
    $prepared = $this->formBuilder->prepareValues($fields, $data);
    $content->setFieldValues($prepared);
    $this->repository->save($content);
    
    return $this->redirect("/admin/content/{$contentId}");
}
```

## CSS Classes

The widget system uses consistent CSS classes:

```css
/* Field wrapper */
.field-widget { }
.field-widget--error { }
.field-widget--required { }
.field-widget--{widget_id} { }
.field-type--{field_type} { }

/* Components */
.field-widget__label { }
.field-widget__required { }
.field-widget__input { }
.field-widget__control { }
.field-widget__help { }
.field-widget__errors { }
.field-widget__error { }

/* Display mode */
.field-display { }
.field-display--empty { }
.field-display--{type} { }
```

## JavaScript Events

Widgets emit standard events:

```javascript
// On value change
document.getElementById('field_id').addEventListener('change', function(e) {
    console.log('New value:', e.target.value);
});

// Custom widget events
document.getElementById('field_wrapper').addEventListener('widget:init', function(e) {
    console.log('Widget initialized');
});
```
