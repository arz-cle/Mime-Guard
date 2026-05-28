<script setup>
import { ref, reactive, computed } from 'vue';

const props = defineProps({
    title: String,
    settings: Object,
    containers: Array,
    blueprints: Array,
    commonMimeTypes: Object,
    actionUrl: String,
    createContainerUrl: String,
    trans: Object,
});

const allKnownMimes = computed(() =>
    Object.values(props.commonMimeTypes).flatMap(types => Object.keys(types))
);

function getCustomTypes(types) {
    const known = Object.values(props.commonMimeTypes).flatMap(t => Object.keys(t));
    return (types || []).filter(t => !known.includes(t)).join('\n');
}

// ===== Global Restrictions =====
const restricted = ref([...(props.settings.restricted_by_default || [])]);
const customRestricted = ref(getCustomTypes(props.settings.restricted_by_default));

function isAllowed(mime) {
    return !restricted.value.includes(mime);
}

function setAllowed(mime, value) {
    if (value) {
        const idx = restricted.value.indexOf(mime);
        if (idx >= 0) restricted.value.splice(idx, 1);
    } else {
        if (!restricted.value.includes(mime)) restricted.value.push(mime);
    }
}

function toggleCategoryRestricted(types) {
    const mimes = Object.keys(types);
    if (mimes.every(m => !restricted.value.includes(m))) {
        mimes.forEach(m => restricted.value.push(m));
    } else {
        restricted.value = restricted.value.filter(m => !mimes.includes(m));
    }
}

function toggleAllRestricted() {
    const all = allKnownMimes.value;
    if (all.every(m => restricted.value.includes(m))) {
        restricted.value = restricted.value.filter(m => !all.includes(m));
    } else {
        const s = new Set(restricted.value);
        all.forEach(m => s.add(m));
        restricted.value = [...s];
    }
}

// ===== Container Rules =====
const containerAllowed = reactive({});
const containerCustom = reactive({});
props.containers.forEach(c => {
    const allow = props.settings.containers?.[c.handle]?.allow || [];
    containerAllowed[c.handle] = [...allow];
    containerCustom[c.handle] = getCustomTypes(allow);
});

function isContainerAllowed(handle, mime) {
    return containerAllowed[handle]?.includes(mime);
}

function setContainerAllowed(handle, mime, value) {
    const arr = containerAllowed[handle];
    if (value) {
        if (!arr.includes(mime)) arr.push(mime);
    } else {
        const idx = arr.indexOf(mime);
        if (idx >= 0) arr.splice(idx, 1);
    }
}

function toggleCategoryContainer(handle, types) {
    const mimes = Object.keys(types);
    const arr = containerAllowed[handle];
    if (mimes.every(m => arr.includes(m))) {
        containerAllowed[handle] = arr.filter(m => !mimes.includes(m));
    } else {
        const s = new Set(arr);
        mimes.forEach(m => s.add(m));
        containerAllowed[handle] = [...s];
    }
}

function hasContainerRules(handle) {
    return (containerAllowed[handle]?.length || 0) > 0;
}

// ===== Blueprint Rules =====
const blueprintAllowed = reactive({});
const blueprintCustom = reactive({});
props.blueprints.forEach(b => {
    const allow = props.settings.blueprints?.[b.handle]?.allow || [];
    blueprintAllowed[b.handle] = [...allow];
    blueprintCustom[b.handle] = getCustomTypes(allow);
});

function isBlueprintAllowed(handle, mime) {
    return blueprintAllowed[handle]?.includes(mime);
}

function setBlueprintAllowed(handle, mime, value) {
    const arr = blueprintAllowed[handle];
    if (value) {
        if (!arr.includes(mime)) arr.push(mime);
    } else {
        const idx = arr.indexOf(mime);
        if (idx >= 0) arr.splice(idx, 1);
    }
}

function toggleCategoryBlueprint(handle, types) {
    const mimes = Object.keys(types);
    const arr = blueprintAllowed[handle];
    if (mimes.every(m => arr.includes(m))) {
        blueprintAllowed[handle] = arr.filter(m => !mimes.includes(m));
    } else {
        const s = new Set(arr);
        mimes.forEach(m => s.add(m));
        blueprintAllowed[handle] = [...s];
    }
}

function hasBlueprintRules(handle) {
    return (blueprintAllowed[handle]?.length || 0) > 0;
}

// ===== Logging =====
const loggingEnabled = ref(props.settings.logging?.enabled ?? true);

