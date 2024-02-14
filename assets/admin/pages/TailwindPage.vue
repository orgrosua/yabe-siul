<script setup>
import { ref, watch, computed, watchEffect, onBeforeMount, onMounted, onUnmounted, toRaw } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { configureMonacoTailwindcss, tailwindcssData } from 'monaco-tailwindcss';
import * as monaco from 'monaco-editor';
import { debounce } from 'lodash-es';
import { __ } from '@wordpress/i18n';
import { useStorage } from '@vueuse/core';

import * as prettier from 'prettier';
import prettierPluginBabel from 'prettier/plugins/babel';
import prettierPluginEstree from 'prettier/plugins/estree';

import {
    collectTypes,
    resolveConfig as playResolveConfig
} from '../library/tailwindcss/config-resolver.js';
import twResolveConfig from 'tailwindcss/resolveConfig';

import ExpansionPanel from '../components/ExpansionPanel.vue';
import { useTailwindStore } from '../stores/tailwind.js';
import { useSettingsStore } from '../stores/settings.js';
import { storeToRefs } from 'pinia';
import WizardLayout from '../components/Wizard/WizardLayout.vue';
import { useNotifier } from '../library/notifier';

import { wizardToTailwindConfig } from '../components/Wizard/TailwindConfig.js';

const tailwindStore = useTailwindStore();
const settingsStore = useSettingsStore();
const notifier = useNotifier();

const { css: twCss, preset: twPreset, wizard: twWizard, config: twConfig } = storeToRefs(tailwindStore);

const configError = ref(null);

// monaco models
const model = {
    css: null,
    preset: null,
    config: null,
};

const modelUri = {
    css: monaco.Uri.parse('file:///main.css'),
    preset: monaco.Uri.parse('file:///preset.js'),
    config: monaco.Uri.parse('file:///tailwind.config.js'),
};

model.css = monaco.editor.getModels().find((model) => model.uri.path === modelUri.css.path) ?? monaco.editor.createModel(tailwindStore.css, 'css', modelUri.css);
model.preset = monaco.editor.getModels().find((model) => model.uri.path === modelUri.preset.path) ?? monaco.editor.createModel(tailwindStore.preset, 'javascript', modelUri.preset);
model.config = monaco.editor.getModels().find((model) => model.uri.path === modelUri.config.path) ?? monaco.editor.createModel(tailwindStore.config, 'typescript', modelUri.config);

const langJSDiagnosticOptions = {
    noSemanticValidation: false,
    noSyntaxValidation: false,
    noSuggestionDiagnostics: false,
    diagnosticCodesToIgnore: [
        80001, // "File is a CommonJS module; it may be converted to an ES6 module."
        2307, // "Cannot find module 'x'."
    ],
};

const langJSCompilerOptions = {
    allowJs: true,
    allowNonTsExtensions: true,
    module: monaco.languages.typescript.ModuleKind.CommonJS,
    target: monaco.languages.typescript.ScriptTarget.Latest,
    checkJs: true,
    moduleResolution:
        monaco.languages.typescript.ModuleResolutionKind.NodeJs,
    typeRoots: ['node_modules/@types'],
};

monaco.languages.typescript.javascriptDefaults.setDiagnosticsOptions(langJSDiagnosticOptions);
monaco.languages.typescript.typescriptDefaults.setDiagnosticsOptions(langJSDiagnosticOptions);

monaco.languages.typescript.javascriptDefaults.setCompilerOptions(langJSCompilerOptions);
monaco.languages.typescript.typescriptDefaults.setCompilerOptions(langJSCompilerOptions);

// webpack 5 now use ?resource instead of !raw-loader
const typeFiles = collectTypes(
    require.context('tailwindcss/?source', false, /\.d\.ts$/),
    require.context('tailwindcss/types/?source', true, /\.d\.ts$/)
);

// Add all the types to the monaco editor
for (let file in typeFiles) {
    monaco.languages.typescript.javascriptDefaults.addExtraLib(
        typeFiles[file],
        `file:///node_modules/@types/tailwindcss/${file}`
    );

    monaco.languages.typescript.typescriptDefaults.addExtraLib(
        typeFiles[file],
        `file:///node_modules/@types/tailwindcss/${file}`
    );
}

// add Lodash types
const _importAll = (r) => {
    return r.keys().map((path) => ({ path, mod: r(path) }));
};

const lodashTypes = {
    ...Object.fromEntries(
        _importAll(require.context('@types/lodash/?source', true, /\.d\.ts$/)).map(({ path, mod }) => [
            path.replace('./', ''),
            mod,
        ])
    )
};

for (let file in lodashTypes) {
    monaco.languages.typescript.javascriptDefaults.addExtraLib(
        lodashTypes[file],
        `file:///node_modules/@types/lodash/${file}`
    );

    monaco.languages.typescript.typescriptDefaults.addExtraLib(
        lodashTypes[file],
        `file:///node_modules/@types/lodash/${file}`
    );
}

// tailwind directives for monaco
monaco.languages.css.cssDefaults.setOptions({
    data: {
        dataProviders: {
            tailwind: tailwindcssData,
        },
    },
});

