import configResolverIframe from './config-resolver-iframe.html';
import compilerIframe from './compiler-iframe.html';
import axios from 'axios';
import { useStorage } from '@vueuse/core';

class IframeManager {
    static configResolverIframeEl = null;
    static compilerIframeEl = null;
    static configResolverIframeReady = false;
    static compilerIframeReady = false;

    /**
     * @param {boolean} forceRecreate - If true, the iframe will be recreated
     * @returns {Promise<HTMLIFrameElement>} The iframe element
     */
    static async getConfigResolverIframe(forceRecreate = false) {
        if (forceRecreate) {
            IframeManager.removeConfigResolverIframe();
            IframeManager.configResolverIframeReady = false;
        }

        if (!IframeManager.configResolverIframeEl) {
            IframeManager.configResolverIframeEl = document.createElement('iframe');
            IframeManager.configResolverIframeEl.id = 'siul-config-resolver-iframe';
            IframeManager.configResolverIframeEl.srcdoc = configResolverIframe;
            IframeManager.configResolverIframeEl.style.display = 'none';
            document.body.appendChild(IframeManager.configResolverIframeEl);
        }

        // Wait for the iframe to be ready. it will send a message to the parent window when it's ready
        if (!IframeManager.configResolverIframeReady) {
            await new Promise((resolve) => {
                window.addEventListener('message', (event) => {
                    if (event.source === IframeManager.configResolverIframeEl.contentWindow && event.data.type === 'iframe-ready') {
                        IframeManager.configResolverIframeReady = true;
                        resolve();
                    }
                });
            });
        }

        return IframeManager.configResolverIframeEl;
    }

    static removeConfigResolverIframe() {
        if (IframeManager.configResolverIframeEl) {
            IframeManager.configResolverIframeEl.remove();
            IframeManager.configResolverIframeEl = null;
        }
    }

    /**
     * @param {string} version - The Tailwind CSS version
     * @param {boolean} forceRecreate - If true, the iframe will be recreated
     * @returns {Promise<HTMLIFrameElement>} The iframe element
     */
    static async getCompilerIframe(version, forceRecreate = false) {
        if (!version) {
            throw new Error('No version provided for the compiler iframe.');
        }

        if (forceRecreate) {
            IframeManager.removeCompilerIframe();
            IframeManager.compilerIframeReady = false;
        }

        if (!IframeManager.compilerIframeEl) {
            let jspmStorage = useStorage('siul.ui.settings.performance.compile.jspm', {}, localStorage, {});

            let jspm = jspmStorage.value[`${version}`];

            if (jspm === undefined || jspm.generatedAt < new Date().getTime() - 60 * 60 * 24 * 1000) {
                jspm = await axios
                    .get('https://api.jspm.io/generate', {
                        params: {
                            env: JSON.stringify(['production', 'browser', 'module']),
                            install: JSON.stringify([
                                {
                                    target: 'tailwindcss@' + version,
                                    subpaths: [
                                        './nesting',
                                        './resolveConfig',
                                        './lib/processTailwindFeatures',
                                        './package.json.js',
                                    ]
                                },
                                { target: 'browserslist' },
                                { target: 'postcss' },
                            ]),
                            defaultProvider: 'esm.sh',
                        },
                    }).then((response) => response.data);

                jspm.generatedAt = new Date().getTime();

                jspmStorage.value[`${version}`] = jspm;
            }

            IframeManager.compilerIframeEl = document.createElement('iframe');
            IframeManager.compilerIframeEl.id = 'siul-compiler-iframe';

            IframeManager.compilerIframeEl.srcdoc = compilerIframe.replace('<!-- <script type="importmap"></script> -->', `<script type="importmap">${JSON.stringify(jspm.map)}</script>`);
            IframeManager.compilerIframeEl.style.display = 'none';
            document.body.appendChild(IframeManager.compilerIframeEl);
        }

        // Wait for the iframe to be ready. it will send a message to the parent window when it's ready
        if (!IframeManager.compilerIframeReady) {
            await new Promise((resolve) => {
                window.addEventListener('message', (event) => {
                    if (event.source === IframeManager.compilerIframeEl.contentWindow && event.data.type === 'iframe-ready') {
                        IframeManager.compilerIframeReady = true;
                        resolve();
                    }
                });
            });
        }

        return IframeManager.compilerIframeEl;
    }

    static removeCompilerIframe() {
        if (IframeManager.compilerIframeEl) {
            IframeManager.compilerIframeEl.remove();
            IframeManager.compilerIframeEl = null;
        }
    }
}

export default IframeManager;