<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

use App\Cms\Fields\Validation\FieldValidator;

// Text widgets
use App\Cms\Fields\Widget\Text\TextInputWidget;
use App\Cms\Fields\Widget\Text\TextareaWidget;
use App\Cms\Fields\Widget\Text\EmailWidget;
use App\Cms\Fields\Widget\Text\UrlWidget;
use App\Cms\Fields\Widget\Text\PhoneWidget;

// Selection widgets
use App\Cms\Fields\Widget\Selection\SelectWidget;
use App\Cms\Fields\Widget\Selection\CheckboxWidget;
use App\Cms\Fields\Widget\Selection\CheckboxesWidget;
use App\Cms\Fields\Widget\Selection\RadioWidget;
use App\Cms\Fields\Widget\Selection\SwitchWidget;

// Number widgets
use App\Cms\Fields\Widget\Number\NumberWidget;
use App\Cms\Fields\Widget\Number\DecimalWidget;
use App\Cms\Fields\Widget\Number\RangeWidget;

// Date widgets
use App\Cms\Fields\Widget\Date\DateWidget;
use App\Cms\Fields\Widget\Date\DateTimeWidget;
use App\Cms\Fields\Widget\Date\TimeWidget;

// Special widgets
use App\Cms\Fields\Widget\Special\ColorWidget;
use App\Cms\Fields\Widget\Special\HiddenWidget;
use App\Cms\Fields\Widget\Special\SlugWidget;
use App\Cms\Fields\Widget\Special\JsonWidget;
use App\Cms\Fields\Widget\Special\PasswordWidget;

// Media widgets
use App\Cms\Fields\Widget\Media\ImageWidget;
use App\Cms\Fields\Widget\Media\FileWidget;
use App\Cms\Fields\Widget\Media\GalleryWidget;
use App\Cms\Fields\Widget\Media\VideoWidget;

// Reference widgets
use App\Cms\Fields\Widget\Reference\EntityReferenceWidget;
use App\Cms\Fields\Widget\Reference\TaxonomyWidget;
use App\Cms\Fields\Widget\Reference\UserReferenceWidget;

// Composite widgets
use App\Cms\Fields\Widget\Composite\RepeaterWidget;

// Rich Text widgets
use App\Cms\Fields\Widget\RichText\WysiwygWidget;
use App\Cms\Fields\Widget\RichText\MarkdownWidget;
use App\Cms\Fields\Widget\RichText\CodeWidget;

// Location widgets
use App\Cms\Fields\Widget\Location\AddressWidget;
use App\Cms\Fields\Widget\Location\LinkWidget;
use App\Cms\Fields\Widget\Location\GeolocationWidget;

/**
 * WidgetFactory - Creates and configures a WidgetRegistry with default widgets
 * 
 * This factory centralizes widget creation and registration, making it easy
 * to add or remove default widgets and set up type defaults.
 */
final class WidgetFactory
{
    /**
     * Create a fully configured WidgetRegistry
     */
    public static function create(?FieldValidator $validator = null): WidgetRegistry
    {
        $validator = $validator ?? new FieldValidator();
        $registry = new WidgetRegistry($validator);
        
        self::registerCoreWidgets($registry);
        self::setTypeDefaults($registry);
        
        return $registry;
    }

