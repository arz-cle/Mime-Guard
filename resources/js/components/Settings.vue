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

// All known MIME types from the common list
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

function isRestricted(mime) {
    return restricted.value.includes(mime);
}

function toggleRestricted(mime, types) {
    if (mime.endsWith('/*')) {
        const mimes = Object.keys(types);
        if (restricted.value.includes(mime)) {
            restricted.value = restricted.value.filter(m => !mimes.includes(m));
        } else {
            const s = new Set(restricted.value);
            mimes.forEach(m => s.add(m));
            restricted.value = [...s];
        }
    } else {
        const idx = restricted.value.indexOf(mime);
        if (idx >= 0) restricted.value.splice(idx, 1);
        else restricted.value.push(mime);
        syncWildcard(restricted.value, types);
    }
}

function toggleCategoryRestricted(types) {
    const mimes = Object.keys(types);
    if (mimes.every(m => restricted.value.includes(m))) {
        restricted.value = restricted.value.filter(m => !mimes.includes(m));
    } else {
        const s = new Set(restricted.value);
        mimes.forEach(m => s.add(m));
        restricted.value = [...s];
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

function restrictedCount(types) {
    const mimes = Object.keys(types);
    return mimes.filter(m => restricted.value.includes(m)).length + '/' + mimes.length;
}

// ===== Wildcard sync helper =====
function syncWildcard(arr, types) {
    const mimes = Object.keys(types);
    const wc = mimes.find(m => m.endsWith('/*'));
    if (!wc) return;
    const individuals = mimes.filter(m => !m.endsWith('/*'));
    const allChecked = individuals.every(m => arr.includes(m));
    const wcIdx = arr.indexOf(wc);
    if (allChecked && wcIdx < 0) arr.push(wc);
    if (!allChecked && wcIdx >= 0) arr.splice(wcIdx, 1);
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

function toggleContainerAllowed(handle, mime, types) {
    const arr = containerAllowed[handle];
    if (mime.endsWith('/*')) {
        const mimes = Object.keys(types);
        if (arr.includes(mime)) {
            containerAllowed[handle] = arr.filter(m => !mimes.includes(m));
        } else {
            const s = new Set(arr);
            mimes.forEach(m => s.add(m));
            containerAllowed[handle] = [...s];
        }
    } else {
        const idx = arr.indexOf(mime);
        if (idx >= 0) arr.splice(idx, 1);
        else arr.push(mime);
        syncWildcard(arr, types);
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

function containerCount(handle, types) {
    const mimes = Object.keys(types);
    return mimes.filter(m => containerAllowed[handle]?.includes(m)).length + '/' + mimes.length;
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

function toggleBlueprintAllowed(handle, mime, types) {
    const arr = blueprintAllowed[handle];
    if (mime.endsWith('/*')) {
        const mimes = Object.keys(types);
        if (arr.includes(mime)) {
            blueprintAllowed[handle] = arr.filter(m => !mimes.includes(m));
        } else {
            const s = new Set(arr);
            mimes.forEach(m => s.add(m));
            blueprintAllowed[handle] = [...s];
        }
    } else {
        const idx = arr.indexOf(mime);
        if (idx >= 0) arr.splice(idx, 1);
        else arr.push(mime);
        syncWildcard(arr, types);
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

function blueprintCount(handle, types) {
    const mimes = Object.keys(types);
    return mimes.filter(m => blueprintAllowed[handle]?.includes(m)).length + '/' + mimes.length;
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
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(props.actionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify(data),
        });

        if (response.ok) {
            const result = await response.json();
            successMessage.value = result.message || props.trans.settings_saved;
            setTimeout(() => { successMessage.value = ''; }, 4000);
        }
    } catch (err) {
        console.error('Failed to save settings:', err);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div>
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
        </header>

        <!-- Success message -->
        <transition name="fade">
            <div v-if="successMessage" class="bg-green-500/10 border border-green-500/30 text-green-600 dark:text-green-400 px-4 py-3 rounded-xl mb-6">
                {{ successMessage }}
            </div>
        </transition>

        <!-- Global Restrictions -->
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-6">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ trans.global_restrictions }}</h2>
                        <button type="button" @click="toggleAllRestricted"
                                class="text-sm cursor-pointer bg-linear-to-b from-white to-gray-50 dark:from-gray-850 dark:to-gray-900 border border-gray-300 dark:border-gray-700/80 shadow-sm px-3 py-1.5 rounded-lg transition-colors hover:to-gray-100 dark:hover:to-gray-850 text-gray-900 dark:text-gray-300">
                            {{ trans.toggle_all }}
                        </button>
                    </div>
                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-1">{{ trans.global_restrictions_help }}</p>
                </header>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    <div v-for="(types, category) in commonMimeTypes" :key="category"
                         class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50 dark:bg-gray-900">
                        <div class="flex items-center justify-between mb-3">
                            <button type="button" @click="toggleCategoryRestricted(types)"
                                    class="font-medium text-sm text-gray-925 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors">
                                {{ category }}
                            </button>
                            <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums">{{ restrictedCount(types) }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="(label, mime) in types" :key="mime" type="button"
                                    class="mime-pill"
                                    :class="[isRestricted(mime) ? 'pill-block active' : 'pill-allow active', mime.endsWith('/*') ? 'pill-wildcard' : '']"
                                    @click="toggleRestricted(mime, types)">
                                <span>{{ label }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="text-sm font-medium text-gray-925 dark:text-gray-300 mb-1.5 block">{{ trans.custom_mime_types }}</label>
                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mb-2">{{ trans.custom_mime_types_help }}</p>
                    <textarea v-model="customRestricted"
                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-925 dark:text-gray-300 placeholder:text-gray-500 dark:placeholder:text-gray-400/85 shadow-sm rounded-lg px-3 py-2 font-mono text-sm"
                              rows="3"
                              placeholder="application/x-custom&#10;text/csv"></textarea>
                </div>
            </div>
        </div>

        <!-- Container Rules -->
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-6 flex items-start justify-between">
                    <div>
                        <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ trans.container_rules }}</h2>
                        <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-1">{{ trans.container_rules_help }}</p>
                    </div>
                    <a :href="createContainerUrl"
                       class="inline-flex items-center justify-center font-medium bg-linear-to-b from-white to-gray-50 dark:from-gray-850 dark:to-gray-900 hover:to-gray-100 dark:hover:to-gray-850 text-gray-900 dark:text-gray-300 border border-gray-300 dark:border-gray-700/80 shadow-sm px-3 h-8 text-sm rounded-lg">
                        {{ trans.create_container }}
                    </a>
                </header>

                <template v-if="containers.length > 0">
                    <div class="space-y-3">
                        <div v-for="container in containers" :key="container.handle"
                             class="border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900">
                            <button type="button" @click="toggleCard('c-' + container.handle)"
                                    class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition-colors">
                                <h3 class="font-medium text-gray-925 dark:text-gray-300">
                                    <span class="text-gray-500">{{ container.handle }}</span>
                                    <span class="text-gray-400 mx-1">&mdash;</span>
                                    <span>{{ container.title }}</span>
                                    <span v-if="hasContainerRules(container.handle)"
                                          class="ml-2 text-xs bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-full">
                                        {{ trans.configured }}
                                    </span>
                                </h3>
                                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 transform transition-transform"
                                     :class="expandedCards['c-' + container.handle] ? 'rotate-0' : '-rotate-90'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div v-show="expandedCards['c-' + container.handle]" class="px-4 pb-4">
                                <p class="text-sm text-gray-600/90 dark:text-gray-400 mb-3">{{ trans.container_types_help }}</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-3">
                                    <div v-for="(types, category) in commonMimeTypes" :key="category"
                                         class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800">
                                        <div class="flex items-center justify-between mb-2">
                                            <button type="button" @click="toggleCategoryContainer(container.handle, types)"
                                                    class="font-medium text-xs text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors">
                                                {{ category }}
                                            </button>
                                            <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums">{{ containerCount(container.handle, types) }}</span>
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <button v-for="(label, mime) in types" :key="mime" type="button"
                                                    class="mime-pill"
                                                    :class="[isContainerAllowed(container.handle, mime) ? 'pill-allow active' : 'pill-block active', mime.endsWith('/*') ? 'pill-wildcard' : '']"
                                                    @click="toggleContainerAllowed(container.handle, mime, types)">
                                                <span :title="mime">{{ label }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-700 dark:text-gray-400 mb-1 block">{{ trans.custom_mime_types }}</label>
                                    <textarea v-model="containerCustom[container.handle]"
                                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-925 dark:text-gray-300 placeholder:text-gray-500 dark:placeholder:text-gray-400/85 shadow-sm rounded-lg px-3 py-2 font-mono text-xs"
                                              rows="2"
                                              placeholder="custom/type"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <p v-else class="text-gray-500 dark:text-gray-400 italic">{{ trans.no_containers }}</p>
            </div>
        </div>

        <!-- Blueprint Rules -->
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-6">
                    <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ trans.blueprint_rules }}</h2>
                    <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-1">{{ trans.blueprint_rules_help }}</p>
                </header>

                <template v-if="blueprints.length > 0">
                    <div class="space-y-3">
                        <div v-for="blueprint in blueprints" :key="blueprint.handle"
                             class="border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900">
                            <button type="button" @click="toggleCard('b-' + blueprint.handle)"
                                    class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition-colors">
                                <h3 class="font-medium text-gray-925 dark:text-gray-300">
                                    <span>{{ blueprint.title }}</span>
                                    <span class="text-gray-500 text-sm font-normal ml-2">{{ blueprint.handle }}</span>
                                    <span v-if="hasBlueprintRules(blueprint.handle)"
                                          class="ml-2 text-xs bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-full">
                                        {{ trans.configured }}
                                    </span>
                                </h3>
                                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 transform transition-transform"
                                     :class="expandedCards['b-' + blueprint.handle] ? 'rotate-0' : '-rotate-90'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div v-show="expandedCards['b-' + blueprint.handle]" class="px-4 pb-4">
                                <p class="text-sm text-gray-600/90 dark:text-gray-400 mb-3">{{ trans.blueprint_types_help }}</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-3">
                                    <div v-for="(types, category) in commonMimeTypes" :key="category"
                                         class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800">
                                        <div class="flex items-center justify-between mb-2">
                                            <button type="button" @click="toggleCategoryBlueprint(blueprint.handle, types)"
                                                    class="font-medium text-xs text-gray-700 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors">
                                                {{ category }}
                                            </button>
                                            <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums">{{ blueprintCount(blueprint.handle, types) }}</span>
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <button v-for="(label, mime) in types" :key="mime" type="button"
                                                    class="mime-pill"
                                                    :class="[isBlueprintAllowed(blueprint.handle, mime) ? 'pill-allow active' : 'pill-block active', mime.endsWith('/*') ? 'pill-wildcard' : '']"
                                                    @click="toggleBlueprintAllowed(blueprint.handle, mime, types)">
                                                <span :title="mime">{{ label }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-700 dark:text-gray-400 mb-1 block">{{ trans.custom_mime_types }}</label>
                                    <textarea v-model="blueprintCustom[blueprint.handle]"
                                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-925 dark:text-gray-300 placeholder:text-gray-500 dark:placeholder:text-gray-400/85 shadow-sm rounded-lg px-3 py-2 font-mono text-xs"
                                              rows="2"
                                              placeholder="custom/type"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <p v-else class="text-gray-500 dark:text-gray-400 italic">{{ trans.no_blueprints }}</p>
            </div>
        </div>

        <!-- Logging -->
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mb-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <header class="mb-4">
                    <h2 class="font-bold text-lg text-gray-925 dark:text-gray-300">{{ trans.logging }}</h2>
                </header>
                <div class="flex items-center gap-3">
                    <button type="button" @click="loggingEnabled = !loggingEnabled"
                            class="toggle-track" :class="{ on: loggingEnabled }">
                        <span class="toggle-knob"></span>
                    </button>
                    <span class="text-gray-925 dark:text-gray-300 cursor-pointer" @click="loggingEnabled = !loggingEnabled">{{ trans.logging_enabled }}</span>
                </div>
                <p class="text-sm text-gray-600/90 dark:text-gray-400 mt-2 ml-12">{{ trans.logging_help }}</p>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end">
            <button type="button" @click="submit" :disabled="saving"
                    class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline disabled:cursor-not-allowed bg-linear-to-b from-primary/90 to-primary hover:bg-primary-hover text-white disabled:opacity-60 border border-primary-border shadow-ui-md px-4 h-10 text-sm gap-2 rounded-lg">
                {{ saving ? '...' : trans.save_settings }}
            </button>
        </div>

        <!-- Help Section -->
        <div class="bg-gray-100 dark:bg-gray-950/35 rounded-2xl p-1.5 mt-6">
            <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-gray-700/80 shadow-sm px-4 py-5">
                <h3 class="font-bold text-gray-925 dark:text-gray-300 mb-3">{{ trans.help_title }}</h3>
                <div class="text-sm space-y-3">
                    <div>
                        <p class="font-medium text-gray-925 dark:text-gray-300">{{ trans.help_hierarchy }}</p>
                        <p class="text-gray-600/90 dark:text-gray-400 mt-1">{{ trans.help_hierarchy_desc }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-925 dark:text-gray-300">{{ trans.help_wildcards }}</p>
                        <p class="text-gray-600/90 dark:text-gray-400 mt-1">{{ trans.help_wildcards_desc }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

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
.mime-pill.active::before {
    background: currentColor;
    opacity: 1;
}
.mime-pill.pill-wildcard {
    border-style: dashed;
    font-weight: 500;
}

/* Block pills (red when active) */
.mime-pill.pill-block.active {
    background: rgb(254 242 242);
    border-color: rgb(252 165 165);
    color: rgb(185 28 28);
}
.mime-pill.pill-block.active:hover {
    background: rgb(254 226 226);
}

/* Allow pills (green when active) */
.mime-pill.pill-allow.active {
    background: rgb(240 253 244);
    border-color: rgb(134 239 172);
    color: rgb(21 128 61);
}
.mime-pill.pill-allow.active:hover {
    background: rgb(220 252 231);
}

/* Dark mode */
.dark .mime-pill {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgb(55 65 81);
    color: rgb(107 114 128);
}
.dark .mime-pill:hover {
    border-color: rgb(75 85 99);
    color: rgb(156 163 175);
}
.dark .mime-pill.pill-block.active {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
    color: rgb(248 113 113);
}
.dark .mime-pill.pill-block.active:hover {
    background: rgba(239, 68, 68, 0.15);
}
.dark .mime-pill.pill-allow.active {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.3);
    color: rgb(74 222 128);
}
.dark .mime-pill.pill-allow.active:hover {
    background: rgba(34, 197, 94, 0.15);
}

/* ===== Toggle Switch ===== */
.toggle-track {
    position: relative;
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: rgb(209 213 219);
    transition: background 0.2s ease;
    flex-shrink: 0;
    cursor: pointer;
    border: none;
    padding: 0;
}
.toggle-track.on {
    background: rgb(34 197 94);
}
.toggle-knob {
    display: block;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: transform 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
    pointer-events: none;
}
.toggle-track.on .toggle-knob {
    transform: translateX(20px);
}
.dark .toggle-track {
    background: rgb(75 85 99);
}

/* ===== Transition ===== */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
