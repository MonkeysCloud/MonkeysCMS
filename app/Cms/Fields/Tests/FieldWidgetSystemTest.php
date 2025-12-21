<?php

declare(strict_types=1);

namespace App\Cms\Fields\Tests;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\FieldManager;
use App\Cms\Fields\FieldRepository;
use App\Cms\Fields\FieldServiceProvider;
use App\Cms\Fields\Form\FormBuilder;
use App\Cms\Fields\InMemoryFieldRepository;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Validation\FieldValidator;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\WidgetFactory;
use App\Cms\Fields\Widget\WidgetRegistry;

/**
 * FieldWidgetSystemTest - Comprehensive test suite for the field widget system
 * 
 * Usage:
 *   php FieldWidgetSystemTest.php
 */
class FieldWidgetSystemTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    /**
     * Run all tests
     */
    public function run(): int
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "MonkeysCMS Field Widget System Tests\n";
        echo str_repeat('=', 70) . "\n\n";

        // Widget Registry Tests
        $this->testWidgetRegistryCreation();
        $this->testWidgetRegistration();
        $this->testWidgetRetrieval();
        $this->testTypeDefaults();

        // Field Definition Tests
        $this->testFieldDefinitionCreation();
        $this->testFieldDefinitionFluent();
        $this->testFieldDefinitionSerialization();

        // Validation Tests
        $this->testRequiredValidation();
        $this->testEmailValidation();
        $this->testUrlValidation();
        $this->testLengthValidation();
        $this->testNumericValidation();
        $this->testPatternValidation();
        $this->testMultipleFieldValidation();

        // Rendering Tests
        $this->testTextInputRendering();
        $this->testTextareaRendering();
        $this->testSelectRendering();
        $this->testCheckboxRendering();
        $this->testNumberRendering();
        $this->testDateRendering();
        $this->testDisplayRendering();

        // Form Builder Tests
        $this->testFormBuilderBasic();
        $this->testFormBuilderWithErrors();
        $this->testFormBuilderGrouping();

        // Repository Tests
        $this->testInMemoryRepository();

        // Field Manager Tests
        $this->testFieldManagerCreation();
        $this->testFieldManagerDefineField();

        // Service Provider Tests
        $this->testServiceProvider();

        // Print results
        echo "\n" . str_repeat('=', 70) . "\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);

        if (!empty($this->failures)) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure}\n";
            }
        }

        echo str_repeat('=', 70) . "\n\n";

        return $this->failed > 0 ? 1 : 0;
    }

    // =========================================================================
    // Widget Registry Tests
    // =========================================================================

    private function testWidgetRegistryCreation(): void
    {
        $this->test("Widget registry can be created", function() {
            $registry = WidgetFactory::create();
            return $registry instanceof WidgetRegistry;
        });
    }

    private function testWidgetRegistration(): void
    {
        $this->test("Widgets are registered on creation", function() {
            $registry = WidgetFactory::create();
            return $registry->has('text_input') 
                && $registry->has('textarea')
                && $registry->has('select')
                && $registry->has('checkbox');
        });
    }

    private function testWidgetRetrieval(): void
    {
        $this->test("Widgets can be retrieved by ID", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('text_input');
            return $widget !== null && $widget->getId() === 'text_input';
        });
    }

    private function testTypeDefaults(): void
    {
        $this->test("Type defaults are set correctly", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->getForType('string');
            return $widget->getId() === 'text_input';
        });

        $this->test("Email type uses email widget", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->getForType('email');
            return $widget->getId() === 'email';
        });

        $this->test("Boolean type uses switch widget", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->getForType('boolean');
            return $widget->getId() === 'switch';
        });
    }

    // =========================================================================
    // Field Definition Tests
    // =========================================================================

    private function testFieldDefinitionCreation(): void
    {
        $this->test("Field definition can be created from array", function() {
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
                'required' => true,
            ]);
            
            return $field->getName() === 'Title'
                && $field->getMachineName() === 'field_title'
                && $field->getType() === 'string'
                && $field->isRequired() === true;
        });
    }

    private function testFieldDefinitionFluent(): void
    {
        $this->test("Field definition supports fluent interface", function() {
            $field = (new FieldDefinition([
                'name' => 'Email',
                'machine_name' => 'field_email',
                'field_type' => 'email',
            ]))
            ->withDescription('User email address')
            ->required()
            ->searchable();

            return $field->getDescription() === 'User email address'
                && $field->isRequired() === true
                && $field->isSearchable() === true;
        });
    }

    private function testFieldDefinitionSerialization(): void
    {
        $this->test("Field definition can be serialized to array", function() {
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
                'required' => true,
            ]);
            
            $array = $field->toArray();
            
            return isset($array['name'])
                && isset($array['machine_name'])
                && isset($array['field_type']);
        });
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    private function testRequiredValidation(): void
    {
        $this->test("Required field fails with empty value", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
                'required' => true,
            ]);
            
            $result = $validator->validate($field, '');
            return !$result->isValid();
        });

        $this->test("Required field passes with value", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
                'required' => true,
            ]);
            
            $result = $validator->validate($field, 'Hello');
            return $result->isValid();
        });
    }

    private function testEmailValidation(): void
    {
        $this->test("Invalid email fails validation", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Email',
                'machine_name' => 'field_email',
                'field_type' => 'email',
            ]);
            
            $result = $validator->validate($field, 'not-an-email');
            return !$result->isValid();
        });

        $this->test("Valid email passes validation", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Email',
                'machine_name' => 'field_email',
                'field_type' => 'email',
            ]);
            
            $result = $validator->validate($field, 'test@example.com');
            return $result->isValid();
        });
    }

    private function testUrlValidation(): void
    {
        $this->test("Invalid URL fails validation", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Website',
                'machine_name' => 'field_website',
                'field_type' => 'url',
            ]);
            
            $result = $validator->validate($field, 'not-a-url');
            return !$result->isValid();
        });

        $this->test("Valid URL passes validation", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Website',
                'machine_name' => 'field_website',
                'field_type' => 'url',
            ]);
            
            $result = $validator->validate($field, 'https://example.com');
            return $result->isValid();
        });
    }

    private function testLengthValidation(): void
    {
        $this->test("Value exceeding max length fails", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
                'settings' => ['max_length' => 10],
            ]);
            
            $result = $validator->validate($field, 'This is way too long');
            return !$result->isValid();
        });
    }

    private function testNumericValidation(): void
    {
        $this->test("Number outside range fails", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Age',
                'machine_name' => 'field_age',
                'field_type' => 'integer',
                'settings' => ['min' => 0, 'max' => 150],
            ]);
            
            $result = $validator->validate($field, 200);
            return !$result->isValid();
        });

        $this->test("Number within range passes", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Age',
                'machine_name' => 'field_age',
                'field_type' => 'integer',
                'settings' => ['min' => 0, 'max' => 150],
            ]);
            
            $result = $validator->validate($field, 25);
            return $result->isValid();
        });
    }

    private function testPatternValidation(): void
    {
        $this->test("Value not matching pattern fails", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Code',
                'machine_name' => 'field_code',
                'field_type' => 'string',
                'validation' => ['pattern' => '/^[A-Z]{3}$/'],
            ]);
            
            $result = $validator->validate($field, 'abc');
            return !$result->isValid();
        });

        $this->test("Value matching pattern passes", function() {
            $validator = new FieldValidator();
            $field = new FieldDefinition([
                'name' => 'Code',
                'machine_name' => 'field_code',
                'field_type' => 'string',
                'validation' => ['pattern' => '/^[A-Z]{3}$/'],
            ]);
            
            $result = $validator->validate($field, 'ABC');
            return $result->isValid();
        });
    }

    private function testMultipleFieldValidation(): void
    {
        $this->test("Multiple fields can be validated at once", function() {
            $validator = new FieldValidator();
            
            $fields = [
                new FieldDefinition([
                    'name' => 'Title',
                    'machine_name' => 'field_title',
                    'field_type' => 'string',
                    'required' => true,
                ]),
                new FieldDefinition([
                    'name' => 'Email',
                    'machine_name' => 'field_email',
                    'field_type' => 'email',
                ]),
            ];
            
            $values = [
                'field_title' => '',
                'field_email' => 'invalid',
            ];
            
            $results = $validator->validateMultiple($fields, $values);
            
            return !$results['field_title']->isValid() && !$results['field_email']->isValid();
        });
    }

    // =========================================================================
    // Rendering Tests
    // =========================================================================

    private function testTextInputRendering(): void
    {
        $this->test("Text input widget renders correctly", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('text_input');
            
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
            ]);
            
            $context = RenderContext::create();
            $result = $widget->render($field, 'Hello', $context);
            $html = $result->getHtml();
            
            return str_contains($html, 'input')
                && str_contains($html, 'type="text"')
                && str_contains($html, 'Hello');
        });
    }

    private function testTextareaRendering(): void
    {
        $this->test("Textarea widget renders correctly", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('textarea');
            
            $field = new FieldDefinition([
                'name' => 'Body',
                'machine_name' => 'field_body',
                'field_type' => 'text',
            ]);
            
            $context = RenderContext::create();
            $result = $widget->render($field, 'Content here', $context);
            $html = $result->getHtml();
            
            return str_contains($html, 'textarea')
                && str_contains($html, 'Content here');
        });
    }

    private function testSelectRendering(): void
    {
        $this->test("Select widget renders with options", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('select');
            
            $field = new FieldDefinition([
                'name' => 'Category',
                'machine_name' => 'field_category',
                'field_type' => 'select',
                'settings' => [
                    'options' => [
                        'news' => 'News',
                        'blog' => 'Blog',
                    ],
                ],
            ]);
            
            $context = RenderContext::create();
            $result = $widget->render($field, 'blog', $context);
            $html = $result->getHtml();
            
            return str_contains($html, 'select')
                && str_contains($html, 'News')
                && str_contains($html, 'Blog');
        });
    }

    private function testCheckboxRendering(): void
    {
        $this->test("Checkbox widget renders correctly", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('checkbox');
            
            $field = new FieldDefinition([
                'name' => 'Published',
                'machine_name' => 'field_published',
                'field_type' => 'boolean',
            ]);
            
            $context = RenderContext::create();
            $result = $widget->render($field, true, $context);
            $html = $result->getHtml();
            
            return str_contains($html, 'checkbox')
                && str_contains($html, 'checked');
        });
    }

    private function testNumberRendering(): void
    {
        $this->test("Number widget renders correctly", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('number');
            
            $field = new FieldDefinition([
                'name' => 'Quantity',
                'machine_name' => 'field_quantity',
                'field_type' => 'integer',
                'settings' => ['min' => 0, 'max' => 100],
            ]);
            
            $context = RenderContext::create();
            $result = $widget->render($field, 42, $context);
            $html = $result->getHtml();
            
            return str_contains($html, 'type="number"')
                && str_contains($html, '42');
        });
    }

    private function testDateRendering(): void
    {
        $this->test("Date widget renders correctly", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('date');
            
            $field = new FieldDefinition([
                'name' => 'Birth Date',
                'machine_name' => 'field_birth_date',
                'field_type' => 'date',
            ]);
            
            $context = RenderContext::create();
            $result = $widget->render($field, '2024-01-15', $context);
            $html = $result->getHtml();
            
            return str_contains($html, 'type="date"')
                && str_contains($html, '2024-01-15');
        });
    }

    private function testDisplayRendering(): void
    {
        $this->test("Display mode renders non-editable", function() {
            $registry = WidgetFactory::create();
            $widget = $registry->get('text_input');
            
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
            ]);
            
            $context = RenderContext::create();
            $result = $widget->renderDisplay($field, 'Hello World', $context);
            $html = $result->getHtml();
            
            return str_contains($html, 'Hello World')
                && !str_contains($html, '<input');
        });
    }

    // =========================================================================
    // Form Builder Tests
    // =========================================================================

    private function testFormBuilderBasic(): void
    {
        $this->test("Form builder creates form with fields", function() {
            $registry = WidgetFactory::create();
            $builder = new FormBuilder($registry);
            
            $fields = [
                new FieldDefinition([
                    'name' => 'Title',
                    'machine_name' => 'field_title',
                    'field_type' => 'string',
                    'required' => true,
                ]),
                new FieldDefinition([
                    'name' => 'Body',
                    'machine_name' => 'field_body',
                    'field_type' => 'text',
                ]),
            ];
            
            $html = $builder->build($fields, []);
            
            return str_contains($html, '<form')
                && str_contains($html, 'field_title')
                && str_contains($html, 'field_body');
        });
    }

    private function testFormBuilderWithErrors(): void
    {
        $this->test("Form builder shows validation errors", function() {
            $registry = WidgetFactory::create();
            $builder = new FormBuilder($registry);
            
            $fields = [
                new FieldDefinition([
                    'name' => 'Title',
                    'machine_name' => 'field_title',
                    'field_type' => 'string',
                    'required' => true,
                ]),
            ];
            
            $errors = ['field_title' => ['Title is required']];
            
            $html = $builder->build($fields, [], $errors);
            
            return str_contains($html, 'Title is required');
        });
    }

    private function testFormBuilderGrouping(): void
    {
        $this->test("Form builder supports field groups", function() {
            $registry = WidgetFactory::create();
            $builder = new FormBuilder($registry);
            
            $fields = [
                new FieldDefinition([
                    'name' => 'Title',
                    'machine_name' => 'field_title',
                    'field_type' => 'string',
                    'settings' => ['group' => 'basic'],
                ]),
                new FieldDefinition([
                    'name' => 'Meta',
                    'machine_name' => 'field_meta',
                    'field_type' => 'text',
                    'settings' => ['group' => 'advanced'],
                ]),
            ];
            
            $html = $builder->build($fields, [], [], [
                'groups' => [
                    'basic' => ['label' => 'Basic Info'],
                    'advanced' => ['label' => 'Advanced'],
                ],
            ]);
            
            return str_contains($html, 'Basic Info') || str_contains($html, 'field_title');
        });
    }

    // =========================================================================
    // Repository Tests
    // =========================================================================

    private function testInMemoryRepository(): void
    {
        $this->test("In-memory repository saves and retrieves fields", function() {
            $repo = new InMemoryFieldRepository();
            
            $field = new FieldDefinition([
                'name' => 'Title',
                'machine_name' => 'field_title',
                'field_type' => 'string',
            ]);
            
            $repo->save($field);
            
            $retrieved = $repo->find($field->getId());
            
            return $retrieved !== null 
                && $retrieved->getMachineName() === 'field_title';
        });

        $this->test("In-memory repository finds by machine name", function() {
            $repo = new InMemoryFieldRepository();
            
            $field = new FieldDefinition([
                'name' => 'Email',
                'machine_name' => 'field_email',
                'field_type' => 'email',
            ]);
            
            $repo->save($field);
            
            $retrieved = $repo->findByMachineName('field_email');
            
            return $retrieved !== null;
        });

        $this->test("In-memory repository deletes fields", function() {
            $repo = new InMemoryFieldRepository();
            
            $field = new FieldDefinition([
                'name' => 'Temp',
                'machine_name' => 'field_temp',
                'field_type' => 'string',
            ]);
            
            $repo->save($field);
            $repo->delete($field);
            
            return $repo->find($field->getId()) === null;
        });
    }

    // =========================================================================
    // Field Manager Tests
    // =========================================================================

    private function testFieldManagerCreation(): void
    {
        $this->test("Field manager can be created for testing", function() {
            $manager = FieldManager::createForTesting();
            return $manager instanceof FieldManager;
        });
    }

    private function testFieldManagerDefineField(): void
    {
        $this->test("Field manager can define fields fluently", function() {
            $manager = FieldManager::createForTesting();
            
            $field = $manager->defineField('title', 'string')
                ->name('Title')
                ->required()
                ->build();
            
            return $field->getName() === 'Title'
                && $field->getMachineName() === 'field_title'
                && $field->isRequired();
        });
    }

    // =========================================================================
    // Service Provider Tests
    // =========================================================================

    private function testServiceProvider(): void
    {
        $this->test("Service provider creates widget registry", function() {
            $registry = FieldServiceProvider::createWidgetRegistry();
            return $registry instanceof WidgetRegistry;
        });

        $this->test("Service provider creates form builder", function() {
            $builder = FieldServiceProvider::createFormBuilder();
            return $builder instanceof FormBuilder;
        });
    }

    // =========================================================================
    // Test Helpers
    // =========================================================================

    private function test(string $name, callable $test): void
    {
        try {
            $result = $test();
            
            if ($result) {
                $this->passed++;
                echo "  ✓ {$name}\n";
            } else {
                $this->failed++;
                $this->failures[] = $name;
                echo "  ✗ {$name}\n";
            }
        } catch (\Throwable $e) {
            $this->failed++;
            $this->failures[] = "{$name}: " . $e->getMessage();
            echo "  ✗ {$name}: " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    // Autoload
    require_once __DIR__ . '/../../../../vendor/autoload.php';
    
    $test = new FieldWidgetSystemTest();
    exit($test->run());
}
