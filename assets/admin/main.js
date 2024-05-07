// import './public-path.js';

// require('./bootstrap');


import './styles/app.scss';
import 'floating-vue/dist/style.css';
import './master.css.js';

import { __, _n, sprintf } from '@wordpress/i18n';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import FloatingVue from 'floating-vue';
import InlineSvg from 'vue-inline-svg';
import { FontAwesomeIcon } from './font-awesome.js';
import Draggable from 'zhyswan-vuedraggable';
import VueSelect from 'vue-select';
import LvButton from 'lightvue/button';
import LvInput from 'lightvue/input';
import LvColorpicker from 'lightvue/color-picker';
import LvOverlayPanel from 'lightvue/overlay-panel';

import { install as VueMonacoEditorPlugin } from '@guolao/vue-monaco-editor';

import App from './App.vue';
import router from './router.js';

const pinia = createPinia();
const app = createApp(App);

app.config.globalProperties.__ = __;
app.config.globalProperties._n = _n;
app.config.globalProperties.sprintf = sprintf;
app.config.globalProperties.siul = window.siul;

// // https://github.com/lightvue/lightvue/blob/d3219dd658e960c85a27ad151bd0ba65c68993a7/docs-v3/src/main.js#L12
app.config.globalProperties.$listeners = '';
app.config.globalProperties.$lightvue = { ripple: true, version: 3 };

app
    .use(pinia)
    .use(router)
    .use(FloatingVue, {
        container: '#siul-app',
    })
    .use(VueMonacoEditorPlugin, {
        paths: {
            // The recommended CDN config
            vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/vs'
        },
    })
    ;

app
    .component('font-awesome-icon', FontAwesomeIcon)
    .component('inline-svg', InlineSvg)
        .component('VueSelect', VueSelect)
        .component('Draggable', Draggable)
        .component('LvButton', LvButton)
        .component('LvInput', LvInput)
        .component('LvColorpicker', LvColorpicker)
        .component('LvOverlayPanel', LvOverlayPanel)
    ;

app.mount('#siul-app');