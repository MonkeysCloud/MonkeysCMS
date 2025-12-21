<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * Setting Entity - Key-value site settings
 */
#[ContentType(
    tableName: 'settings',
    label: 'Setting',
    description: 'Site configuration settings',
    icon: '⚙️',
    revisionable: false,
    publishable: false
)]
class Setting extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'Key', required: true, length: 255, unique: true, indexed: true)]
    public string $key = '';

    #[Field(type: 'text', label: 'Value', required: false)]
    public ?string $value = null;

    #[Field(
        type: 'string',
        label: 'Type',
        required: true,
        length: 50,
        default: 'string',
        widget: 'select',
        options: [
            'string' => 'String',
            'text' => 'Text',
            'int' => 'Integer',
            'float' => 'Float',
            'bool' => 'Boolean',
            'json' => 'JSON',
            'array' => 'Array'
        ]
    )]
    public string $type = 'string';

    #[Field(type: 'string', label: 'Group', required: true, length: 100, indexed: true, default: 'general')]
    public string $group = 'general';

    #[Field(type: 'string', label: 'Label', required: false, length: 255)]
    public ?string $label = null;

    #[Field(type: 'text', label: 'Description', required: false)]
    public ?string $description = null;

    #[Field(type: 'boolean', label: 'Is System', default: false)]
    public bool $is_system = false;

    #[Field(type: 'boolean', label: 'Autoload', default: true)]
    public bool $autoload = true;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;

    /**
     * Get typed value
     */
    public function getTypedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'int' => (int) $this->value,
            'float' => (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }

    /**
     * Set typed value
     */
    public function setTypedValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'bool' => $value ? '1' : '0',
            'json', 'array' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Get default settings
     */
    public static function getDefaults(): array
    {
        return [
            // Site settings
            ['key' => 'site.name', 'value' => 'MonkeysCMS', 'type' => 'string', 'group' => 'site', 'label' => 'Site Name', 'is_system' => true],
            ['key' => 'site.tagline', 'value' => 'A modern CMS', 'type' => 'string', 'group' => 'site', 'label' => 'Tagline'],
            ['key' => 'site.email', 'value' => 'admin@example.com', 'type' => 'string', 'group' => 'site', 'label' => 'Site Email'],
            ['key' => 'site.logo', 'value' => null, 'type' => 'string', 'group' => 'site', 'label' => 'Logo URL'],
            ['key' => 'site.favicon', 'value' => null, 'type' => 'string', 'group' => 'site', 'label' => 'Favicon URL'],
            ['key' => 'site.timezone', 'value' => 'UTC', 'type' => 'string', 'group' => 'site', 'label' => 'Timezone'],
            ['key' => 'site.locale', 'value' => 'en', 'type' => 'string', 'group' => 'site', 'label' => 'Default Locale'],
            ['key' => 'site.date_format', 'value' => 'Y-m-d', 'type' => 'string', 'group' => 'site', 'label' => 'Date Format'],
            ['key' => 'site.time_format', 'value' => 'H:i', 'type' => 'string', 'group' => 'site', 'label' => 'Time Format'],

            // Content settings
            ['key' => 'content.default_status', 'value' => 'draft', 'type' => 'string', 'group' => 'content', 'label' => 'Default Content Status'],
            ['key' => 'content.revisions_enabled', 'value' => '1', 'type' => 'bool', 'group' => 'content', 'label' => 'Enable Revisions'],
            ['key' => 'content.revision_limit', 'value' => '10', 'type' => 'int', 'group' => 'content', 'label' => 'Max Revisions'],

            // User settings
            ['key' => 'users.registration_enabled', 'value' => '0', 'type' => 'bool', 'group' => 'users', 'label' => 'Allow Registration'],
            ['key' => 'users.email_verification', 'value' => '1', 'type' => 'bool', 'group' => 'users', 'label' => 'Require Email Verification'],
            ['key' => 'users.default_role', 'value' => 'authenticated', 'type' => 'string', 'group' => 'users', 'label' => 'Default Role'],

            // Media settings
            ['key' => 'media.max_upload_size', 'value' => '10485760', 'type' => 'int', 'group' => 'media', 'label' => 'Max Upload Size (bytes)'],
            ['key' => 'media.allowed_types', 'value' => '["image/jpeg","image/png","image/gif","image/webp","application/pdf"]', 'type' => 'json', 'group' => 'media', 'label' => 'Allowed MIME Types'],
            ['key' => 'media.image_quality', 'value' => '85', 'type' => 'int', 'group' => 'media', 'label' => 'Image Quality'],

            // SEO settings
            ['key' => 'seo.meta_title_suffix', 'value' => ' | MonkeysCMS', 'type' => 'string', 'group' => 'seo', 'label' => 'Meta Title Suffix'],
            ['key' => 'seo.meta_description', 'value' => '', 'type' => 'text', 'group' => 'seo', 'label' => 'Default Meta Description'],
            ['key' => 'seo.robots_txt', 'value' => "User-agent: *\nAllow: /", 'type' => 'text', 'group' => 'seo', 'label' => 'robots.txt'],

            // API settings
            ['key' => 'api.rate_limit', 'value' => '60', 'type' => 'int', 'group' => 'api', 'label' => 'API Rate Limit (per minute)'],
            ['key' => 'api.pagination_limit', 'value' => '100', 'type' => 'int', 'group' => 'api', 'label' => 'Max Items Per Page'],
        ];
    }
}
