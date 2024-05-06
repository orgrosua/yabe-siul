<script setup>
import { ref, watch, computed, watchEffect, onBeforeMount, onMounted, onUnmounted, toRaw, shallowRef, nextTick } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useMonaco } from '@guolao/vue-monaco-editor';
import { useStorage, useRefHistory } from '@vueuse/core';
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash-es';

// import * as monaco from 'monaco-editor';

import { configureMonacoTailwindcss, tailwindcssData } from 'monaco-tailwindcss';

import { useTailwindStore } from '../stores/tailwind.js';
import { useSettingsStore } from '../stores/settings.js';
import { useNotifier } from '../library/notifier';
import ExpansionPanel from '../components/ExpansionPanel.vue';
import { editor } from 'monaco-editor';

const tailwindStore = useTailwindStore();
const settingsStore = useSettingsStore();
const notifier = useNotifier();
const { monacoRef, unload } = useMonaco();

const bc = new BroadcastChannel('siul_channel');

const { css: twCss, preset: twPreset, wizard: twWizard, config: twConfig } = storeToRefs(tailwindStore);

const configError = ref(null);

// monaco theme
const theme = useStorage('theme', 'light');
const monacoTheme = computed(() => theme.value === 'light' ? 'vs' : 'vs-dark');

const MONACO_EDITOR_OPTIONS = {
    colorDecorators: true,
    automaticLayout: true,
    formatOnPaste: true,
}

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
    module: 1, // monaco.languages.typescript.ModuleKind.CommonJS,
    target: 99, // monaco.languages.typescript.ScriptTarget.Latest,
    checkJs: true,
    moduleResolution: 2, // monaco.languages.typescript.ModuleResolutionKind.NodeJs,
    typeRoots: ['node_modules/@types'],
};

// configure monaco for tailwind
let monacoTailwindcss;

// CSS Editor
/** @type {?import('monaco-editor').editor.IStandaloneCodeEditor} */
const editorCssRef = shallowRef();
const handleCssEditorMount = editor => (editorCssRef.value = editor);

// Preset editor
/** @type {?import('monaco-editor').editor.IStandaloneCodeEditor} */
const editorPresetRef = shallowRef();
const handlePresetEditorMount = editor => (editorPresetRef.value = editor);

// Config editor
/** @type {?import('monaco-editor').editor.IStandaloneCodeEditor} */
const editorConfigRef = shallowRef();
const handleConfigEditorMount = editor => (editorConfigRef.value = editor);




function doSave() {
    const promise = tailwindStore.doPush();

    notifier.async(
        promise,
        resp => notifier.success(resp.message),
        err => notifier.alert(err.message),
        'Storing TailwindCSS config...'
    );
}

const handleConfigEditorBeforeMount = editor => {
    if (monacoRef.value) {

        console.log('editorConfigRef', editorConfigRef.value)

        // monacoRef.value.editor
        console.log('monacoRef.value', monacoRef.value);

        monacoRef.value.languages.typescript.javascriptDefaults.setDiagnosticsOptions(langJSDiagnosticOptions);
        monacoRef.value.languages.typescript.typescriptDefaults.setDiagnosticsOptions(langJSDiagnosticOptions);

        monacoRef.value.languages.typescript.javascriptDefaults.setCompilerOptions(langJSCompilerOptions);
        monacoRef.value.languages.typescript.typescriptDefaults.setCompilerOptions(langJSCompilerOptions);


        // Vite
        const lodashTypes = import.meta.glob('../../../node_modules/@types/lodash/*.d.ts', {
            query: '?raw',
            import: 'default',
            eager: true,
        });

        console.log('lt', lodashTypes);

        // lodashTypes is object, not array, let's loop through it

        for (const [key, value] of Object.entries(lodashTypes)) {
            monacoRef.value.languages.typescript.javascriptDefaults.addExtraLib(
                value,
                `file:///${key.replaceAll('../', '')}`
            );
            monacoRef.value.languages.typescript.typescriptDefaults.addExtraLib(
                value,
                `file:///${key.replaceAll('../', '')}`
            );
        }


        console.log('compilers', monacoRef.value.languages.typescript.javascriptDefaults.getCompilerOptions());

        console.log('extralibs', monacoRef.value.languages.typescript.typescriptDefaults.getExtraLibs());


    
        

        // add key binding command to monaco.editor to save all changes
        monacoRef.value.editor.addEditorAction({
            id: 'save',
            label: 'Save',
            keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS],
            run: () => {
                doSave();
            }
        });
    }
};


// const stop = watchEffect(() => {
//     if (monacoRef.value) {
//         nextTick(() => stop())

//         console.log('editorConfigRef', editorConfigRef.value)

//         // monacoRef.value.editor
//         console.log('monacoRef.value', monacoRef.value);