// ===== UI State =====
const expandedCards = reactive({});
const saving = ref(false);
const successMessage = ref('');

function toggleCard(id) {
    expandedCards[id] = !expandedCards[id];
}

// ===== Form Submission =====
async function submit() {
    saving.value = true;
    successMessage.value = '';

    const data = {
        restricted_by_default: restricted.value,
        restricted_by_default_custom: customRestricted.value,
        containers: {},
        blueprints: {},
        logging_enabled: loggingEnabled.value,
    };

    props.containers.forEach(c => {
        data.containers[c.handle] = {
            allow: containerAllowed[c.handle] || [],
            allow_custom: containerCustom[c.handle] || '',
        };
    });

    props.blueprints.forEach(b => {
        data.blueprints[b.handle] = {
            allow: blueprintAllowed[b.handle] || [],
            allow_custom: blueprintCustom[b.handle] || '',
        };
    });

    try {
        const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '');
        const response = await fetch(props.actionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': token,
            },
            body: JSON.stringify(data),
        });

        if (response.ok) {
            const result = await response.json();
            successMessage.value = result.message || props.trans.settings_saved;
            setTimeout(() => { successMessage.value = ''; }, 4000);
        } else {
            const text = await response.text();
            console.error('Save failed:', response.status, text);
        }
    } catch (err) {
        console.error('Failed to save settings:', err);
    } finally {
        saving.value = false;
    }
}

// ===== Tab persistence =====
const activeTab = ref('global');

function initTabFromHash() {
    const hash = window.location.hash.substring(1);
    if (['global', 'containers', 'blueprints', 'help'].includes(hash)) {
        activeTab.value = hash;
    }
}

function onTabChange(tab) {
    activeTab.value = tab;
    window.location.hash = tab;
}

// Init on mount
if (typeof window !== 'undefined') initTabFromHash();
</script>

