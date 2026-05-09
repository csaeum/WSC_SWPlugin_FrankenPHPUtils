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
            lastStatus: null,
            statusPollInterval: null,
        };
    },

    created() {
        this.loadStatus();
        this.statusPollInterval = window.setInterval(() => this.loadStatus(), 10000);
    },

    beforeDestroy() {
        if (this.statusPollInterval) {
            window.clearInterval(this.statusPollInterval);
        }
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
                this.loadStatus();
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
                this.loadStatus();
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
                this.loadStatus();
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
                this.loadStatus();
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
                this.loadStatus();
            }
        },

        async loadStatus() {
            try {
                const token = Shopware.Service('loginService').getToken();
                const response = await Shopware.Application.getContainer('init').httpClient.get(
                    '/api/wsc-frankenphp/status',
                    { headers: { Authorization: `Bearer ${token}` } }
                );
                this.lastStatus = response.data.status;
            } catch (e) {
                this.lastStatus = null;
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
            const message = this.buildResultMessage(data);

            if (data.success) {
                this.createNotificationSuccess({ message });
                this.lastResult = { success: true, message, results: data.results ?? null };
            } else {
                this.createNotificationError({ message });
                this.lastResult = { success: false, message, results: data.results ?? null };
            }
        },

        buildResultMessage(data) {
            const message = data.messageKey ? this.$tc(data.messageKey) : data.message;
            const details = data.results ? this.formatResults(data.results) : '';

            return details ? `${message} (${details})` : message;
        },

        formatResults(results) {
            return Object.keys(results).map((key) => {
                return `${this.getResultLabel(key)}: ${results[key] ? 'OK' : this.$tc('wsc-frankenphp-utils.results.failed')}`;
            }).join(', ');
        },

        getResultLabel(key) {
            const labels = {
                cacheClear: this.$tc('wsc-frankenphp-utils.results.cache'),
                themeCompile: this.$tc('wsc-frankenphp-utils.results.theme'),
                restart: this.$tc('wsc-frankenphp-utils.results.restart'),
                'cache:clear': this.$tc('wsc-frankenphp-utils.results.cache'),
                'theme:compile': this.$tc('wsc-frankenphp-utils.results.theme'),
            };

            return labels[key] ?? key;
        },

        getStatusActionLabel(action) {
            const labels = {
                restart: this.$tc('wsc-frankenphp-utils.status.actions.restart'),
                cacheClearRestart: this.$tc('wsc-frankenphp-utils.status.actions.cacheClearRestart'),
                fullDeploy: this.$tc('wsc-frankenphp-utils.status.actions.fullDeploy'),
                'cache:clear': this.$tc('wsc-frankenphp-utils.status.actions.cacheClear'),
                'theme:compile': this.$tc('wsc-frankenphp-utils.status.actions.themeCompile'),
            };

            return labels[action] ?? action;
        },

        formatStatusTime(value) {
            if (!value) {
                return '-';
            }

            return new Date(value).toLocaleString();
        },

        showError(message) {
            this.createNotificationError({ message });
            this.lastResult = { success: false, message };
        },
    },
});
