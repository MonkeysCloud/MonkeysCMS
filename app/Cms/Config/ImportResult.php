<?php

declare(strict_types=1);

namespace App\Cms\Config;

/**
 * ImportResult — Value object summarizing the outcome of a config import operation.
 */
final class ImportResult
{
    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $updated = [];

    /** @var list<string> */
    public array $skipped = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    public function addCreated(string $item): void
    {
        $this->created[] = $item;
    }

    public function addUpdated(string $item): void
    {
        $this->updated[] = $item;
    }

    public function addSkipped(string $item): void
    {
        $this->skipped[] = $item;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function merge(self $other): void
    {
        $this->created  = array_merge($this->created, $other->created);
        $this->updated  = array_merge($this->updated, $other->updated);
        $this->skipped  = array_merge($this->skipped, $other->skipped);
        $this->errors   = array_merge($this->errors, $other->errors);
        $this->warnings = array_merge($this->warnings, $other->warnings);
    }

    public function toArray(): array
    {
        return [
            'created'  => $this->created,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
            'summary'  => sprintf(
                '%d created, %d updated, %d skipped, %d errors',
                count($this->created),
                count($this->updated),
                count($this->skipped),
                count($this->errors),
            ),
        ];
    }
}
