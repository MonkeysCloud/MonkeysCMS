<?php

declare(strict_types=1);

namespace App\Cms\Field\Widget;

use App\Cms\Field\FieldDefinition;

/**
 * PasswordInputWidget — Masked password input with visibility toggle.
 */
final class PasswordInputWidget extends AbstractWidget
{
    public static function type(): string { return 'password_input'; }

    public function render(FieldDefinition $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $id = $this->fieldId($field);

        $html = '<div style="position:relative">'
            . '<input type="password" name="' . $this->fieldName($field, $namePrefix) . '" '
            . $this->commonAttrs($field) . ' '
            . 'class="form-input" '
            . 'value="" '
            . 'placeholder="' . ($value ? '••••••••' : 'Enter password') . '" '
            . 'autocomplete="new-password" '
            . 'style="padding-right:2.5rem">'
            . '<button type="button" style="position:absolute;right:.5rem;top:50%;transform:translateY(-50%);'
            . 'background:none;border:none;color:#64748b;cursor:pointer;padding:.25rem" '
            . 'onclick="var i=document.getElementById(\'' . $id . '\');'
            . 'i.type=i.type===\'password\'?\'text\':\'password\';'
            . 'this.innerHTML=i.type===\'password\'?\'👁\':\'🙈\'" '
            . 'title="Toggle visibility">👁</button>'
            . '</div>';

        return $this->wrapGroup($field, $html);
    }
}
