@extends('statamic::layout')

@section('title', $title)

@section('content')
    <style>
        /* ===== Pill Toggles ===== */
        .mime-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid;
            cursor: pointer;
            font-size: 0.8125rem;
            line-height: 1.25rem;
            transition: all 0.15s ease;
            user-select: none;
            background: rgb(249 250 251);
            border-color: rgb(229 231 235);
            color: rgb(156 163 175);
        }
        .mime-pill:hover {
            border-color: rgb(209 213 219);
            color: rgb(107 114 128);
        }
        .mime-pill::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 1.5px solid currentColor;
            opacity: 0.4;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .mime-pill:has(input:checked)::before {
            background: currentColor;
            opacity: 1;
        }
        .mime-pill.pill-wildcard {
            border-style: dashed;
            font-weight: 500;
        }

        /* Block pills (red when active) */
        .mime-pill.pill-block:has(input:checked) {
            background: rgb(254 242 242);
            border-color: rgb(252 165 165);
            color: rgb(185 28 28);
        }
        .mime-pill.pill-block:has(input:checked):hover {
            background: rgb(254 226 226);
        }

        /* Allow pills (blue when active) */
        .mime-pill.pill-allow:has(input:checked) {
            background: rgb(239 246 255);
            border-color: rgb(147 197 253);
            color: rgb(29 78 216);
        }
        .mime-pill.pill-allow:has(input:checked):hover {
            background: rgb(219 234 254);
        }

        /* Dark mode */
        .dark .mime-pill {
            background: rgba(255 255 255 / 0.03);
            border-color: rgb(55 65 81);
            color: rgb(107 114 128);
        }
        .dark .mime-pill:hover {
            border-color: rgb(75 85 99);
            color: rgb(156 163 175);
        }
        .dark .mime-pill.pill-block:has(input:checked) {
            background: rgba(239 68 68 / 0.1);
            border-color: rgba(239 68 68 / 0.3);
            color: rgb(248 113 113);
        }
        .dark .mime-pill.pill-block:has(input:checked):hover {
            background: rgba(239 68 68 / 0.15);
        }
        .dark .mime-pill.pill-allow:has(input:checked) {
            background: rgba(59 130 246 / 0.1);
            border-color: rgba(59 130 246 / 0.3);
            color: rgb(96 165 250);
        }
        .dark .mime-pill.pill-allow:has(input:checked):hover {
            background: rgba(59 130 246 / 0.15);
        }

        /* ===== Toggle Switch ===== */
        .toggle-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        .toggle-track {
            width: 44px;
            height: 24px;
            border-radius: 12px;
            background: rgb(209 213 219);
            position: relative;
            transition: background 0.2s ease;
            flex-shrink: 0;
        }
        .toggle-switch input:checked + .toggle-track {
            background: rgb(34 197 94);
        }
        .toggle-track::after {
            content: '';
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        }
        .toggle-switch input:checked + .toggle-track::after {
            transform: translateX(20px);
        }
        .dark .toggle-track {
            background: rgb(75 85 99);
        }
    </style>

    <header class="flex flex-wrap items-center justify-between gap-4 px-2 sm:px-0 py-6 max-md:pb-8 md:py-8">
        <h1 class="text-[25px] leading-[1.25] st-text-legibility font-medium antialiased flex items-center gap-2.5 md:flex-1">
            <div class="size-5 relative">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5 text-gray-500">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="M9 12l2 2 4-4"/>
                </svg>
            </div>
            {{ $title }}
        </h1>
    </header>

    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-600 dark:text-green-400 px-4 py-3 rounded-xl mb-6">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ cp_route('mime-guard.update') }}" method="POST">
        @csrf

        {{-- Global Restrictions --}}
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ __('mime-guard::messages.global_restrictions') }}</h2>
                        <button type="button" id="toggle-all-mime" class="text-sm cursor-pointer bg-linear-to-b from-white to-gray-50 dark:from-gray-850 dark:to-gray-900 border border-gray-300 dark:border-gray-700/80 shadow-sm px-3 py-1.5 rounded-lg transition-colors hover:to-gray-100 dark:hover:to-gray-850 text-gray-900 dark:text-gray-300">
                            {{ __('mime-guard::messages.toggle_all') }}
                        </button>
                    </div>
                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-1">{{ __('mime-guard::messages.global_restrictions_help') }}</p>
                </header>

                <div id="mime-checkboxes" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    @foreach($commonMimeTypes as $category => $types)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50 dark:bg-gray-900 mime-category-card">
                            <div class="flex items-center justify-between mb-3">
                                <button type="button" class="category-toggle font-medium text-sm text-gray-925 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors">{{ $category }}</button>
                                <span class="category-count text-xs text-gray-400 dark:text-gray-500 tabular-nums"></span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($types as $mime => $label)
                                    <label class="mime-pill pill-block {{ str_ends_with($mime, '/*') ? 'pill-wildcard' : '' }}">
                                        <input
                                            type="checkbox"
                                            name="restricted_by_default[]"
                                            value="{{ $mime }}"
                                            {{ in_array($mime, $settings['restricted_by_default'] ?? []) ? 'checked' : '' }}
                                            class="sr-only"
                                        >
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    <label class="text-sm font-medium text-gray-925 dark:text-gray-300 mb-1.5 block">{{ __('mime-guard::messages.custom_mime_types') }}</label>
                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mb-2">{{ __('mime-guard::messages.custom_mime_types_help') }}</p>
                    <textarea
                        name="restricted_by_default_custom"
                        class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-925 dark:text-gray-300 placeholder:text-gray-500 dark:placeholder:text-gray-400/85 shadow-sm rounded-lg px-3 py-2 font-mono text-sm"
                        rows="3"
                        placeholder="application/x-custom&#10;text/csv"
                    >{{ implode("\n", array_diff($settings['restricted_by_default'] ?? [], array_keys(array_merge(...array_values($commonMimeTypes))))) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Container Rules --}}
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-6 flex items-start justify-between">
                    <div>
                        <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ __('mime-guard::messages.container_rules') }}</h2>
                        <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-1">{{ __('mime-guard::messages.container_rules_help') }}</p>
                    </div>
                    <a href="{{ cp_route('asset-containers.create') }}" class="inline-flex items-center justify-center font-medium bg-linear-to-b from-white to-gray-50 dark:from-gray-850 dark:to-gray-900 hover:to-gray-100 dark:hover:to-gray-850 text-gray-900 dark:text-gray-300 border border-gray-300 dark:border-gray-700/80 shadow-sm px-3 h-8 text-sm rounded-lg">
                        {{ __('mime-guard::messages.create_container') }}
                    </a>
                </header>

                @if(count($containers) > 0)
                    <div class="space-y-3">
                        @foreach($containers as $container)
                            @php
                                $containerRules = $settings['containers'][$container['handle']] ?? [];
                                $hasRules = !empty($containerRules['allow']);
                            @endphp
                            <div class="border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900 collapsible-card" data-collapsed="true">
                                <button type="button" class="collapsible-header w-full flex items-center justify-between p-4 text-left hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition-colors">
                                    <h3 class="font-medium text-gray-925 dark:text-gray-300">
                                        <span class="text-gray-500 dark:text-gray-500">{{ $container['handle'] }}</span>
                                        <span class="text-gray-400 mx-1">&mdash;</span>
                                        <span>{{ $container['title'] }}</span>
                                        @if($hasRules)
                                            <span class="ml-2 text-xs bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-full">{{ __('mime-guard::messages.configured') }}</span>
                                        @endif
                                    </h3>
                                    <svg class="collapsible-icon w-5 h-5 text-gray-400 dark:text-gray-500 transform transition-transform -rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <div class="collapsible-content hidden px-4 pb-4">
                                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mb-3">{{ __('mime-guard::messages.container_types_help') }}</p>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-3">
                                        @foreach($commonMimeTypes as $category => $types)
                                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800 mime-category-card">
                                                <div class="flex items-center justify-between mb-2">
                                                    <button type="button" class="category-toggle font-medium text-xs text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors">{{ $category }}</button>
                                                    <span class="category-count text-xs text-gray-400 dark:text-gray-500 tabular-nums"></span>
                                                </div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($types as $mime => $label)
                                                        <label class="mime-pill pill-allow {{ str_ends_with($mime, '/*') ? 'pill-wildcard' : '' }}">
                                                            <input
                                                                type="checkbox"
                                                                name="containers[{{ $container['handle'] }}][allow][]"
                                                                value="{{ $mime }}"
                                                                {{ in_array($mime, $containerRules['allow'] ?? []) ? 'checked' : '' }}
                                                                class="sr-only"
                                                            >
                                                            <span title="{{ $mime }}">{{ $label }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div>
                                        <label class="text-xs font-medium text-gray-700 dark:text-gray-400 mb-1 block">{{ __('mime-guard::messages.custom_mime_types') }}</label>
                                        <textarea
                                            name="containers[{{ $container['handle'] }}][allow_custom]"
                                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-925 dark:text-gray-300 placeholder:text-gray-500 dark:placeholder:text-gray-400/85 shadow-sm rounded-lg px-3 py-2 font-mono text-xs"
                                            rows="2"
                                            placeholder="custom/type"
                                        >{{ implode("\n", array_diff($containerRules['allow'] ?? [], array_keys(array_merge(...array_values($commonMimeTypes))))) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400 italic">{{ __('mime-guard::messages.no_containers') }}</p>
                @endif
            </div>
        </div>

        {{-- Blueprint Rules --}}
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-6">
                    <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ __('mime-guard::messages.blueprint_rules') }}</h2>
                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-1">{{ __('mime-guard::messages.blueprint_rules_help') }}</p>
                </header>

                @if(count($blueprints) > 0)
                    <div class="space-y-3">
                        @foreach($blueprints as $blueprint)
                            @php
                                $blueprintRules = $settings['blueprints'][$blueprint['handle']] ?? [];
                                $hasRules = !empty($blueprintRules['allow']);
                            @endphp
                            <div class="border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900 collapsible-card" data-collapsed="true">
                                <button type="button" class="collapsible-header w-full flex items-center justify-between p-4 text-left hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition-colors">
                                    <h3 class="font-medium text-gray-925 dark:text-gray-300">
                                        <span>{{ $blueprint['title'] }}</span>
                                        <span class="text-gray-500 dark:text-gray-500 text-sm font-normal ml-2">{{ $blueprint['handle'] }}</span>
                                        @if($hasRules)
                                            <span class="ml-2 text-xs bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-full">{{ __('mime-guard::messages.configured') }}</span>
                                        @endif
                                    </h3>
                                    <svg class="collapsible-icon w-5 h-5 text-gray-400 dark:text-gray-500 transform transition-transform -rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <div class="collapsible-content hidden px-4 pb-4">
                                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mb-3">{{ __('mime-guard::messages.blueprint_types_help') }}</p>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-3">
                                        @foreach($commonMimeTypes as $category => $types)
                                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800 mime-category-card">
                                                <div class="flex items-center justify-between mb-2">
                                                    <button type="button" class="category-toggle font-medium text-xs text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors">{{ $category }}</button>
                                                    <span class="category-count text-xs text-gray-400 dark:text-gray-500 tabular-nums"></span>
                                                </div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($types as $mime => $label)
                                                        <label class="mime-pill pill-allow {{ str_ends_with($mime, '/*') ? 'pill-wildcard' : '' }}">
                                                            <input
                                                                type="checkbox"
                                                                name="blueprints[{{ $blueprint['handle'] }}][allow][]"
                                                                value="{{ $mime }}"
                                                                {{ in_array($mime, $blueprintRules['allow'] ?? []) ? 'checked' : '' }}
                                                                class="sr-only"
                                                            >
                                                            <span title="{{ $mime }}">{{ $label }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div>
                                        <label class="text-xs font-medium text-gray-700 dark:text-gray-400 mb-1 block">{{ __('mime-guard::messages.custom_mime_types') }}</label>
                                        <textarea
                                            name="blueprints[{{ $blueprint['handle'] }}][allow_custom]"
                                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-925 dark:text-gray-300 placeholder:text-gray-500 dark:placeholder:text-gray-400/85 shadow-sm rounded-lg px-3 py-2 font-mono text-xs"
                                            rows="2"
                                            placeholder="custom/type"
                                        >{{ implode("\n", array_diff($blueprintRules['allow'] ?? [], array_keys(array_merge(...array_values($commonMimeTypes))))) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400 italic">{{ __('mime-guard::messages.no_blueprints') }}</p>
                @endif
            </div>
        </div>

        {{-- Logging --}}
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-4">
                    <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ __('mime-guard::messages.logging') }}</h2>
                </header>

                <label class="toggle-switch flex items-center gap-3">
                    <input
                        type="checkbox"
                        name="logging_enabled"
                        value="1"
                        {{ ($settings['logging']['enabled'] ?? true) ? 'checked' : '' }}
                        class="sr-only"
                    >
                    <span class="toggle-track"></span>
                    <span class="text-gray-925 dark:text-gray-300">{{ __('mime-guard::messages.logging_enabled') }}</span>
                </label>
                <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-2 ml-12">{{ __('mime-guard::messages.logging_help') }}</p>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end">
            <button type="submit" class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline disabled:[&_svg]:opacity-30 disabled:cursor-not-allowed [&_svg]:shrink-0 dark:[&_svg]:text-white bg-linear-to-b from-primary/90 to-primary hover:bg-primary-hover text-white disabled:opacity-60 disabled:text-white dark:disabled:text-white border border-primary-border shadow-ui-md inset-shadow-2xs inset-shadow-white/25 disabled:inset-shadow-none dark:disabled:inset-shadow-none [&_svg]:text-white [&_svg]:opacity-60 px-4 h-10 text-sm gap-2 rounded-lg">
                {{ __('mime-guard::messages.save_settings') }}
            </button>
        </div>
    </form>

    {{-- Help Section --}}
    <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mt-6">
        <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
            <h3 class="font-bold text-gray-925 dark:text-gray-300 mb-3">{{ __('mime-guard::messages.help_title') }}</h3>
            <div class="text-sm space-y-3">
                <div>
                    <p class="font-medium text-gray-925 dark:text-gray-300">{{ __('mime-guard::messages.help_hierarchy') }}</p>
                    <p class="text-gray-600/90 dark:text-gray-400 mt-1">{{ __('mime-guard::messages.help_hierarchy_desc') }}</p>
                </div>
                <div>
                    <p class="font-medium text-gray-925 dark:text-gray-300">{{ __('mime-guard::messages.help_wildcards') }}</p>
                    <p class="text-gray-600/90 dark:text-gray-400 mt-1">{{ __('mime-guard::messages.help_wildcards_desc') }}</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxContainer = document.getElementById('mime-checkboxes');

            // Toggle all button
            document.getElementById('toggle-all-mime').addEventListener('click', function() {
                const checkboxes = checkboxContainer.querySelectorAll('input[type="checkbox"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkboxes.forEach(cb => cb.checked = !allChecked);
                updateAllCategoryCounts();
            });

            // Category title toggle
            document.querySelectorAll('.category-toggle').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const card = this.closest('.mime-category-card');
                    if (!card) return;

                    const cbs = card.querySelectorAll('input[type="checkbox"]');
                    const allChecked = Array.from(cbs).every(cb => cb.checked);
                    cbs.forEach(cb => cb.checked = !allChecked);
                    updateCategoryCount(card);
                });
            });

            // Wildcard checkbox toggles all in category
            document.querySelectorAll('input[type="checkbox"][value$="/*"]').forEach(wc => {
                wc.addEventListener('change', function() {
                    const card = this.closest('.mime-category-card');
                    if (!card) return;
                    card.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                        if (cb !== this) cb.checked = this.checked;
                    });
                    updateCategoryCount(card);
                });
            });

            // Auto-check wildcard when all individual types are checked
            document.querySelectorAll('.mime-category-card input[type="checkbox"]:not([value$="/*"])').forEach(cb => {
                cb.addEventListener('change', function() {
                    const card = this.closest('.mime-category-card');
                    if (!card) return;
                    const wc = card.querySelector('input[type="checkbox"][value$="/*"]');
                    if (!wc) return;
                    const others = Array.from(card.querySelectorAll('input[type="checkbox"]')).filter(c => c !== wc);
                    wc.checked = others.every(c => c.checked);
                    updateCategoryCount(card);
                });
            });

            // Category count display
            function updateCategoryCount(card) {
                const el = card.querySelector('.category-count');
                if (!el) return;
                const total = card.querySelectorAll('input[type="checkbox"]').length;
                const checked = card.querySelectorAll('input[type="checkbox"]:checked').length;
                el.textContent = checked + '/' + total;
            }

            function updateAllCategoryCounts() {
                document.querySelectorAll('.mime-category-card').forEach(updateCategoryCount);
            }

            // Collapsible cards
            document.querySelectorAll('.collapsible-card').forEach(card => {
                const header = card.querySelector('.collapsible-header');
                const content = card.querySelector('.collapsible-content');
                const icon = card.querySelector('.collapsible-icon');

                header.addEventListener('click', function() {
                    const isCollapsed = card.dataset.collapsed === 'true';

                    if (isCollapsed) {
                        content.classList.remove('hidden');
                        icon.classList.remove('-rotate-90');
                        icon.classList.add('rotate-0');
                        card.dataset.collapsed = 'false';
                    } else {
                        content.classList.add('hidden');
                        icon.classList.add('-rotate-90');
                        icon.classList.remove('rotate-0');
                        card.dataset.collapsed = 'true';
                    }
                });
            });

            // Initialize
            updateAllCategoryCounts();
        });
    </script>
@endsection
