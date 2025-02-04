const Encore = require('@symfony/webpack-encore');
const path = require('path');
const getEzConfig = require('./ez.webpack.config.js');
const getIbexaConfig = require('./ibexa.webpack.config.js');
const eZConfig = getEzConfig(Encore);
const ibexaConfig = getIbexaConfig(Encore);
const customConfigs = require('./ibexa.webpack.custom.configs.js');

Encore.reset();
Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .enableStimulusBridge('./assets/controllers.json')
    .enableSassLoader()
    .enableReactPreset()
    .enableSingleRuntimeChunk()
    .copyFiles({
        from: './assets/images',
        to: 'images/[path][name].[ext]',
        pattern: /\.(png|svg)$/
    })
    .configureBabel((config) => {
        config.plugins.push('@babel/plugin-proposal-class-properties');
    })

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
;

// Welcome page stylesheets
Encore.addEntry('welcome_page', [
    path.resolve(__dirname, './assets/scss/welcome-page.scss'),
]);

Encore.addEntry('app', './assets/app.js');

// Image Editor Dot Action 
ibexaConfigManager.add({ 
    ibexaConfig, 
    entryName: 'ibexa-admin-ui-layout-js', 
    newItems: [ path.resolve(__dirname, './assets/random_dot/random-dot.js'), ], 
});

const projectConfig = Encore.getWebpackConfig();
module.exports = [ eZConfig, ibexaConfig, ...customConfigs, projectConfig ];

// uncomment this line if you've commented-out the above lines
// module.exports = [ eZConfig, ...customConfigs ];
