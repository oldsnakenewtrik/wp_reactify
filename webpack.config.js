const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';
    
    return {
        entry: {
            admin: './assets/js/admin.js',
            gutenberg: './assets/js/gutenberg.js',
            frontend: './assets/js/frontend.js'
        },
        
        output: {
            path: path.resolve(__dirname, 'assets/dist'),
            filename: isProduction ? '[name].[contenthash].js' : '[name].js',
            clean: true
        },
        
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env']
                        }
                    }
                },
                {
                    test: /\.css$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        'css-loader'
                    ]
                }
            ]
        },
        
        plugins: [
            new MiniCssExtractPlugin({
                filename: isProduction ? '[name].[contenthash].css' : '[name].css'
            })
        ],
        
        optimization: {
            splitChunks: {
                chunks: 'all',
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendors',
                        chunks: 'all'
                    }
                }
            }
        },
        
        devtool: isProduction ? 'source-map' : 'eval-source-map',
        
        externals: {
            jquery: 'jQuery',
            wp: 'wp'
        },
        
        resolve: {
            extensions: ['.js', '.json'],
            alias: {
                '@': path.resolve(__dirname, 'assets/js')
            }
        }
    };
};
