(async () => {
    let rootContainer;

    console.log('waiting for the root container...');

    // wait for the root container to be available
    while (!rootContainer) {
        rootContainer = document.querySelector('iframe[name="editor-canvas"]');
        await new Promise(resolve => setTimeout(resolve, 100));
    }

    // wait for the script and style to be available
    console.log('finding SIUL script and style...');

    // Timeout flag and timer to limit the search duration
    let timeoutOccurred = false;
    let timeout = setTimeout(() => {
        timeoutOccurred = true;
    }, 3000);

    // wait for the script and style to be available
    while (!timeoutOccurred) {
        let cssElement = document.querySelector('style#siul-tailwindcss-main-css');
        let jitElement = document.querySelector('script#siul-tailwindcss-jit');
        let playElement = document.querySelector('script#siul-tailwindcss-play-cdn');

        if (cssElement && jitElement && playElement) {
            clearTimeout(timeout);
            break;
        }

        await new Promise(resolve => setTimeout(resolve, 100));
    }

    if (timeoutOccurred) {
        console.log('time out! failed to find SIUL script and style');
        return;
    }

    console.log('found SIUL script and style');

    // Create a textarea element to manipulate script content
    let textareaPlayElement = document.createElement('textarea');
    let textareaJitElement = document.createElement('textarea');

    // Copy the Play CDN script content to the textarea
    textareaPlayElement.innerHTML = document.querySelector('script#siul-tailwindcss-play-cdn').outerHTML;
    let playContent = textareaPlayElement.value;

    // Copy the JIT script content to the textarea
    textareaJitElement.innerHTML = document.querySelector('script#siul-tailwindcss-jit').outerHTML;
    let jitContent = textareaJitElement.value;


    let contentWindow = rootContainer.contentWindow || rootContainer;
    let contentDocument = rootContainer.contentDocument || contentWindow.document;

    // Inject the mutation observer script into the root iframe
    let sandboxScript = document.createElement('script');
    sandboxScript.innerHTML = `
        // Create a textarea element to manipulate script content
        let textareaPlayElement = document.createElement('textarea');
        let textareaJitElement = document.createElement('textarea');

        // Copy the Play CDN script content to the textarea
        textareaPlayElement.innerHTML = document.querySelector('script#siul-tailwindcss-play-cdn').outerHTML;
        let playContent = textareaPlayElement.value;

        // Copy the JIT script content to the textarea
        textareaJitElement.innerHTML = document.querySelector('script#siul-tailwindcss-jit').outerHTML;
        let jitContent = textareaJitElement.value;

        // Function to inject the script and style into the iframe
        let inject = () => {
            let iframes = document.querySelectorAll('iframe.components-sandbox');
            for (let iframe of iframes) {
                let contentWindow = iframe.contentWindow || iframe;
                let contentDocument = iframe.contentDocument || contentWindow.document;

                if (!contentDocument.querySelector('script#tailwindcss-play')) {
                    contentDocument.head.appendChild(document.createRange().createContextualFragment(jitContent));
                    contentDocument.head.appendChild(document.createRange().createContextualFragment(playContent));
                    contentDocument.head.appendChild(document.querySelector('style#siul-tailwindcss-main-css').cloneNode(true));
                }
            }
        };

        // Observe the root container (current window) for new iframes ('iframe.components-sandbox')
        new MutationObserver(mutations => {
            for (let mutation of mutations) {
                for (let node of mutation.addedNodes) {
                    if (node.tagName === 'IFRAME') {
                        // Inject the script and style into the iframe
                        console.log('injecting SIUL script and style into the component sandbox iframe');
                        inject();
                    }
                }
            }
        }).observe(document, {
            subtree: true,
            childList: true
        });
    `;

    // Inject the script and style into the root iframe
    console.log('injecting SIUL script and style into the root container');

    if (!contentDocument.querySelector('script#tailwindcss-play')) {
        console.log('starting the root injection process...');
        contentDocument.head.appendChild(document.createRange().createContextualFragment(jitContent));
        contentDocument.head.appendChild(document.createRange().createContextualFragment(playContent));
        contentDocument.head.appendChild(document.querySelector('style#siul-tailwindcss-main-css').cloneNode(true));
        contentDocument.head.appendChild(sandboxScript);
    }
})();