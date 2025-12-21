<?php

declare(strict_types=1);

/**
 * MonkeysCMS Field Widget System - Usage Examples
 *
 * This file demonstrates the object-oriented approach to working
 * with fields, widgets, validation, and form building.
 */

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\FieldRepository;
use App\Cms\Fields\FieldServiceProvider;
use App\Cms\Fields\Form\FormBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Examples\StarRatingWidget;

// =============================================================================
// 1. SETTING UP THE SYSTEM
// =============================================================================

/**
 * Option A: Use the service provider (recommended for DI containers)
 */
$registry = FieldServiceProvider::createWidgetRegistry();
$formBuilder = FieldServiceProvider::createFormBuilder($registry);

/**
 * Option B: Manual setup with more control
 */
$validator = new FieldValidator();
$registry = WidgetFactory::create($validator);
$formBuilder = new FormBuilder($registry);

// =============================================================================
// 2. DEFINING FIELDS
// =============================================================================

/**
 * Using the fluent interface
 */
$titleField = FieldDefinition::create('Title', 'field_title', 'string')
    ->required()
    ->withHelpText('Enter a descriptive title')
    ->withWidget('text_input')
    ->withSettings([
        'max_length' => 255,
        'placeholder' => 'Enter title...',
    ])
    ->withValidation([
        'minLength' => 3,
        'maxLength' => 255,
    ])
    ->withWeight(0);

$bodyField = FieldDefinition::create('Body', 'field_body', 'text')
    ->required()
    ->withHelpText('Main content of the article')
    ->withWidget('textarea')
    ->withSettings([
        'rows' => 10,
        'show_counter' => true,
        'max_length' => 50000,
    ])
    ->withWeight(10);

$categoryField = FieldDefinition::create('Category', 'field_category', 'taxonomy_reference')
    ->required()
    ->withWidget('taxonomy')
    ->withWidgetSettings([
        'vocabulary' => 'categories',
        'display_style' => 'checkboxes',
    ])
    ->multiple()
    ->withWeight(20);

$publishedField = FieldDefinition::create('Published', 'field_published', 'boolean')
    ->withDefault(false)
    ->withWidget('switch')
    ->withWidgetSettings([
        'on_label' => 'Published',
        'off_label' => 'Draft',
    ])
    ->withWeight(30);

$imageField = FieldDefinition::create('Featured Image', 'field_image', 'image')
    ->withWidget('image')
    ->withWidgetSettings([
        'preview_size' => 'large',
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_size' => 5242880, // 5MB
    ])
    ->withWeight(40);

$galleryField = FieldDefinition::create('Gallery', 'field_gallery', 'gallery')
    ->withWidget('gallery')
    ->multiple()
    ->withWidgetSettings([
        'max_items' => 20,
        'thumb_size' => 100,
    ])
    ->withWeight(50);

$priceField = FieldDefinition::create('Price', 'field_price', 'decimal')
    ->withWidget('decimal')
    ->withWidgetSettings([
        'currency' => 'USD',
        'decimals' => 2,
        'min' => 0,
    ])
    ->withWeight(60);

$tagsField = FieldDefinition::create('Tags', 'field_tags', 'taxonomy_reference')
    ->withWidget('taxonomy')
    ->multiple()
    ->withWidgetSettings([
        'vocabulary' => 'tags',
        'display_style' => 'tags',
        'allow_new' => true,
    ])
    ->withWeight(70);

$metaField = FieldDefinition::create('Metadata', 'field_meta', 'json')
    ->withWidget('json')
    ->withSettings([
        'rows' => 8,
    ])
    ->withWeight(80);

/**
 * Creating a repeater field with sub-fields
 */
$faqField = FieldDefinition::create('FAQs', 'field_faq', 'repeater')
    ->withWidget('repeater')
    ->multiple()
    ->withWidgetSettings([
        'sub_fields' => [
            [
                'name' => 'Question',
                'machine_name' => 'question',
                'field_type' => 'string',
                'required' => true,
            ],
            [
                'name' => 'Answer',
                'machine_name' => 'answer',
                'field_type' => 'text',
                'required' => true,
                'widget' => 'textarea',
                'settings' => ['rows' => 4],
            ],
        ],
        'min_items' => 1,
        'max_items' => 10,
        'item_label' => 'FAQ',
        'collapsible' => true,
    ])
    ->withWeight(90);

