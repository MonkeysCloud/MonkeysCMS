<?php

declare(strict_types=1);

namespace App\Cms\Fields\Rendering;

/**
 * RenderContext - Immutable value object containing rendering context
 * 
 * Encapsulates all context needed for field rendering including
 * form identification, errors, display states, and customization options.
 */
final class RenderContext
{
    private function __construct(
        private readonly string $formId,
        private readonly string $namePrefix,
        private readonly ?int $index,
        private readonly bool $disabled,
        private readonly bool $readonly,
        private readonly bool $hideLabel,
        private readonly bool $hideHelp,
        private readonly array $errors,
        private readonly array $customData,
    ) {}

    /**
     * Create a new RenderContext with default values
     */
    public static function create(array $options = []): self
    {
        return new self(
            formId: $options['form_id'] ?? 'form',
            namePrefix: $options['name_prefix'] ?? '',
            index: $options['index'] ?? null,
            disabled: (bool) ($options['disabled'] ?? false),
            readonly: (bool) ($options['readonly'] ?? false),
            hideLabel: (bool) ($options['hide_label'] ?? false),
            hideHelp: (bool) ($options['hide_help'] ?? false),
            errors: $options['errors'] ?? [],
            customData: $options['custom'] ?? [],
        );
    }

    /**
     * Create context for displaying (non-editable) fields
     */
    public static function forDisplay(array $options = []): self
    {
        return self::create(array_merge($options, [
            'readonly' => true,
            'hide_help' => true,
        ]));
    }

    // Getters
    public function getFormId(): string
    {
        return $this->formId;
    }

    public function getNamePrefix(): string
    {
        return $this->namePrefix;
    }

    public function getIndex(): ?int
    {
        return $this->index;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function shouldHideLabel(): bool
    {
        return $this->hideLabel;
    }

    public function shouldHideHelp(): bool
    {
        return $this->hideHelp;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsFor(string $fieldName): array
    {
        return $this->errors[$fieldName] ?? [];
    }

    public function hasErrorsFor(string $fieldName): bool
    {
        return !empty($this->errors[$fieldName]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->customData[$key] ?? $default;
    }

    // Immutable transformations
    public function withFormId(string $formId): self
    {
        return new self(
            $formId,
            $this->namePrefix,
            $this->index,
            $this->disabled,
            $this->readonly,
            $this->hideLabel,
            $this->hideHelp,
            $this->errors,
            $this->customData,
        );
    }

    public function withNamePrefix(string $prefix): self
    {
        return new self(
            $this->formId,
            $prefix,
            $this->index,
            $this->disabled,
            $this->readonly,
            $this->hideLabel,
            $this->hideHelp,
            $this->errors,
            $this->customData,
        );
    }

    public function withIndex(int $index): self
    {
        return new self(
            $this->formId,
            $this->namePrefix,
            $index,
            $this->disabled,
            $this->readonly,
            $this->hideLabel,
            $this->hideHelp,
            $this->errors,
            $this->customData,
        );
    }

    public function withDisabled(bool $disabled = true): self
    {
        return new self(
            $this->formId,
            $this->namePrefix,
            $this->index,
            $disabled,
            $this->readonly,
            $this->hideLabel,
            $this->hideHelp,
            $this->errors,
            $this->customData,
        );
    }

    public function withErrors(array $errors): self
    {
        return new self(
            $this->formId,
            $this->namePrefix,
            $this->index,
            $this->disabled,
            $this->readonly,
            $this->hideLabel,
            $this->hideHelp,
            $errors,
            $this->customData,
        );
    }

    public function withCustomData(string $key, mixed $value): self
    {
        $customData = $this->customData;
        $customData[$key] = $value;
        
        return new self(
            $this->formId,
            $this->namePrefix,
            $this->index,
            $this->disabled,
            $this->readonly,
            $this->hideLabel,
            $this->hideHelp,
            $this->errors,
            $customData,
        );
    }

    /**
     * Convert to legacy array format for backwards compatibility
     */
    public function toArray(): array
    {
        return [
            'form_id' => $this->formId,
            'name_prefix' => $this->namePrefix,
            'index' => $this->index,
            'disabled' => $this->disabled,
            'readonly' => $this->readonly,
            'hide_label' => $this->hideLabel,
            'hide_help' => $this->hideHelp,
            'errors' => $this->errors,
            ...$this->customData,
        ];
    }
}
