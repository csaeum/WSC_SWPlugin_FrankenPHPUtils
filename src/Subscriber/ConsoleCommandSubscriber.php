<?php declare(strict_types=1);

namespace WSC\WSC_SWPlugin_FrankenPHPUtils\Subscriber;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WSC\WSC_SWPlugin_FrankenPHPUtils\Service\FrankenPHPService;

class ConsoleCommandSubscriber implements EventSubscriberInterface
{
    private const WATCHED_COMMANDS = [
        'cache:clear',
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
        if (getenv('WSC_FRANKENPHP_INTERNAL') === '1') {
            return;
        }

        if ($event->getExitCode() !== 0) {
            return;
        }

        $commandName = $event->getCommand()?->getName();
        if ($commandName === null || !in_array($commandName, self::WATCHED_COMMANDS, true)) {
            return;
        }

        $this->frankenPHPService->restartWorkers('console_command:' . $commandName);
    }
}
