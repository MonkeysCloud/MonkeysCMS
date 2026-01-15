<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget\Reference;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Rendering\Html;
use App\Cms\Fields\Rendering\HtmlBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Rendering\RenderResult;
use App\Cms\Fields\Widget\AbstractWidget;

/**
 * UserReferenceWidget - Reference to users
 */
final class UserReferenceWidget extends AbstractWidget
{
    public function getId(): string
    {
        return 'user_reference';
    }

    public function getLabel(): string
    {
        return 'User Reference';
    }

    public function getCategory(): string
    {
        return 'Reference';
    }

    public function getIcon(): string
    {
        return '👤';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function getSupportedTypes(): array
    {
        return ['user_reference', 'user', 'string', 'integer'];
    }

    public function supportsMultiple(): bool
    {
        return true;
    }

    protected function initializeAssets(): void
    {
        $this->assets->addCss('/css/fields/reference.css');
        $this->assets->addJs('/js/fields/entity-reference.js');
    }

    protected function buildInput(FieldDefinition $field, mixed $value, RenderContext $context): HtmlBuilder|string
    {
        $settings = $this->getSettings($field);
        $fieldId = $this->getFieldId($field, $context);
        $fieldName = $this->getFieldName($field, $context);
        $roleFilter = $settings->getArray('role_filter', []);
        $multiple = $field->multiple;
        $values = is_array($value) ? $value : ($value ? [$value] : []);

        $wrapper = Html::div()
            ->id($fieldId . '_wrapper')
            ->class('field-entity-reference')
            ->data('field-id', $fieldId)
            ->data('multiple', $multiple ? 'true' : 'false')
            ->data('roles', json_encode($roleFilter));

        // Hidden input
        $wrapper->child(
            Html::hidden($fieldName, $multiple ? json_encode($values) : ($values[0] ?? ''))
                ->id($fieldId)
                ->class('field-entity-reference__value')
        );

        // Selected users
        $selected = Html::div()
            ->id($fieldId . '_selected')
            ->class('field-entity-reference__selected');

        foreach ($values as $userId) {
            // In production, fetch user info from database
            $selected->child($this->buildUserBadge($userId, 'User #' . $userId));
        }

        $wrapper->child($selected);

        // Search input
        $wrapper->child(
            Html::div()
                ->class('field-entity-reference__search')
                ->child(
                    Html::input('text')
                        ->id($fieldId . '_search')
                        ->class('field-entity-reference__input')
                        ->attr('placeholder', 'Search users...')
                        ->attr('autocomplete', 'off')
                )
        );

        // Dropdown
        $wrapper->child(
            Html::div()
                ->class('field-entity-reference__dropdown')
                ->id($fieldId . '_results')
        );

        return $wrapper;
    }

    private function buildUserBadge(mixed $userId, string $displayName, ?string $avatar = null): HtmlBuilder
    {
        $badge = Html::div()
            ->class('field-entity-reference__item')
            ->data('id', $userId);

        if ($avatar) {
            $badge->child(
                Html::element('img')
                    ->class('field-entity-reference__avatar')
                    ->attr('src', $avatar)
                    ->attr('alt', '')
            );
        }

        $badge->child(
            Html::span()
                ->class('field-entity-reference__item-label')
                ->text($displayName)
        );

        $badge->child(
            Html::button()
                ->class('field-entity-reference__item-remove')
                ->attr('type', 'button')
                ->attr('onclick', "window.removeEntityReference(this.closest('[data-field-id]').dataset.fieldId, {$userId})")
                ->text('×')
        );

        return $badge;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        $options = [
            'targetType' => 'user',
            'multiple' => $field->multiple,
            'searchEndpoint' => '/api/users/search',
            'lookupEndpoint' => '/api/users/lookup',
        ];

        // Values are now read from the DOM input if not provided
        return "window.initEntityReference('{$elementId}', null, " . json_encode($options) . ");";
    }

    public function prepareValue(FieldDefinition $field, mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $field->multiple ? $decoded : ($decoded[0] ?? null);
            }
        }

        if (!$field->multiple) {
            return is_array($value) ? ($value[0] ?? null) : $value;
        }

        return is_array($value) ? array_values(array_filter($value)) : ($value ? [$value] : []);
    }

    public function renderDisplay(FieldDefinition $field, mixed $value, RenderContext $context): RenderResult
    {
        $values = is_array($value) ? $value : ($value ? [$value] : []);

        if (empty($values)) {
            return parent::renderDisplay($field, null, $context);
        }

        // In production, fetch user names from database
        $html = Html::span()
            ->class('field-display', 'field-display--user-reference')
            ->text(implode(', ', array_map(fn($id) => 'User #' . $id, $values)))
            ->render();

        return RenderResult::fromHtml($html);
    }

    public function getSettingsSchema(): array
    {
        return [
            'role_filter' => ['type' => 'array', 'label' => 'Filter by Roles'],
            'show_avatar' => ['type' => 'boolean', 'label' => 'Show Avatar', 'default' => true],
        ];
    }
}
