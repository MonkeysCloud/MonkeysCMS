<?php

declare(strict_types=1);

namespace App\Cms\Webform;

use App\Cms\Form\ValidationResult;

/**
 * WebformValidator — Server-side validation for webform submissions.
 *
 * Validates submitted data against the field configuration stored in the WebformEntity.
 * Handles required, min/max length, pattern, email format, and file rules.
 */
final class WebformValidator
{
    /**
     * Validate submission data against form field config.
     *
     * @param list<array> $fields   Field definitions from WebformEntity
     * @param array<string, mixed> $data  Submitted values
     * @param array<string, mixed> $files Uploaded files (field_name => file info)
     * @return ValidationResult
     */
    public function validate(array $fields, array $data, array $files = []): ValidationResult
    {
        $errors = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            if ($name === '' || ($field['type'] ?? '') === 'hidden') {
                continue;
            }

            $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
            $value = $data[$name] ?? null;
            $required = !empty($field['required']);
            $rules = $field['rules'] ?? [];

            // ── Required ────────────────────────────────────────────────
            if ($required) {
                if ($field['type'] === 'file') {
                    if (empty($files[$name])) {
                        $errors[$name] = "{$label} is required.";
                        continue;
                    }
                } elseif ($value === null || $value === '' || $value === []) {
                    $errors[$name] = "{$label} is required.";
                    continue;
                }
            }

            // Skip further validation if empty and not required
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // ── Type-specific validation ────────────────────────────────
            $typeError = $this->validateType($field['type'] ?? 'text', $value, $label);
            if ($typeError !== null) {
                $errors[$name] = $typeError;
                continue;
            }

            // ── Rule-based validation ───────────────────────────────────
            if (is_string($value)) {
                if (isset($rules['min']) && mb_strlen($value) < (int) $rules['min']) {
                    $errors[$name] = "{$label} must be at least {$rules['min']} characters.";
                    continue;
                }
                if (isset($rules['max']) && mb_strlen($value) > (int) $rules['max']) {
                    $errors[$name] = "{$label} must not exceed {$rules['max']} characters.";
                    continue;
                }
                if (!empty($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                    $errors[$name] = "{$label} has an invalid format.";
                    continue;
                }
            }

            if (is_numeric($value)) {
                if (isset($rules['min_value']) && (float) $value < (float) $rules['min_value']) {
                    $errors[$name] = "{$label} must be at least {$rules['min_value']}.";
                    continue;
                }
                if (isset($rules['max_value']) && (float) $value > (float) $rules['max_value']) {
                    $errors[$name] = "{$label} must not exceed {$rules['max_value']}.";
                    continue;
                }
            }
        }

        return new ValidationResult($errors);
    }

    /**
     * Validate file uploads against field config.
     *
     * @param array $field  Field definition
     * @param array $file   Upload info (name, type, tmp_name, size, error)
     * @return string|null  Error message or null
     */
    public function validateFile(array $field, array $file): ?string
    {
        $label = $field['label'] ?? 'File';
        $rules = $field['rules'] ?? [];

        // Check upload error
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return "{$label}: Upload failed.";
        }

        // Max file size (in bytes)
        $maxSize = $rules['max_file_size'] ?? (10 * 1024 * 1024); // 10MB default
        if (($file['size'] ?? 0) > $maxSize) {
            $maxMb = round($maxSize / 1048576, 1);
            return "{$label} must not exceed {$maxMb}MB.";
        }

        // Allowed MIME types
        $allowed = $rules['allowed_types'] ?? null;
        if (is_array($allowed) && !empty($allowed)) {
            $mime = $file['type'] ?? '';
            if (!in_array($mime, $allowed, true)) {
                return "{$label}: File type '{$mime}' is not allowed.";
            }
        }

        // Allowed extensions
        $allowedExt = $rules['allowed_extensions'] ?? null;
        if (is_array($allowedExt) && !empty($allowedExt)) {
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                return "{$label}: Extension '.{$ext}' is not allowed.";
            }
        }

        return null;
    }

    /**
     * Type-specific value validation.
     */
    private function validateType(string $type, mixed $value, string $label): ?string
    {
        return match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? "{$label} must be a valid email address."
                : null,

            'url' => filter_var($value, FILTER_VALIDATE_URL) === false
                ? "{$label} must be a valid URL."
                : null,

            'number' => !is_numeric($value)
                ? "{$label} must be a number."
                : null,

            'phone' => !preg_match('/^[\d\s\-\+\(\)\.]{7,20}$/', (string) $value)
                ? "{$label} must be a valid phone number."
                : null,

            'date' => !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
                ? "{$label} must be a valid date (YYYY-MM-DD)."
                : null,

            default => null,
        };
    }
}
