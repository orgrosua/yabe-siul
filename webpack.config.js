const Encore = require('@symfony/webpack-encore');
const MonacoWebpackPlugin = require('monaco-editor-webpack-plugin');
const path = require('path');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('build/')
    // public path used by the web server to access the output path
    //.setPublicPath('/build')
    .setPublicPath('')

    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    // .addEntry('app', './assets/app.js')

    // admin entry
    .addEntry('admin', './assets/admin/app.js')

    // builder entry
    // .addEntry('builder', './assets/builder/app.js')

    // builder entry
    // .addEntry('builder-main', './assets/builder-main/app.js')

    // modules entry
    .addEntry('module-autocomplete', './assets/module/autocomplete/app.js')

    .copyFiles({
        from: './assets/frontend',
        to: 'frontend/[path][name].[ext]',
    })

    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    // .enableStimulusBridge('./assets/controllers.json')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    // .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // configure Babel
    // .configureBabel((config) => {
    //     config.plugins.push('@babel/a-babel-plugin');
    // })

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

    // enables Sass/SCSS support
    .enableSassLoader()
    
    // uncomment if you use TypeScript
    // .enableTypeScriptLoader((tsConfig) => {
    //     tsConfig.allowTsInNodeModules = true;
    // })


    // uncomment if you use React
    //.enableReactPreset()

    // uncomment if you use Vue 
    .enableVueLoader((options) => {
        options.defineModel = true;
    }, { version: 3, runtimeCompilerBuild: false })

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    //.autoProvidejQuery()

    // add monaco editor
    .addPlugin(new MonacoWebpackPlugin({
        globalAPI: true,
        publicPath: false,
        // languages: ['css', 'scss', 'less', 'javascript', 'typescript'],
        customLanguages: [{
            label: 'tailwindcss',
            // entry: path.resolve(__dirname, './assets/admin/tailwindcss.contribution.js'),
            entry: undefined,
            worker: {
                id: 'monaco-tailwindcss/tailwindcss.worker',
                entry: path.resolve(__dirname, 'node_modules/monaco-tailwindcss/tailwindcss.worker.js'),
            },
        }]
    }))

    .configureDevServerOptions(options => {
        // options.server = {
        //     type: 'https',
        //     options: {
        //         key: '/path/to/server.key',
        //         cert: '/path/to/server.crt',
        //     }
        // }

        options.devMiddleware = {
            index: false, // specify to enable root proxying,
            writeToDisk: true,
        };
        options.proxy = {
            context: () => true,
            target: 'http://127.0.0.1:80',
        };
    })

    // Allow HTML files to be imported. https://webpack.js.org/loaders/html-loader/
    .addRule({
        test: /\.html$/i,
        loader: 'html-loader',

        options: {
            minimize: {
                // don't remove comments
                removeComments: false,
            },
        },
    })

    // import a file as a string. Previously achievable by using raw-loader.
    .addRule({
        resourceQuery: /source/,
        type: 'asset/source',
    })

    // emit a file into the output directory. Previously achievable by using file-loader.
    .addRule({
        resourceQuery: /resource/,
        type: 'asset/resource',
    })

    // exports a data URI of the asset
    .addRule({
        resourceQuery: /inline/,
        type: 'asset/inline',
    })

    // handle rive.app files
    .addRule({
        test: /\.riv$/i,
        type: 'asset/resource',
        generator: {
            filename: 'rive/[name].[hash:8][ext]',
        },
        parser: {
            dataUrlCondition: {
                maxSize: null,
            },
        },
    })
    ;


const webpackConfig = Encore.getWebpackConfig();

// webpackConfig.resolve.fallback = {
//     ...webpackConfig.resolve.fallback,
//     'buffer': require.resolve('buffer/'),
// };

// webpackConfig.module.parser = {
//     ...webpackConfig.module.parser,
//     javascript: {
//         url: 'relative',
//     },
// };


module.exports = webpackConfig;