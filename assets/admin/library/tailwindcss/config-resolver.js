import iframeManager from './iframe-manager.js';

export function importAll(r) {
    return r.keys().map((path) => ({ path, mod: r(path) }));
}

export function collectTypes(rootFolder, typesFolder) {
    return {
        'index.d.ts': 'export * from "./types/config"',
        ...Object.fromEntries(
            importAll(rootFolder).map(({ path, mod }) => [
                path.replace('./', ''),
                mod,
            ])
        ),
        ...Object.fromEntries(
            importAll(typesFolder).map(({ path, mod }) => [
                path.replace('./', 'types/'),
                // Remove the `content` field
                mod?.replace(
                    /interface RequiredConfig \{.*?\}/s,
                    'interface RequiredConfig {}'
                ),
            ])
        ),
    };
}

export async function resolveConfig(configStr) {
    const iframe = await iframeManager.getConfigResolverIframe();

    const iframeEval = await new Promise(resolve => {
        // add event listener, and remove before resolve
        const listener = (event) => {
            // Check if the message comes from the specific iframe
            if (event.source === iframe.contentWindow && event.data.type === 'action' && event.data.action === 'resolve-config') {
                // Process the message from the iframe
                window.removeEventListener('message', listener);
                
                resolve(event.data);
            }
        };

        window.addEventListener('message', listener, false);

        iframe.contentWindow.postMessage(configStr, '*');
    });

    return iframeEval;
}
