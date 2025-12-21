<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Layout;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Validation\ValidationResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * GeolocationWidget - Latitude/Longitude with map picker
 */
final class GeolocationWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'geolocation';
    }

    public function getLabel(): string
    {
        return 'Geolocation';
    }

    public function getCategory(): string
    {
        return 'Layout';
    }

    public function getIcon(): string
    {
        return '🌍';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['geolocation', 'location', 'json', 'array'];
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/geolocation.css');
        $this->assets->addJs('/js/fields/geolocation.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $value = is_array($value) ? $value : ['lat' => '', 'lng' => ''];
        $showMap = $settings->getBool('show_map', true);
        $defaultLat = $settings->getFloat('default_lat', 0);
        $defaultLng = $settings->getFloat('default_lng', 0);
        $defaultZoom = $settings->getInt('default_zoom', 12);

        $wrapper = Html::div()
            ->class('field-geolocation')
            ->data('field-id', $fieldId)
            ->data('default-lat', $defaultLat)
            ->data('default-lng', $defaultLng)
            ->data('default-zoom', $defaultZoom);

        // Coordinate inputs
        $coords = Html::div()->class('field-geolocation__coords');

        $coords->child(
            Html::div()
                ->class('field-geolocation__field')
                ->child(Html::label()->attr('for', $fieldId . '_lat')->text('Latitude'))
                ->child(
                    Html::input('number')
                        ->id($fieldId . '_lat')
                        ->name($fieldName . '[lat]')
                        ->value($value['lat'] ?? '')
                        ->attr('step', '0.000001')
                        ->attr('min', '-90')
                        ->attr('max', '90')
                        ->class('field-geolocation__input')
                        ->when($context->isDisabled(), fn($el) => $el->disabled())
                )
        );

        $coords->child(
            Html::div()
                ->class('field-geolocation__field')
                ->child(Html::label()->attr('for', $fieldId . '_lng')->text('Longitude'))
                ->child(
                    Html::input('number')
                        ->id($fieldId . '_lng')
                        ->name($fieldName . '[lng]')
                        ->value($value['lng'] ?? '')
                        ->attr('step', '0.000001')
                        ->attr('min', '-180')
                        ->attr('max', '180')
                        ->class('field-geolocation__input')
                        ->when($context->isDisabled(), fn($el) => $el->disabled())
                )
        );

        $wrapper->child($coords);

        // Actions
        $wrapper->child(
            Html::div()
                ->class('field-geolocation__actions')
                ->child(
                    Html::button()
                        ->class('field-geolocation__btn')
                        ->attr('type', 'button')
                        ->data('action', 'locate')
                        ->text('📍 Use My Location')
                )
                ->child(
                    Html::button()
                        ->class('field-geolocation__btn')
                        ->attr('type', 'button')
                        ->data('action', 'clear')
                        ->text('Clear')
                )
        );

        // Map container
        if ($showMap) {
            $wrapper->child(
                Html::div()
                    ->class('field-geolocation__map')
                    ->id($fieldId . '_map')
            );
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);

        if (!$settings->getBool('show_map', true)) {
            return null;
        }

        $apiKey = $settings->getString('maps_api_key', '');

        return "CmsGeolocation.init('{$elementId}', '{$apiKey}');";
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if (!is_array($value) || (empty($value['lat']) && empty($value['lng']))) {
            if ($field->required) {
                return ValidationResult::failure('Location is required');
            }
            return ValidationResult::success();
        }

        $errors = [];

        if (!empty($value['lat'])) {
            $lat = (float) $value['lat'];
            if ($lat < -90 || $lat > 90) {
                $errors[] = 'Latitude must be between -90 and 90';
            }
        }

        if (!empty($value['lng'])) {
            $lng = (float) $value['lng'];
            if ($lng < -180 || $lng > 180) {
                $errors[] = 'Longitude must be between -180 and 180';
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        if (empty($value['lat']) && empty($value['lng'])) {
            return null;
        }

        return [
            'lat' => (float) ($value['lat'] ?? 0),
            'lng' => (float) ($value['lng'] ?? 0),
        ];
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if (!is_array($value) || (empty($value['lat']) && empty($value['lng']))) {
            return parent::renderDisplay($field, null, $context);
        }

        $lat = $value['lat'];
        $lng = $value['lng'];

        $html = Html::span()
            ->class('field-display', 'field-display--geolocation')
            ->child(
                Html::element('a')
                    ->attr('href', "https://www.google.com/maps?q={$lat},{$lng}")
                    ->attr('target', '_blank')
                    ->attr('rel', 'noopener')
                    ->text("📍 {$lat}, {$lng}")
            )
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'show_map' => ['type' => 'boolean', 'label' => 'Show Map', 'default' => true],
            'default_lat' => ['type' => 'float', 'label' => 'Default Latitude', 'default' => 0],
            'default_lng' => ['type' => 'float', 'label' => 'Default Longitude', 'default' => 0],
            'default_zoom' => ['type' => 'integer', 'label' => 'Default Zoom', 'default' => 12],
            'maps_api_key' => ['type' => 'string', 'label' => 'Maps API Key'],
        ];
    }
}
