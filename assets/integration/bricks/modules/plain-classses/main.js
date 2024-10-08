/**
 * @module plain-classes 
 * @package Yabe Siul
 * @since 1.0.0
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 * 
 * Add plain classes to the element panel.
 */

import './style.scss';

import { logger } from '../../../common/logger.js';

import tippy, { followCursor } from 'https://esm.sh/tippy.js';

import { nextTick, ref, watch } from 'vue';
import autosize from 'https://esm.sh/autosize';
import Tribute from 'https://esm.sh/gh/zurb/tribute';

import { getHighlighterCore, loadWasm } from 'https://esm.sh/shiki/core';

import HighlightInTextarea from '../../../library/highlight-in-textarea.js';
import { brxGlobalProp, brxIframeGlobalProp, brxIframe } from '../../constant.js';

let shikiHighlighter = null;

(async () => {
    await loadWasm(import('https://esm.sh/shiki/wasm'));
    shikiHighlighter = await getHighlighterCore({
        themes: [
            import('https://esm.sh/shiki/themes/dark-plus.mjs'),
            import('https://esm.sh/shiki/themes/light-plus.mjs'),
        ],
        langs: [
            import('https://esm.sh/shiki/langs/css.mjs'),
        ],
    });
})();

const textInput = document.createRange().createContextualFragment(/*html*/ `
    <textarea id="siulbricks-plc-input" class="siulbricks-plc-input" rows="2" spellcheck="false"></textarea>
`).querySelector('#siulbricks-plc-input');

const containerAction = document.createRange().createContextualFragment(/*html*/ `
    <div class="siulbricks-plc-action-container">
        <div class="actions">
        </div>
    </div>
`).querySelector('.siulbricks-plc-action-container');
const containerActionButtons = containerAction.querySelector('.actions');

const classSortButton = document.createRange().createContextualFragment(/*html*/ `
    <span id="siulbricks-plc-class-sort" class="bricks-svg-wrapper siulbricks-plc-class-sort" data-balloon="Automatic Class Sorting" data-balloon-pos="bottom-right">
        <svg  xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round" class="bricks-svg icon icon-tabler icons-tabler-outline icon-tabler-reorder"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" /><path d="M10 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" /><path d="M17 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" /><path d="M5 11v-3a3 3 0 0 1 3 -3h8a3 3 0 0 1 3 3v3" /><path d="M16.5 8.5l2.5 2.5l2.5 -2.5" /></svg>    
    </span>
`).querySelector('#siulbricks-plc-class-sort');
containerActionButtons.appendChild(classSortButton);

const visibleElementPanel = ref(false);
const activeElementId = ref(null);

let twConfig = null;
let screenBadgeColors = [];

(async () => {
    if (brxIframe.contentWindow.tailwind) {
        const tw = brxIframe.contentWindow.tailwind;
        twConfig = await tw.resolveConfig(tw.config);

        // find all colors that value a object and the object has 500 key
        let baseColors = Object.keys(tw.colors).filter((color) => {
            return typeof tw.colors[color] === 'object' && tw.colors[color][500] !== undefined && ![
                "slate",
                "gray",
                "zinc",
                "neutral",
                "stone",
                "warmGray",
                "trueGray",
                "coolGray",
                "blueGray"
            ].includes(color);
        });

        baseColors = baseColors.map((color) => {
            return {
                name: color,
                value: tw.colors[color][500],
            };
        });

        // randomize the base colors
        baseColors.sort(() => Math.random() - 0.5);

        let screenKeys = Object.keys(twConfig.theme.screens);

        for (let i = 0; i < screenKeys.length; i++) {
            screenBadgeColors.push({
                screen: screenKeys[i],
                color: baseColors[i].value,
            });
        }
    }
})();

let hit = null; // highlight any text except spaces and new lines

autosize(textInput);

let autocompleteItems = [];