//         monacoRef.value.languages.typescript.javascriptDefaults.setDiagnosticsOptions(langJSDiagnosticOptions);
//         monacoRef.value.languages.typescript.typescriptDefaults.setDiagnosticsOptions(langJSDiagnosticOptions);

//         monacoRef.value.languages.typescript.javascriptDefaults.setCompilerOptions(langJSCompilerOptions);
//         monacoRef.value.languages.typescript.typescriptDefaults.setCompilerOptions(langJSCompilerOptions);


//         // Vite
//         const lodashTypes = import.meta.glob('../../../node_modules/@types/lodash/*.d.ts', {
//             query: '?raw',
//             import: 'default',
//             eager: true,
//         });

//         console.log('lt', lodashTypes);

//         // lodashTypes is object, not array, let's loop through it

//         for (const [key, value] of Object.entries(lodashTypes)) {
//             monacoRef.value.languages.typescript.javascriptDefaults.addExtraLib(
//                 value,
//                 `file:///${key.replaceAll('../', '')}`
//             );
//             monacoRef.value.languages.typescript.typescriptDefaults.addExtraLib(
//                 value,
//                 `file:///${key.replaceAll('../', '')}`
//             );
//         }


//         console.log('compilers', monacoRef.value.languages.typescript.javascriptDefaults.getCompilerOptions());

//         console.log('extralibs', monacoRef.value.languages.typescript.typescriptDefaults.getExtraLibs());


    
        

//         // add key binding command to monaco.editor to save all changes
//         monacoRef.value.editor.addEditorAction({
//             id: 'save',
//             label: 'Save',
//             keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS],
//             run: () => {
//                 doSave();
//             }
//         });
//     }
// })

function resetToDefault(k) {
    if (confirm(__('Are you sure you want to reset to default?', 'yabe-siul'))) {
        if (k === 'css') {
            twCss.value = tailwindStore.defaultValues.css;
            // editorCss.setValue(tailwindStore.defaultValues.css);
        } else if (k === 'preset') {
            // editorPreset.setValue(tailwindStore.defaultValues.preset);
        }
    }
}


onMounted(() => {
    // set the monaco editor content
    (async () => {
        if (tailwindStore.initValues.preset === null) {
            // get the stored data
            await tailwindStore.doPull();
        }

        if (Object.keys(settingsStore.options).length === 0) {
            await settingsStore.doPull();
        }
    })();
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
                        <!-- <div ref="editorCssRef" class="h:600"></div> -->
                        <div class="h:600">
                            <vue-monaco-editor v-model:value="twCss" language="scss" :theme="monacoTheme" :options="MONACO_EDITOR_OPTIONS" @mount="handleCssEditorMount" />
                        </div>
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
                        <!-- <div id="editorPreset" ref="editorPresetEl" class="h:600"></div> -->
                        <div class="h:600">
                            <vue-monaco-editor v-model:value="twPreset" language="javascript" :theme="monacoTheme" :options="MONACO_EDITOR_OPTIONS" @mount="handlePresetEditorMount" />
                        </div>
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

        <!-- <ExpansionPanel namespace="tailwind" name="wizard" class="my:8">
            <template #header>
                <div class="flex">
                    <div class="flex-grow:1">
                        <font-awesome-icon :icon="['fas', 'gear']" class="mr:6" />
                        <span class="font:16 font:semibold">wizard</span>
                        <span class="bg:yellow-5/.5 fg:yellow-70 font:12 font:medium ml:8 outline:1|solid|yellow-60/.2 px:8 py:4 r:6">Experimental</span>
                    </div>

                    <div class="flex gap:10 mr:20">
                        <button type="button" @click="twWizardUndo" :disabled="!twWizardCanUndo" title="undo">
                            <font-awesome-icon :icon="['fas', 'reply']" />
                        </button>
                        <button type="button" @click="twWizardRedo" :disabled="!twWizardCanRedo" title="redo">
                            <font-awesome-icon :icon="['fas', 'share']" />
                        </button>
                    </div>
                </div>
            </template>

            <template #default>
                <div class="">
                    <WizardLayout />
                </div>
            </template>
        </ExpansionPanel> -->

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

                <span class="bg:gray-5/.5 fg:gray-70 font:12 font:medium ml:8 outline:1|solid|gray-60/.2 px:8 py:4 r:6">read-only</span>
            </template>

            <template #default>
                <div class="">
                    <div class="editor-container">
                        <!-- <div id="editorConfig" ref="editorConfigEl" class="h:600"></div> -->

                        <div class="h:600">
                            <vue-monaco-editor v-model:value="twConfig" language="typescript" :theme="monacoTheme" :options="{ ...MONACO_EDITOR_OPTIONS, readOnly: true }" @mount="handleConfigEditorMount" @beforeMount="handleConfigEditorBeforeMount" />
                        </div>
                    </div>
                </div>
            </template>
        </ExpansionPanel>
    </div>
</template>