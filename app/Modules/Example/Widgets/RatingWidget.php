<?php

declare(strict_types=1);

namespace App\Modules\Example\Widgets;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widgets\AbstractFieldWidget;

/**
 * RatingWidget - Star rating selector
 *
 * Example custom widget that can be added to any module.
 * This demonstrates how to create custom field widgets.
 *
 * @example
 * ```php
 * // In module's service registration or module.php
 * $widgetManager->register(new RatingWidget());
 * ```
 */
class RatingWidget extends AbstractFieldWidget
{
    protected const ID = 'rating';
    protected const LABEL = 'Star Rating';
    protected const CATEGORY = 'Custom';
    protected const ICON = '⭐';
    protected const PRIORITY = 100;

    public static function getSupportedTypes(): array
    {
        return ['integer', 'float', 'decimal'];
    }

    public function render(FieldDefinition $field, mixed $value, array $context = []): string
    {
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);

        $maxStars = $field->getSetting('max_stars', 5);
        $allowHalf = $field->getSetting('allow_half', false);
        $size = $field->getSetting('size', 'medium');
        $currentValue = $value ?? 0;

        $step = $allowHalf ? 0.5 : 1;
        $sizeClass = 'field-rating--' . $size;

        $inputHtml = '<div class="field-rating ' . $sizeClass . '" id="' . $this->escape($fieldId) . '_wrapper">';

        // Hidden input for form submission
        $inputHtml .= '<input type="hidden" name="' . $this->escape($fieldName) . '" id="' . $this->escape($fieldId) . '" value="' . $this->escape((string) $currentValue) . '">';

        // Star display
        $inputHtml .= '<div class="field-rating__stars" data-max="' . $maxStars . '" data-step="' . $step . '">';

        for ($i = 1; $i <= $maxStars; $i++) {
            $filled = $currentValue >= $i ? 'filled' : ($currentValue >= $i - 0.5 && $allowHalf ? 'half' : 'empty');
            $inputHtml .= '<span class="field-rating__star field-rating__star--' . $filled . '" data-value="' . $i . '" onclick="window.setRating(\'' . $fieldId . '\', ' . $i . ')">';
            $inputHtml .= $this->getStarSvg($filled);
            $inputHtml .= '</span>';
        }

        $inputHtml .= '</div>';

        // Display value
        $showValue = $field->getSetting('show_value', true);
        if ($showValue) {
            $inputHtml .= '<span class="field-rating__value" id="' . $fieldId . '_display">' . number_format($currentValue, $allowHalf ? 1 : 0) . '</span>';
        }

        // Clear button
        $allowClear = $field->getSetting('allow_clear', true);
        if ($allowClear) {
            $inputHtml .= '<button type="button" class="field-rating__clear" onclick="window.clearRating(\'' . $fieldId . '\')" title="Clear rating">×</button>';
        }

        $inputHtml .= '</div>';

        return $this->renderWrapper($field, $inputHtml, $context);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, array $context = []): string
    {
        if ($this->isEmpty($value)) {
            return '<span class="field-display field-display--empty">Not rated</span>';
        }

        $maxStars = $field->getSetting('max_stars', 5);
        $allowHalf = $field->getSetting('allow_half', false);

        $html = '<span class="field-display field-display--rating">';

        for ($i = 1; $i <= $maxStars; $i++) {
            $filled = $value >= $i ? '★' : ($value >= $i - 0.5 && $allowHalf ? '⯨' : '☆');
            $html .= $filled;
        }

        $html .= ' <span class="field-rating__number">(' . number_format((float) $value, 1) . ')</span>';
        $html .= '</span>';

        return $html;
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $maxStars = $field->getSetting('max_stars', 5);
        $value = (float) $value;

        // Clamp value
        return max(0, min($maxStars, $value));
    }

    public function validate(FieldDefinition $field, mixed $value): array
    {
        $errors = [];

        if ($value !== null && $value !== '') {
            $maxStars = $field->getSetting('max_stars', 5);
            $allowHalf = $field->getSetting('allow_half', false);
            $numValue = (float) $value;

            if ($numValue < 0 || $numValue > $maxStars) {
                $errors[] = "Rating must be between 0 and {$maxStars}";
            }

            if (!$allowHalf && $numValue != (int) $numValue) {
                $errors[] = 'Half ratings are not allowed';
            }
        }

        return $errors;
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

    public function getInitScript(FieldDefinition $field, string $elementId): string
    {
        $maxStars = $field->getSetting('max_stars', 5);
        $allowHalf = $field->getSetting('allow_half', false);
        $step = $allowHalf ? 0.5 : 1;

        return <<<JS
(function() {
    const wrapper = document.getElementById('{$elementId}_wrapper');
    if (!wrapper) return;
    
    const stars = wrapper.querySelectorAll('.field-rating__star');
    const input = document.getElementById('{$elementId}');
    
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

    public static function getCssAssets(): array
    {
        return ['/css/fields/rating.css'];
    }

    public static function getJsAssets(): array
    {
        return ['/js/fields/rating.js'];
    }

    public static function getSettingsSchema(): array
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