wp.hooks.addAction('siulbricks-autocomplete-items-refresh', 'siulbricks', () => {
    // wp hook filters. {value, color?, fontWeight?, namespace?}[]
    autocompleteItems = wp.hooks.applyFilters('siulbricks-autocomplete-items', [], textInput.value);
});

wp.hooks.doAction('siulbricks-autocomplete-items-refresh');

const tribute = new Tribute({
    containerClass: 'siulbricks-tribute-container',

    autocompleteMode: true,

    // Limits the number of items in the menu
    menuItemLimit: 40,

    noMatchTemplate: '',

    values: async function (text, cb) {
        const filters = await wp.hooks.applyFilters('siulbricks-autocomplete-items-query', autocompleteItems, text);
        cb(filters);
    },

    lookup: 'value',

    itemClass: 'class-item',

    // template
    menuItemTemplate: function (item) {
        let customStyle = '';

        if (item.original.color !== undefined) {
            customStyle += `background-color: ${item.original.color};`;
        }

        if (item.original.fontWeight !== undefined) {
            customStyle += `font-weight: ${item.original.fontWeight};`;
        }

        return `
            <span class="class-name" data-tribute-class-name="${item.original.value}">${item.string}</span>
            <span class="class-hint" style="${customStyle}"></span>
        `;
    },
});

tribute.setMenuContainer = function (el) {
    this.menuContainer = el;
};

const tributeEventCallbackOrigFn = tribute.events.callbacks;

tribute.events.callbacks = function () {
    return {
        ...tributeEventCallbackOrigFn.call(this),
        up: (e, el) => {
            // navigate up ul
            if (this.tribute.isActive && this.tribute.current.filteredItems) {
                e.preventDefault();
                e.stopPropagation();
                let count = this.tribute.current.filteredItems.length,
                    selected = this.tribute.menuSelected;

                if (count > selected && selected > 0) {
                    this.tribute.menuSelected--;
                    this.setActiveLi();
                } else if (selected === 0) {
                    this.tribute.menuSelected = count - 1;
                    this.setActiveLi();
                    this.tribute.menu.scrollTop = this.tribute.menu.scrollHeight;
                }
                previewTributeEventCallbackUpDown();
            }
        },
        down: (e, el) => {
            // navigate down ul
            if (this.tribute.isActive && this.tribute.current.filteredItems) {
                e.preventDefault();
                e.stopPropagation();
                let count = this.tribute.current.filteredItems.length - 1,
                    selected = this.tribute.menuSelected;

                if (count > selected) {
                    this.tribute.menuSelected++;
                    this.setActiveLi();
                } else if (count === selected) {
                    this.tribute.menuSelected = 0;
                    this.setActiveLi();
                    this.tribute.menu.scrollTop = 0;
                }
                previewTributeEventCallbackUpDown();
            }
        },
    };
};

tribute.attach(textInput);

const observer = new MutationObserver(function (mutations) {

    mutations.forEach(function (mutation) {
        if (mutation.type === 'attributes') {
            if (mutation.target.id === 'bricks-panel-element' && mutation.attributeName === 'style') {
                if (mutation.target.style.display !== 'none') {
                    visibleElementPanel.value = true;
                } else {
                    visibleElementPanel.value = false;
                }
            } else if ('placeholder' === mutation.attributeName && 'INPUT' === mutation.target.tagName && mutation.target.classList.contains('placeholder')) {
                activeElementId.value = brxGlobalProp.$_activeElement.value.id;
            }
        } else if (mutation.type === 'childList') {
            if (mutation.addedNodes.length > 0) {

                if (mutation.target.id === 'bricks-panel-sticky' && mutation.addedNodes[0].id === 'bricks-panel-element-classes') {
                    activeElementId.value = brxGlobalProp.$_activeElement.value.id;
                } else if (mutation.target.dataset && mutation.target.dataset.controlkey === '_cssClasses' && mutation.addedNodes[0].childNodes.length > 0) {
                    document.querySelector('#_cssClasses').addEventListener('input', function (e) {
                        nextTick(() => {
                            textInput.value = e.target.value;
                            onTextInputChanges();
                        });
                    });
                }
            }
        }
    });
});

