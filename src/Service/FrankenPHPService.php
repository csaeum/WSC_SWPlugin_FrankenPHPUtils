<?php

declare(strict_types=1);

namespace WSC\WSC_SWPlugin_FrankenPHPUtils\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class FrankenPHPService
{
    private const CONFIG_PREFIX = 'WSC_SWPlugin_FrankenPHPUtils.config.';
    private const LOG_FILE = 'wsc_swplugin_frankenphputils.log';
    private const STATUS_FILE = 'wsc_swplugin_frankenphputils_status.json';

    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Vollständiger Cache-Reset in drei Schritten:
     * 1. var/cache löschen (inkl. kompilierten Container-Ordner prod_<hash>)
     * 2. cache:warmup – kompilierten Container neu erzeugen
     * 3. cache:pool:clear --all – alle konfigurierten Cache-Pools leeren (inkl. Redis TagAware)
     * 4. OPcache und Realpath-Cache leeren
     *
     * Zuverlässiger als cache:clear, das in FrankenPHP-Mode den prod_<hash>-Ordner
     * nicht immer vollständig entfernt und Redis-Pools nicht berücksichtigt.
     */
    public function clearCache(string $triggeredBy = 'manual'): bool
    {
        $cacheDir = $this->projectDir . '/var/cache';

        try {
            $filesystem = new Filesystem();
            if ($filesystem->exists($cacheDir)) {
                $filesystem->remove($cacheDir);
                $this->log('info', 'var/cache gelöscht', ['triggered_by' => $triggeredBy]);
            }
        } catch (\Throwable $e) {
            $this->log('error', 'var/cache konnte nicht gelöscht werden: ' . $e->getMessage(), ['triggered_by' => $triggeredBy]);
        }

        // Container neu erzeugen bevor cache:pool:clear läuft (braucht den Container für Pool-Konfiguration)
        $success = $this->runConsoleCommand(['cache:warmup'], $triggeredBy);

        // Alle Symfony Cache-Pools leeren – inkl. Redis TagAware
        $this->runConsoleCommand(['cache:pool:clear', '--all'], $triggeredBy);

        $this->resetPhpCache($triggeredBy);

        return $success;
    }

    /**
     * Kompiliert alle Themes über bin/console theme:compile.
     */
    public function compileTheme(string $triggeredBy = 'manual'): bool
    {
        return $this->runConsoleCommand(['theme:compile'], $triggeredBy);
    }

    /**
     * Löscht var/cache, führt cache:warmup aus, leert OPcache und startet Workers neu.
     * Entspricht einem vollständigen Container-Neustart.
     */
    public function restartWorkers(string $triggeredBy = 'manual'): bool
    {
        $this->clearCache($triggeredBy);

        return $this->sendRestartSignal($triggeredBy);
    }

