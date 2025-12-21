<?php

declare(strict_types=1);

namespace App\Cms\Fields;

/**
 * FieldType - Supported field types for dynamic content
 *
 * These field types can be used for:
 * - Block type fields
 * - Content type fields
 * - Taxonomy term fields
 */
enum FieldType: string
{
    // Text fields
    case STRING = 'string';
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case HTML = 'html';
    case MARKDOWN = 'markdown';

    // Numeric fields
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case DECIMAL = 'decimal';

    // Boolean
    case BOOLEAN = 'boolean';

    // Date/Time
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIME = 'time';

    // Selection
    case SELECT = 'select';
    case RADIO = 'radio';
    case CHECKBOX = 'checkbox';
    case MULTISELECT = 'multiselect';

    // Media
    case IMAGE = 'image';
    case FILE = 'file';
    case GALLERY = 'gallery';
    case VIDEO = 'video';

    // References
    case ENTITY_REFERENCE = 'entity_reference';
    case TAXONOMY_REFERENCE = 'taxonomy_reference';
    case USER_REFERENCE = 'user_reference';
    case BLOCK_REFERENCE = 'block_reference';

    // Special
    case EMAIL = 'email';
    case URL = 'url';
    case PHONE = 'phone';
    case COLOR = 'color';
    case SLUG = 'slug';
    case JSON = 'json';
    case CODE = 'code';

    // Layout
    case LINK = 'link';
    case ADDRESS = 'address';
    case GEOLOCATION = 'geolocation';

    /**
     * Get SQL column type for this field type
     */
    public function getSqlType(): string
    {
        return match ($this) {
            self::STRING, self::EMAIL, self::URL, self::PHONE,
            self::COLOR, self::SLUG => 'VARCHAR(255)',

            self::TEXT, self::TEXTAREA, self::HTML,
            self::MARKDOWN, self::CODE => 'LONGTEXT',

            self::INTEGER, self::ENTITY_REFERENCE, self::TAXONOMY_REFERENCE,
            self::USER_REFERENCE, self::BLOCK_REFERENCE => 'INT',

            self::FLOAT => 'FLOAT',
            self::DECIMAL => 'DECIMAL(15,4)',

            self::BOOLEAN => 'TINYINT(1)',

            self::DATE => 'DATE',
            self::DATETIME => 'DATETIME',
            self::TIME => 'TIME',

            self::SELECT, self::RADIO => 'VARCHAR(100)',
            self::CHECKBOX, self::MULTISELECT => 'JSON',

            self::IMAGE, self::FILE, self::VIDEO => 'INT', // Media ID reference
            self::GALLERY => 'JSON', // Array of media IDs

            self::JSON, self::LINK, self::ADDRESS, self::GEOLOCATION => 'JSON',
        };
    }

    /**
     * Get default widget for this field type
     */
    public function getDefaultWidget(): string
    {
        return match ($this) {
            self::STRING, self::EMAIL, self::URL,
            self::PHONE, self::SLUG => 'textfield',

            self::TEXT, self::TEXTAREA => 'textarea',
            self::HTML => 'wysiwyg',
            self::MARKDOWN => 'markdown_editor',
            self::CODE => 'code_editor',

            self::INTEGER, self::FLOAT, self::DECIMAL => 'number',

            self::BOOLEAN => 'checkbox',

            self::DATE => 'datepicker',
            self::DATETIME => 'datetimepicker',
            self::TIME => 'timepicker',

            self::SELECT => 'select',
            self::RADIO => 'radios',
            self::CHECKBOX => 'checkboxes',
            self::MULTISELECT => 'multiselect',

            self::IMAGE => 'image_upload',
            self::FILE => 'file_upload',
            self::GALLERY => 'gallery_upload',
            self::VIDEO => 'video_upload',

            self::ENTITY_REFERENCE => 'entity_autocomplete',
            self::TAXONOMY_REFERENCE => 'taxonomy_select',
            self::USER_REFERENCE => 'user_autocomplete',
            self::BLOCK_REFERENCE => 'block_select',

            self::COLOR => 'colorpicker',
            self::JSON => 'json_editor',
            self::LINK => 'link_field',
            self::ADDRESS => 'address_field',
            self::GEOLOCATION => 'map_field',
        };
    }

    /**
     * Check if this field type supports multiple values
     */
    public function supportsMultiple(): bool
    {
        return match ($this) {
            self::GALLERY, self::MULTISELECT, self::CHECKBOX => true,
            default => false,
        };
    }

    /**
     * Get PHP type for this field
     */
    public function getPhpType(): string
    {
        return match ($this) {
            self::STRING, self::TEXT, self::TEXTAREA, self::HTML,
            self::MARKDOWN, self::CODE, self::EMAIL, self::URL,
            self::PHONE, self::COLOR, self::SLUG, self::SELECT,
            self::RADIO => 'string',

            self::INTEGER, self::ENTITY_REFERENCE, self::TAXONOMY_REFERENCE,
            self::USER_REFERENCE, self::BLOCK_REFERENCE, self::IMAGE,
            self::FILE, self::VIDEO => 'int',

            self::FLOAT, self::DECIMAL => 'float',

            self::BOOLEAN => 'bool',

            self::DATE, self::DATETIME, self::TIME => 'DateTimeInterface',

            self::CHECKBOX, self::MULTISELECT, self::GALLERY,
            self::JSON, self::LINK, self::ADDRESS, self::GEOLOCATION => 'array',
        };
    }

