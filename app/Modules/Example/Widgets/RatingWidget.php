<?php

declare(strict_types=1);

namespace App\Modules\Example\Widgets;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widget\AbstractWidget;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Validation\ValidationResult;

/**
 * RatingWidget - Star rating selector
 *
 * Example custom widget that demonstrates how to create module widgets.
 */
class RatingWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'rating';
    }

    public function getLabel(): string
    {
        return 'Star Rating';
    }

    public function getCategory(): string
    {
        return 'Custom';
    }

    public function getIcon(): string
    {
        return '⭐';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['integer', 'float', 'decimal'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);

        $maxStars = (int) $field->getSetting('max_stars', 5);
        $allowHalf = (bool) $field->getSetting('allow_half', false);
        $size = $field->getSetting('size', 'medium');
        $currentValue = $value ?? 0;

        $step = $allowHalf ? 0.5 : 1;
        $sizeClass = 'field-rating--' . $size;

        $wrapper = Html::div()
            ->class('field-rating', $sizeClass)
            ->id($fieldId . '_wrapper');

        // Hidden input
        $wrapper->child(
            Html::input('hidden')
                ->name($fieldName)
                ->id($fieldId)
                ->value((string) $currentValue)
        );

        // Stars container
        $starsContainer = Html::div()
            ->class('field-rating__stars')
            ->data('max', (string) $maxStars)
            ->data('step', (string) $step);

        for ($i = 1; $i <= $maxStars; $i++) {
            $filled = $currentValue >= $i ? 'filled' : ($currentValue >= $i - 0.5 && $allowHalf ? 'half' : 'empty');
            
            $star = Html::span()
                ->class('field-rating__star', 'field-rating__star--' . $filled)
                ->data('value', (string) $i)
                ->attr('onclick', "window.setRating('{$fieldId}', {$i})")
                ->html($this->getStarSvg($filled));

            $starsContainer->child($star);
        }

        $wrapper->child($starsContainer);

        // Display Value
        if ($field->getSetting('show_value', true)) {
            $wrapper->child(
                Html::span()
                    ->class('field-rating__value')
                    ->id($fieldId . '_display')
                    ->text(number_format((float) $currentValue, $allowHalf ? 1 : 0))
            );
        }

        // Clear Button
        if ($field->getSetting('allow_clear', true)) {
            $wrapper->child(
                Html::button()
                    ->attr('type', 'button')
                    ->class('field-rating__clear')
                    ->attr('onclick', "window.clearRating('{$fieldId}')")
                    ->attr('title', 'Clear rating')
                    ->text('×')
            );
        }

        return $wrapper;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return RenderResult::fromHtml(
                Html::span()->class('field-display', 'field-display--empty')->text('Not rated')->render()
            );
        }

        $maxStars = (int) $field->getSetting('max_stars', 5);
        $allowHalf = (bool) $field->getSetting('allow_half', false);

        $stars = '';
        for ($i = 1; $i <= $maxStars; $i++) {
            $stars .= $value >= $i ? '★' : ($value >= $i - 0.5 && $allowHalf ? '⯨' : '☆');
        }

        $html = Html::span()
            ->class('field-display', 'field-display--rating')
            ->html($stars . ' <span class="field-rating__number">(' . number_format((float) $value, 1) . ')</span>');

        return RenderResult::fromHtml($html->render());
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        $errors = [];

        if ($value !== null && $value !== '') {
            $maxStars = (int) $field->getSetting('max_stars', 5);
            $allowHalf = (bool) $field->getSetting('allow_half', false);
            $numValue = (float) $value;

            if ($numValue < 0 || $numValue > $maxStars) {
                $errors[] = "Rating must be between 0 and {$maxStars}";
            }

            if (!$allowHalf && $numValue != (int) $numValue) {
                $errors[] = 'Half ratings are not allowed';
            }
        }

        if (!empty($errors)) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success();
    }

    private function getStarSvg(string $state): string
    {
        $fill = match ($state) {
            'filled' => '#fbbf24',
            'half' => 'url(#half-gradient)',
            default => '#d1d5db',
        };

        if ($state === 'half') {
            return '<svg viewBox="0 0 24 24" class="field-rating__svg">
                <defs>
                    <linearGradient id="half-gradient">
                        <stop offset="50%" stop-color="#fbbf24"/>
                        <stop offset="50%" stop-color="#d1d5db"/>
                    </linearGradient>
                </defs>
                <path fill="' . $fill . '" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>';
        }

        return '<svg viewBox="0 0 24 24" class="field-rating__svg">
            <path fill="' . $fill . '" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
        </svg>';
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return <<<JS
(function() {
    const wrapper = document.getElementById('{$elementId}_wrapper');
    if (!wrapper) return;
    
    const stars = wrapper.querySelectorAll('.field-rating__star');
    const input = document.getElementById('{$elementId}');
    
    if(!input) return;

    stars.forEach((star, index) => {
        star.addEventListener('mouseenter', function() {
            highlightStars(stars, index + 1);
        });
        
        star.addEventListener('mouseleave', function() {
            highlightStars(stars, parseFloat(input.value) || 0);
        });
    });
    
    function highlightStars(stars, count) {
        stars.forEach((s, i) => {
            s.classList.toggle('field-rating__star--highlighted', i < count);
        });
    }
})();
JS;
    }

    public function getSettingsSchema(): array
    {
        return [
            'max_stars' => [
                'type' => 'integer',
                'label' => 'Maximum stars',
                'default' => 5,
                'min' => 1,
                'max' => 10,
            ],
            'allow_half' => [
                'type' => 'boolean',
                'label' => 'Allow half stars',
                'default' => false,
            ],
            'show_value' => [
                'type' => 'boolean',
                'label' => 'Show numeric value',
                'default' => true,
            ],
            'allow_clear' => [
                'type' => 'boolean',
                'label' => 'Allow clearing rating',
                'default' => true,
            ],
            'size' => [
                'type' => 'select',
                'label' => 'Star size',
                'options' => [
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                ],
                'default' => 'medium',
            ],
        ];
    }
}