// configure monaco for tailwind
let monacoTailwindcss;

// monaco theme
const theme = useStorage('theme', 'light');
const monacoTheme = computed(() => theme.value === 'light' ? 'vs-light' : 'vs-dark');
watchEffect(() => {
    monaco.editor.setTheme(monacoTheme.value);
});

// add key binding command to monaco.editor to save all changes
monaco.editor.addEditorAction({
    id: 'save',
    label: 'Save',
    keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS],
    run: () => {
        doSave();
    }
});

// CSS Editor
const editorCssEl = ref(null);

/** @type {?import('monaco-editor').editor.IStandaloneCodeEditor} */
let editorCss = null;

// Preset Editor
const editorPresetEl = ref(null);

/** @type {?import('monaco-editor').editor.IStandaloneCodeEditor} */
let editorPreset = null;

// Config Editor
const editorConfigEl = ref(null);

/** @type {?import('monaco-editor').editor.IStandaloneCodeEditor} */
let editorConfig = null;

// debounce the config parsing to avoid too many calls
const debouncedPlayResolveConfig = debounce(() => {
    playResolveConfig(model.preset.getValue())
        .then((result) => {
            configError.value = result.config._error ?? null;
        });
}, 500);

async function consumeConfig() {
    await playResolveConfig(model.config.getValue())
        .then((tailwindConfig) => {
            if (tailwindConfig._error) {
                console.error('tailwindConfig._error', tailwindConfig._error);
                return;
            }

            const parsed = JSON.parse(JSON.stringify(twResolveConfig(tailwindConfig)));

            if (monacoTailwindcss) {
                monacoTailwindcss.setTailwindConfig(parsed);
            } else {
                monacoTailwindcss = configureMonacoTailwindcss(monaco, { tailwindConfig: parsed });
            }
        });
}

function doSave() {
    const promise = tailwindStore.doPush();

    notifier.async(
        promise,
        resp => notifier.success(resp.message),
        err => notifier.alert(err.message),
        'Storing TailwindCSS config...'
    );
}

watch(twConfig, (value, oldValue) => {
    if (!editorConfig || value == oldValue) {
        return;
    }

    editorConfig.setValue(twConfig.value);
});

function updateTwConfig() {
    const _preset = twPreset.value.includes('//-@-wizard')
        ? twPreset.value.replace('//-@-wizard', wizardToTailwindConfig(toRaw(twWizard.value), settingsStore.options?.general?.tailwindcss?.version ?? 'latest'))
        : twPreset.value;

    (async () => {
        try {
            const formatted = await prettier.format(_preset, {
                parser: 'babel',
                plugins: [prettierPluginBabel, prettierPluginEstree],
            });
            twConfig.value = formatted;
        } catch (e) { /* empty */ }
    })();
}

function resetToDefault(k) {
    if (confirm(__('Are you sure you want to reset to default?', 'yabe-siul'))) {
        if (k === 'css') {
            editorCss.setValue(tailwindStore.defaultValues.css);
        } else if (k === 'preset') {
            editorPreset.setValue(tailwindStore.defaultValues.preset);
        }
    }
}

watch(twWizard, () => {
    updateTwConfig();
}, { deep: true });

watch(twPreset, () => {
    updateTwConfig();
});

onBeforeMount(() => {
});

onMounted(() => {
    /** @type {import('monaco-editor').editor.IStandaloneEditorConstructionOptions} */
    const cssMonacoOptions = {
        colorDecorators: true,
        automaticLayout: true,
        model: model.css,
    };

    editorCss = monaco.editor.create(editorCssEl.value, cssMonacoOptions);

    /** @type {import('monaco-editor').editor.IStandaloneEditorConstructionOptions} */
    const presetMonacoOptions = {
        automaticLayout: true,
        model: model.preset,
    };

    editorPreset = monaco.editor.create(editorPresetEl.value, presetMonacoOptions);

    /** @type {import('monaco-editor').editor.IStandaloneEditorConstructionOptions} */
    const configMonacoOptions = {
        automaticLayout: true,
        model: model.config,
        readOnly: true,
    };

    editorConfig = monaco.editor.create(editorConfigEl.value, configMonacoOptions);

    // if the css editor content changes
    model.css?.onDidChangeContent(() => {
        twCss.value = model.css.getValue();
    });

    // if the preset editor content changes
    model.preset?.onDidChangeContent(() => {
        debouncedPlayResolveConfig();
        twPreset.value = model.preset.getValue();
    });

    model.config?.onDidChangeContent(() => {
        consumeConfig();
    });

    // set the monaco editor content
    (async () => {
        if (tailwindStore.initValues.preset === null) {
            // get the stored data
            await tailwindStore.doPull();
        }

        if (Object.keys(settingsStore.options).length === 0) {
            await settingsStore.doPull();            
        }

        // set the css editor content
        editorCss.setValue(tailwindStore.css);

        // set the preset editor content
        editorPreset.setValue(tailwindStore.preset);
    })();
});

