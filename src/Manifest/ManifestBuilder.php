<?php

namespace MirkoHaaser\Teams\Manifest;

use MirkoHaaser\Teams\Exceptions\TeamsConfigurationError;
use ZipArchive;

/**
 * Builds the Teams app manifest from config('teams') and packs it with the
 * icons. Limits are checked before anything is written.
 */
class ManifestBuilder
{
    public const SCHEMA_VERSION = '1.29';

    /**
     * @param  array<string, mixed>  $config  the whole teams config
     */
    public function __construct(private readonly array $config) {}

    /**
     * @return list<string> problems; empty when the manifest can be built
     */
    public function problems(): array
    {
        $m = (array) ($this->config['manifest'] ?? []);
        $problems = [];

        foreach (['app_id' => 'TEAMS_APP_ID', 'client_id' => 'TEAMS_CLIENT_ID'] as $key => $env) {
            if ((string) ($this->config[$key] ?? '') === '') {
                $problems[] = "{$key} is empty ({$env})";
            }
        }

        if ((string) ($m['host'] ?? '') === '') {
            $problems[] = 'manifest.host is empty (TEAMS_HOST)';
        }

        if (mb_strlen((string) ($m['name']['short'] ?? '')) > 30) {
            $problems[] = 'manifest.name.short is longer than 30 characters';
        }

        foreach (['short' => 80, 'full' => 4000] as $field => $max) {
            $text = (string) ($m['description'][$field] ?? '');
            if ($text === '' || mb_strlen($text) > $max) {
                $problems[] = "manifest.description.{$field} must have 1 to {$max} characters";
            }
        }

        foreach ((array) ($m['activity_types'] ?? []) as $type => $definition) {
            if (mb_strlen((string) ($definition['template'] ?? '')) > 128) {
                $problems[] = "activity type {$type}: templateText is longer than 128 characters";
            }
        }

        if (count((array) ($m['tabs'] ?? [])) > 16) {
            $problems[] = 'more than 16 static tabs';
        }

        foreach (['color' => 192, 'outline' => 32] as $icon => $size) {
            $path = (string) ($m['icons'][$icon] ?? '');
            $info = is_file($path) ? @getimagesize($path) : false;

            if ($info === false) {
                $problems[] = "icon {$icon} not found or not an image: {$path}";
            } elseif ($info[0] !== $size || $info[1] !== $size) {
                $problems[] = "icon {$icon} must be {$size}x{$size}, is {$info[0]}x{$info[1]}";
            }
        }

        return $problems;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $c = $this->config;
        $m = (array) ($c['manifest'] ?? []);
        $host = (string) $m['host'];
        $clientId = (string) $c['client_id'];
        $botId = (string) ($c['bot']['app_id'] ?? '');
        $activityTypes = (array) ($m['activity_types'] ?? []);

        $manifest = [
            '$schema' => 'https://developer.microsoft.com/json-schemas/teams/v'.self::SCHEMA_VERSION.'/MicrosoftTeams.schema.json',
            'manifestVersion' => self::SCHEMA_VERSION,
            'version' => (string) ($m['version'] ?? '1.0.0'),
            'id' => (string) $c['app_id'],
            'developer' => [
                'name' => (string) ($m['developer']['name'] ?? ''),
                'websiteUrl' => (string) ($m['developer']['website_url'] ?? "https://{$host}"),
                'privacyUrl' => (string) ($m['developer']['privacy_url'] ?? "https://{$host}"),
                'termsOfUseUrl' => (string) ($m['developer']['terms_url'] ?? "https://{$host}"),
            ],
            'name' => ['short' => (string) $m['name']['short'], 'full' => (string) ($m['name']['full'] ?? $m['name']['short'])],
            'description' => ['short' => (string) $m['description']['short'], 'full' => (string) $m['description']['full']],
            'icons' => ['color' => 'color.png', 'outline' => 'outline.png'],
            'accentColor' => (string) ($m['accent_color'] ?? '#FFFFFF'),
        ];

        $tabs = [];
        foreach ((array) ($m['tabs'] ?? []) as $entityId => $tab) {
            $tabs[] = array_filter([
                'entityId' => (string) $entityId,
                'name' => (string) $tab['name'],
                'contentUrl' => "https://{$host}".$tab['path'],
                'websiteUrl' => isset($tab['website_path']) ? "https://{$host}".$tab['website_path'] : null,
                'scopes' => ['personal'],
                'context' => ['personalTab'],
            ], static fn ($v) => $v !== null);
        }
        if ($tabs !== []) {
            $manifest['staticTabs'] = $tabs;
        }

        if ($botId !== '') {
            $manifest['bots'] = [[
                'botId' => $botId,
                'scopes' => ['personal'],
                'supportsFiles' => false,
                'isNotificationOnly' => false,
            ]];

            $commands = (array) ($m['bot_commands'] ?? []);
            if ($commands !== []) {
                $manifest['bots'][0]['commandLists'] = [[
                    'scopes' => ['personal'],
                    'commands' => array_map(
                        fn (string $title, string $description) => ['title' => $title, 'description' => $description],
                        array_keys($commands),
                        array_values($commands),
                    ),
                ]];
            }
        }

        // With a bot in the app the resource must use the botid- form.
        $manifest['webApplicationInfo'] = [
            'id' => $clientId,
            'resource' => $botId !== '' ? "api://{$host}/botid-{$clientId}" : "api://{$host}/{$clientId}",
        ];

        if ($activityTypes !== []) {
            $manifest['activities'] = ['activityTypes' => array_map(
                fn (string $type, array $d) => ['type' => $type, 'description' => (string) $d['description'], 'templateText' => (string) $d['template']],
                array_keys($activityTypes),
                array_values($activityTypes),
            )];
            $manifest['authorization'] = ['permissions' => ['resourceSpecific' => [
                ['type' => 'Application', 'name' => 'TeamsActivity.Send.User'],
            ]]];
        }

        $manifest['validDomains'] = [$host];
        $manifest['permissions'] = ['identity'];

        return $manifest;
    }

    /** Write the zip and return its path. */
    public function build(): string
    {
        if ($problems = $this->problems()) {
            throw new TeamsConfigurationError("Manifest cannot be built:\n- ".implode("\n- ", $problems));
        }

        $m = (array) $this->config['manifest'];
        $dir = (string) ($m['output'] ?? sys_get_temp_dir());
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new TeamsConfigurationError("Cannot create {$dir}");
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower((string) $m['name']['short'])) ?: 'teams-app';
        $path = rtrim($dir, '/')."/{$slug}-{$m['version']}.zip";

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new TeamsConfigurationError("Cannot write {$path}");
        }

        $zip->addFromString('manifest.json', (string) json_encode($this->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->addFile((string) $m['icons']['color'], 'color.png');
        $zip->addFile((string) $m['icons']['outline'], 'outline.png');
        $zip->close();

        return $path;
    }
}