    /**
     * Get label for this field type
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::STRING => 'Text (single line)',
            self::TEXT => 'Text (plain)',
            self::TEXTAREA => 'Text (multiline)',
            self::HTML => 'HTML (formatted)',
            self::MARKDOWN => 'Markdown',
            self::INTEGER => 'Integer',
            self::FLOAT => 'Decimal',
            self::DECIMAL => 'Decimal (precise)',
            self::BOOLEAN => 'Boolean (Yes/No)',
            self::DATE => 'Date',
            self::DATETIME => 'Date and Time',
            self::TIME => 'Time',
            self::SELECT => 'Select list',
            self::RADIO => 'Radio buttons',
            self::CHECKBOX => 'Checkboxes',
            self::MULTISELECT => 'Multi-select',
            self::IMAGE => 'Image',
            self::FILE => 'File',
            self::GALLERY => 'Image Gallery',
            self::VIDEO => 'Video',
            self::ENTITY_REFERENCE => 'Content Reference',
            self::TAXONOMY_REFERENCE => 'Taxonomy Term',
            self::USER_REFERENCE => 'User Reference',
            self::BLOCK_REFERENCE => 'Block Reference',
            self::EMAIL => 'Email',
            self::URL => 'URL',
            self::PHONE => 'Phone',
            self::COLOR => 'Color',
            self::SLUG => 'URL Slug',
            self::JSON => 'JSON',
            self::CODE => 'Code',
            self::LINK => 'Link',
            self::ADDRESS => 'Address',
            self::GEOLOCATION => 'Geolocation',
        };
    }

    /**
     * Get category for this field type
     */
    public function getCategory(): string
    {
        $grouped = self::getGrouped();
        foreach ($grouped as $category => $types) {
            if (in_array($this, $types, true)) {
                return $category;
            }
        }
        return 'Other';
    }

    /**
     * Get description for this field type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::STRING => 'A simple single-line text field',
            self::TEXT => 'A multi-line text area',
            self::HTML => 'Rich text editor with HTML support',
            self::MARKDOWN => 'Markdown editor with preview',
            self::INTEGER => 'Whole number input',
            self::FLOAT, self::DECIMAL => 'Decimal number input',
            self::BOOLEAN => 'True/False toggle or checkbox',
            self::DATE => 'Date picker',
            self::DATETIME => 'Date and time picker',
            self::TIME => 'Time picker',
            self::SELECT => 'Dropdown select list',
            self::RADIO => 'Radio button group',
            self::CHECKBOX => 'Checkboxes for multiple selections',
            self::MULTISELECT => 'Multi-select dropdown',
            self::IMAGE => 'Image upload',
            self::FILE => 'File upload',
            self::GALLERY => 'Multiple image gallery',
            self::VIDEO => 'Video upload or embed',
            self::ENTITY_REFERENCE => 'Link to other content items',
            self::TAXONOMY_REFERENCE => 'Tag content with taxonomy terms',
            self::USER_REFERENCE => 'Link to a user account',
            self::EMAIL => 'Email address with validation',
            self::URL => 'Website URL with validation',
            self::PHONE => 'Phone number',
            self::COLOR => 'Color picker',
            self::SLUG => 'URL-friendly identifier',
            self::JSON => 'Raw JSON data editor',
            self::CODE => 'Code editor with syntax highlighting',
            self::LINK => 'Link with title and target',
            self::ADDRESS => 'Physical address fields',
            self::GEOLOCATION => 'Map coordinates',
            default => '',
        };
    }

    /**
     * Get field types grouped by category
     */
    public static function getGrouped(): array
    {
        return [
            'Text' => [
                self::STRING,
                self::TEXT,
                self::TEXTAREA,
                self::HTML,
                self::MARKDOWN,
            ],
            'Number' => [
                self::INTEGER,
                self::FLOAT,
                self::DECIMAL,
            ],
            'Date/Time' => [
                self::DATE,
                self::DATETIME,
                self::TIME,
            ],
            'Selection' => [
                self::BOOLEAN,
                self::SELECT,
                self::RADIO,
                self::CHECKBOX,
                self::MULTISELECT,
            ],
            'Media' => [
                self::IMAGE,
                self::FILE,
                self::GALLERY,
                self::VIDEO,
            ],
            'Reference' => [
                self::ENTITY_REFERENCE,
                self::TAXONOMY_REFERENCE,
                self::USER_REFERENCE,
                self::BLOCK_REFERENCE,
            ],
            'Special' => [
                self::EMAIL,
                self::URL,
                self::PHONE,
                self::COLOR,
                self::SLUG,
                self::CODE,
                self::JSON,
                self::LINK,
                self::ADDRESS,
                self::GEOLOCATION,
            ],
        ];
    }
}
