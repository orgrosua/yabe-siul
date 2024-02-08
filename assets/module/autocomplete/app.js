import TailwindcssAutocomplete from './tailwindcss-autocomplete/dist/index.js';
import { set } from 'lodash-es';

(async () => {
    while (!window.siul?.loaded?.siul) {
        await new Promise((resolve) => setTimeout(resolve, 100));
    }

    const autocomplete = new TailwindcssAutocomplete(window.tailwind.config);

    const getSuggestionList = async (value) => await autocomplete.getSuggestionList(value);

    window.wp.hooks.addFilter('siul.module.autocomplete', 'siul', getSuggestionList);

    set(window, 'siul.loaded.module.autocomplete', true);
})();
