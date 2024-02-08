import { defineStore } from 'pinia';
import { computed, ref, watch, toRaw } from 'vue';
import { useApi } from '../library/api.js';
import { useBusyStore } from './busy.js';
import { useNotifier } from '../library/notifier.js';
import { isEqual } from 'lodash-es';

export const useTailwindStore = defineStore('tailwind', () => {
    const busyStore = useBusyStore();
    const api = useApi();
    const notifier = useNotifier();

    /**
     * The Tailwind main CSS file.
     * @param {string} css
     */
    const css = ref(null);
    const _cssInit = ref(null);

    /**
     * The Tailwind preset that will be used to generate the Tailwind config.
     * @param {string} preset
     */
    const preset = ref(null);
    const _presetInit = ref(null);

    /**
     * The Tailwind config that gets generated from the preset.
     * @param {string} config
     */
    const config = ref(null);
    const _configInit = ref(null);

    /**
     * The Tailwind wizard.
     * @param {object[]} wizard
     */
    const wizard = ref([]);
    const _wizardInit = ref([]);

    const selectedWizardId = ref(null);

    const selectedWizard = computed(() => {
        return wizard.value.find(w => w.id === selectedWizardId.value);
    });

    watch(wizard, (value) => {
        if (selectedWizardId.value !== null && !value.find((item) => item.id === selectedWizardId.value)) {
            selectedWizardId.value = null;
        }
    }, { deep: true });

    /**
     * Pull the data from the server.
     *
     * @returns {Promise} A promise.
     */
    async function doPull() {
        busyStore.add('tailwind.doPull');

        return await api
            .request({
                method: 'GET',
                url: '/admin/tailwind/index',
            })
            .then((response) => {
                const data = response.data.tailwind;
                css.value = data.css;
                preset.value = data.preset;
                config.value = data.config;
                wizard.value = data.wizard;
                selectedWizardId.value = data.wizard[0].id;

                updateInitValues();
            })
            .catch((error) => {
                notifier.alert(error.message);
            })
            .finally(() => {
                busyStore.remove('tailwind.doPull');
            });
    }

    /**
     * Push the data to the server.
     *
     * @returns {Promise} A promise
     */
    async function doPush() {
        busyStore.add('tailwind.doPush');

        return api
            .request({
                method: 'POST',
                url: '/admin/tailwind/store',
                data: {
                    tailwind: {
                        css: css.value,
                        preset: preset.value,
                        config: config.value,
                        wizard: wizard.value,
                    }
                },
            })
            .then((response) => {
                updateInitValues();

                return {
                    message: response.data.message,
                    success: true,
                };
            })
            .catch((error) => {
                throw new Error(error.response ? error.response.data.message : error.message);
            })
            .finally(() => {
                busyStore.remove('tailwind.doPush');
            });
    }

    /**
     * Store the initial values.
     */
    function updateInitValues() {
        _cssInit.value = css.value;
        _presetInit.value = preset.value;
        _configInit.value = config.value;
        _wizardInit.value = toRaw(wizard.value);
    }

    /**
     * Check if the data has changed.
     */
    function hasChanged() {
        if (isEqual(_cssInit.value, css.value) === false) return true;
        if (isEqual(_presetInit.value, preset.value) === false) return true;
        if (isEqual(_configInit.value, config.value) === false) return true;
        if (isEqual(toRaw(_wizardInit.value), toRaw(wizard.value)) === false) return true;
        return false;
    }

    return {
        initValues: {
            css: _cssInit,
            preset: _presetInit,
            config: _configInit,
            wizard: _wizardInit,
        },
        css,
        preset,
        config,
        wizard,
        selectedWizardId,
        selectedWizard,
        doPull,
        doPush,
        hasChanged,
    };
});