<template>
    <div class="mg-settings max-w-5xl 3xl:max-w-6xl mx-auto">
        <!-- Header -->
        <header class="flex flex-wrap items-center justify-between gap-4 px-2 sm:px-0 py-6 max-md:pb-8 md:py-8">
            <h1 class="text-[25px] leading-[1.25] st-text-legibility font-medium antialiased flex items-center gap-2.5 md:flex-1">
                <div class="size-5 relative">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5 text-gray-500">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
                {{ title }}
            </h1>
            <button type="button" @click="submit" :disabled="saving"
                    class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline disabled:cursor-not-allowed bg-linear-to-b from-primary/90 to-primary hover:bg-primary-hover text-white disabled:opacity-60 border border-primary-border shadow-ui-md px-4 h-10 text-sm gap-2 rounded-lg">
                {{ saving ? '...' : trans.save_settings }}
            </button>
        </header>

        <!-- Success -->
        <ui-alert v-if="successMessage" variant="success" :text="successMessage" class="mb-6" />

        <!-- Tabs + Logging toggle -->
        <ui-tabs :model-value="activeTab" @update:model-value="onTabChange">
            <div class="flex items-center justify-between">
                <ui-tab-list>
                    <ui-tab-trigger value="global">{{ trans.global_restrictions }}</ui-tab-trigger>
                    <ui-tab-trigger value="containers">{{ trans.container_rules }}</ui-tab-trigger>
                    <ui-tab-trigger value="blueprints">{{ trans.blueprint_rules }}</ui-tab-trigger>
                    <ui-tab-trigger value="help">{{ trans.help_title }}</ui-tab-trigger>
                </ui-tab-list>
                <div class="flex items-center gap-2.5 shrink-0">
                    <ui-popover>
                        <template #trigger>
                            <button type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors cursor-pointer">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </button>
                        </template>
                        <p class="text-xs text-gray-600 dark:text-gray-300 p-2 max-w-[220px]">Logs include: MIME type, filename, user, and container.</p>
                    </ui-popover>
                    <span class="text-xs font-medium" :class="loggingEnabled ? 'text-emerald-600 dark:text-emerald-300' : 'text-gray-400'">
                        {{ loggingEnabled ? 'Logs on' : 'Logs off' }}
                    </span>
                    <ui-switch v-model="loggingEnabled" size="sm" />
                </div>
            </div>

            <!-- ============ Tab: Global ============ -->
            <ui-tab-content value="global">
                <div class="flex flex-col mb-4 mt-6">
                    <ui-subheading>{{ trans.global_restrictions_help }}</ui-subheading>
                </div>

                <div class="space-y-6">
                    <ui-panel v-for="(types, category) in commonMimeTypes" :key="category">
                        <ui-panel-header class="flex items-center justify-between">
                            <ui-heading :text="category" />
                            <ui-button size="sm" :text="trans.toggle_all" icon="fieldtype-toggle" @click="toggleCategoryRestricted(types)" />
                        </ui-panel-header>
                        <ui-card inset>
                            <ui-card-list-item v-for="(label, mime) in types" :key="mime">
                                <span class="text-sm">{{ label }}</span>
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xs font-medium" :class="isAllowed(mime) ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'">
                                        {{ isAllowed(mime) ? 'Allowed' : 'Blocked' }}
                                    </span>
                                    <ui-switch :model-value="isAllowed(mime)" @update:model-value="v => setAllowed(mime, v)" size="sm" />
                                </div>
                            </ui-card-list-item>
                        </ui-card>
                    </ui-panel>

                    <ui-panel>
                        <ui-panel-header>
                            <ui-heading :text="trans.custom_mime_types" />
                        </ui-panel-header>
                        <ui-card>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">{{ trans.custom_mime_types_help }}</p>
                            <textarea v-model="customRestricted"
                                      class="input-text font-mono text-sm w-full border border-gray-300 dark:border-gray-700 rounded-lg"
                                      rows="3"
                                      placeholder="application/x-custom&#10;text/csv"></textarea>
                        </ui-card>
                    </ui-panel>
                </div>
            </ui-tab-content>

            <!-- ============ Tab: Containers ============ -->
            <ui-tab-content value="containers">
                <div class="flex items-start justify-between mb-4 mt-6">
                    <ui-subheading>{{ trans.container_rules_help }}</ui-subheading>
                    <a :href="createContainerUrl"
                       class="text-sm font-medium bg-linear-to-b from-white to-gray-50 dark:from-gray-850 dark:to-gray-900 hover:to-gray-100 dark:hover:to-gray-850 text-gray-900 dark:text-gray-300 border border-gray-300 dark:border-gray-700/80 shadow-sm px-3 py-1.5 rounded-lg shrink-0 ml-4">
                        {{ trans.create_container }}
                    </a>
                </div>

                <template v-if="containers.length > 0">
                    <div class="space-y-6">
                        <template v-for="(container, idx) in containers" :key="container.handle">
                            <div v-if="idx > 0" class="py-4"><ui-separator variant="dots" /></div>
                            <ui-panel>
                            <ui-panel-header class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <ui-heading :text="container.title" />
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ container.handle }}</span>
                                    <span v-if="hasContainerRules(container.handle)"
                                          class="text-[10px] bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded-full font-medium">
                                        {{ trans.configured }}
                                    </span>
                                </div>
                            </ui-panel-header>
                            <ui-card>
                                <div class="space-y-4">
                                    <ui-panel v-for="(types, category) in commonMimeTypes" :key="category">
                                        <ui-panel-header class="flex items-center justify-between">
                                            <ui-heading :text="category" />
                                            <ui-button size="sm" :text="trans.toggle_all" icon="fieldtype-toggle" @click="toggleCategoryContainer(container.handle, types)" />
                                        </ui-panel-header>
                                        <ui-card inset>
                                            <ui-card-list-item v-for="(label, mime) in types" :key="mime">
                                                <span class="text-sm">{{ label }}</span>
                                                <div class="flex items-center gap-2.5">
                                                    <span class="text-xs font-medium" :class="isContainerAllowed(container.handle, mime) ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'">
                                                        {{ isContainerAllowed(container.handle, mime) ? 'Allowed' : 'Blocked' }}
                                                    </span>
                                                    <ui-switch :model-value="!!isContainerAllowed(container.handle, mime)" @update:model-value="v => setContainerAllowed(container.handle, mime, v)" size="sm" />
                                                </div>
                                            </ui-card-list-item>
                                        </ui-card>
                                    </ui-panel>

                                    <div>
                                        <label class="text-xs font-medium text-gray-700 dark:text-gray-400 mb-1 block">{{ trans.custom_mime_types }}</label>
                                        <textarea v-model="containerCustom[container.handle]"
                                                  class="input-text font-mono text-xs w-full border border-gray-300 dark:border-gray-700 rounded-lg"
                                                  rows="2" placeholder="custom/type"></textarea>
                                    </div>
                                </div>
                            </ui-card>
                        </ui-panel>
                        </template>
                    </div>
                </template>
                <p v-else class="text-gray-500 dark:text-gray-400 italic text-sm mt-6">{{ trans.no_containers }}</p>
            </ui-tab-content>

            <!-- ============ Tab: Blueprints ============ -->
            <ui-tab-content value="blueprints">
                <div class="flex flex-col mb-4 mt-6">
                    <ui-subheading>{{ trans.blueprint_rules_help }}</ui-subheading>
                </div>

                <template v-if="blueprints.length > 0">
                    <div class="space-y-6">
                        <template v-for="(blueprint, idx) in blueprints" :key="blueprint.handle">
                            <div v-if="idx > 0" class="py-4"><ui-separator variant="dots" /></div>
                            <ui-panel>
                            <ui-panel-header class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <ui-heading :text="blueprint.title" />
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ blueprint.collection }}</span>
                                    <span v-if="hasBlueprintRules(blueprint.handle)"
                                          class="text-[10px] bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded-full font-medium">
                                        {{ trans.configured }}
                                    </span>
                                </div>
                            </ui-panel-header>
                            <ui-card>
                                <div class="space-y-4">
                                    <ui-panel v-for="(types, category) in commonMimeTypes" :key="category">
                                        <ui-panel-header class="flex items-center justify-between">
                                            <ui-heading :text="category" />
                                            <ui-button size="sm" :text="trans.toggle_all" icon="fieldtype-toggle" @click="toggleCategoryBlueprint(blueprint.handle, types)" />
                                        </ui-panel-header>
                                        <ui-card inset>
                                            <ui-card-list-item v-for="(label, mime) in types" :key="mime">
                                                <span class="text-sm">{{ label }}</span>
                                                <div class="flex items-center gap-2.5">
                                                    <span class="text-xs font-medium" :class="isBlueprintAllowed(blueprint.handle, mime) ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'">
                                                        {{ isBlueprintAllowed(blueprint.handle, mime) ? 'Allowed' : 'Blocked' }}
                                                    </span>
                                                    <ui-switch :model-value="!!isBlueprintAllowed(blueprint.handle, mime)" @update:model-value="v => setBlueprintAllowed(blueprint.handle, mime, v)" size="sm" />
                                                </div>
                                            </ui-card-list-item>
                                        </ui-card>
                                    </ui-panel>

                                    <div>
                                        <label class="text-xs font-medium text-gray-700 dark:text-gray-400 mb-1 block">{{ trans.custom_mime_types }}</label>
                                        <textarea v-model="blueprintCustom[blueprint.handle]"
                                                  class="input-text font-mono text-xs w-full border border-gray-300 dark:border-gray-700 rounded-lg"
                                                  rows="2" placeholder="custom/type"></textarea>
                                    </div>
                                </div>
                            </ui-card>
                        </ui-panel>
                        </template>
                    </div>
                </template>
                <p v-else class="text-gray-500 dark:text-gray-400 italic text-sm mt-6">{{ trans.no_blueprints }}</p>
            </ui-tab-content>

            <!-- ============ Tab: Help ============ -->
            <ui-tab-content value="help">
                <div class="mt-6 space-y-6">
                    <ui-panel>
                        <ui-panel-header>
                            <ui-heading :text="trans.help_hierarchy" />
                        </ui-panel-header>
                        <ui-card>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ trans.help_hierarchy_desc }}</p>
                        </ui-card>
                    </ui-panel>

                    <ui-panel>
                        <ui-panel-header>
                            <ui-heading :text="trans.help_wildcards" />
                        </ui-panel-header>
                        <ui-card>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ trans.help_wildcards_desc }}</p>
                        </ui-card>
                    </ui-panel>
                </div>
            </ui-tab-content>
        </ui-tabs>

    </div>
</template>

<style>
/* Success (emerald) for allowed, Error (red) for blocked — matches Alert variants */
.mg-settings [data-ui-switch][data-state=checked] {
    background-color: rgba(110, 231, 183, 0.2) !important;
    border-color: transparent !important;
    box-shadow: none !important;
}
.mg-settings [data-ui-switch][data-state=checked] > span {
    background-color: #10b981 !important;
}
.mg-settings [data-ui-switch][data-state=unchecked] {
    background-color: rgba(252, 165, 165, 0.15) !important;
    border-color: transparent !important;
}
.mg-settings [data-ui-switch][data-state=unchecked] > span {
    background-color: #ef4444 !important;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
