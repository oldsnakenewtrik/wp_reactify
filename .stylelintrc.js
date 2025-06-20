module.exports = {
    extends: [
        'stylelint-config-standard',
        'stylelint-config-wordpress'
    ],
    rules: {
        // Indentation
        'indentation': 4,
        
        // Line length
        'max-line-length': 120,
        
        // Colors
        'color-hex-case': 'lower',
        'color-hex-length': 'short',
        'color-named': 'never',
        'color-no-invalid-hex': true,
        
        // Fonts
        'font-family-name-quotes': 'always-where-recommended',
        'font-family-no-duplicate-names': true,
        'font-family-no-missing-generic-family-keyword': true,
        
        // Functions
        'function-calc-no-invalid': true,
        'function-calc-no-unspaced-operator': true,
        'function-comma-newline-after': 'always-multi-line',
        'function-comma-space-after': 'always-single-line',
        'function-comma-space-before': 'never',
        'function-linear-gradient-no-nonstandard-direction': true,
        'function-max-empty-lines': 0,
        'function-name-case': 'lower',
        'function-parentheses-newline-inside': 'always-multi-line',
        'function-parentheses-space-inside': 'never-single-line',
        'function-url-quotes': 'always',
        'function-whitespace-after': 'always',
        
        // Numbers
        'number-leading-zero': 'always',
        'number-max-precision': 3,
        'number-no-trailing-zeros': true,
        
        // Strings
        'string-no-newline': true,
        'string-quotes': 'double',
        
        // Units
        'length-zero-no-unit': true,
        'unit-case': 'lower',
        'unit-no-unknown': true,
        
        // Values
        'value-keyword-case': 'lower',
        'value-list-comma-newline-after': 'always-multi-line',
        'value-list-comma-space-after': 'always-single-line',
        'value-list-comma-space-before': 'never',
        'value-list-max-empty-lines': 0,
        
        // Properties
        'property-case': 'lower',
        'property-no-unknown': true,
        'property-no-vendor-prefix': true,
        
        // Declarations
        'declaration-bang-space-after': 'never',
        'declaration-bang-space-before': 'always',
        'declaration-colon-newline-after': 'always-multi-line',
        'declaration-colon-space-after': 'always-single-line',
        'declaration-colon-space-before': 'never',
        'declaration-empty-line-before': 'never',
        'declaration-no-important': true,
        
        // Declaration blocks
        'declaration-block-no-duplicate-properties': true,
        'declaration-block-no-shorthand-property-overrides': true,
        'declaration-block-semicolon-newline-after': 'always-multi-line',
        'declaration-block-semicolon-space-after': 'always-single-line',
        'declaration-block-semicolon-space-before': 'never',
        'declaration-block-single-line-max-declarations': 1,
        'declaration-block-trailing-semicolon': 'always',
        
        // Blocks
        'block-closing-brace-empty-line-before': 'never',
        'block-closing-brace-newline-after': 'always',
        'block-closing-brace-newline-before': 'always-multi-line',
        'block-closing-brace-space-before': 'always-single-line',
        'block-no-empty': true,
        'block-opening-brace-newline-after': 'always-multi-line',
        'block-opening-brace-space-after': 'always-single-line',
        'block-opening-brace-space-before': 'always',
        
        // Selectors
        'selector-attribute-brackets-space-inside': 'never',
        'selector-attribute-operator-space-after': 'never',
        'selector-attribute-operator-space-before': 'never',
        'selector-attribute-quotes': 'always',
        'selector-combinator-space-after': 'always',
        'selector-combinator-space-before': 'always',
        'selector-descendant-combinator-no-non-space': true,
        'selector-max-compound-selectors': 4,
        'selector-max-id': 0,
        'selector-no-qualifying-type': [true, {
            ignore: ['attribute', 'class']
        }],
        'selector-no-vendor-prefix': true,
        'selector-pseudo-class-case': 'lower',
        'selector-pseudo-class-no-unknown': true,
        'selector-pseudo-class-parentheses-space-inside': 'never',
        'selector-pseudo-element-case': 'lower',
        'selector-pseudo-element-colon-notation': 'double',
        'selector-pseudo-element-no-unknown': true,
        'selector-type-case': 'lower',
        'selector-type-no-unknown': true,
        
        // Selector lists
        'selector-list-comma-newline-after': 'always',
        'selector-list-comma-space-before': 'never',
        
        // Rules
        'rule-empty-line-before': ['always-multi-line', {
            except: ['first-nested'],
            ignore: ['after-comment']
        }],
        
        // Media
        'media-feature-colon-space-after': 'always',
        'media-feature-colon-space-before': 'never',
        'media-feature-name-case': 'lower',
        'media-feature-name-no-unknown': true,
        'media-feature-name-no-vendor-prefix': true,
        'media-feature-parentheses-space-inside': 'never',
        'media-feature-range-operator-space-after': 'always',
        'media-feature-range-operator-space-before': 'always',
        'media-query-list-comma-newline-after': 'always-multi-line',
        'media-query-list-comma-space-after': 'always-single-line',
        'media-query-list-comma-space-before': 'never',
        
        // At-rules
        'at-rule-empty-line-before': ['always', {
            except: ['blockless-after-same-name-blockless', 'first-nested'],
            ignore: ['after-comment']
        }],
        'at-rule-name-case': 'lower',
        'at-rule-name-space-after': 'always-single-line',
        'at-rule-no-unknown': true,
        'at-rule-no-vendor-prefix': true,
        'at-rule-semicolon-newline-after': 'always',
        
        // Comments
        'comment-empty-line-before': ['always', {
            except: ['first-nested'],
            ignore: ['stylelint-commands']
        }],
        'comment-no-empty': true,
        'comment-whitespace-inside': 'always',
        
        // General
        'max-empty-lines': 2,
        'max-nesting-depth': 3,
        'no-descending-specificity': null,
        'no-duplicate-at-import-rules': true,
        'no-duplicate-selectors': true,
        'no-empty-source': true,
        'no-eol-whitespace': true,
        'no-extra-semicolons': true,
        'no-invalid-double-slash-comments': true,
        'no-missing-end-of-source-newline': true,
        'no-unknown-animations': true
    },
    ignoreFiles: [
        'vendor/**/*.css',
        'node_modules/**/*.css',
        'coverage/**/*.css'
    ]
};