onBeforeRouteLeave((to, from, next) => {
    if (tailwindStore.hasChanged() && !confirm(__('You have unsaved changes. Are you sure you want to leave?', 'yabe-siul'))) next(from);
    else next();
});

window.onbeforeunload = function () {
    if (tailwindStore.hasChanged()) {
        return __('You have unsaved changes. Are you sure you want to leave?', 'yabe-siul');
    }
};

onUnmounted(() => {
    editorCss?.dispose();
    editorPreset?.dispose();
});

// Expose the doSave function to be used by the App.vue
defineExpose({
    doSave,
});
</script>

<template>
    <div class="flex flex:col">
        <ExpansionPanel namespace="tailwind" name="css" class="my:8">
            <template #header>
                <div class="flex">
                    <span class="flex-grow:1 font:16 font:semibold">main.css</span>
                    <div @click="resetToDefault('css')" class="cursor:pointer fg:red-80 font:16 font:semibold mr:20"><font-awesome-icon :icon="['fas', 'trash-undo']" title="Reset to default" /></div>
                </div>
            </template>

            <template #default>
                <div class="">
                    <div class="editor-container">
                        <div id="editorCss" ref="editorCssEl" class="h:600"></div>
                    </div>
                </div>
            </template>
        </ExpansionPanel>

        <ExpansionPanel namespace="tailwind" name="preset" class="my:8">
            <template #header>
                <div class="flex">
                    <span class="flex-grow:1 font:16 font:semibold">preset.js</span>
                    <div @click="resetToDefault('preset')" class="cursor:pointer fg:red-80 font:16 font:semibold mr:20"><font-awesome-icon :icon="['fas', 'trash-undo']" title="Reset to default" /></div>
                </div>
            </template>

            <template #default>
                <div class="">
                    <div class="editor-container">
                        <div id="editorPreset" ref="editorPresetEl" class="h:600"></div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div v-if="configError" class="bg:red-5 fg:red-80 p:24">
                    <h2 class="flex align-items:center f:16 fg:red-95 font:semibold lh:24px mb:16">
                        <span class="rounded! b:4|solid|red-20 bg:red-40 box:content h:8 w:8"></span>
                        <span class="ml:14">Config Error</span>
                        <dl v-if="configError.line !== undefined" class="rounded! bg:red-10 f:14 fg:red-80 font:medium lh:24px ml:16 my:0 px:12">
                            <dt class="inline">Line</dt>
                            <dd class="inline ml:4">{{ configError.line }}</dd>
                        </dl>
                    </h2>
                    <p class="f:14 font:mono lh:20px"> {{ configError.message }}</p>
                </div>
            </template>
        </ExpansionPanel>

        <ExpansionPanel namespace="tailwind" name="wizard" class="my:8">
            <template #header>
                <font-awesome-icon :icon="['fas', 'gear']" class="mr:6" />
                <span class="font:16 font:semibold">wizard</span>
                <span class="bg:yellow-5/.5 fg:yellow-70 font:12 font:medium ml:8 outline:1|solid|yellow-60/.2 px:8 py:4 r:6">Experimental</span>
            </template>

            <template #default>
                <div class="">
                    <WizardLayout />
                </div>
            </template>
        </ExpansionPanel>

        <ExpansionPanel namespace="tailwind" name="config" class="my:8">
            <template #header>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 54 33" class="h:1em mr:6 vertical-align:-0.125em">
                    <g clip-path="url(#prefix__clip0)">
                        <path fill="#38bdf8" fill-rule="evenodd" d="M27 0c-7.2 0-11.7 3.6-13.5 10.8 2.7-3.6 5.85-4.95 9.45-4.05 2.054.513 3.522 2.004 5.147 3.653C30.744 13.09 33.808 16.2 40.5 16.2c7.2 0 11.7-3.6 13.5-10.8-2.7 3.6-5.85 4.95-9.45 4.05-2.054-.513-3.522-2.004-5.147-3.653C36.756 3.11 33.692 0 27 0zM13.5 16.2C6.3 16.2 1.8 19.8 0 27c2.7-3.6 5.85-4.95 9.45-4.05 2.054.514 3.522 2.004 5.147 3.653C17.244 29.29 20.308 32.4 27 32.4c7.2 0 11.7-3.6 13.5-10.8-2.7 3.6-5.85 4.95-9.45 4.05-2.054-.513-3.522-2.004-5.147-3.653C23.256 19.31 20.192 16.2 13.5 16.2z" clip-rule="evenodd" />
                    </g>
                    <defs>
                        <clipPath id="prefix__clip0">
                            <path fill="#fff" d="M0 0h54v32.4H0z" />
                        </clipPath>
                    </defs>
                </svg>

                <span class="font:16 font:semibold">tailwind.config.js</span>

                <font-awesome-icon :icon="['fas', 'file-lock']" class="fg:gray-40 ml:6" title="read-only" />
            </template>

            <template #default>
                <div class="">
                    <div class="editor-container">
                        <div id="editorConfig" ref="editorConfigEl" class="h:600"></div>
                    </div>
                </div>
            </template>
        </ExpansionPanel>
    </div>
</template>