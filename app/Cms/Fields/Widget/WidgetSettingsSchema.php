<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

/**
 * WidgetSettingsSchema - Schema for widget settings
 */
final class WidgetSettingsSchema
{
    /** @var WidgetSetting[] */
    private array $settings;

    private function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public static function create(): self
    {
        return new self([]);
    }

    public static function fromArray(array $schema): self
    {
        $settings = [];
        foreach ($schema as $name => $definition) {
            $settings[$name] = WidgetSetting::fromArray($name, $definition);
        }
        return new self($settings);
    }

    public function add(WidgetSetting $setting): self
    {
        $settings = $this->settings;
        $settings[$setting->getName()] = $setting;
        return new self($settings);
    }

    public function string(string $name, string $label, ?string $default = null): self
    {
        return $this->add(WidgetSetting::string($name, $label, $default));
    }

    public function integer(string $name, string $label, int $default = 0): self
    {
        return $this->add(WidgetSetting::integer($name, $label, $default));
    }

    public function boolean(string $name, string $label, bool $default = false): self
    {
        return $this->add(WidgetSetting::boolean($name, $label, $default));
    }

    public function select(string $name, string $label, array $options, ?string $default = null): self
    {
        return $this->add(WidgetSetting::select($name, $label, $options, $default));
    }

    public function get(string $name): ?WidgetSetting
    {
        return $this->settings[$name] ?? null;
    }

    public function all(): array
    {
        return $this->settings;
    }

    public function toArray(): array
    {
        return array_map(fn($s) => $s->toArray(), $this->settings);
    }
}
