import './page/wsc-frankenphp-utils-index';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('wsc-frankenphp-utils', {
    type: 'plugin',
    name: 'wsc-frankenphp-utils',
    title: 'wsc-frankenphp-utils.title',
    description: 'wsc-frankenphp-utils.description',
    color: '#F19D35',
    icon: 'regular-tools',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'wsc-frankenphp-utils-index',
            path: 'index',
        },
    },

    navigation: [{
        label: 'wsc-frankenphp-utils.title',
        color: '#F19D35',
        path: 'wsc.frankenphp.utils.index',
        icon: 'regular-tools',
        parent: 'sw-settings',
        position: 100,
    }],
});
