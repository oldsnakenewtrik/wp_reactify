module.exports = {
    // Use WordPress prettier config as base
    ...require('@wordpress/prettier-config'),
    
    // Override specific settings for this project
    printWidth: 120,
    tabWidth: 4,
    useTabs: false,
    semi: true,
    singleQuote: true,
    quoteProps: 'as-needed',
    trailingComma: 'none',
    bracketSpacing: true,
    bracketSameLine: false,
    arrowParens: 'avoid',
    endOfLine: 'lf',
    
    // File-specific overrides
    overrides: [
        {
            files: '*.json',
            options: {
                tabWidth: 2
            }
        },
        {
            files: '*.md',
            options: {
                printWidth: 80,
                proseWrap: 'always'
            }
        },
        {
            files: '*.php',
            options: {
                printWidth: 120,
                tabWidth: 4,
                useTabs: false
            }
        },
        {
            files: '*.css',
            options: {
                singleQuote: false
            }
        }
    ]
};
