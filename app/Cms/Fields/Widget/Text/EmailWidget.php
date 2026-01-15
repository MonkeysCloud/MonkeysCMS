<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Text;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * EmailWidget - Email input with validation
 */
final class EmailWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'email';
    }

    public function getLabel(): string
    {
        return 'Email';
    }

    public function getCategory(): string
    {
        return 'Text';
    }

    public function getIcon(): string
    {
        return '📧';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['email', 'string'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/email.css');
        $this->assets->addJs('/js/fields/email.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);

        $wrapper = Html::div()
            ->class('field-email')
            ->data('field-id', $fieldId);

        // Input wrapper for icons
        $inputWrapper = Html::div()->class('field-email__input-wrapper');

        $inputWrapper->child(
            Html::input('email')
                ->id($fieldId)
                ->name($fieldName)
                ->class('field-email__input')
                ->value($value ?? '')
                ->attr('placeholder', 'email@example.com')
        );

        $wrapper->child($inputWrapper);

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsEmail.init('{$elementId}');";
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($this->isEmpty($value)) {
            return ValidationResult::success();
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::failure('Please enter a valid email address');
        }

        return ValidationResult::success();
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        $html = Html::element('a')
            ->class('field-display', 'field-display--email')
            ->attr('href', 'mailto:' . $value)
            ->text($value)
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'placeholder' => ['type' => 'string', 'label' => 'Placeholder', 'default' => 'email@example.com'],
        ];
    }
}
