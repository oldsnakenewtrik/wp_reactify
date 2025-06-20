module.exports = {
    root: true,
    env: {
        browser: true,
        es2021: true,
        node: true,
        jquery: true,
        jest: true
    },
    extends: [
        '@wordpress/eslint-plugin/recommended',
        'eslint:recommended',
        'plugin:prettier/recommended'
    ],
    plugins: [
        'prettier',
        'jest'
    ],
    parserOptions: {
        ecmaVersion: 2021,
        sourceType: 'module'
    },
    globals: {
        // WordPress globals
        wp: 'readonly',
        jQuery: 'readonly',
        $: 'readonly',
        ajaxurl: 'readonly',
        
        // ReactifyWP specific globals
        reactifyWP: 'readonly',
        reactifyWPAdmin: 'readonly',
        
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        
        // Testing globals
        describe: 'readonly',
        it: 'readonly',
        test: 'readonly',
        expect: 'readonly',
        beforeEach: 'readonly',
        afterEach: 'readonly',
        beforeAll: 'readonly',
        afterAll: 'readonly',
        jest: 'readonly'
    },
    rules: {
        // Prettier integration
        'prettier/prettier': 'error',
        
        // Code quality
        'no-console': 'warn',
        'no-debugger': 'error',
        'no-unused-vars': ['error', { 
            argsIgnorePattern: '^_',
            varsIgnorePattern: '^_'
        }],
        'no-undef': 'error',
        'no-redeclare': 'error',
        'no-shadow': 'error',
        
        // Best practices
        'eqeqeq': ['error', 'always'],
        'curly': ['error', 'all'],
        'dot-notation': 'error',
        'no-eval': 'error',
        'no-implied-eval': 'error',
        'no-new-func': 'error',
        'no-script-url': 'error',
        'no-sequences': 'error',
        'no-throw-literal': 'error',
        'no-useless-concat': 'error',
        'no-void': 'error',
        'radix': 'error',
        'wrap-iife': ['error', 'inside'],
        'yoda': ['error', 'never'],
        
        // Variables
        'no-delete-var': 'error',
        'no-label-var': 'error',
        'no-restricted-globals': ['error', 'event'],
        'no-undef-init': 'error',
        'no-undefined': 'error',
        
        // Stylistic issues
        'array-bracket-spacing': ['error', 'never'],
        'block-spacing': ['error', 'always'],
        'brace-style': ['error', '1tbs', { allowSingleLine: true }],
        'camelcase': ['error', { properties: 'never' }],
        'comma-dangle': ['error', 'never'],
        'comma-spacing': ['error', { before: false, after: true }],
        'comma-style': ['error', 'last'],
        'computed-property-spacing': ['error', 'never'],
        'eol-last': ['error', 'always'],
        'func-call-spacing': ['error', 'never'],
        'indent': ['error', 4, { SwitchCase: 1 }],
        'key-spacing': ['error', { beforeColon: false, afterColon: true }],
        'keyword-spacing': ['error', { before: true, after: true }],
        'linebreak-style': ['error', 'unix'],
        'max-len': ['error', { 
            code: 120, 
            tabWidth: 4,
            ignoreUrls: true,
            ignoreStrings: true,
            ignoreTemplateLiterals: true
        }],
        'new-cap': ['error', { newIsCap: true, capIsNew: false }],
        'new-parens': 'error',
        'no-array-constructor': 'error',
        'no-mixed-spaces-and-tabs': 'error',
        'no-multiple-empty-lines': ['error', { max: 2, maxEOF: 1 }],
        'no-new-object': 'error',
        'no-tabs': 'error',
        'no-trailing-spaces': 'error',
        'no-whitespace-before-property': 'error',
        'object-curly-spacing': ['error', 'always'],
        'one-var': ['error', 'never'],
        'operator-linebreak': ['error', 'after'],
        'padded-blocks': ['error', 'never'],
        'quote-props': ['error', 'as-needed'],
        'quotes': ['error', 'single', { avoidEscape: true }],
        'semi': ['error', 'always'],
        'semi-spacing': ['error', { before: false, after: true }],
        'space-before-blocks': ['error', 'always'],
        'space-before-function-paren': ['error', {
            anonymous: 'always',
            named: 'never',
            asyncArrow: 'always'
        }],
        'space-in-parens': ['error', 'never'],
        'space-infix-ops': 'error',
        'space-unary-ops': ['error', { words: true, nonwords: false }],
        'spaced-comment': ['error', 'always'],
        
        // ES6
        'arrow-spacing': ['error', { before: true, after: true }],
        'constructor-super': 'error',
        'no-class-assign': 'error',
        'no-const-assign': 'error',
        'no-dupe-class-members': 'error',
        'no-duplicate-imports': 'error',
        'no-new-symbol': 'error',
        'no-this-before-super': 'error',
        'no-useless-computed-key': 'error',
        'no-useless-constructor': 'error',
        'no-useless-rename': 'error',
        'no-var': 'error',
        'object-shorthand': ['error', 'always'],
        'prefer-arrow-callback': 'error',
        'prefer-const': 'error',
        'prefer-rest-params': 'error',
        'prefer-spread': 'error',
        'prefer-template': 'error',
        'rest-spread-spacing': ['error', 'never'],
        'template-curly-spacing': ['error', 'never'],
        'yield-star-spacing': ['error', 'after']
    },
    overrides: [
        {
            files: ['tests/**/*.js'],
            env: {
                jest: true
            },
            rules: {
                'no-console': 'off'
            }
        },
        {
            files: ['webpack.config.js', '.eslintrc.js'],
            env: {
                node: true
            },
            rules: {
                'no-console': 'off'
            }
        }
    ]
};