    /**
     * Register all core widgets
     */
    public static function registerCoreWidgets(WidgetRegistry $registry): void
    {
        // Text widgets
        $registry->registerMany([
            new TextInputWidget(),
            new TextareaWidget(),
            new EmailWidget(),
            new UrlWidget(),
            new PhoneWidget(),
        ]);

        // Selection widgets
        $registry->registerMany([
            new SelectWidget(),
            new CheckboxWidget(),
            new CheckboxesWidget(),
            new RadioWidget(),
            new SwitchWidget(),
        ]);

        // Number widgets
        $registry->registerMany([
            new NumberWidget(),
            new DecimalWidget(),
            new RangeWidget(),
        ]);

        // Date widgets
        $registry->registerMany([
            new DateWidget(),
            new DateTimeWidget(),
            new TimeWidget(),
        ]);

        // Special widgets
        $registry->registerMany([
            new ColorWidget(),
            new HiddenWidget(),
            new SlugWidget(),
            new JsonWidget(),
            new PasswordWidget(),
        ]);

        // Media widgets
        $registry->registerMany([
            new ImageWidget(),
            new FileWidget(),
            new GalleryWidget(),
            new VideoWidget(),
        ]);

        // Reference widgets
        $registry->registerMany([
            new EntityReferenceWidget(),
            new TaxonomyWidget(),
            new UserReferenceWidget(),
        ]);

        // Composite widgets
        $repeater = new RepeaterWidget();
        $repeater->setWidgetRegistry($registry);
        $registry->register($repeater);

        // Rich Text widgets
        $registry->registerMany([
            new WysiwygWidget(),
            new MarkdownWidget(),
            new CodeWidget(),
        ]);

        // Location widgets
        $registry->registerMany([
            new AddressWidget(),
            new LinkWidget(),
            new GeolocationWidget(),
        ]);
    }

    /**
     * Set default widget for each field type
     */
    public static function setTypeDefaults(WidgetRegistry $registry): void
    {
        $defaults = [
            // Text types
            'string' => 'text_input',
            'text' => 'textarea',
            'textarea' => 'textarea',
            'email' => 'email',
            'url' => 'url',
            'phone' => 'phone',
            'password' => 'password',
            'slug' => 'slug',
            
            // Selection types
            'select' => 'select',
            'boolean' => 'switch',
            'checkbox' => 'checkboxes',
            'multiselect' => 'checkboxes',
            'radio' => 'radio',
            
            // Number types
            'integer' => 'number',
            'float' => 'number',
            'decimal' => 'decimal',
            
            // Date types
            'date' => 'date',
            'datetime' => 'datetime',
            'time' => 'time',
            
            // Special types
            'color' => 'color',
            'hidden' => 'hidden',
            'json' => 'json',
            'array' => 'json',
            'object' => 'json',
            
            // Media types
            'image' => 'image',
            'file' => 'file',
            'document' => 'file',
            'gallery' => 'gallery',
            'images' => 'gallery',
            'video' => 'video',
            'media' => 'image',
            
            // Reference types
            'entity_reference' => 'entity_reference',
            'reference' => 'entity_reference',
            'taxonomy_reference' => 'taxonomy',
            'taxonomy' => 'taxonomy',
            'tags' => 'taxonomy',
            'user_reference' => 'user_reference',
            'user' => 'user_reference',
            
            // Composite types
            'repeater' => 'repeater',
            
            // Rich Text types
            'html' => 'wysiwyg',
            'wysiwyg' => 'wysiwyg',
            'richtext' => 'wysiwyg',
            'markdown' => 'markdown',
            'code' => 'code',
            
            // Location types
            'address' => 'address',
            'link' => 'link',
            'geolocation' => 'geolocation',
            'location' => 'geolocation',
            'coordinates' => 'geolocation',
        ];

        foreach ($defaults as $type => $widgetId) {
            if ($registry->has($widgetId)) {
                $registry->setTypeDefault($type, $widgetId);
            }
        }
    }

    /**
     * Discover and register widgets from a module path
     * 
     * Scans the module's Widgets directory for widget classes
     */
    public static function registerFromModule(WidgetRegistry $registry, string $modulePath): void
    {
        $widgetsPath = $modulePath . '/Widgets';
        
        if (!is_dir($widgetsPath)) {
            return;
        }

        foreach (glob($widgetsPath . '/*Widget.php') as $file) {
            $className = self::extractClassName($file);
            
            if ($className && class_exists($className)) {
                $reflection = new \ReflectionClass($className);
                
                if ($reflection->implementsInterface(WidgetInterface::class) && !$reflection->isAbstract()) {
                    $registry->register($reflection->newInstance());
                }
            }
        }
    }

    /**
     * Extract fully qualified class name from a PHP file
     */
    private static function extractClassName(string $file): ?string
    {
        $content = file_get_contents($file);
        
        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }
        
        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
            return $namespace ? $namespace . '\\' . $className : $className;
        }
        
        return null;
    }
}
