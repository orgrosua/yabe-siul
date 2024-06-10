import { stringify as stringifyYaml } from 'yaml';
import axios from 'axios';
import { useApi } from '../../admin/library/api';
import { compileCSS } from '../../admin/library/tailwindcss/compiler';

const api = useApi();

let compileError = null;

let versions = [];
let providers = [];
const twConfig = {
    css: null,
    config: null,
    _custom: null,
    version: null,
};

async function fetchVersion() {
    await axios
        .get('https://data.jsdelivr.com/v1/package/npm/tailwindcss')
        .then((response) => {
            versions = response.data.versions.filter((v) => {
                return v >= '3.0.0' && v < '4.0.0';
            });
        });
}

async function pullSettings() {
    return await api
        .request({
            method: 'GET',
            url: '/admin/settings/options/index',
        })
        .then((response) => {
            twConfig.version = response.data.options?.general?.tailwindcss?.version || 'latest';
        })
        .catch((error) => {
            throw error;
        });
}

async function pullConfig() {
    return await api
        .request({
            method: 'GET',
            url: '/admin/tailwind/index',
        })
        .then(response => response.data)
        .then((data) => {
            twConfig.css = data.tailwind.css;
            twConfig.config = data.tailwind.config;
            twConfig._custom = data._custom;
        })
        .catch((error) => {
            throw error;
        });
}

async function pullProviders() {
    await api
        .get('admin/settings/cache/providers')
        .then((resp) => {
            providers = resp.data.providers;
        });
}

export function generateCache() {
    compileError = null;

    const promise = (async () => {
        if (versions.length === 0) {
            await fetchVersion();
        }

        if (twConfig.config === null) {
            try {
                await pullSettings();
            } catch (error) {
                compileError = {
                    message: error.message,
                    action: 'pull-settings',
                }
                return false;
            }

            try {
                await pullConfig();
            } catch (error) {
                compileError = {
                    message: error.message,
                    action: 'pull-config',
                }
                return false;
            }
        }

        await pullProviders();

        if (providers.length === 0) {
            compileError = {
                message: 'No cache provider found',
                action: 'scan-content',
            }
            return false;
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

        const promises = providers.filter(provider => provider.enabled)
            .map(provider => fetchProviderContents(provider));

        await Promise.all(promises);

        const contents = content_pool.map((c) => {
            let content = atob(c.content);

            if (c.type === 'json') {
                content = stringifyYaml(JSON.parse(content));
            }

            return content;
        });

        const compiled_css = await compileCSS(
            twConfig.version === 'latest' ? versions[0] : twConfig.version,
            `${twConfig._custom.config.prepend}\n${twConfig.config}\n${twConfig._custom.config.append}`,
            `${twConfig._custom.css.prepend}\n${twConfig.css}\n${twConfig._custom.css.append}`,
            contents
        );

        if (compiled_css._error) {
            compileError = compiled_css._error;
            return false;
        }

        const license = `/* ! tailwindcss v${twConfig.version === 'latest' ? versions[0] : twConfig.version} | MIT License | https://tailwindcss.com */`;

        await api
            .post('admin/settings/cache/store', {
                // @see https://developer.mozilla.org/en-US/docs/Glossary/Base64#the_unicode_problem
                content: btoa(String.fromCodePoint(...new TextEncoder().encode(`${license}\n${compiled_css.css}`))),
            });

        return true;
    })();

    return {
        promise: promise,
        _error: compileError,
    };
}

window.generateTailwindCache = generateCache;