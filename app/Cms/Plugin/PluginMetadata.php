<?php

declare(strict_types=1);

namespace App\Cms\Plugin;

/**
 * PluginMetadata — Immutable value object parsed from {name}.plugin.mlc.
 *
 * Example hello-world.plugin.mlc:
 *   plugin {
 *       name        = "hello-world"
 *       vendor      = "monkeyscloud"
 *       version     = "1.0.0"
 *       description = "A sample hello world plugin"
 *       author      = "MonkeysCloud"
 *       namespace   = "MonkeysCloud\\HelloWorld"
 *       provider    = "MonkeysCloud\\HelloWorld\\HelloWorldPlugin"
 *       requires    = ["core:>=2.0.0"]
 *       core        = "2.x"
 *   }
 */
final readonly class PluginMetadata
{
    /**
     * @param string       $name        Machine name (e.g. "hello-world")
     * @param string       $vendor      Vendor name (e.g. "monkeyscloud")
     * @param string       $version     SemVer version string
     * @param string       $description Human-readable description
     * @param string       $author      Author name
     * @param string       $namespace   PSR-4 namespace root for the plugin
     * @param string       $provider    FQCN of the plugin provider class
     * @param list<string> $requires    Dependency strings (e.g. "core:>=2.0.0")
     * @param string       $core        Core compatibility (e.g. "2.x")
     * @param string       $path        Absolute path to the plugin directory
     * @param string       $type        "custom" or "contrib"
     * @param string       $machineName Full machine name: "vendor/name"
     */
    public function __construct(
        public string $name,
        public string $vendor,
        public string $version,
        public string $description,
        public string $author,
        public string $namespace,
        public string $provider,
        public array  $requires,
        public string $core,
        public string $path,
        public string $type,
        public string $machineName,
    ) {}

    /**
     * Parse plugin metadata from a plugin.mlc file content.
     */
    public static function fromMlc(string $content, string $path, string $type): self
    {
        // Strip comments
        $content = preg_replace('/^\\s*#.*$/m', '', $content);

        $get = static function (string $key, string $default = '') use ($content): string {
            if (preg_match('/' . preg_quote($key) . '\\s*=\\s*"([^"]*)"/', $content, $m)) {
                return str_replace('\\\\', '\\', $m[1]);
            }
            if (preg_match('/' . preg_quote($key) . '\\s*=\\s*(\\S+)/', $content, $m)) {
                return str_replace('\\\\', '\\', $m[1]);
            }
            return $default;
        };

        $getArray = static function (string $key) use ($content): array {
            if (preg_match('/' . preg_quote($key) . '\\s*=\\s*\\[([^\\]]*)\\]/', $content, $m)) {
                preg_match_all('/"([^"]*)"/', $m[1], $items);
                return $items[1] ?? [];
            }
            return [];
        };

        $name   = $get('name');
        $vendor = $get('vendor');

        return new self(
            name:        $name,
            vendor:      $vendor,
            version:     $get('version', '1.0.0'),
            description: $get('description'),
            author:      $get('author'),
            namespace:   $get('namespace'),
            provider:    $get('provider'),
            requires:    $getArray('requires'),
            core:        $get('core', '2.x'),
            path:        $path,
            type:        $type,
            machineName: $vendor . '/' . $name,
        );
    }
}
