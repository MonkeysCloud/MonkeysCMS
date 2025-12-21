<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Special;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * PasswordWidget - Password input with toggle
 */
final class PasswordWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'password';
    }

    public function getLabel(): string
    {
        return 'Password';
    }

    public function getCategory(): string
    {
        return 'Special';
    }

    public function getIcon(): string
    {
        return '🔒';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['password', 'string'];
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $showToggle = $settings->getBool('show_toggle', true);
        $showStrength = $settings->getBool('show_strength', false);

        $wrapper = Html::div()->class('field-password');

        // Password input
        $wrapper->child(
            Html::input('password')
                ->attrs($this->buildCommonAttributes($field, $context))
                ->attr('autocomplete', 'new-password')
                ->value($value ?? '')
        );

        // Toggle visibility button
        if ($showToggle) {
            $wrapper->child(
                Html::button()
                    ->class('field-password__toggle')
                    ->attr('type', 'button')
                    ->data('target', $fieldId)
                    ->aria('label', 'Toggle password visibility')
                    ->text('👁️')
            );
        }

        // Strength indicator
        if ($showStrength) {
            $wrapper->child(
                Html::div()
                    ->class('field-password__strength')
                    ->id($fieldId . '_strength')
            );
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $showToggle = $settings->getBool('show_toggle', true);
        $showStrength = $settings->getBool('show_strength', false);

        $js = '';

        if ($showToggle) {
            $js .= <<<JS
(function() {
    var input = document.getElementById('{$elementId}');
    var toggle = document.querySelector('[data-target="{$elementId}"]');
    
    if (toggle) {
        toggle.addEventListener('click', function() {
            var type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            this.textContent = type === 'password' ? '👁️' : '🙈';
        });
    }
})();
JS;
        }

        if ($showStrength) {
            $js .= <<<JS
(function() {
    var input = document.getElementById('{$elementId}');
    var strength = document.getElementById('{$elementId}_strength');
    
    function checkStrength(password) {
        var score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;
        return score;
    }
    
    input.addEventListener('input', function() {
        var score = checkStrength(this.value);
        var labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
        var classes = ['very-weak', 'weak', 'fair', 'strong', 'very-strong'];
        
        strength.textContent = this.value ? labels[Math.min(score, 4)] : '';
        strength.className = 'field-password__strength field-password__strength--' + classes[Math.min(score, 4)];
    });
})();
JS;
        }

        return $js ?: null;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        // Never display password values
        $html = Html::span()
            ->class('field-display', 'field-display--password')
            ->text($value ? '••••••••' : '—')
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'show_toggle' => ['type' => 'boolean', 'label' => 'Show Toggle Button', 'default' => true],
            'show_strength' => ['type' => 'boolean', 'label' => 'Show Strength Indicator', 'default' => false],
        ];
    }
}