observer.observe(document.getElementById('bricks-panel-element'), {
    subtree: true,
    attributes: true,
    childList: true,
});

watch([activeElementId, visibleElementPanel], (newVal, oldVal) => {
    if (newVal[0] !== oldVal[0]) {
        nextTick(() => {
            textInput.value = brxGlobalProp.$_activeElement.value.settings._cssClasses || '';
            onTextInputChanges();
        });
    }

    if (newVal[0] && newVal[1]) {
        nextTick(() => {
            const panelElementClassesEl = document.querySelector('#bricks-panel-element-classes');
            if (panelElementClassesEl.querySelector('.siulbricks-plc-input') === null) {
                panelElementClassesEl.appendChild(containerAction);

                panelElementClassesEl.appendChild(textInput);
                hit = new HighlightInTextarea(textInput, {
                    highlight: [
                        {
                            highlight: /(?<=\s|^)(?:(?!\s).)+(?=\s|$)/g,
                            className: 'word',
                        },
                        {
                            highlight: /(?<=\s)\s/g,
                            className: 'multispace',
                            blank: true,
                        },
                    ],
                });

                autosize.update(textInput);
            }
        });
    }
});


textInput.addEventListener('input', function (e) {
    brxGlobalProp.$_activeElement.value.settings._cssClasses = e.target.value;
});

function onTextInputChanges() {
    nextTick(() => {
        try {
            hit.handleInput();
        } catch (error) { }
        autosize.update(textInput);
        // tribute.setMenuContainer(document.querySelector('div.hit-container'));
        tribute.hideMenu();
    });
};

// Disabled until the feature is stable
textInput.addEventListener('highlights-updated', function (e) {
    colorizeBackground();
    // hoverPreviewProvider();
});

function hoverPreviewProvider() {
    if (brxIframe.contentWindow.siul?.loaded?.module?.classNameToCss !== true) {
        return;
    }

    let someTippyIsVisible = false;

    let registeredTippyElements = [];

    let detectedMarkWordElement = null;

    const hitContainerEl = document.querySelector('.hit-container');

    if (hitContainerEl === null) {
        return;
    }

    // when mouse are entering the `.hit-container` element, get the coordinates of the mouse and check if the mouse is hovering the `mark` element
    hitContainerEl.addEventListener('mousemove', async function (event) {
        const x = event.clientX;
        const y = event.clientY;

        // get all elements that overlap the mouse
        const elements = document.elementsFromPoint(x, y);

        // is found the `mark` element
        const found = elements.some((element) => {
            if (element.matches('mark[class="word"]')) {
                detectedMarkWordElement = element;
                return true;
            }
        });

        if (!found) {
            detectedMarkWordElement = null;
        }


        if (detectedMarkWordElement === null) {
            if (someTippyIsVisible === false) {
                return;
            }
            someTippyIsVisible = false;

            registeredTippyElements.forEach((tippyInstance) => {
                tippyInstance.destroy();
            });

            registeredTippyElements = [];

            return;
        }

        if (someTippyIsVisible === detectedMarkWordElement.textContent) {
            return;
        } else {
            registeredTippyElements.forEach((tippyInstance) => {
                tippyInstance.destroy();
            });

            registeredTippyElements = [];
        }

        const generatedCssCode = brxIframe.contentWindow.siul.module.classNameToCss.generate(detectedMarkWordElement.textContent);
        if (generatedCssCode === null) {
            return null;
        };

        someTippyIsVisible = detectedMarkWordElement.textContent;

        const tippyInstance = tippy(detectedMarkWordElement, {
            plugins: [followCursor],
            allowHTML: true,
            arrow: false,
            duration: [500, null],
            followCursor: true,
            trigger: 'manual',

            content: (reference) => {
                return shikiHighlighter.codeToHtml(generatedCssCode, {
                    lang: 'css',
                    theme: 'dark-plus',
                });
            }
        });

        tippyInstance.show();

        // push the element to the registered tippy elements
        registeredTippyElements.push(tippyInstance);

        detectedMarkWordElement = null;
    });

    // on mouse leave the `.hit-container` element, hide all tippy
    hitContainerEl.addEventListener('mouseleave', async function (event) {
        someTippyIsVisible = false;

        registeredTippyElements.forEach((tippyInstance) => {
            tippyInstance.destroy();
        });

        registeredTippyElements = [];
    });
}

