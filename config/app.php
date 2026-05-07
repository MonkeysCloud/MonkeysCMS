<?php
declare(strict_types=1);

/**
 * MonkeysLegion v2 — DI Container Overrides.
 *
 * This file is for interface-to-concrete bindings and complex
 * factory definitions ONLY. All typed configuration belongs
 * in the .mlc config files.
 *
 * @see https://monkeyslegion.com/docs/di
 */
return array_merge(
    [
        // Bridge CMS PDO injection to framework's connection manager
        \PDO::class => fn($c): \PDO => $c->get(
            \MonkeysLegion\Database\Contracts\ConnectionInterface::class
        )->pdo(),
    ],
    \App\Cms\Provider\ContentProvider::getDefinitions(),
    \App\Cms\Provider\MediaProvider::getDefinitions(),
    \App\Cms\Provider\FormProvider::getDefinitions(),
    \App\Cms\Provider\ScheduleProvider::getDefinitions(),
    \App\Cms\Provider\PluginProvider::getDefinitions(),
    \App\Cms\Provider\AdminMenuProvider::getDefinitions(),
);