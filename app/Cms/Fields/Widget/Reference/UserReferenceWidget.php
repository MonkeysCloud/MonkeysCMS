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
        return ['user_reference', 'user'];
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
            ->class('field-user-reference')
            ->data('field-id', $fieldId)
            ->data('multiple', $multiple ? 'true' : 'false')
            ->data('roles', json_encode($roleFilter));

        // Hidden input
        $wrapper->child(
            Html::hidden($fieldName, $multiple ? json_encode($values) : ($values[0] ?? ''))
                ->id($fieldId)
                ->class('field-user-reference__value')
        );

        // Selected users
        $selected = Html::div()->class('field-user-reference__selected');

        foreach ($values as $userId) {
            // In production, fetch user info from database
            $selected->child($this->buildUserBadge($userId, 'User #' . $userId));
        }

        $wrapper->child($selected);

        // Search input
        $wrapper->child(
            Html::div()
                ->class('field-user-reference__search')
                ->child(
                    Html::input('text')
                        ->class('field-user-reference__input')
                        ->attr('placeholder', 'Search users...')
                        ->attr('autocomplete', 'off')
                )
        );

        // Dropdown
        $wrapper->child(
            Html::div()
                ->class('field-user-reference__dropdown')
                ->id($fieldId . '_dropdown')
        );

        return $wrapper;
    }

    private function buildUserBadge(mixed $userId, string $displayName, ?string $avatar = null): HtmlBuilder
    {
        $badge = Html::div()
            ->class('field-user-reference__user')
            ->data('id', $userId);

        if ($avatar) {
            $badge->child(
                Html::element('img')
                    ->class('field-user-reference__avatar')
                    ->attr('src', $avatar)
                    ->attr('alt', '')
            );
        }

        $badge->child(
            Html::span()
                ->class('field-user-reference__name')
                ->text($displayName)
        );

        $badge->child(
            Html::button()
                ->class('field-user-reference__remove')
                ->attr('type', 'button')
                ->text('×')
        );

        return $badge;
    }

    protected function getInitScript(FieldDefinition $field, string $elementId): ?string
    {
        return "CmsEntityReference.init('{$elementId}', '/api/users/search');";
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
