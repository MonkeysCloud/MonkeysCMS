<?php

declare(strict_types=1);

namespace App\Cms\Field;

/**
 * FieldType — Enum of all supported field types in MonkeysCMS.
 *
 * Each type maps to a SQL column type, a default widget, and
 * serialization/deserialization behavior.
 */
enum FieldType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case HTML = 'html';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIME = 'time';
    case EMAIL = 'email';
    case URL = 'url';
    case PHONE = 'phone';
    case COLOR = 'color';
    case JSON = 'json';
    case SELECT = 'select';
    case MULTISELECT = 'multiselect';
    case CHECKBOX = 'checkbox';
    case IMAGE = 'image';
    case FILE = 'file';
    case VIDEO = 'video';
    case GALLERY = 'gallery';
    case LINK = 'link';
    case ADDRESS = 'address';
    case GEOLOCATION = 'geolocation';
    case ENTITY_REFERENCE = 'entity_reference';
    case TAXONOMY_REFERENCE = 'taxonomy';
    case USER_REFERENCE = 'user_reference';
    case BLOCK_REFERENCE = 'block_reference';
    case CODE = 'code';
    case MARKDOWN = 'markdown';
    case SLUG = 'slug';
    case PASSWORD = 'password';

    /**
     * SQL column type for this field
     */
    public function getSqlType(): string
    {
        return match ($this) {
            self::STRING, self::EMAIL, self::URL, self::PHONE,
            self::COLOR, self::SELECT, self::SLUG, self::PASSWORD => 'VARCHAR(255)',
            self::TEXT, self::HTML, self::MARKDOWN, self::CODE => 'LONGTEXT',
            self::INTEGER, self::ENTITY_REFERENCE, self::TAXONOMY_REFERENCE,
            self::USER_REFERENCE, self::BLOCK_REFERENCE, self::IMAGE,
            self::FILE, self::VIDEO => 'BIGINT',
            self::FLOAT => 'DOUBLE',
            self::DECIMAL => 'DECIMAL(10,2)',
            self::BOOLEAN => 'TINYINT(1)',
            self::DATE => 'DATE',
            self::DATETIME => 'DATETIME',
            self::TIME => 'TIME',
            self::JSON, self::MULTISELECT, self::CHECKBOX, self::GALLERY,
            self::LINK, self::ADDRESS, self::GEOLOCATION => 'JSON',
        };
    }

    /**
     * Default widget for this field type
     */
    public function getDefaultWidget(): string
    {
        return match ($this) {
            self::STRING, self::EMAIL, self::URL, self::PHONE,
            self::COLOR, self::SLUG => 'text_input',
            self::TEXT => 'textarea',
            self::HTML => 'wysiwyg',
            self::MARKDOWN => 'markdown_editor',
            self::CODE => 'code_editor',
            self::INTEGER, self::FLOAT, self::DECIMAL => 'number_input',
            self::BOOLEAN => 'toggle',
            self::DATE => 'date_picker',
            self::DATETIME => 'datetime_picker',
            self::TIME => 'time_picker',
            self::SELECT => 'select',
            self::MULTISELECT => 'multiselect',
            self::CHECKBOX => 'checkboxes',
            self::IMAGE => 'media_picker',
            self::FILE => 'file_upload',
            self::VIDEO => 'video_embed',
            self::GALLERY => 'gallery_picker',
            self::LINK => 'link_input',
            self::ADDRESS => 'address_input',
            self::GEOLOCATION => 'map_picker',
            self::JSON => 'json_editor',
            self::ENTITY_REFERENCE => 'entity_autocomplete',
            self::TAXONOMY_REFERENCE => 'taxonomy_select',
            self::USER_REFERENCE => 'user_autocomplete',
            self::BLOCK_REFERENCE => 'block_select',
            self::PASSWORD => 'password_input',
        };
    }

    /**
     * Whether this field type supports multiple values
     */
    public function supportsMultiple(): bool
    {
        return match ($this) {
            self::MULTISELECT, self::CHECKBOX, self::GALLERY,
            self::TAXONOMY_REFERENCE => true,
            default => false,
        };
    }

    /**
     * Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::STRING => 'Text (single line)',
            self::TEXT => 'Text (multi-line)',
            self::HTML => 'Rich Text (HTML)',
            self::INTEGER => 'Integer',
            self::FLOAT => 'Float',
            self::DECIMAL => 'Decimal',
            self::BOOLEAN => 'Boolean',
            self::DATE => 'Date',
            self::DATETIME => 'Date & Time',
            self::TIME => 'Time',
            self::EMAIL => 'Email',
            self::URL => 'URL',
            self::PHONE => 'Phone',
            self::COLOR => 'Color',
            self::JSON => 'JSON',
            self::SELECT => 'Select',
            self::MULTISELECT => 'Multi-select',
            self::CHECKBOX => 'Checkboxes',
            self::IMAGE => 'Image',
            self::FILE => 'File',
            self::VIDEO => 'Video',
            self::GALLERY => 'Gallery',
            self::LINK => 'Link',
            self::ADDRESS => 'Address',
            self::GEOLOCATION => 'Geolocation',
            self::ENTITY_REFERENCE => 'Content Reference',
            self::TAXONOMY_REFERENCE => 'Taxonomy Reference',
            self::USER_REFERENCE => 'User Reference',
            self::BLOCK_REFERENCE => 'Block Reference',
            self::CODE => 'Code',
            self::MARKDOWN => 'Markdown',
            self::SLUG => 'URL Slug',
            self::PASSWORD => 'Password',
        };
    }
}