// Collect all fields
$fields = [
    $titleField,
    $bodyField,
    $categoryField,
    $publishedField,
    $imageField,
    $galleryField,
    $priceField,
    $tagsField,
    $metaField,
    $faqField,
];

// =============================================================================
// 3. RENDERING FIELDS
// =============================================================================

/**
 * Render a single field
 */
$context = RenderContext::create([
    'form_id' => 'article_form',
    'errors' => [], // Pass validation errors here
]);

$result = $registry->renderField($titleField, 'My Article Title', $context);
echo $result->getHtml();

// Get assets separately (useful for collecting in head/footer)
$assets = $result->getAssets();
$cssLinks = $assets->getCssFiles(); // Array of CSS file paths
$jsLinks = $assets->getJsFiles();   // Array of JS file paths

/**
 * Render for display (non-editable)
 */
$displayContext = RenderContext::forDisplay();
$displayResult = $registry->renderFieldDisplay($titleField, 'My Article Title', $displayContext);
echo $displayResult->getHtml();

// =============================================================================
// 4. BUILDING FORMS
// =============================================================================

/**
 * Build a complete form
 */
$values = [
    'field_title' => 'My Article',
    'field_body' => 'Article content...',
    'field_category' => [1, 2],
    'field_published' => true,
    'field_price' => 29.99,
    'field_tags' => ['tech', 'news'],
    'field_faq' => [
        ['question' => 'What is this?', 'answer' => 'A demo'],
    ],
];

$formResult = $formBuilder
    ->id('article_form')
    ->action('/admin/articles/save')
    ->submitLabel('Save Article')
    ->cancelUrl('/admin/articles')
    ->groupFields(true)
    ->build($fields, $values);

// Output complete form with assets
echo $formResult->getHtmlWithAssets();

/**
 * Build form with validation errors
 */
$errors = [
    'field_title' => ['Title must be at least 3 characters'],
    'field_body' => ['Body is required'],
    '_form' => ['Please fix the errors below'],
];

$formWithErrors = $formBuilder
    ->errors($errors)
    ->build($fields, $values);

// =============================================================================
// 5. VALIDATING VALUES
// =============================================================================

/**
 * Validate a single field
 */
$titleErrors = $registry->validateField($titleField, '');
// Returns: ['Title is required']

$titleErrors = $registry->validateField($titleField, 'Hi');
// Returns: ['Title must be at least 3 characters']

$titleErrors = $registry->validateField($titleField, 'Valid Title');
// Returns: [] (empty - valid)

/**
 * Validate multiple fields at once
 */
$submittedValues = [
    'field_title' => 'My Article',
    'field_body' => '', // Missing!
    'field_published' => true,
];

$allErrors = $registry->validateFields($fields, $submittedValues);
// Returns: ['field_body' => ['Body is required']]

/**
 * Using the validator directly for custom rules
 */
$validator = new FieldValidator();

// Register a custom rule
$validator->registerRule(new class implements \App\Cms\Fields\Validation\ValidationRuleInterface {
    public function getName(): string
    {
        return 'profanity';
    }

    public function validate(
        mixed $value,
        mixed $parameter,
        \App\Cms\Fields\Validation\ValidationContext $context
    ): \App\Cms\Fields\Validation\ValidationResult {
        $badWords = ['spam', 'test'];

        foreach ($badWords as $word) {
            if (stripos($value, $word) !== false) {
                return \App\Cms\Fields\Validation\ValidationResult::failure(
                    "{$context->fieldLabel} contains prohibited content"
                );
            }
        }

        return \App\Cms\Fields\Validation\ValidationResult::success();
    }
});

// =============================================================================
// 6. PREPARING VALUES FOR STORAGE
// =============================================================================

/**
 * Prepare values (transform for database storage)
 */
$preparedValues = $registry->prepareValues($fields, $submittedValues);
// Boolean converted to true bool
// Arrays JSON encoded where needed
// Dates formatted correctly
// etc.

/**
 * Format values (transform for form display)
 */
$formattedValues = [];
foreach ($fields as $field) {
    $storedValue = $preparedValues[$field->machine_name] ?? null;
    $formattedValues[$field->machine_name] = $registry->formatValue($field, $storedValue);
}

// =============================================================================
// 7. WORKING WITH WIDGETS
// =============================================================================

/**
 * Get available widgets for a field type
 */
$textWidgets = $registry->getForType('string');
// Returns widgets: text_input, textarea, email, url, phone, etc.

