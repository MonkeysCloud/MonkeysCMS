<?php

declare(strict_types=1);

namespace App\Cms\Fields\Examples;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * Custom Star Rating Widget
 */
final class StarRatingWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'star_rating';
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
        return 0;
    }

    public function getSupportedTypes(): array
    {
        return ['integer', 'float', 'rating'];
    }

    public function supportsMultiple(): bool
    {
        return false;
    }

    protected function buildInput(
        FieldDefinition $field,
        mixed $value,
        RenderContext $context
    ): HtmlBuilder|string {
        $settings = $this->getSettings($field);
        $maxStars = $settings->getInt('max_stars', 5);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);

        $wrapper = Html::div()->class('field-star-rating');

        // Hidden input
        $wrapper->child(
            Html::hidden($fieldName, $value ?? 0)
                ->id($fieldId)
        );

        // Stars container
        $stars = Html::div()->class('field-star-rating__stars');

        for ($i = 1; $i <= $maxStars; $i++) {
            $filled = $i <= ($value ?? 0);
            $stars->child(
                Html::span()
                    ->class('field-star-rating__star', $filled ? 'filled' : '')
                    ->data('value', $i)
                    ->text($filled ? '★' : '☆')
            );
        }

        $wrapper->child($stars);

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return <<<JS
(function() {
    var input = document.getElementById('{$elementId}');
    var stars = document.querySelectorAll('#{$elementId} ~ .field-star-rating__stars .field-star-rating__star');
    
    stars.forEach(function(star) {
        star.addEventListener('click', function() {
            var value = this.dataset.value;
            input.value = value;
            
            stars.forEach(function(s, i) {
                s.textContent = (i + 1) <= value ? '★' : '☆';
                s.classList.toggle('filled', (i + 1) <= value);
            });
        });
    });
})();
JS;
    }

    public function getSettingsSchema(): array
    {
        return [
            'max_stars' => [
                'type' => 'integer',
                'label' => 'Maximum Stars',
                'default' => 5,
                'min' => 1,
                'max' => 10,
            ],
        ];
    }
}
