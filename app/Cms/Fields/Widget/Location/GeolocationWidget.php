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
 * GeolocationWidget - Latitude/Longitude input with map
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
        return 'Location';
    }

    public function getIcon(): string
    {
        return '🌐';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['geolocation', 'coordinates', 'json', 'object'];
    }

    public function usesLabelableInput(): bool
    {
        return false;
    }

    protected function initializeAssets(): void
    {
        // Leaflet library for OpenStreetMap
        $this->assets->addCss('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        $this->assets->addJs('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
        
        // Custom styling and functionality
        $this->assets->addCss('/css/fields/geolocation.css');
        $this->assets->addJs('/js/fields/geolocation.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $showMap = $settings->getBool('show_map', true);
        $mapHeight = $settings->getInt('map_height', 300);
        $defaultLat = $settings->getFloat('default_lat', 0.0);
        $defaultLng = $settings->getFloat('default_lng', 0.0);
        $defaultZoom = $settings->getInt('default_zoom', 10);

        // Parse value
        $coords = is_array($value) ? $value : ['lat' => $defaultLat, 'lng' => $defaultLng];

        $wrapper = Html::div()
            ->class('field-geolocation')
            ->data('field-id', $fieldId)
            ->data('default-zoom', $defaultZoom);

        // Hidden input for JSON value
        $wrapper->child(
            Html::hidden($fieldName, json_encode($coords))
                ->id($fieldId)
                ->class('field-geolocation__value')
        );

        // Coordinate inputs row
        $coordsRow = Html::div()->class('field-geolocation__coords');

        $coordsRow->child(
            Html::div()
                ->class('field-geolocation__field')
                ->child(Html::label()->attr('for', $fieldId . '_lat')->text('Latitude'))
                ->child(
                    Html::input('number')
                        ->id($fieldId . '_lat')
                        ->class('field-geolocation__input')
                        ->data('field', 'lat')
                        ->attr('step', '0.000001')
                        ->attr('min', '-90')
                        ->attr('max', '90')
                        ->value($coords['lat'] ?? $defaultLat)
                )
        );

        $coordsRow->child(
            Html::div()
                ->class('field-geolocation__field')
                ->child(Html::label()->attr('for', $fieldId . '_lng')->text('Longitude'))
                ->child(
                    Html::input('number')
                        ->id($fieldId . '_lng')
                        ->class('field-geolocation__input')
                        ->data('field', 'lng')
                        ->attr('step', '0.000001')
                        ->attr('min', '-180')
                        ->attr('max', '180')
                        ->value($coords['lng'] ?? $defaultLng)
                )
        );

        // Get current location button
        $coordsRow->child(
            Html::button()
                ->class('field-geolocation__locate')
                ->attr('type', 'button')
                ->data('action', 'locate')
                ->text('📍 My Location')
        );

        $wrapper->child($coordsRow);

        // Map container
        if ($showMap) {
            $wrapper->child(
                Html::div()
                    ->class('field-geolocation__map')
                    ->id($fieldId . '_map')
                    ->attr('style', "height: {$mapHeight}px;")
            );
        }

        return $wrapper;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $settings = $this->getSettings($field);
        $showMap = $settings->getBool('show_map', true);

        if ($showMap) {
            return "CmsGeolocation.initWithMap('{$elementId}');";
        }

        return "CmsGeolocation.init('{$elementId}');";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return [
                    'lat' => (float) ($decoded['lat'] ?? 0),
                    'lng' => (float) ($decoded['lng'] ?? 0),
                ];
            }
        }

        if (is_array($value)) {
            return [
                'lat' => (float) ($value['lat'] ?? 0),
                'lng' => (float) ($value['lng'] ?? 0),
            ];
        }

        return null;
    }

    public function validate(FieldDefinition $field, mixed $value): ValidationResult
    {
        if ($this->isEmpty($value)) {
            return ValidationResult::success();
        }

        $coords = is_array($value) ? $value : json_decode($value, true);

        if (!is_array($coords)) {
            return ValidationResult::failure('Invalid coordinate format');
        }

        $lat = $coords['lat'] ?? null;
        $lng = $coords['lng'] ?? null;

        $errors = [];

        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $errors[] = 'Latitude must be between -90 and 90';
        }

        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $errors[] = 'Longitude must be between -180 and 180';
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        if (!is_array($value) || (!isset($value['lat']) && !isset($value['lng']))) {
            return parent::renderDisplay($field, null, $context);
        }

        $lat = $value['lat'] ?? 0;
        $lng = $value['lng'] ?? 0;

        $html = Html::span()
            ->class('field-display', 'field-display--geolocation')
            ->child(
                Html::element('a')
                    ->attr('href', "https://maps.google.com/?q={$lat},{$lng}")
                    ->attr('target', '_blank')
                    ->text("{$lat}, {$lng}")
            )
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'show_map' => ['type' => 'boolean', 'label' => 'Show Map', 'default' => true],
            'map_height' => ['type' => 'integer', 'label' => 'Map Height (px)', 'default' => 300],
            'default_lat' => ['type' => 'float', 'label' => 'Default Latitude', 'default' => 0],
            'default_lng' => ['type' => 'float', 'label' => 'Default Longitude', 'default' => 0],
            'default_zoom' => ['type' => 'integer', 'label' => 'Default Zoom', 'default' => 10],
            'map_provider' => [
                'type' => 'select',
                'label' => 'Map Provider',
                'options' => [
                    'leaflet' => 'Leaflet (OpenStreetMap)',
                    'google' => 'Google Maps',
                    'mapbox' => 'Mapbox',
                ],
                'default' => 'leaflet',
            ],
        ];
    }
}
