
// window.MonacoEnvironment = { getWorker: () => proxy };

// let proxy = URL.createObjectURL(new Blob([/*js*/`
//     // importScripts('https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/vs/base/worker/workerMain.js');

//     import editorWorker from "https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/esm/vs/editor/editor.worker.js?worker"
//     import tsWorker from "https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/esm/vs/language/typescript/ts.worker.js?worker";


//     self.MonacoEnvironment = {
//         // baseUrl: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/',
//         getWorker(moduleId, label) {
//             console.log('getWorker', moduleId, label);
//             // switch(label) {
//             //     case 'tailwindcss':
//             //         return new Worker('https://esm.sh/monaco-tailwindcss/tailwindcss.worker?worker');
//             //     // case 'css':
//             //     // case 'scss':
//             //     //     return new Worker('https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/esm/vs/language/css/css.worker.js');
//             //     // case 'javascript':
//             //     // case 'typescript':
//             //     //     return new Worker('https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/esm/vs/language/typescript/ts.worker.js');
//             //     default:
//             //         return new Worker('https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/vs/base/common/worker/simpleWorker.nls.js');
//             // }

//             if (label === "typescript" || label === "javascript") {
//                 return new tsWorker();
//             }
//             return new editorWorker();
            
//         }
//     };
//     console.log('workerMain.js loaded');
//     console.log('proxy', self.MonacoEnvironment);
// `], { type: 'text/javascript' }));














// import monacoTailwind from 'https://esm.sh/monaco-tailwindcss/tailwindcss.worker?worker';


// window.MonacoEnvironment = {
//     // getWorkerUrl: function (moduleId, label) {
//     //     switch(label) {
//     //         case 'tailwindcss':
//     //             // return 'https://esm.sh/monaco-tailwindcss/tailwindcss.worker?worker';
//     //             // return 'https://esm.sh/monaco-tailwindcss/tailwindcss.worker?bundle';
//     //             return new monacoTailwind();
//     //         case 'css':
//     //         case 'scss':
//     //             return 'https://esm.sh/monaco-editor/esm/vs/language/css/css.worker.js';
//     //         case 'javascript':
//     //         case 'typescript':
//     //             return 'https://esm.sh/monaco-editor/esm/vs/language/typescript/ts.worker.js';
//     //         default:
//     //             return 'https://esm.sh/monaco-editor/esm/vs/base/worker/workerMain.js';
//     //     }
//     // }

//     getWorker(moduleId, label) {
//         console.log('getWorker', moduleId, label);
//         switch(label) {
//             case 'tailwindcss':
//                 return new monacoTailwind();
//             case 'css':
//             case 'scss':
//                 return new Worker('https://esm.sh/monaco-editor/esm/vs/language/css/css.worker.js');
//             case 'javascript':
//             case 'typescript':
//                 return new Worker('https://esm.sh/monaco-editor/esm/vs/language/typescript/ts.worker.js');
//             default:
//                 return new Worker('https://esm.sh/monaco-editor/esm/vs/base/worker/workerMain.js');
//         }
//     }
// };







// https://esm.sh/monaco-tailwindcss@0.6.0/tailwindcss.worker?worker

// // window.MonacoEnvironment = { getWorkerUrl: () => proxy };
// // let proxy = URL.createObjectURL(new Blob([`
// // 	self.MonacoEnvironment = {
// // 		baseUrl: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/'
// // 	};
// // 	importScripts('https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/vs/base/worker/workerMain.js');
// //     console.log('workerMain.js loaded');
// // `], { type: 'text/javascript' }));



// // add monaco-tailwindcss/tailwindcss.worker.js with label 'tailwindcss'
// // window.MonacoEnvironment = {
// //     getWorkerUrl: (moduleId, label) => {
// //         console.log('getWorkerUrl', moduleId, label);
// //         if (label === 'tailwindcss') {
// //             return 'https://esm.sh/monaco-tailwindcss@0.6.0/tailwindcss.worker.js';
// //         }

// //         // return the default worker url
// //     }
// // };



// // Failed to construct 'Worker': Script at 'https://esm.sh/monaco-tailwindcss@0.6.0/tailwindcss.worker.js' cannot be accessed from origin 'http://127.0.0.1'.

// window.MonacoEnvironment = { getWorkerUrl: () => proxy };

// let proxy = URL.createObjectURL(new Blob([/*js*/`
//     self.MonacoEnvironment = {
//         baseUrl: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/'
//     };
//     importScripts('https://cdn.jsdelivr.net/npm/monaco-editor@0.48.0/min/vs/base/worker/workerMain.js');
//     console.log('workerMain.js loaded');
// `], { type: 'text/javascript' }));