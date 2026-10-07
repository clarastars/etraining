const mix = require('laravel-mix');
const webpack = require('webpack');
const path = require('path');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */
mix.disableNotifications();

mix.options({
    hmrOptions: {
        host: 'etraining.test',
        port: 8000
    }
});

mix.copy('node_modules/@zoomus/websdk/dist', 'public/node_modules/@zoomus/websdk/dist')


mix.js('resources/js/app.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css', [
        require('postcss-import'),
        require('tailwindcss'),
    ])
    .webpackConfig({
        // output: { chunkFilename: 'js/[name].js?id=[chunkhash]' },
        resolve: {
            alias: {
                vue$: 'vue/dist/vue.runtime.esm.js',
                '@': path.resolve('resources/js'),
            },
        },
        plugins: [
            new webpack.DefinePlugin({
                'process.env': {
                    BUILD_VERSION: JSON.stringify(process.env.BUILD_VERSION || (new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 12)))
                }
            }),
            new webpack.BannerPlugin({
                banner: 'etraining-runtime',
                test: /manifest\.js$/,
            }),
        ]
    });

mix.extract()

if (mix.inProduction()) {
    mix.version();
}

