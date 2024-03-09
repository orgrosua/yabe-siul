// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';
import 'vue-select/dist/vue-select.css';
import 'floating-vue/dist/style.css';

import './master.css.js';

const storedVersion = localStorage.getItem('siul.version');

if (!storedVersion || storedVersion !== window.siul._version) {
    localStorage.setItem('siul.version', window.siul._version);
    // clear the localStorage to avoid stale data
    localStorage.removeItem('siul.ui.settings.performance.compile.jspm');
}