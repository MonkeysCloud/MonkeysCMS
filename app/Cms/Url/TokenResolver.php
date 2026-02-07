<?php

declare(strict_types=1);

namespace App\Cms\Url;

/**
 * TokenResolver - Modern URL pattern token resolution system
 *
 * Supports:
 * - Simple tokens: {token}
 * - Tokens with modifiers: {token|modifier}
 * - Modifier arguments: {token|modifier:arg}
 * - Chained modifiers: {token|mod1|mod2}
 *
 * Built-in tokens: title, slug, id, uuid, type, author, author.id, created, category
 * Built-in modifiers: slug, lower, upper, truncate, format, raw
 */
class TokenResolver
{
    /**
     * @var array<string, callable>
     */
    private array $tokens = [];

    /**
     * @var array<string, callable>
     */
    private array $modifiers = [];

    public function __construct()
    {
        $this->registerBuiltInTokens();
        $this->registerBuiltInModifiers();
    }

    /**
     * Resolve all tokens in a pattern string
     */
    public function resolve(string $pattern, array $context): string
    {
        // Match {token}, {token|modifier}, {token|modifier:arg}, {token|mod1|mod2}
        return (string) preg_replace_callback(
            '/\{([a-z_][a-z0-9_.]*(?:\|[a-z_][a-z0-9_:]*)*)\}/i',
            fn(array $matches) => $this->resolveToken($matches[1], $context),
            $pattern
        );
    }

    /**
     * Register a custom token
     */
    public function registerToken(string $name, callable $resolver): self
    {
        $this->tokens[$name] = $resolver;
        return $this;
    }

    /**
     * Register a custom modifier
     */
    public function registerModifier(string $name, callable $modifier): self
    {
        $this->modifiers[$name] = $modifier;
        return $this;
    }

    /**
     * Get all available tokens with descriptions
     */
    public function getAvailableTokens(): array
    {
        return [
            'title' => 'Content title (auto-slugified)',
            'slug' => 'Content slug field',
            'id' => 'Content ID',
            'uuid' => 'Content UUID',
            'type' => 'Content type machine name',
            'author' => 'Author username',
            'author.id' => 'Author user ID',
            'created' => 'Creation date (format: Y/m/d)',
            'category' => 'Primary taxonomy term',
        ];
    }

    /**
     * Get all available modifiers with descriptions
     */
    public function getAvailableModifiers(): array
    {
        return [
            'slug' => 'Convert to URL-safe slug',
            'lower' => 'Convert to lowercase',
            'upper' => 'Convert to uppercase',
            'truncate:N' => 'Truncate to N characters',
            'format:X' => 'Date format (e.g., format:Y-m)',
            'raw' => 'No auto-slugification',
        ];
    }

    /**
     * Resolve a single token expression (e.g., "title|slug|truncate:30")
     */
    private function resolveToken(string $expression, array $context): string
    {
        $parts = explode('|', $expression);
        $tokenName = array_shift($parts);
        $modifiers = $parts;

        // Get raw token value
        $value = $this->getTokenValue($tokenName, $context);

        // Apply modifiers
        $hasRawModifier = false;
        foreach ($modifiers as $modifier) {
            if ($modifier === 'raw') {
                $hasRawModifier = true;
                continue;
            }
            $value = $this->applyModifier($modifier, $value, $context);
        }

        // Auto-slugify strings unless 'raw' modifier was used
        if (!$hasRawModifier && is_string($value) && !$this->isAlreadySlugified($value)) {
            $value = $this->slugify($value);
        }

        return (string) $value;
    }

    /**
     * Get the raw value for a token
     */
    private function getTokenValue(string $name, array $context): mixed
    {
        // Check registered custom tokens first
        if (isset($this->tokens[$name])) {
            return call_user_func($this->tokens[$name], $context);
        }

        // Handle nested tokens (e.g., author.id)
        if (str_contains($name, '.')) {
            return $this->getNestedValue($name, $context);
        }

        // Direct context lookup
        return $context[$name] ?? '';
    }

    /**
     * Get nested value (e.g., author.id -> $context['author']['id'])
     */
    private function getNestedValue(string $path, array $context): mixed
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $value = $value->$key;
            } else {
                return '';
            }
        }

        return $value;
    }

    /**
     * Apply a modifier to a value
     */
    private function applyModifier(string $modifier, mixed $value, array $context): mixed
    {
        // Parse modifier:argument
        $parts = explode(':', $modifier, 2);
        $modifierName = $parts[0];
        $argument = $parts[1] ?? null;

        // Check registered custom modifiers
        if (isset($this->modifiers[$modifierName])) {
            return call_user_func($this->modifiers[$modifierName], $value, $argument, $context);
        }

        return $value;
    }

    /**
     * Register built-in tokens
     */
    private function registerBuiltInTokens(): void
    {
        $this->tokens['title'] = fn(array $c) => $c['title'] ?? '';
        $this->tokens['slug'] = fn(array $c) => $c['slug'] ?? '';
        $this->tokens['id'] = fn(array $c) => $c['id'] ?? '';
        $this->tokens['uuid'] = fn(array $c) => $c['uuid'] ?? '';
        $this->tokens['type'] = fn(array $c) => $c['type'] ?? $c['content_type'] ?? '';
        
        $this->tokens['author'] = fn(array $c) => $c['author']['name'] ?? $c['author_name'] ?? '';
        $this->tokens['author.id'] = fn(array $c) => $c['author']['id'] ?? $c['author_id'] ?? '';
        
        $this->tokens['created'] = function(array $c): string {
            $date = $c['created_at'] ?? $c['created'] ?? null;
            if ($date instanceof \DateTimeInterface) {
                return $date->format('Y/m/d');
            }
            if (is_string($date)) {
                return date('Y/m/d', strtotime($date));
            }
            return date('Y/m/d');
        };

        $this->tokens['category'] = fn(array $c) => $c['category']['name'] ?? $c['category'] ?? '';
    }

    /**
     * Register built-in modifiers
     */
    private function registerBuiltInModifiers(): void
    {
        $this->modifiers['slug'] = fn($v) => $this->slugify((string) $v);
        
        $this->modifiers['lower'] = fn($v) => strtolower((string) $v);
        
        $this->modifiers['upper'] = fn($v) => strtoupper((string) $v);
        
        $this->modifiers['truncate'] = function($v, $arg) {
            $length = (int) ($arg ?? 50);
            $str = (string) $v;
            return mb_strlen($str) > $length ? mb_substr($str, 0, $length) : $str;
        };

        $this->modifiers['format'] = function($v, $arg, $context) {
            $format = $arg ?? 'Y-m-d';
            if ($v instanceof \DateTimeInterface) {
                return $v->format($format);
            }
            if (is_string($v) && !empty($v)) {
                return date($format, strtotime($v));
            }
            // If value is empty, use created_at from context
            $date = $context['created_at'] ?? $context['created'] ?? null;
            if ($date instanceof \DateTimeInterface) {
                return $date->format($format);
            }
            if (is_string($date)) {
                return date($format, strtotime($date));
            }
            return date($format);
        };
    }

    /**
     * Convert string to URL-safe slug
     */
    private function slugify(string $text): string
    {
        // Transliterate non-ASCII characters
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: strtolower($text);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace non-alphanumeric with hyphens
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remove leading/trailing hyphens
        return trim($text, '-');
    }

    /**
     * Check if a string appears to already be slugified
     */
    private function isAlreadySlugified(string $text): bool
    {
        // If it only contains lowercase letters, numbers, and hyphens, it's probably a slug
        return (bool) preg_match('/^[a-z0-9-]+$/', $text);
    }
}
