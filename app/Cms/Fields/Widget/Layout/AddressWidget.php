<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Layout;

use App\Cms\Fields\Definition\Field;
use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Value\FieldValue;
use App\Cms\Fields\Widget\AbstractWidget;
use App\Cms\Fields\Widget\WidgetOutput;

/**
 * AddressWidget - Multi-field address input
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
        return 'Layout';
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
        return ['address', 'json', 'array'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/address.css');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $value = is_array($value) ? $value : [];
        
        $enabledFields = $settings->getArray('fields', [
            'street1', 'street2', 'city', 'state', 'postal_code', 'country'
        ]);
        
        $wrapper = Html::div()->class('field-address');

        // Street Address 1
        if (in_array('street1', $enabledFields)) {
            $wrapper->child($this->buildSubField(
                $fieldId, $fieldName, 'street1', 'Street Address',
                $value['street1'] ?? '', 'text', $context
            ));
        }

        // Street Address 2
        if (in_array('street2', $enabledFields)) {
            $wrapper->child($this->buildSubField(
                $fieldId, $fieldName, 'street2', 'Street Address 2',
                $value['street2'] ?? '', 'text', $context, false
            ));
        }

        // City and State row
        $row = Html::div()->class('field-address__row');

        if (in_array('city', $enabledFields)) {
            $row->child($this->buildSubField(
                $fieldId, $fieldName, 'city', 'City',
                $value['city'] ?? '', 'text', $context
            )->addClass('field-address__col--2'));
        }

        if (in_array('state', $enabledFields)) {
            $row->child($this->buildSubField(
                $fieldId, $fieldName, 'state', 'State/Province',
                $value['state'] ?? '', 'text', $context
            )->addClass('field-address__col--1'));
        }

        $wrapper->child($row);

        // Postal code and Country row
        $row2 = Html::div()->class('field-address__row');

        if (in_array('postal_code', $enabledFields)) {
            $row2->child($this->buildSubField(
                $fieldId, $fieldName, 'postal_code', 'Postal Code',
                $value['postal_code'] ?? '', 'text', $context
            )->addClass('field-address__col--1'));
        }

        if (in_array('country', $enabledFields)) {
            $row2->child($this->buildCountrySelect(
                $fieldId, $fieldName, $value['country'] ?? '', $context
            )->addClass('field-address__col--2'));
        }

        $wrapper->child($row2);

        return $wrapper;
    }

    private function buildSubField(
        string $fieldId,
        string $fieldName,
        string $subField,
        string $label,
        string $value,
        string $type,
        RenderContext $context,
        bool $required = true
    ): HtmlBuilder {
        $id = $fieldId . '_' . $subField;
        $name = $fieldName . '[' . $subField . ']';

        return Html::div()
            ->class('field-address__field')
            ->child(
                Html::label()
                    ->attr('for', $id)
                    ->text($label)
            )
            ->child(
                Html::input($type)
                    ->id($id)
                    ->name($name)
                    ->value($value)
                    ->class('field-address__input')
                    ->when($context->isDisabled(), fn($el) => $el->disabled())
                    ->when($context->isReadonly(), fn($el) => $el->readonly())
            );
    }

    private function buildCountrySelect(
        string $fieldId,
        string $fieldName,
        string $value,
        RenderContext $context
    ): HtmlBuilder {
        $countries = $this->getCountries();

        $select = Html::select()
            ->id($fieldId . '_country')
            ->name($fieldName . '[country]')
            ->class('field-address__input')
            ->when($context->isDisabled(), fn($el) => $el->disabled());

        $select->child(Html::option('', '- Select Country -'));

        foreach ($countries as $code => $name) {
            $select->child(Html::option($code, $name, $code === $value));
        }

        return Html::div()
            ->class('field-address__field')
            ->child(Html::label()->attr('for', $fieldId . '_country')->text('Country'))
            ->child($select);
    }

    private function getCountries(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'MX' => 'Mexico',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'CN' => 'China',
            'AU' => 'Australia',
            'BR' => 'Brazil',
            'IN' => 'India',
            // Add more as needed
        ];
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        // Filter empty values
        $filtered = array_filter($value, fn($v) => $v !== null && $v !== '');
        
        return empty($filtered) ? null : $filtered;
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if (!is_array($value) || empty($value)) {
            return parent::renderDisplay($field, null, $context);
        }

        $parts = $this->buildAddressParts($value);
        $html = $this->buildAddressHtml($parts);

        return RenderResult::fromHtml($html);
    }

    public function display(Field $field, FieldValue $value, RenderContext $context): WidgetOutput
    {
        $val = $value->asArray();
        
        if (empty($val)) {
            $html = Html::span()
                ->class('field-display', 'field-display--empty')
                ->text('—')
                ->render();
            return WidgetOutput::html($html);
        }

        $parts = $this->buildAddressParts($val);
        $html = $this->buildAddressHtml($parts);
        
        return WidgetOutput::html($html);
    }

    private function buildAddressParts(array $value): array
    {
        $parts = [];
        
        if (!empty($value['street1'])) {
            $parts[] = $value['street1'];
        }
        if (!empty($value['street2'])) {
            $parts[] = $value['street2'];
        }
        
        $cityLine = [];
        if (!empty($value['city'])) {
            $cityLine[] = $value['city'];
        }
        if (!empty($value['state'])) {
            $cityLine[] = $value['state'];
        }
        if (!empty($value['postal_code'])) {
            $cityLine[] = $value['postal_code'];
        }
        if (!empty($cityLine)) {
            $parts[] = implode(', ', $cityLine);
        }
        
        if (!empty($value['country'])) {
            $countries = $this->getCountries();
            $parts[] = $countries[$value['country']] ?? $value['country'];
        }

        return $parts;
    }

    private function buildAddressHtml(array $parts): string
    {
        return Html::address()
            ->class('field-display', 'field-display--address')
            ->html(implode('<br>', array_map('htmlspecialchars', $parts)))
            ->render();
    }

    public function getSettingsSchema(): array
    {
        return [
            'fields' => [
                'type' => 'array',
                'label' => 'Enabled Fields',
                'default' => ['street1', 'street2', 'city', 'state', 'postal_code', 'country'],
            ],
            'default_country' => ['type' => 'string', 'label' => 'Default Country', 'default' => 'US'],
        ];
    }
}