function colorizeBackground() {
    if (twConfig === null) return;

    if (screenBadgeColors.length === 0) return;

    const markElements = document.querySelectorAll('.hit-backdrop>.hit-highlights.hit-content>mark[class="word"]');

    markElements.forEach((markElement) => {
        // get the text content of the `mark` element
        const text = markElement.textContent;

        // loop through all screen badge colors
        screenBadgeColors.forEach((screenBadgeColor) => {
            // if the text content of the `mark` element contains the screen name
            if (text.includes(screenBadgeColor.screen + ':')) {
                const ruleVal = `color-mix(in srgb, ${screenBadgeColor.color} 20%, white 1%)`;
                markElement.style.backgroundColor = ruleVal;
                markElement.style.outlineColor = ruleVal;
            }
        });
    });
}

const observerAutocomplete = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
            mutation.addedNodes.forEach((node) => {
                const className = node.querySelector('.class-name').dataset.tributeClassName;

                node.addEventListener('mouseenter', (e) => {
                    previewAddClass(className);
                });

                node.addEventListener('mouseleave', (e) => {
                    previewResetClass();
                });

                node.addEventListener('click', (e) => {
                    previewResetClass();
                });
            });
        }
    });
});

let menuAutocompleteItemeEl = null;

textInput.addEventListener('tribute-active-true', function (e) {
    if (menuAutocompleteItemeEl === null) {
        menuAutocompleteItemeEl = document.querySelector('.siulbricks-tribute-container>ul');
    }
    nextTick(() => {
        if (menuAutocompleteItemeEl) {
            observerAutocomplete.observe(menuAutocompleteItemeEl, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            });
        }
    });
});

classSortButton.addEventListener('click', function (e) {
    if (brxIframe.contentWindow.siul?.loaded?.module?.classSorter !== true) {
        return;
    }

    textInput.value = brxIframe.contentWindow.siul.module.classSorter.sort(textInput.value);
    brxGlobalProp.$_activeElement.value.settings._cssClasses = textInput.value;
    onTextInputChanges();
});

function previewAddClass(className) {
    const elementNode = brxIframeGlobalProp.$_getElementNode(brxIframeGlobalProp.$_activeElement.value);
    elementNode.classList.add(className);
}

function previewResetClass() {
    const activeEl = brxIframeGlobalProp.$_activeElement.value;
    const elementNode = brxIframeGlobalProp.$_getElementNode(activeEl);
    const elementClasses = brxIframeGlobalProp.$_getElementClasses(activeEl);
    elementNode.classList.value = elementClasses.join(' ');
}

function previewTributeEventCallbackUpDown() {
    let li = tribute.menu.querySelector('li.highlight>span.class-name');
    const activeEl = brxIframeGlobalProp.$_activeElement.value;
    const elementNode = brxIframeGlobalProp.$_getElementNode(activeEl);
    const elementClasses = brxIframeGlobalProp.$_getElementClasses(activeEl);
    elementNode.classList.value = elementClasses.join(' ') + ' ' + li.dataset.tributeClassName;
}

logger('Module loaded!', { module: 'plain-classes' });