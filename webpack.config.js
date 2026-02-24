var path = require('path');
var webpack = require('webpack');

module.exports = {
    entry: './resources/assets/js/app.js',
    output: {
        path: path.resolve(__dirname, 'public/js'),
        filename: 'app.js'
    },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.common.js'
        },
        extensions: ['.js', '.vue']
    },
    plugins: [
        new webpack.LoaderOptionsPlugin({
            options: {
                vue: {
                    buble: {
                        objectAssign: 'Object.assign'
                    }
                }
            }
        })
    ],
    module: {
        noParse: [/uikit\/dist\/js\//],
        loaders: [
            {
                test: /\.vue$/,
                loader: 'vue-loader'
            },
            {
                test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
                loader: 'file-loader',
                query: {
                    limit: 10000,
                    name: '../img/[name].[hash:7].[ext]'
                }
            },
            {
                test: /\.(woff2?|eot|ttf|otf)(\?.*)?$/,
                loader: 'url-loader',
                query: {
                    limit: 10000,
                    name: '../fonts/[name].[hash:7].[ext]'
                }
            }
        ]
    }
};