$booleanWidgets = $registry->getForType('boolean');
// Returns widgets: checkbox, switch

/**
 * Get widget options for a dropdown
 */
$options = $registry->getOptionsForType('string');
// Returns: ['text_input' => 'Text Input', 'textarea' => 'Textarea', ...]

/**
 * Get all widgets grouped by category
 */
$grouped = $registry->getGroupedByCategory();
// Returns: [
//   'Text' => [TextInputWidget metadata, TextareaWidget metadata, ...],
//   'Selection' => [SelectWidget metadata, CheckboxWidget metadata, ...],
//   ...
// ]

/**
 * Check if a specific widget exists
 */
if ($registry->has('custom_widget')) {
    $widget = $registry->get('custom_widget');
}

// =============================================================================
// 8. CREATING CUSTOM WIDGETS
// =============================================================================

use App\Cms\Fields\Widget\AbstractWidget;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;

/**
 * Custom Star Rating Widget
 * Note: See app/Cms/Fields/Examples/StarRatingWidget.php
 */
// use App\Cms\Fields\Examples\StarRatingWidget;

// Register custom widget
$registry->register(new StarRatingWidget());
$registry->setTypeDefault('rating', 'star_rating');

// =============================================================================
// 9. WORKING WITH THE REPOSITORY
// =============================================================================

/**
 * Save and retrieve fields from database
 */
$pdo = new \PDO('mysql:host=localhost;dbname=cms', 'user', 'password');
$repository = new FieldRepository($pdo);

// Save a field
$repository->save($titleField);
echo "Field saved with ID: " . $titleField->id;

// Find by ID
$field = $repository->find(1);

// Find by machine name
$field = $repository->findByMachineName('field_title');

// Find all fields
$allFields = $repository->findAll();

// Find fields for an entity type
$articleFields = $repository->findByEntityType('article');

// Attach a field to an entity type
$repository->attachToEntity($titleField, 'article', bundleId: 1, weight: 0);

// Detach a field
$repository->detachFromEntity($titleField, 'article', bundleId: 1);

// Delete a field
$repository->delete($titleField);

// =============================================================================
// 10. ASSET MANAGEMENT
// =============================================================================

use App\Cms\Fields\Rendering\AssetCollection;

/**
 * Collect all assets from rendered fields
 */
$assets = new AssetCollection();

foreach ($fields as $field) {
    $result = $registry->renderField($field, $values[$field->machine_name] ?? null, $context);
    $assets->merge($result->getAssets());
}

// In your layout's <head>
echo $assets->renderCssTags();
echo $assets->renderInlineStyles();

// Before closing </body>
echo $assets->renderJsTags();
echo $assets->renderInitScripts();

// Or get everything at once
echo $assets->render();

// =============================================================================
// 11. RENDER CONTEXT OPTIONS
// =============================================================================

/**
 * Create context with various options
 */
$context = RenderContext::create([
    'form_id' => 'my_form',
    'name_prefix' => 'entity[fields]',  // Results in: entity[fields][field_name]
    'disabled' => false,
    'readonly' => false,
    'hide_label' => false,
    'hide_help' => false,
    'errors' => [
        'field_title' => ['Error 1', 'Error 2'],
    ],
]);

// Immutable transformations
$disabledContext = $context->withDisabled();
$indexedContext = $context->withIndex(5);
$prefixedContext = $context->withNamePrefix('items[0]');

// For repeater items
$itemContext = $context
    ->withNamePrefix('field_faq')
    ->withIndex(0);

// =============================================================================
// 12. HTML BUILDER UTILITIES
// =============================================================================

use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;

/**
 * Build HTML elements programmatically
 */
$div = Html::div()
    ->id('my-div')
    ->class('container', 'mx-auto')
    ->data('controller', 'dropdown')
    ->child(
        Html::element('h1')
            ->class('title')
            ->text('Hello World')
    )
    ->child(
        Html::input('text')
            ->name('username')
            ->placeholder('Enter username')
            ->required()
    );

echo $div->render();

// Conditional attributes
$button = Html::button()
    ->class('btn')
    ->when($isDisabled, fn($b) => $b->disabled())
    ->when($isLoading, fn($b) => $b->addClass('loading'))
    ->text('Submit');

// Building select options
$select = Html::select()->name('country');
foreach ($countries as $code => $name) {
    $select->child(
        Html::option($code, $name, $code === $selectedCountry)
    );
}
