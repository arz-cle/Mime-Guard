<?php

declare(strict_types=1);

namespace Arzou\MimeGuard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\YAML;
use Statamic\Http\Controllers\CP\CpController;

class SettingsController extends CpController
{
    protected string $settingsPath;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->settingsPath = storage_path('statamic/addons/mime-guard/settings.yaml');
    }

    public function index()
    {
        return Inertia::render('mime-guard::Settings', [
            'title' => __('mime-guard::messages.settings_title'),
            'settings' => $this->getSettings(),
            'containers' => $this->getContainers(),
            'blueprints' => $this->getBlueprints(),
            'commonMimeTypes' => $this->getCommonMimeTypes(),
            'actionUrl' => cp_route('mime-guard.update'),
            'createContainerUrl' => cp_route('asset-containers.create'),
            'trans' => $this->getTranslations(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'restricted_by_default' => 'nullable|array',
            'restricted_by_default.*' => 'string',
            'restricted_by_default_custom' => 'nullable|string',
            'containers' => 'nullable|array',
            'blueprints' => 'nullable|array',
            'logging_enabled' => 'boolean',
        ]);

        // Merge checkbox values with custom textarea values
        $restrictedTypes = array_filter($validated['restricted_by_default'] ?? []);
        $customTypes = array_filter(
            array_map('trim', explode("\n", $validated['restricted_by_default_custom'] ?? ''))
        );
        $allRestricted = array_unique(array_merge($restrictedTypes, $customTypes));

        $settings = [
            'restricted_by_default' => array_values($allRestricted),
            'containers' => $this->parseContainerRules($validated['containers'] ?? []),
            'blueprints' => $this->parseBlueprintRules($validated['blueprints'] ?? []),
            'logging' => [
                'enabled' => $request->boolean('logging_enabled'),
            ],
        ];

        $this->saveSettings($settings);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('mime-guard::messages.settings_saved'),
            ]);
        }

        return redirect()
            ->route('statamic.cp.mime-guard.index')
            ->with('success', __('mime-guard::messages.settings_saved'));
    }

    protected function getSettings(): array
    {
        $defaults = config('mime-guard');

        if (File::exists($this->settingsPath)) {
            $saved = YAML::file($this->settingsPath)->parse();

            return array_merge($defaults, $saved);
        }

        return $defaults;
    }

    protected function saveSettings(array $settings): void
    {
        $directory = dirname($this->settingsPath);

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($this->settingsPath, YAML::dump($settings));

        // Clear config cache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    protected function getContainers(): array
    {
        return AssetContainer::all()->map(function ($container) {
            return [
                'handle' => $container->handle(),
                'title' => $container->title(),
            ];
        })->values()->all();
    }

    protected function getBlueprints(): array
    {
        $blueprints = [];

        Collection::all()->each(function ($collection) use (&$blueprints) {
            $collection->entryBlueprints()->each(function ($blueprint) use ($collection, &$blueprints) {
                $blueprints[] = [
                    'handle' => $collection->handle().'::'.$blueprint->handle(),
                    'title' => $blueprint->title(),
                    'collection' => $collection->title(),
                ];
            });
        });

        return $blueprints;
    }

    protected function parseContainerRules(array $containers): array
    {
        $rules = [];

        foreach ($containers as $handle => $config) {
            // Merge checkbox values with custom textarea values
            $allowChecked = is_array($config['allow'] ?? null) ? $config['allow'] : [];
            $allowCustom = array_filter(
                array_map('trim', explode("\n", $config['allow_custom'] ?? ''))
            );
            $allow = array_values(array_unique(array_merge($allowChecked, $allowCustom)));

            if (empty($allow)) {
                continue;
            }

            $rules[$handle] = ['allow' => $allow];
        }

        return $rules;
    }

    protected function parseBlueprintRules(array $blueprints): array
    {
        $rules = [];

        foreach ($blueprints as $handle => $config) {
            // Merge checkbox values with custom textarea values
            $allowChecked = is_array($config['allow'] ?? null) ? $config['allow'] : [];
            $allowCustom = array_filter(
                array_map('trim', explode("\n", $config['allow_custom'] ?? ''))
            );
            $allow = array_values(array_unique(array_merge($allowChecked, $allowCustom)));

            if (empty($allow)) {
                continue;
            }

            $rules[$handle] = ['allow' => $allow];
        }

        return $rules;
    }

    protected function getTranslations(): array
    {
        return [
            'global_restrictions' => __('mime-guard::messages.global_restrictions'),
            'global_restrictions_help' => __('mime-guard::messages.global_restrictions_help'),
            'toggle_all' => __('mime-guard::messages.toggle_all'),
            'custom_mime_types' => __('mime-guard::messages.custom_mime_types'),
            'custom_mime_types_help' => __('mime-guard::messages.custom_mime_types_help'),
            'container_rules' => __('mime-guard::messages.container_rules'),
            'container_rules_help' => __('mime-guard::messages.container_rules_help'),
            'create_container' => __('mime-guard::messages.create_container'),
            'configured' => __('mime-guard::messages.configured'),
            'container_types_help' => __('mime-guard::messages.container_types_help'),
            'no_containers' => __('mime-guard::messages.no_containers'),
            'blueprint_rules' => __('mime-guard::messages.blueprint_rules'),
            'blueprint_rules_help' => __('mime-guard::messages.blueprint_rules_help'),
            'blueprint_types_help' => __('mime-guard::messages.blueprint_types_help'),
            'no_blueprints' => __('mime-guard::messages.no_blueprints'),
            'logging' => __('mime-guard::messages.logging'),
            'logging_enabled' => __('mime-guard::messages.logging_enabled'),
            'logging_help' => __('mime-guard::messages.logging_help'),
            'save_settings' => __('mime-guard::messages.save_settings'),
            'settings_saved' => __('mime-guard::messages.settings_saved'),
            'help_title' => __('mime-guard::messages.help_title'),
            'help_hierarchy' => __('mime-guard::messages.help_hierarchy'),
            'help_hierarchy_desc' => __('mime-guard::messages.help_hierarchy_desc'),
            'help_wildcards' => __('mime-guard::messages.help_wildcards'),
            'help_wildcards_desc' => __('mime-guard::messages.help_wildcards_desc'),
        ];
    }

    protected function getCommonMimeTypes(): array
    {
        return [
            'Images' => [
                'image/jpeg' => 'JPEG',
                'image/png' => 'PNG',
                'image/gif' => 'GIF',
                'image/webp' => 'WebP',
                'image/svg+xml' => 'SVG',
            ],
            'Documents' => [
                'application/pdf' => 'PDF',
                'application/msword' => 'Word (DOC)',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (DOCX)',
            ],
            'Archives' => [
                'application/zip' => 'ZIP',
                'application/x-rar-compressed' => 'RAR',
                'application/x-7z-compressed' => '7Z',
                'application/octet-stream' => 'Binary (generic)',
            ],
            '3D Models' => [
                'model/stl' => 'STL',
                'application/sla' => 'STL (alt)',
                'model/gltf+json' => 'GLTF',
                'model/gltf-binary' => 'GLB',
            ],
            'Videos' => [
                'video/mp4' => 'MP4',
                'video/webm' => 'WebM',
                'video/quicktime' => 'MOV',
            ],
        ];
    }
}
