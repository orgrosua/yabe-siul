import { initCSSRuntime } from '@master/css-runtime';

/** @type {import('@master/css').Config} */
const config = {
    scope: '#siul-app',
};

const masterCSS = initCSSRuntime(config);

export {
    config,
    masterCSS,
};
