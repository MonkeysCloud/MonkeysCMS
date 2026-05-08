<?php

declare(strict_types=1);

namespace App\Cms\Form;

/**
 * FormRenderer — Renders a Form object to themed admin HTML.
 *
 * Produces HTML using MonkeysCMS admin CSS classes (admin-card, form-group, etc.).
 * Handles all element types, conditional visibility data attributes, and layout modes.
 */
final class FormRenderer
{
    /**
     * Render a complete Form to HTML string.
     */
    public function render(Form $form): string
    {
        $html = $this->openTag($form);

        // CSRF token
        if ($form->csrf) {
            $html .= '<input type="hidden" name="_csrf" value="' . htmlspecialchars($form->csrf) . '">';
        }

        // Grouped layout
        if ($form->hasGroups()) {
            $html .= $this->renderGrouped($form);
        } else {
            $html .= $this->renderFlat($form);
        }

        // Submit / actions
        $html .= $this->renderActions($form);

        $html .= '</form>';
        return $html;
    }

    /**
     * Render only the fields (no <form> tag) — for embedding inside existing forms.
     */
    public function renderFields(Form $form): string
    {
        $html = '';

        if ($form->csrf) {
            $html .= '<input type="hidden" name="_csrf" value="' . htmlspecialchars($form->csrf) . '">';
        }

        if ($form->hasGroups()) {
            $html .= $this->renderGrouped($form);
        } else {
            $html .= $this->renderFlat($form);
        }

        return $html;
    }

    // ── Layout Rendering ────────────────────────────────────────────────

    private function openTag(Form $form): string
    {
        $attrs = 'action="' . htmlspecialchars($form->action) . '"';
        $attrs .= ' method="' . htmlspecialchars($form->method) . '"';

        if ($form->id) {
            $attrs .= ' id="' . htmlspecialchars($form->id) . '"';
        }

        foreach ($form->getFormAttributes() as $k => $v) {
            $attrs .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"';
        }

        return '<form ' . $attrs . '>';
    }

    private function renderFlat(Form $form): string
    {
        $html = '';
        foreach ($form->getElements() as $el) {
            $html .= $this->renderElement($el);
        }
        return $html;
    }

    private function renderGrouped(Form $form): string
    {
        $layout = $form->formLayout;
        $wrapperClass = match ($layout) {
            'settings-grid' => 'admin-settings-grid',
            'two-column' => 'admin-grid admin-grid--2',
            'three-column' => 'admin-grid admin-grid--3',
            default => '',
        };

        $html = '';
        if ($wrapperClass) {
            $html .= '<div class="' . $wrapperClass . '">';
        }

        // Sort groups by weight
        $groups = $form->getGroups();
        uasort($groups, fn(FormGroup $a, FormGroup $b) => $a->weight <=> $b->weight);

        foreach ($groups as $group) {
            if ($group->isEmpty()) {
                continue;
            }
            $html .= $this->renderGroup($group);
        }

        if ($wrapperClass) {
            $html .= '</div>';
        }

        // Render ungrouped elements
        $ungrouped = $form->getUngroupedElements();
        foreach ($ungrouped as $el) {
            $html .= $this->renderElement($el);
        }

        return $html;
    }

