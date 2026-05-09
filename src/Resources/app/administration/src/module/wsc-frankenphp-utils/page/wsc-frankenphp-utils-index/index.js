import template from './wsc-frankenphp-utils-index.html.twig';

const { Component, Mixin } = Shopware;

Component.register('wsc-frankenphp-utils-index', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoadingRestart: false,
            isLoadingCache: false,
            isLoadingCacheRestart: false,
            isLoadingTheme: false,
            isLoadingFullDeploy: false,
            lastResult: null,
        };
    },

    methods: {
        async onRestartWorkers() {
            this.isLoadingRestart = true;
            this.lastResult = null;
            try {
                const data = await this.callApi('/api/wsc-frankenphp/restart');
                this.handleResult(data);
            } catch (e) {
                this.showError(this.$tc('wsc-frankenphp-utils.notifications.restartError'));
            } finally {
                this.isLoadingRestart = false;
            }
        },

        async onClearCache() {
            this.isLoadingCache = true;
            this.lastResult = null;
            try {
                const data = await this.callApi('/api/wsc-frankenphp/cache-clear');
                this.handleResult(data);
            } catch (e) {
                this.showError(this.$tc('wsc-frankenphp-utils.notifications.cacheError'));
            } finally {
                this.isLoadingCache = false;
            }
        },

        async onClearCacheAndRestart() {
            this.isLoadingCacheRestart = true;
            this.lastResult = null;
            try {
                const data = await this.callApi('/api/wsc-frankenphp/cache-clear-restart');
                this.handleResult(data);
            } catch (e) {
                this.showError(this.$tc('wsc-frankenphp-utils.notifications.cacheRestartError'));
            } finally {
                this.isLoadingCacheRestart = false;
            }
        },

        async onCompileTheme() {
            this.isLoadingTheme = true;
            this.lastResult = null;
            try {
                const data = await this.callApi('/api/wsc-frankenphp/theme-compile');
                this.handleResult(data);
            } catch (e) {
                this.showError(this.$tc('wsc-frankenphp-utils.notifications.themeError'));
            } finally {
                this.isLoadingTheme = false;
            }
        },

        async onFullDeploy() {
            this.isLoadingFullDeploy = true;
            this.lastResult = null;
            try {
                const data = await this.callApi('/api/wsc-frankenphp/full-deploy');
                this.handleResult(data);
            } catch (e) {
                this.showError(this.$tc('wsc-frankenphp-utils.notifications.fullDeployError'));
            } finally {
                this.isLoadingFullDeploy = false;
            }
        },

        async callApi(endpoint) {
            const token = Shopware.Service('loginService').getToken();
            const response = await Shopware.Application.getContainer('init').httpClient.post(
                endpoint,
                {},
                { headers: { Authorization: `Bearer ${token}` } }
            );
            return response.data;
        },

        handleResult(data) {
            const message = data.messageKey ? this.$tc(data.messageKey) : data.message;

            if (data.success) {
                this.createNotificationSuccess({ message });
                this.lastResult = { success: true, message, results: data.results ?? null };
            } else {
                this.createNotificationError({ message });
                this.lastResult = { success: false, message, results: data.results ?? null };
            }
        },

        showError(message) {
            this.createNotificationError({ message });
            this.lastResult = { success: false, message };
        },
    },
});
