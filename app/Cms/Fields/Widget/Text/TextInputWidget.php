<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Text;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * TextInputWidget - Simple single-line text input
 */
final class TextInputWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'text_input';
    }

    public function getLabel(): string
    {
        return 'Text Input';
    }

    public function getCategory(): string
    {
        return 'Text';
    }

    public function getIcon(): string
    {
        return '📝';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['string', 'text'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $prefix = $settings->getString('prefix');
        $suffix = $settings->getString('suffix');
        
        $input = Html::input('text')
            ->attrs($this->buildCommonAttributes($field, $context))
            ->value($value ?? '');

        if ($maxLength = $settings->getInt('max_length')) {
            $input->attr('maxlength', $maxLength);
        }

        if ($minLength = $settings->getInt('min_length')) {
            $input->attr('minlength', $minLength);
        }

        if ($pattern = $settings->getString('pattern')) {
            $input->attr('pattern', $pattern);
        }

        // No prefix/suffix - return input directly
        if (!$prefix && !$suffix) {
            return $input;
        }

        // Wrap with prefix/suffix
        $wrapper = Html::div()->class('field-input-group');

        if ($prefix) {
            $wrapper->child(
                Html::span()
                    ->class('field-input-group__prefix')
                    ->text($prefix)
            );
        }

        $wrapper->child($input);

        if ($suffix) {
            $wrapper->child(
                Html::span()
                    ->class('field-input-group__suffix')
                    ->text($suffix)
            );
        }

        return $wrapper;
    }

    public function getSettingsSchema(): array
    {
        return [
            'placeholder' => ['type' => 'string', 'label' => 'Placeholder'],
            'prefix' => ['type' => 'string', 'label' => 'Prefix'],
            'suffix' => ['type' => 'string', 'label' => 'Suffix'],
            'max_length' => ['type' => 'integer', 'label' => 'Max Length'],
            'min_length' => ['type' => 'integer', 'label' => 'Min Length'],
            'pattern' => ['type' => 'string', 'label' => 'Regex Pattern'],
        ];
    }
}
