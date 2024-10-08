<script setup>
import { ref, onBeforeMount, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import dayjs from 'dayjs';
import { stringify as stringifyYaml } from 'yaml';
import prettyBytes from 'pretty-bytes';

import { Switch } from '@headlessui/vue';
import { useBusyStore } from '../stores/busy';
import ExpansionPanel from '../components/ExpansionPanel.vue';
import { useLicenseStore } from '../stores/license';
import { useNotifier } from '../library/notifier';
import { useSettingsStore } from '../stores/settings';
import { useApi } from '../library/api';
import { useTailwindStore } from '../stores/tailwind';
import { compileCSS } from '../library/tailwindcss/compiler';

import { debounce } from 'lodash-es';

const notifier = useNotifier();
const busyStore = useBusyStore();
const api = useApi();

const licenseStore = useLicenseStore();
const settingsStore = useSettingsStore();
const tailwindStore = useTailwindStore();
const providers = ref([]);

const versions = ref([]);

function fetchVersion() {
    busyStore.add('settings.general.versions.fetchVersions');

    axios
        .get('https://data.jsdelivr.com/v1/package/npm/tailwindcss')
        .then((response) => {
            versions.value = response.data.versions.filter((v) => {
                return v >= '3.0.0' && v < '4.0.0';
            });
        })
        .finally(() => {
            busyStore.remove('settings.general.versions.fetchVersions');
        });
}

const licenseKey = ref('');

function doLicenseChange() {
    const promise = licenseStore.license.key && licenseStore.isActivated
        ? licenseStore.doDeactivate()
        : licenseStore.doActivate(licenseKey.value);

    promise.then(() => {
        licenseKey.value = licenseStore.license.key;
    });

    notifier.async(
        promise,
        resp => notifier.success(resp.message),
        err => notifier.alert(err.message),
        `${licenseStore.license.key && licenseStore.isActivated ? 'Deactivating' : 'Activating'} license...`
    );
}

const css_cache = ref({
    last_generated: null,
    file_url: null,
    file_size: false,
});

function doSave() {
    const promise = settingsStore.doPush();

    notifier.async(
        promise,
        resp => notifier.success(resp.message),
        err => notifier.alert(err.message),
        'Storing settings...'
    );
}

const compileError = ref(null);

async function pullProviders() {
    await api
        .get('admin/settings/cache/providers')
        .then((resp) => {
            providers.value = resp.data.providers;
        });
}

function handleEnableKeyup(e, providerId) {
    if (e.code === 'Space') {
        e.preventDefault();
        // item.status = !item.status;
        settingsStore.virtualOptions(`integration.${providerId}.enabled`, true).value = !settingsStore.virtualOptions(`integration.${providerId}.enabled`, true).value;
    }
}

function switchProviderStatus(providerId) {
    settingsStore.virtualOptions(`integration.${providerId}.enabled`, true).value = !settingsStore.virtualOptions(`integration.${providerId}.enabled`, true).value;
    // save the settings
    doSave();
}

function doGenerateCache(forcePull = false) {
    busyStore.add('settings.performance.cached_css.generate');
    compileError.value = null;

    const promise = (async () => {
        if (forcePull === true || tailwindStore.initValues.preset === null) {
            await tailwindStore.doPull();
        }

        await pullProviders();

        if (providers.value.length === 0) {
            notifier.alert('No cache provider found');
            return;
        }

        let content_pool = [];

        // Helper function to handle batch processing for a single provider
        async function fetchProviderContents(provider) {
            let batch = false;

            do {
                const scan = await api
                    .post('admin/settings/cache/providers/scan', {
                        provider_id: provider.id,
                        metadata: { next_batch: batch },
                    })
                    .then((resp) => resp.data);

                content_pool.push(...scan.contents);

                batch = scan.metadata?.next_batch || false;
            } while (batch !== false);

            return Promise.resolve();
        }

        const promises = providers.value.filter(provider => provider.enabled)
            .map(provider => fetchProviderContents(provider));

        await Promise.all(promises);

        const contents = content_pool.map((c) => {
            let content = atob(c.content);

            if (c.type === 'json') {
                content = stringifyYaml(JSON.parse(content));
            }

            return content;
        });

        const tw_version = settingsStore.virtualOptions('general.tailwindcss.version', 'latest').value;

        const compiled_css = await compileCSS(
            tw_version === 'latest' ? versions.value[0] : tw_version,
            `${tailwindStore.customValue.config.prepend}\n${tailwindStore.config}\n${tailwindStore.customValue.config.append}`,
            `${tailwindStore.customValue.css.prepend}\n${tailwindStore.css}\n${tailwindStore.customValue.css.append}`,
            contents
        );

        if (compiled_css._error) {
            compileError.value = compiled_css._error;
            throw new Error();
        }

        const license = `/* ! tailwindcss v${tw_version === 'latest' ? versions.value[0] : tw_version} | MIT License | https://tailwindcss.com */`;

        await api
            .post('admin/settings/cache/store', {
                // @see https://developer.mozilla.org/en-US/docs/Glossary/Base64#the_unicode_problem
                content: btoa(String.fromCodePoint(...new TextEncoder().encode(`${license}\n${compiled_css.css}`))),
            })
            .then((resp) => {
                css_cache.value = resp.data.cache;
            });

    })().finally(() => {
        busyStore.remove('settings.performance.cached_css.generate');
    });

    notifier.async(
        promise,
        resp => {
            notifier.success(`Tailwind CSS: <b>${prettyBytes(css_cache.value.file_size, { maximumFractionDigits: 2, space: false })}</b>`);
        },
        err => {
            notifier.alert('Failed to generate cache');
        },
        'Generating cache...'
    );
}

// debounce the generate cache function. only execute after is busyStore is not busy
const debounceGenerateCache = debounce((forcePull = false) => {
    if (!busyStore.isBusy) {
        doGenerateCache();
    } else {
        debounceGenerateCache(forcePull);
    }
}, 1000);

const bc = new BroadcastChannel('siul_channel');

onBeforeMount(() => {
    fetchVersion();
    licenseStore.doPull().then(() => {
        licenseKey.value = licenseStore.license.key;
    });

    settingsStore.doPull();

    pullProviders();

    api
        .get('admin/settings/cache/index')
        .then((resp) => {
            css_cache.value = resp.data.cache;
        });
});

onMounted(() => {
    bc.addEventListener('message', (event) => {
        if (event.data.key === 'generate-cache') {
            // if it's enabled, generate the cache
            if (settingsStore.virtualOptions('performance.cache.enabled', false).value) {
                debounceGenerateCache(
                    event.data.force_pull === true
                );
            }
        }
    });
});

onUnmounted(() => {
    bc.close();
});

// Expose the doSave function to be used by the App.vue
defineExpose({
    doSave,
});
</script>

<template>
    <div class="flex flex:col">
        <ExpansionPanel namespace="settings" name="license" class="border:1|solid|#e8e8eb box-shadow:none! max-w:screen-2xs mx:auto my:8 w:full">
            <template #header>
                <span class="fg:gray-90 font:18 font:semibold">License</span>
            </template>

            <template #default>
                <div class="{bt:1|solid|#e8e8eb}>*+* bg:white">
                    <div class="p:20">
                        <div class="flex flex:column gap:10">
                            <span class="fg:gray-60 font:15 font:medium">License key</span>
                            <div class="flex gap:6">
                                <input v-model="licenseKey" type="password" id="license_key" :disabled="licenseStore.isActivated" class="max-w:400 w:full">
                                <button @click="doLicenseChange" type="button" :disabled="!licenseKey || busyStore.isBusy" class="button button-secondary inline-flex align-items:center gap:8">
                                    <font-awesome-icon v-if="busyStore.isBusy && busyStore.tasks.some((t) => t.task === 'settings.license.activate' || t.task === 'settings.license.deactivate')" :icon="['fas', 'circle-notch']" class="@rotate|1s|infinite|linear" />
                                    <template v-if="busyStore.isBusy && busyStore.tasks.some((t) => t.task === 'settings.license.activate' || t.task === 'settings.license.deactivate')">
                                        {{ licenseStore.isActivated ? 'Deactivating' : 'Activating' }}
                                    </template>
                                    <template v-else>
                                        {{ licenseStore.isActivated ? 'Deactivate' : 'Activate' }}
                                    </template>

                                </button>
                            </div>
                            <div v-if="licenseStore.license.key" class="flex align-items:center font:medium">
                                Status:
                                <span :class="licenseStore.isActivated ? 'bg:green-80' : 'bg:yellow-70'" class="fg:white font:regular ml:10 px:6 py:4 r:4 user-select:none">
                                    {{ licenseStore.isActivated ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p class="my:0">To access updates when they are available, please provide your license key.</p>
                        </div>
                        <div></div>
                    </div>
                </div>
            </template>
        </ExpansionPanel>

        <ExpansionPanel namespace="settings" name="general" class="border:1|solid|#e8e8eb box-shadow:none! max-w:screen-2xs mx:auto my:8 w:full">
            <template #header>
                <span class="fg:gray-90 font:18 font:semibold">General</span>
            </template>

            <template #default>
                <div class="flex {bt:1|solid|#e8e8eb}>*+* bg:white flex:column">
                    <div class="flex flex:column gap:30 p:20">
                        <div class="flex flex:column gap:10">
                            <span class="fg:gray-60 font:15 font:medium">Tailwind CSS version</span>
                            <select v-model="settingsStore.virtualOptions('general.tailwindcss.version', 'latest').value">
                                <option value="latest">latest</option>
                                <option v-for="version in versions" :key="version" :value="version">{{ version }}</option>
                            </select>
                            <p class="my:0">Please refer to the <a href="https://github.com/tailwindlabs/tailwindcss/releases" target="_blank">release notes</a> to learn more about the Tailwind CSS versions.</p>
                        </div>
                        <div class="flex flex:column gap:10">
                            <span class="fg:gray-60 font:15 font:medium">Embedded compiler</span>
                            <div class="flex align-items:center gap:4">
                                <input type="checkbox" id="enable_embedded_compiler" v-model="settingsStore.virtualOptions('general.compiler.embedded.enabled', false).value" class="checkbox mt:0">
                                <label for="enable_embedded_compiler" class="font:medium">Enable compiler on the front page</label>
                            </div>
                            <p class="my:0">
                                The compiler can be utilized to compile the Tailwind CSS for 3rd party integrations.
                            </p>
                        </div>
                        <!-- <div class="flex flex:column gap:10">
                            <span class="fg:gray-60 font:15 font:medium">Autocomplete engine</span>
                            <div class="flex align-items:center gap:4">
                                <input type="checkbox" id="enable_autocomplete_engine" v-model="settingsStore.virtualOptions('general.autocomplete.engine.enabled', false).value" class="checkbox mt:0">
                                <label for="enable_autocomplete_engine" class="font:medium">Enable engine integration</label>
                            </div>
                            <p class="my:0">The engine can be integrated with external tools to provide autocomplete suggestion items.</p>
                        </div> -->
                    </div>
                </div>
            </template>
        </ExpansionPanel>

        <ExpansionPanel namespace="settings" name="performance" class="border:1|solid|#e8e8eb box-shadow:none! max-w:screen-2xs mx:auto my:8 w:full">
            <template #header>
                <span class="fg:gray-90 font:18 font:semibold">Performance</span>
            </template>

            <template #default>
                <div class="flex {bt:1|solid|#e8e8eb}>*+* bg:white flex:column">
                    <div class="flex flex:column gap:30 p:20">
                        <div class="flex flex:column gap:10">
                            <span class="fg:gray-60 font:15 font:medium">Cached CSS</span>
                            <div class="flex align-items:center gap:4">
                                <input type="checkbox" id="enable_cached_css" v-model="settingsStore.virtualOptions('performance.cache.enabled', false).value" class="checkbox mt:0">
                                <label for="enable_cached_css" class="font:medium">Use cached CSS if available</label>
                            </div>
                            <div class="flex align-items:center gap:4">
                                <input type="checkbox" id="force_cdn_admin" v-model="settingsStore.virtualOptions('performance.cache.exclude_admin', false).value" class="checkbox mt:0">
                                <label for="force_cdn_admin" class="font:medium">Admin always uses the Play CDN</label>
                            </div>
                            <div class="flex align-items:center gap:4">
                                <input type="checkbox" id="inline_cached_css" v-model="settingsStore.virtualOptions('performance.cache.inline_load', false).value" class="checkbox mt:0">
                                <label for="inline_cached_css" class="font:medium">Load the cached CSS inline</label>
                            </div>
                            <p class="my:0">Serve the CSS file from the cache instead of generating it on the fly using Play CDN.</p>
                            <p class="flex gap-x:4 my:0">
                                <span class="font:medium">Last Generated:</span>
                                <template v-if="css_cache.last_generated">
                                    {{ new dayjs(css_cache.last_generated * 1000).format('YYYY-MM-DD HH:mm:ss') }}
                                    <a :href="`${css_cache.file_url}?ver=${css_cache.last_generated}`" target="_blank">
                                        <font-awesome-icon :icon="['far', 'arrow-up-right-from-square']" />
                                    </a>
                                </template>
                                <template v-if="css_cache.file_size">
                                    <div class="bg:lime-5/.5 fg:lime-70 font:12 font:medium ml:8 outline:1|solid|lime-60/.2 px:8 py:2 r:6">{{ prettyBytes(css_cache.file_size, { maximumFractionDigits: 2, space: false }) }}</div>
                                </template>
                            </p>
                            <div>
                                <button @click="doGenerateCache" :disabled="busyStore.isBusy" type="button" class="button button-secondary inline-flex align-items:center gap:8">
                                    <font-awesome-icon v-if="busyStore.isBusy && busyStore.hasTask('settings.performance.cached_css.generate')" :icon="['fas', 'circle-notch']" class="@rotate|1s|infinite|linear" />
                                    {{ busyStore.isBusy && busyStore.hasTask('settings.performance.cached_css.generate') ? 'Generating' : 'Generate' }}

                                    <!-- Generate cache -->
                                </button>
                            </div>
                            <div v-if="compileError" class="bg:red-5 fg:red-80 px:24">
                                <h2 class="flex align-items:center f:16 fg:red-95 font:semibold lh:24px mb:16">
                                    <span class="rounded! b:4|solid|red-20 bg:red-40 box:content h:8 w:8"></span>
                                    <span v-if="compileError.action === 'parse-config'" class="ml:14">Config Error</span>
                                    <span v-else-if="compileError.action === 'compile-css'" class="ml:14">Compile Error</span>
                                    <dl v-if="compileError.line !== undefined" class="rounded! bg:red-10 f:14 fg:red-80 font:medium lh:24px ml:16 my:0 px:12">
                                        <dt class="inline">Line</dt>
                                        <dd class="inline ml:4">{{ compileError.line }}</dd>
                                    </dl>
                                </h2>
                                <p class="f:14 font:mono lh:20px"> {{ compileError.message }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </ExpansionPanel>


        <ExpansionPanel namespace="settings" name="integration" class="border:1|solid|#e8e8eb box-shadow:none! max-w:screen-2xs mx:auto my:8 w:full">
            <template #header>
                <span class="fg:gray-90 font:18 font:semibold">Integrations</span>
            </template>

            <template #default>
                <div class="bg:white">
                    <div class="{mt:-1;mb:0}>:not([hidden])~:not([hidden])">
                        <div v-for="provider in providers" :key="provider.id" :class="[settingsStore.virtualOptions(`integration.${provider.id}.enabled`, true).value ? 'b:1|solid|sky-20/.5 bg:sky-5 z:10' : 'b:1|solid|gray-20/.5']" class="flex rel p:12">
                            <div class="flex align-items:center">
                                <Switch :aria-disabled="busyStore.isBusy" :checked="settingsStore.virtualOptions(`integration.${provider.id}.enabled`, true).value" @click="switchProviderStatus(provider.id)" @keyup="e => handleEnableKeyup(e, provider.id)" :class="[settingsStore.virtualOptions(`integration.${provider.id}.enabled`, true).value ? 'bg:sky-70' : 'bg:gray-15 opacity:.5']" class="inline-flex rel rounded b:2 b:transparent box-shadow:rgb(255,255,255)|0|0|0|2,rgb(14,165,233)|0|0|0|4,rgba(0,0,0,0)|0|0|0|0:focus cursor:pointer flex-shrink:0 h:24 outline:2|solid|transparent:focus p:0 transition-duration:200 transition-property:color,background-color,border-color,text-decoration-color,fill,stroke transition-timing-function:cubic-bezier(0.4,0,0.2,1) w:44">
                                    <span :class="[settingsStore.virtualOptions(`integration.${provider.id}.enabled`, true).value ? 'translateX(20)' : 'translateX(0)']" class="inline-block rel rounded bg:white box-shadow:rgb(255,255,255)|0|0|0|0,rgba(59,130,246,0.5)|0|0|0|0,rgba(0,0,0,0.1)|0|1|3|0,rgba(0,0,0,0.1)|0|1|2|-1 font:12 h:20 pointer-events:none transition-duration:200 transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,backdrop-filter transition-timing-function:cubic-bezier(0.4,0,0.2,1) w:20">
                                        <span aria-hidden="true" :class="[settingsStore.virtualOptions(`integration.${provider.id}.enabled`, true).value ? 'opacity:0 transition-duration:100 transition-timing-function:ease-out' : 'opacity:1 transition-duration:200 transition-timing-function:ease-in']" class="abs flex align-items:center h:full inset:0 justify-content:center w:full">
                                            <font-awesome-icon :icon="['fas', 'xmark']" class="fg:gray-40" />
                                        </span>
                                        <span aria-hidden="true" :class="[settingsStore.virtualOptions(`integration.${provider.id}.enabled`, true).value ? 'opacity:1 transition-duration:200 transition-timing-function:ease-in' : 'opacity:0 transition-duration:100 transition-timing-function:ease-out']" class="abs flex align-items:center h:full inset:0 justify-content:center w:full">
                                            <font-awesome-icon :icon="['fas', 'check']" class="fg:sky-70" />
                                        </span>
                                    </span>
                                </Switch>
                            </div>
                            <div class="flex flex:col ml:12">
                                <div>
                                    <span class="font:semibold">{{ provider.name }}</span>
                                    <span class="fg:gray-40 font:10 ml:8">[ {{ provider.id }} ]</span>
                                </div>
                                <span class="">
                                    {{ provider.description }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </ExpansionPanel>
    </div>
</template>

<style lang="scss" scoped>
input[type=checkbox].checkbox:checked {
    background-color: #236de7;
    border: none;
    border-radius: 4px;

    &:before {
        content: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill-rule='evenodd' clip-rule='evenodd'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2' d='M4 8.5 7.2 11 12 5'/%3E%3C/svg%3E");
        height: 16px !important;
        margin: initial;
        position: relative;
        width: 16px !important;
    }

    &:disabled {
        background-color: #bbbdc6;
    }
}
</style>