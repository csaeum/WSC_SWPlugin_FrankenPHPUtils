<?php declare(strict_types=1);

namespace WSC\WSC_SWPlugin_FrankenPHPUtils\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FrankenPHPService
{
    private const CONFIG_PREFIX = 'WSC_SWPlugin_FrankenPHPUtils.config.';
    private const LOG_FILE = 'wsc_swplugin_frankenphputils.log';

    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Startet alle FrankenPHP Worker graceful neu über die Caddy Admin API.
     */
    public function restartWorkers(string $triggeredBy = 'manual'): bool
    {
        $url = rtrim((string) $this->getConfig('adminApiUrl', 'http://localhost:2019'), '/');
        $timeout = (int) $this->getConfig('timeout', 5);

        try {
            $response = $this->httpClient->request('POST', $url . '/frankenphp/workers/restart', [
                'timeout' => $timeout,
            ]);

            $success = $response->getStatusCode() === 200;

            $this->log(
                $success ? 'info' : 'error',
                $success
                    ? 'FrankenPHP Workers erfolgreich neu gestartet'
                    : 'FrankenPHP Worker-Neustart fehlgeschlagen (HTTP ' . $response->getStatusCode() . ')',
                ['triggered_by' => $triggeredBy, 'url' => $url]
            );

            return $success;
        } catch (\Throwable $e) {
            $this->log('error', 'FrankenPHP Worker-Neustart Exception: ' . $e->getMessage(), [
                'triggered_by' => $triggeredBy,
                'url' => $url,
            ]);

            return false;
        }
    }

    /**
     * Leert den Shopware-Cache über bin/console cache:clear.
     */
    public function clearCache(string $triggeredBy = 'manual'): bool
    {
        return $this->runConsoleCommand(['cache:clear'], $triggeredBy);
    }

    /**
     * Kompiliert alle Themes über bin/console theme:compile.
     */
    public function compileTheme(string $triggeredBy = 'manual'): bool
    {
        return $this->runConsoleCommand(['theme:compile'], $triggeredBy);
    }

    /**
     * Full-Deploy: Cache leeren → Theme kompilieren → Worker neu starten.
     * Gibt Array mit Einzelergebnissen zurück.
     *
     * @return array{cacheClear: bool, themeCompile: bool, restart: bool}
     */
    public function runFullDeploy(string $triggeredBy = 'manual'): array
    {
        $results = [
            'cacheClear'   => false,
            'themeCompile' => false,
            'restart'      => false,
        ];

        $results['cacheClear'] = $this->clearCache($triggeredBy);
        $results['themeCompile'] = $this->compileTheme($triggeredBy);
        $results['restart'] = $this->restartWorkers($triggeredBy);

        $this->log('info', 'Full-Deploy abgeschlossen', array_merge($results, ['triggered_by' => $triggeredBy]));

        return $results;
    }

    /**
     * Cache leeren und anschliessend Worker neu starten.
     *
     * @return array{cacheClear: bool, restart: bool}
     */
    public function runCacheClearAndRestart(string $triggeredBy = 'manual'): array
    {
        $results = [
            'cacheClear' => false,
            'restart'    => false,
        ];

        $results['cacheClear'] = $this->clearCache($triggeredBy);
        $results['restart'] = $this->restartWorkers($triggeredBy);

        $this->log('info', 'Cache-Clear und Worker-Restart abgeschlossen', array_merge($results, ['triggered_by' => $triggeredBy]));

        return $results;
    }

    // -------------------------------------------------------------------------
    // Konfigurations-Getter für den Subscriber
    // -------------------------------------------------------------------------

    public function isAutoRestartEnabled(): bool
    {
        return (bool) $this->getConfig('autoRestartOnPluginEvent', true);
    }

    public function isAutoCacheClearEnabled(): bool
    {
        return (bool) $this->getConfig('autoCacheClearOnPluginEvent', true);
    }

    public function isAutoThemeCompileEnabled(): bool
    {
        return (bool) $this->getConfig('autoThemeCompileOnPluginEvent', false);
    }

    // -------------------------------------------------------------------------
    // Interne Hilfsmethoden
    // -------------------------------------------------------------------------

    private function runConsoleCommand(array $command, string $triggeredBy): bool
    {
        $consolePath = $this->projectDir . '/bin/console';
        $process = new Process(array_merge(['php', $consolePath], $command));
        $process->setTimeout(300);

        try {
            $process->run();
            $success = $process->isSuccessful();

            $this->log(
                $success ? 'info' : 'error',
                $success
                    ? 'Konsolenbefehl erfolgreich: ' . implode(' ', $command)
                    : 'Konsolenbefehl fehlgeschlagen: ' . implode(' ', $command),
                [
                    'triggered_by' => $triggeredBy,
                    'output'       => $success ? $process->getOutput() : $process->getErrorOutput(),
                ]
            );

            return $success;
        } catch (\Throwable $e) {
            $this->log('error', 'Konsolenbefehl Exception: ' . implode(' ', $command) . ' – ' . $e->getMessage(), [
                'triggered_by' => $triggeredBy,
            ]);

            return false;
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!(bool) $this->getConfig('enableLogging', true)) {
            return;
        }

        $this->logger->{$level}('[WSC_FrankenPHPUtils] ' . $message, $context);
        $this->writePluginLog($level, $message, $context);
    }

    private function writePluginLog(string $level, string $message, array $context = []): void
    {
        $logDir = $this->projectDir . '/var/log';
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }

        $contextJson = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf(
            "[%s] %s: %s%s%s",
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $contextJson,
            PHP_EOL
        );

        @file_put_contents($logDir . '/' . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }

    private function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->systemConfig->get(self::CONFIG_PREFIX . $key) ?? $default;
    }
}
