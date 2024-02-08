module.exports = {
    'ignorePatterns': ['webpack.config.js', 'node_modules/', 'src/', 'assets/module/autocomplete/tailwindcss-autocomplete/'],
    'env': {
        'browser': true,
        'es2021': true,
        'commonjs': true
    },
    'extends': [
        'eslint:recommended',
        'plugin:vue/vue3-essential',
        '@master/css'
    ],
    'overrides': [
        {
            'env': {
                'node': true
            },
            'files': [
                '.eslintrc.{js,cjs}'
            ],
            'parserOptions': {
                'sourceType': 'script'
            }
        }
    ],
    'parserOptions': {
        'ecmaVersion': 'latest',
        'sourceType': 'module'
    },
    'plugins': [
        'vue'
    ],
    'rules': {
        'indent': [
            'error',
            4
        ],
        'quotes': [
            'error',
            'single'
        ],
        'semi': [
            'error',
            'always'
        ],
        '@master/css/class-validation': 'off'
    }
};
