<?php

declare(strict_types=1);

namespace WSC\WSC_SWPlugin_FrankenPHPUtils\Subscriber;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WSC\WSC_SWPlugin_FrankenPHPUtils\Service\FrankenPHPService;

class ConsoleCommandSubscriber implements EventSubscriberInterface
{
    private const WATCHED_COMMANDS = [
        'cache:clear',
        'cache:warmup',
        'theme:compile',
    ];

    public function __construct(
        private readonly FrankenPHPService $frankenPHPService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
        ];
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ('1' === getenv('WSC_FRANKENPHP_INTERNAL')) {
            return;
        }

        $commandName = $event->getCommand()?->getName();
        if (null === $commandName || !in_array($commandName, self::WATCHED_COMMANDS, true)) {
            return;
        }

        // Restart regardless of exit code: in FrankenPHP worker mode, OPcache may hold
        // file handles during cache:clear causing non-zero exit even on success.
        // A worker restart is always safe after any cache operation.
        $this->frankenPHPService->restartWorkers('console_command:' . $commandName);
    }
}
