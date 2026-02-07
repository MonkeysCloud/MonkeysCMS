<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Location;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * AddressWidget - Structured address input
 */
final class AddressWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'address';
    }

    public function getLabel(): string
    {
        return 'Address';
    }

    public function getCategory(): string
    {
        return 'Location';
    }

    public function getIcon(): string
    {
        return '📍';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['address', 'json', 'object'];
    }

    public function usesLabelableInput(): bool
    {
        return false;
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/address.css');
        $this->assets->addJs('/js/fields/address.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        
        $countryMode = $settings->getString('country_mode', 'all');
        $allowedCountries = $settings->getArray('allowed_countries', []);
        $singleCountry = $settings->getString('single_country', 'US');
        $defaultCountry = $settings->getString('default_country', 'US');

        // Determine effective country list and default
        $countries = \App\Cms\Data\Countries::getAll();
        
        if ($countryMode === 'specific' && !empty($allowedCountries)) {
            $countries = array_intersect_key($countries, array_flip($allowedCountries));
            // Ensure default is in allowed list, otherwise pick first allowed
            if (!isset($countries[$defaultCountry])) {
                $defaultCountry = array_key_first($countries);
            }
        } elseif ($countryMode === 'single') {
            $defaultCountry = $singleCountry;
        }

        // Parse value
        $address = is_array($value) ? $value : [];
        $currentCountry = $address['country'] ?? $defaultCountry;

        $wrapper = Html::div()
            ->class('field-address')
            ->id($fieldId . '_wrapper') // Added ID for JS init
            ->data('field-id', $fieldId);

        // Hidden input for JSON value
        $wrapper->child(
            Html::hidden($fieldName, json_encode($address))
                ->id($fieldId)
                ->class('field-address__value')
        );

        // Street Address 1
        $wrapper->child(
            $this->buildAddressField(
                $fieldId . '_street1',
                'street1',
                'Street Address',
                $address['street1'] ?? '',
                'Enter street address'
            )
        );

        // Street Address 2
        $wrapper->child(
            $this->buildAddressField(
                $fieldId . '_street2',
                'street2',
                'Address Line 2',
                $address['street2'] ?? '',
                'Apartment, suite, unit, etc. (optional)'
            )
        );

        // City and State row
        $cityStateRow = Html::div()->class('field-address__row');

        $cityStateRow->child(
            Html::div()
                ->class('field-address__field', 'field-address__field--city')
                ->child(
                    Html::label()
                        ->attr('for', $fieldId . '_city')
                        ->text('City')
                )
                ->child(
                    Html::input('text')
                        ->id($fieldId . '_city')
                        ->class('field-address__input')
                        ->data('field', 'city')
                        ->value($address['city'] ?? '')
                        ->attr('placeholder', 'City')
                )
        );

        $cityStateRow->child(
            Html::div()
                ->class('field-address__field', 'field-address__field--state')
                ->child(
                    Html::label()
                        ->attr('for', $fieldId . '_state')
                        ->text('State/Province')
                )
                ->child(
                    Html::input('text')
                        ->id($fieldId . '_state')
                        ->class('field-address__input')
                        ->data('field', 'state')
                        ->value($address['state'] ?? '')
                        ->attr('placeholder', 'State')
                )
        );

        $wrapper->child($cityStateRow);

        // Postal Code and Country row
        $postalCountryRow = Html::div()->class('field-address__row');

        $postalCountryRow->child(
            Html::div()
                ->class('field-address__field', 'field-address__field--postal')
                ->child(
                    Html::label()
                        ->attr('for', $fieldId . '_postal_code')
                        ->text('Postal Code')
                )
                ->child(
                    Html::input('text')
                        ->id($fieldId . '_postal_code')
                        ->class('field-address__input')
                        ->data('field', 'postal_code')
                        ->value($address['postal_code'] ?? '')
                        ->attr('placeholder', 'Postal Code')
                )
        );

        // Country selection logic
        if ($countryMode === 'single') {
            // In single mode, we just add a hidden input for the country
            // and optionally display the country name as text
            $wrapper->child(
                Html::hidden('', $currentCountry)
                    ->data('field', 'country')
            );
        } else {
            // For 'all' or 'specific', show the dropdown
            $postalCountryRow->child(
                Html::div()
                    ->class('field-address__field', 'field-address__field--country')
                    ->child(
                        Html::label()
                            ->attr('for', $fieldId . '_country')
                            ->text('Country')
                    )
                    ->child(
                        $this->buildCountrySelect($fieldId . '_country', $currentCountry, $countries)
                    )
            );
        }

        $wrapper->child($postalCountryRow);

        return $wrapper;
    }

    private function buildAddressField(string $id, string $field, string $label, string $value, string $placeholder): HtmlBuilder
    {
        return Html::div()
            ->class('field-address__field')
            ->child(
                Html::label()->attr('for', $id)->text($label)
            )
            ->child(
                Html::input('text')
                    ->id($id)
                    ->class('field-address__input')
                    ->data('field', $field)
                    ->value($value)
                    ->attr('placeholder', $placeholder)
            );
    }

    private function buildCountrySelect(string $id, string $selected, array $countries): HtmlBuilder
    {
        $select = Html::select()
            ->id($id)
            ->class('field-address__input', 'field-address__select')
            ->data('field', 'country');

        foreach ($countries as $code => $name) {
            $select->child(Html::option($code, $name, $code === $selected));
        }

        return $select;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        // Using _wrapper ID for init to scope events correctly
        return "CmsAddress.init('{$elementId}_wrapper');";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }

        return is_array($value) ? $value : null;
    }

    public function formatValue(FieldDefinition $field, mixed $value): mixed
    {
        return is_array($value) ? $value : [];
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if (!is_array($value) || empty(array_filter($value))) {
            return parent::renderDisplay($field, null, $context);
        }

        $countries = \App\Cms\Data\Countries::getAll();
        $countryCode = $value['country'] ?? '';
        $countryName = $countries[$countryCode] ?? $countryCode;

        $parts = array_filter([
            $value['street1'] ?? '',
            $value['street2'] ?? '',
            implode(', ', array_filter([
                $value['city'] ?? '',
                $value['state'] ?? '',
                $value['postal_code'] ?? '',
            ])),
            $countryName,
        ]);

        $html = Html::element('address')
            ->class('field-display', 'field-display--address')
            ->html(implode('<br>', array_map('htmlspecialchars', $parts)))
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
                    'specific' => 'Specific Countries',
                    'single' => 'Single Country'
                ],
                'default' => 'all'
            ],
            'allowed_countries' => [
                'type' => 'multiselect',
                'label' => 'Allowed Countries',
                'options' => $countries,
                'depends_on' => ['country_mode' => 'specific']
            ],
            'single_country' => [
                'type' => 'select',
                'label' => 'Country',
                'options' => $countries,
                'depends_on' => ['country_mode' => 'single']
            ],
            'default_country' => [
                'type' => 'select',
                'label' => 'Default Country', 
                'options' => $countries,
                'default' => 'US',
                'depends_on' => ['country_mode' => ['all', 'specific']]
            ],
        ];
    }
}
