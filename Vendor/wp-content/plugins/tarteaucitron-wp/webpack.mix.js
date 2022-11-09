let mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for your application, as well as bundling up your JS files.
 |
 */

mix.js('assets/admin.js', 'dist/')
    .copy('node_modules/tarteaucitronjs/', 'dist/tarteaucitronjs/')
    .sourceMaps(true, 'source-map')
    .version()
    .setPublicPath('dist');
