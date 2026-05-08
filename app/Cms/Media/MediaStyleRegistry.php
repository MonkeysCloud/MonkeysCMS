<?php

declare(strict_types=1);

namespace App\Cms\Media;

use MonkeysLegion\Files\Image\Conversion;
use MonkeysLegion\Files\Image\ConversionRegistry;
use MonkeysLegion\Files\Image\ImageFormat;

/**
 * MediaStyleRegistry — Named image styles for the media module.
 *
 * Wraps the monkeyslegion-files ConversionRegistry with CMS-specific
 * defaults (thumb, medium, large) and allows admin configuration.
 *
 * PHP 8.4+
 */
final class MediaStyleRegistry
{
    private readonly ConversionRegistry $registry;

    /** @var array<string, array{width: int, height: int, fit: string}> */
    private array $definitions = [];

    /** Number of registered styles */
    public int $count {
        get => count($this->definitions);
    }

    /** Names of all registered styles */
    public array $names {
        get => array_keys($this->definitions);
    }

    public function __construct(?ConversionRegistry $registry = null)
    {
        $this->registry = $registry ?? new ConversionRegistry();
    }

    /**
     * Register the built-in default styles.
     */
    public function registerDefaults(): self
    {
        $this->register('thumb', 150, 150, 'cover');
        $this->register('medium', 600, 600, 'contain');
        $this->register('large', 1200, 1200, 'contain');

        return $this;
    }

    /**
     * Register an image style.
     */
    public function register(string $name, int $width, int $height, string $fit = 'cover'): self
    {
        $this->definitions[$name] = [
            'width'  => $width,
            'height' => $height,
            'fit'    => $fit,
        ];

        $this->registry->register(new Conversion(
            name: $name,
            width: $width,
            height: $height,
            fit: $fit,
        ));

        return $this;
    }

    /**
     * Register styles from a config array (admin settings).
     *
     * @param array<string, array{width: int, height: int, fit?: string}> $styles
     */
    public function registerFromConfig(array $styles): self
    {
        foreach ($styles as $name => $config) {
            $this->register(
                name: $name,
                width: (int) ($config['width'] ?? 150),
                height: (int) ($config['height'] ?? 150),
                fit: (string) ($config['fit'] ?? 'cover'),
            );
        }

        return $this;
    }

    /**
     * Get a conversion by name.
     */
    public function get(string $name): ?Conversion
    {
        return $this->registry->get($name);
    }

    /**
     * Get all registered conversions.
     *
     * @return array<string, Conversion>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    /**
     * Get style definitions (for admin UI / serialization).
     *
     * @return array<string, array{width: int, height: int, fit: string}>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * Get the default style definitions (before any admin customization).
     *
     * @return array<string, array{width: int, height: int, fit: string}>
     */
    public static function getDefaults(): array
    {
        return [
            'thumb'  => ['width' => 150, 'height' => 150, 'fit' => 'cover'],
            'medium' => ['width' => 600, 'height' => 600, 'fit' => 'contain'],
            'large'  => ['width' => 1200, 'height' => 1200, 'fit' => 'contain'],
        ];
    }

    /**
     * Build the suffix path for a style (e.g., "styles/thumb").
     */
    public function stylePath(string $name): string
    {
        return 'styles/' . $name;
    }

    /**
     * Get the underlying ConversionRegistry.
     */
    public function getConversionRegistry(): ConversionRegistry
    {
        return $this->registry;
    }
}
