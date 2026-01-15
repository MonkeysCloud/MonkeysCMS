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
 * PhoneWidget - Phone number input
 */
final class PhoneWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'phone';
    }

    public function getLabel(): string
    {
        return 'Phone';
    }

    public function getCategory(): string
    {
        return 'Text';
    }

    public function getIcon(): string
    {
        return '📞';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['phone', 'string'];
    }

    protected function initializeAssets(): void
    {
        // Local vendor assets
        $this->assets->addCss('/vendor/intl-tel-input/css/intlTelInput.css');
        $this->assets->addJs('/vendor/intl-tel-input/js/intlTelInput.min.js');
        
        // Local widget assets
        $this->assets->addCss('/css/fields/phone.css');
        $this->assets->addJs('/js/fields/phone.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        
        $wrapper = Html::div()
            ->class('field-phone')
            ->id($fieldId . '_wrapper')
            ->child(
                Html::div()->class('field-phone__validation-msg')
            );

        // Visible input for user interaction
        $input = Html::input('tel')
            ->id($fieldId)
            ->class('field-phone__input')
            ->value($value ?? ''); // Initially show raw value, JS will format it? Or should we default to null?

        // Hidden input for submission (standardizes format)
        // If we want to submit the formatted value, we use this.
        // Actually, let's keep the name on the main input for now as fallback, 
        // but the JS will update a hidden input if we want strict E164.
        // For simple integration, let's use a hidden input for the "real" value if we want specific formatting.
        // The implementation plan said: "Render hidden input for the raw value (so we can save the clean formatted version)."
        // So main input is just UI, hidden input is the name="$fieldName"
        
        $input->attr('data-cw-field', $fieldId); // Marker
        
        $hidden = Html::hidden($fieldName, $value ?? '')
            ->id($fieldId . '_hidden');

        $wrapper->child($input)
                ->child($hidden);

        return $wrapper;
    }
    
    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        
        // Fix getBoolean -> getBool
        $options = [
            'initialCountry' => $settings->getString('default_country', 'auto'),
            'preferredCountries' => $settings->getArray('preferred_countries', ['us', 'gb', 'ca']),
            'separateDialCode' => $settings->getBool('separate_dial_code', true),
        ];

        // Handle country restrictions
        $mode = $settings->getString('country_mode', 'all');
        if ($mode === 'specific') {
            $options['onlyCountries'] = $settings->getArray('allowed_countries', []);
        }

        $jsonOptions = json_encode($options);
        
        return "CmsPhone.init('{$elementId}_wrapper', {$jsonOptions});";
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if ($this->isEmpty($value)) {
            return parent::renderDisplay($field, $value, $context);
        }

        // Use standard HtmlBuilder methods, avoid unknown static shortcuts
        $html = Html::element('a')
            ->class('field-display', 'field-display--phone')
            ->attr('href', 'tel:' . preg_replace('/[^+0-9]/', '', $value))
            ->child(Html::element('svg') // Html::raw not available on static helper, constructing manually or using element
                ->attr('xmlns', 'http://www.w3.org/2000/svg')
                ->attr('fill', 'none')
                ->attr('viewBox', '0 0 24 24')
                ->attr('stroke', 'currentColor')
                ->child(Html::element('path')
                    ->attr('stroke-linecap', 'round')
                    ->attr('stroke-linejoin', 'round')
                    ->attr('stroke-width', '2')
                    ->attr('d', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z')
                )
            )
            ->child(Html::span()->text($value)) // Html::span() takes no args, chain text()
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        $countries = \App\Cms\Data\Countries::getAll();
        
        return [
            'country_mode' => [
                'type' => 'select',
                'label' => 'Country Selection',
                'options' => [
                    'all' => 'All Countries',
                    'specific' => 'Specific Countries'
                ],
                'default' => 'all'
            ],
            'allowed_countries' => [
                'type' => 'multiselect',
                'label' => 'Allowed Countries',
                'options' => $countries,
                'depends_on' => ['country_mode' => 'specific']
            ],
            'default_country' => [
                'type' => 'select',
                'label' => 'Default Country', 
                'options' => array_merge(['auto' => 'Auto Detect (GeoIP)'], $countries),
                'default' => 'auto'
            ],
            'preferred_countries' => [
                'type' => 'multiselect',
                'label' => 'Preferred Countries (Top of list)',
                'options' => $countries,
                'default' => ['US', 'GB', 'CA']
            ],
            'separate_dial_code' => [
                'type' => 'boolean',
                'label' => 'Separate Dial Code',
                'default' => true
            ]
        ];
    }
}
