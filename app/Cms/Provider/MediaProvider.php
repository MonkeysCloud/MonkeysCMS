<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Media\MediaConfig;
use App\Cms\Media\MediaModule;
use App\Cms\Media\MediaRepository;
use App\Cms\Media\MediaStyleRegistry;
use MonkeysLegion\Files\FilesManager;
use MonkeysLegion\Files\FilesServiceProvider;
use MonkeysLegion\Files\Image\ImageDriver;
use MonkeysLegion\Files\Image\ImageProcessor;
use MonkeysLegion\Files\Security\ContentValidator;
use MonkeysLegion\Files\Upload\UploadValidator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PDO;

/**
 * MediaProvider — Registers all media module services in the DI container.
 *
 * Services:
 *   - MediaConfig: settings from DB (group = 'media')
 *   - MediaStyleRegistry: named image styles (thumb, medium, large)
 *   - FilesManager: multi-disk storage (local, S3, GCS, Azure)
 *   - ImageProcessor: GD/Imagick image processing
 *   - MediaModule: central media facade
 */
final class MediaProvider
{
    /**
     * DI definitions for the media module.
     *
     * @return array<string, callable>
     */
    public static function getDefinitions(): array
    {
        return [
            // ── Config ──────────────────────────────────────────────
            MediaConfig::class => fn($c) => MediaConfig::fromDatabase(
                $c->get(PDO::class),
            ),

            // ── Repository ──────────────────────────────────────────
            MediaRepository::class => fn($c) => new MediaRepository(
                $c->get(PDO::class),
            ),

            // ── Image Style Registry ────────────────────────────────
            MediaStyleRegistry::class => function ($c) {
                $config = $c->get(MediaConfig::class);
                $registry = new MediaStyleRegistry();

                // Register defaults
                $registry->registerDefaults();

                // Override with admin-configured styles
                if (!empty($config->imageStyles)) {
                    $registry->registerFromConfig($config->imageStyles);
                }

                return $registry;
            },

            // ── Files Manager (monkeyslegion-files) ─────────────────
            FilesManager::class => function ($c) {
                $config = $c->get(MediaConfig::class);
                $basePath = base_path();

                $disks = $config->getDisksConfig($basePath);
                $logger = $c->has(LoggerInterface::class)
                    ? $c->get(LoggerInterface::class)
                    : new NullLogger();

                return FilesServiceProvider::create(
                    disks: $disks,
                    defaultDisk: 'media',
                    logger: $logger,
                );
            },

            // ── Upload Validator ────────────────────────────────────
            UploadValidator::class => fn($c) => new UploadValidator(
                maxSize: $c->get(MediaConfig::class)->maxFileSize,
                allowedMimes: $c->get(MediaConfig::class)->allowedMimeTypes,
                deniedExtensions: $c->get(MediaConfig::class)->deniedExtensions,
            ),

            // ── Content Validator (MIME sniffing) ───────────────────
            ContentValidator::class => fn() => new ContentValidator(),

            // ── Image Processor ─────────────────────────────────────
            ImageProcessor::class => fn($c) => new ImageProcessor(
                driver: ImageDriver::Gd,
                defaultQuality: $c->get(MediaConfig::class)->imageQuality,
                registry: $c->get(MediaStyleRegistry::class)->getConversionRegistry(),
            ),

            // ── Media Module (facade) ───────────────────────────────
            MediaModule::class => fn($c) => new MediaModule(
                config: $c->get(MediaConfig::class),
                repository: $c->get(MediaRepository::class),
                styleRegistry: $c->get(MediaStyleRegistry::class),
                files: $c->get(FilesManager::class),
                imageProcessor: $c->get(ImageProcessor::class),
                pdo: $c->get(PDO::class),
                basePath: base_path(),
                logger: $c->has(LoggerInterface::class)
                    ? $c->get(LoggerInterface::class)
                    : new NullLogger(),
            ),
        ];
    }
}