    private function renderGroup(FormGroup $group): string
    {
        $html = '<div class="admin-card">';

        // Header
        $html .= '<div class="admin-card__header">';
        $html .= '<h3 class="admin-card__title">';
        if ($group->icon) {
            $html .= '<i data-lucide="' . htmlspecialchars($group->icon) . '" class="w-5 h-5"></i> ';
        }
        $html .= htmlspecialchars($group->title);
        $html .= '</h3>';
        if ($group->description) {
            $html .= '<p class="admin-card__desc">' . htmlspecialchars($group->description) . '</p>';
        }
        $html .= '</div>';

        // Body
        $html .= '<div class="admin-card__body">';
        foreach ($group->getElements() as $el) {
            $html .= $this->renderElement($el);
        }
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    // ── Element Rendering ───────────────────────────────────────────────

    private function renderElement(FormElement $el): string
    {
        return match ($el->type) {
            'hidden' => $this->renderHidden($el),
            'separator' => '<hr class="form-separator">',
            'html' => (string) $el->currentValue,
            'heading' => $this->renderHeading($el),
            'toggle', 'checkbox' => $this->renderToggle($el),
            'select' => $this->renderWrapped($el, $this->renderSelect($el)),
            'radio' => $this->renderWrapped($el, $this->renderRadio($el)),
            'textarea' => $this->renderWrapped($el, $this->renderTextarea($el)),
            'range' => $this->renderWrapped($el, $this->renderRange($el)),
            'file' => $this->renderWrapped($el, $this->renderFile($el)),
            default => $this->renderWrapped($el, $this->renderInput($el)),
        };
    }

    private function renderWrapped(FormElement $el, string $inputHtml): string
    {
        $conditions = $el->getConditions();
        $condAttrs = '';
        if ($conditions) {
            $condAttrs = ' data-show-when="' . htmlspecialchars($conditions['field'] ?? '') . '"';
            $condAttrs .= ' data-show-value="' . htmlspecialchars((string)($conditions['value'] ?? '')) . '"';
        }

        $wrapClass = 'form-group';
        if ($el->getWrapper) {
            $wrapClass .= ' ' . $el->getWrapper;
        }

        $html = '<div class="' . $wrapClass . '"' . $condAttrs . '>';

        if ($el->label) {
            $html .= '<label for="' . $el->htmlId . '" class="form-label">';
            $html .= htmlspecialchars($el->label);
            if ($el->isRequired) {
                $html .= ' <span class="form-required">*</span>';
            }
            $html .= '</label>';
        }

        if ($el->getPrefix) {
            $html .= '<div class="form-input-group">';
            $html .= '<span class="form-input-group__prefix">' . $el->getPrefix . '</span>';
        }

        $html .= $inputHtml;

        if ($el->getPrefix) {
            if ($el->getSuffix) {
                $html .= '<span class="form-input-group__suffix">' . $el->getSuffix . '</span>';
            }
            $html .= '</div>';
        } elseif ($el->getSuffix) {
            $html .= '<span class="form-suffix">' . $el->getSuffix . '</span>';
        }

        if ($el->getHelp) {
            $html .= '<span class="form-hint">' . htmlspecialchars($el->getHelp) . '</span>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderInput(FormElement $el): string
    {
        $attrs = 'type="' . htmlspecialchars($el->type) . '"';
        $attrs .= ' id="' . $el->htmlId . '"';
        $attrs .= ' name="' . $el->htmlName . '"';
        $attrs .= ' class="form-input"';

        if ($el->currentValue !== null) {
            $attrs .= ' value="' . htmlspecialchars((string) $el->currentValue) . '"';
        }
        if ($el->getPlaceholder) {
            $attrs .= ' placeholder="' . htmlspecialchars($el->getPlaceholder) . '"';
        }
        if ($el->isRequired) {
            $attrs .= ' required';
        }
        if ($el->isDisabled) {
            $attrs .= ' disabled';
        }
        if ($el->isReadonly) {
            $attrs .= ' readonly';
        }
        $attrs .= $el->buildAttrString();

        return '<input ' . $attrs . '>';
    }

    private function renderTextarea(FormElement $el): string
    {
        $attrs = 'id="' . $el->htmlId . '"';
        $attrs .= ' name="' . $el->htmlName . '"';

        $extraAttrs = $el->getAttributes();
        $class = $extraAttrs['class'] ?? 'form-input';
        $rows = $extraAttrs['rows'] ?? '4';

        $attrs .= ' class="' . htmlspecialchars($class) . '"';
        $attrs .= ' rows="' . htmlspecialchars((string) $rows) . '"';

        if ($el->getPlaceholder) {
            $attrs .= ' placeholder="' . htmlspecialchars($el->getPlaceholder) . '"';
        }
        if ($el->isRequired) {
            $attrs .= ' required';
        }
        if ($el->isDisabled) {
            $attrs .= ' disabled';
        }

        $value = htmlspecialchars((string) ($el->currentValue ?? ''));
        return '<textarea ' . $attrs . '>' . $value . '</textarea>';
    }

    private function renderSelect(FormElement $el): string
    {
        $attrs = 'id="' . $el->htmlId . '"';
        $attrs .= ' name="' . $el->htmlName . '"';
        $attrs .= ' class="form-input"';
        if ($el->isRequired) {
            $attrs .= ' required';
        }
        if ($el->isDisabled) {
            $attrs .= ' disabled';
        }
        $attrs .= $el->buildAttrString();

        $html = '<select ' . $attrs . '>';
        foreach ($el->getOptions() as $optVal => $optLabel) {
            $selected = ((string) $el->currentValue === (string) $optVal) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string) $optVal) . '"' . $selected . '>';
            $html .= htmlspecialchars($optLabel);
            $html .= '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function renderRadio(FormElement $el): string
    {
        $html = '<div class="form-radio-group">';
        foreach ($el->getOptions() as $optVal => $optLabel) {
            $checked = ((string) $el->currentValue === (string) $optVal) ? ' checked' : '';
            $id = $el->htmlId . '--' . htmlspecialchars((string) $optVal);
            $html .= '<label class="form-radio" for="' . $id . '">';
            $html .= '<input type="radio" id="' . $id . '" name="' . $el->htmlName . '"';
            $html .= ' value="' . htmlspecialchars((string) $optVal) . '"' . $checked . '>';
            $html .= '<span class="form-radio__label">' . htmlspecialchars($optLabel) . '</span>';
            $html .= '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderToggle(FormElement $el): string
    {
        $conditions = $el->getConditions();
        $condAttrs = '';
        if ($conditions) {
            $condAttrs = ' data-show-when="' . htmlspecialchars($conditions['field'] ?? '') . '"';
            $condAttrs .= ' data-show-value="' . htmlspecialchars((string)($conditions['value'] ?? '')) . '"';
        }

        $checked = $el->currentValue ? ' checked' : '';
        $html = '<div class="form-group"' . $condAttrs . '>';
        $html .= '<label class="form-toggle">';
        $html .= '<input type="hidden" name="' . $el->htmlName . '" value="0">';
        $html .= '<input type="checkbox" name="' . $el->htmlName . '" value="1"' . $checked;
        if ($el->isDisabled) {
            $html .= ' disabled';
        }
        $html .= '>';
        $html .= '<span class="form-toggle__label">' . htmlspecialchars($el->label) . '</span>';
        $html .= '</label>';
        if ($el->getHelp) {
            $html .= '<span class="form-hint">' . htmlspecialchars($el->getHelp) . '</span>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderRange(FormElement $el): string
    {
        $extraAttrs = $el->getAttributes();
        $min = $extraAttrs['min'] ?? '0';
        $max = $extraAttrs['max'] ?? '100';
        $step = $extraAttrs['step'] ?? '1';

        $html = '<input type="range" id="' . $el->htmlId . '" name="' . $el->htmlName . '"';
        $html .= ' class="form-range"';
        $html .= ' value="' . htmlspecialchars((string) ($el->currentValue ?? $min)) . '"';
        $html .= ' min="' . htmlspecialchars((string) $min) . '"';
        $html .= ' max="' . htmlspecialchars((string) $max) . '"';
        $html .= ' step="' . htmlspecialchars((string) $step) . '"';
        $html .= '>';
        return $html;
    }

    private function renderFile(FormElement $el): string
    {
        $attrs = 'type="file" id="' . $el->htmlId . '" name="' . $el->htmlName . '"';
        $attrs .= ' class="form-input"';

        $accept = $el->getAttributes()['accept'] ?? null;
        if ($accept) {
            $attrs .= ' accept="' . htmlspecialchars((string) $accept) . '"';
        }
        if ($el->isRequired) {
            $attrs .= ' required';
        }

        return '<input ' . $attrs . '>';
    }

    private function renderHidden(FormElement $el): string
    {
        return '<input type="hidden" name="' . $el->htmlName . '" value="' .
            htmlspecialchars((string) ($el->currentValue ?? '')) . '">';
    }

    private function renderHeading(FormElement $el): string
    {
        $level = $el->getAttributes()['level'] ?? 'h4';
        return '<' . $level . ' class="form-heading">' . htmlspecialchars($el->label) . '</' . $level . '>';
    }

    // ── Actions ─────────────────────────────────────────────────────────

    private function renderActions(Form $form): string
    {
        $label = $form->getSubmitLabel;
        if ($label === null) {
            return '';
        }

        $html = '<div class="admin-form-actions">';

        $html .= '<button type="submit" class="btn btn--primary btn--lg">';
        if ($form->getSubmitIcon) {
            $html .= '<i data-lucide="' . htmlspecialchars($form->getSubmitIcon) . '" class="w-5 h-5"></i> ';
        }
        $html .= htmlspecialchars($label);
        $html .= '</button>';

        if ($form->getCancelUrl) {
            $html .= '<a href="' . htmlspecialchars($form->getCancelUrl) . '" class="btn btn--ghost btn--lg">Cancel</a>';
        }

        $html .= '</div>';
        return $html;
    }
}