    /**
     * Full-Deploy: var/cache löschen → cache:warmup → Theme kompilieren → Workers neu starten.
     *
     * @return array{cacheClear: bool, themeCompile: bool, restart: bool}
     */
    public function runFullDeploy(string $triggeredBy = 'manual'): array
    {
        $results = [
            'cacheClear' => false,
            'themeCompile' => false,
            'restart' => false,
        ];

        $results['cacheClear'] = $this->clearCache($triggeredBy);
        $results['themeCompile'] = $this->compileTheme($triggeredBy);
        $results['restart'] = $this->sendRestartSignal($triggeredBy);

        $this->log('info', 'Full-Deploy abgeschlossen', array_merge($results, ['triggered_by' => $triggeredBy]));
        $this->writeStatus('fullDeploy', $triggeredBy, !in_array(false, $results, true), $results);

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastStatus(): ?array
    {
        $statusPath = $this->getLogDir() . '/' . self::STATUS_FILE;
        if (!is_file($statusPath)) {
            return null;
        }

        $contents = file_get_contents($statusPath);
        if (false === $contents) {
            return null;
        }

        $status = json_decode($contents, true);

        return is_array($status) ? $status : null;
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

    /**
     * Sendet den Restart-Signal an die Caddy Admin API.
     * Wird intern von restartWorkers() und runFullDeploy() genutzt, damit
     * clearCache() nicht doppelt aufgerufen wird.
     */
    private function sendRestartSignal(string $triggeredBy): bool
    {
        $url = rtrim((string) $this->getConfig('adminApiUrl', 'http://localhost:2019'), '/');
        $timeout = (int) $this->getConfig('timeout', 5);

        try {
            $ch = curl_init($url . '/frankenphp/workers/restart');
            if (false === $ch) {
                throw new \RuntimeException('curl_init failed');
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            $responseBody = (string) curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ('' !== $curlError) {
                throw new \RuntimeException($curlError);
            }

            $success = 200 === $statusCode;

            $this->log(
                $success ? 'info' : 'error',
                $success
                    ? 'FrankenPHP Workers erfolgreich neu gestartet'
                    : 'FrankenPHP Worker-Neustart fehlgeschlagen (HTTP ' . $statusCode . ')',
                ['triggered_by' => $triggeredBy, 'url' => $url, 'http_status' => $statusCode, 'response' => $responseBody]
            );
            $this->writeStatus('restart', $triggeredBy, $success, ['restart' => $success]);

            return $success;
        } catch (\Throwable $e) {
            $this->log('error', 'FrankenPHP Worker-Neustart Exception: ' . $e->getMessage(), [
                'triggered_by' => $triggeredBy,
                'url' => $url,
            ]);
            $this->writeStatus('restart', $triggeredBy, false, ['restart' => false], $e->getMessage());

            return false;
        }
    }

    /**
     * Leert PHP In-Memory-Caches aus dem laufenden Worker-Prozess heraus.
     * Realpath-Cache und OPcache (shared memory, gilt für alle Worker) werden zurückgesetzt.
     * APCu wird bewusst NICHT geleert: apcu_clear_cache() mitten in einem aktiven HTTP-Request
     * löscht auch Laufzeit-Daten anderer Worker (Plugin-Bundle-Registry, Snippet-Cache), was
     * zu fehlenden Admin-Snippets führt. OPcache-Reset allein löst das Bytecode-Staleness-Problem.
     */
    private function resetPhpCache(string $triggeredBy): void
    {
        clearstatcache(true);

        if (\function_exists('opcache_reset')) {
            \opcache_reset();
        }

        $this->log('info', 'PHP In-Memory-Cache geleert (Realpath-Cache, OPcache)', ['triggered_by' => $triggeredBy]);
    }

    private function runConsoleCommand(array $command, string $triggeredBy): bool
    {
        $consolePath = $this->projectDir . '/bin/console';
        $process = new Process(array_merge($this->getConsoleCommandPrefix($consolePath), $command));
        $process->setEnv(['WSC_FRANKENPHP_INTERNAL' => '1']);
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
                    'output' => $success ? $process->getOutput() : $process->getErrorOutput(),
                ]
            );
            $this->writeStatus($command[0] ?? 'console', $triggeredBy, $success, [$command[0] ?? 'console' => $success]);

            return $success;
        } catch (\Throwable $e) {
            $this->log('error', 'Konsolenbefehl Exception: ' . implode(' ', $command) . ' – ' . $e->getMessage(), [
                'triggered_by' => $triggeredBy,
            ]);
            $this->writeStatus($command[0] ?? 'console', $triggeredBy, false, [$command[0] ?? 'console' => false], $e->getMessage());

            return false;
        }
    }

    /**
     * FrankenPHP exposes console execution through "frankenphp php-cli" instead of a plain php binary.
     * In worker mode PHP_BINARY is empty — detect FrankenPHP by checking the empty binary constant
     * and fall back to calling "frankenphp" directly (resolved from PATH by Symfony Process).
     *
     * @return list<string>
     */
    private function getConsoleCommandPrefix(string $consolePath): array
    {
        $phpBinary = PHP_BINARY;

        if ('' === $phpBinary || str_contains(basename($phpBinary), 'frankenphp')) {
            return ['frankenphp', 'php-cli', $consolePath];
        }

        return [$phpBinary, $consolePath];
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!(bool) $this->getConfig('enableLogging', true)) {
            return;
        }

        match ($level) {
            'error' => $this->logger->error('[WSC_FrankenPHPUtils] ' . $message, $context),
            'warning' => $this->logger->warning('[WSC_FrankenPHPUtils] ' . $message, $context),
            default => $this->logger->info('[WSC_FrankenPHPUtils] ' . $message, $context),
        };
        $this->writePluginLog($level, $message, $context);
    }

    private function writePluginLog(string $level, string $message, array $context = []): void
    {
        $logDir = $this->getLogDir();
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }

        $contextJson = [] !== $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf(
            '[%s] %s: %s%s%s',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $contextJson,
            PHP_EOL
        );

        @file_put_contents($logDir . '/' . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }

    private function writeStatus(string $action, string $triggeredBy, bool $success, array $results = [], ?string $error = null): void
    {
        $logDir = $this->getLogDir();
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }

        $status = [
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'action' => $action,
            'triggeredBy' => $triggeredBy,
            'success' => $success,
            'results' => $results,
            'error' => $error,
        ];

        @file_put_contents(
            $logDir . '/' . self::STATUS_FILE,
            json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function getLogDir(): string
    {
        return $this->projectDir . '/var/log';
    }

    private function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->systemConfig->get(self::CONFIG_PREFIX . $key) ?? $default;
    }
}
