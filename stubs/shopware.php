<?php

declare(strict_types=1);

namespace Symfony\Component\EventDispatcher {
    interface EventSubscriberInterface
    {
        /** @return array<string, string|array<int, string|int>> */
        public static function getSubscribedEvents(): array;
    }
}

namespace Psr\Log {
    interface LoggerInterface
    {
        /** @param array<string, mixed> $context */
        public function error(string $message, array $context = []): void;

        /** @param array<string, mixed> $context */
        public function warning(string $message, array $context = []): void;

        /** @param array<string, mixed> $context */
        public function info(string $message, array $context = []): void;
    }
}

namespace Symfony\Component\Filesystem {
    class Filesystem
    {
        public function exists(string $path): bool
        {
            return false;
        }

        public function remove(string $path): void
        {
        }
    }
}

namespace Symfony\Component\Process {
    class Process
    {
        /** @param array<string> $command */
        public function __construct(array $command)
        {
        }

        /** @param array<string, string> $env */
        public function setEnv(array $env): static
        {
            return $this;
        }

        public function setTimeout(?float $timeout): static
        {
            return $this;
        }

        public function run(): int
        {
            return 0;
        }

        public function isSuccessful(): bool
        {
            return false;
        }

        public function getOutput(): string
        {
            return '';
        }

        public function getErrorOutput(): string
        {
            return '';
        }
    }
}

namespace Symfony\Component\Console {
    class ConsoleEvents
    {
        public const TERMINATE = 'console.terminate';
    }

    class Command
    {
        public function getName(): ?string
        {
            return null;
        }
    }
}

namespace Symfony\Component\Console\Event {
    class ConsoleTerminateEvent
    {
        public function getCommand(): ?\Symfony\Component\Console\Command
        {
            return null;
        }
    }
}

namespace Symfony\Component\HttpFoundation {
    class JsonResponse
    {
        /** @param array<string, mixed>|null $data */
        public function __construct(mixed $data = null, int $status = 200, array $headers = [], bool $json = false)
        {
        }
    }
}

namespace Symfony\Component\Routing\Attribute {
    #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    class Route
    {
        /** @param array<string, mixed> $defaults */
        public function __construct(
            string $path = '',
            ?string $name = null,
            string|array $methods = [],
            array $defaults = [],
        ) {
        }
    }
}

namespace Shopware\Core\Framework {
    abstract class Plugin
    {
        public function getPath(): string
        {
            return '';
        }
    }

    class Context
    {
    }
}

namespace Shopware\Core\Framework\Plugin {
    class PluginEntity
    {
        public function getName(): string
        {
            return '';
        }
    }
}

namespace Shopware\Core\Framework\Plugin\Context {
    class InstallContext
    {
        public function getPlugin(): \Shopware\Core\Framework\Plugin\PluginEntity
        {
            return new \Shopware\Core\Framework\Plugin\PluginEntity();
        }
    }

    class ActivateContext extends InstallContext
    {
    }

    class DeactivateContext extends InstallContext
    {
    }

    class UninstallContext
    {
        public function getPlugin(): \Shopware\Core\Framework\Plugin\PluginEntity
        {
            return new \Shopware\Core\Framework\Plugin\PluginEntity();
        }
    }

    class UpdateContext extends InstallContext
    {
    }
}

namespace Shopware\Core\Framework\Plugin\Event {
    class PluginPostInstallEvent
    {
        public function getContext(): \Shopware\Core\Framework\Plugin\Context\InstallContext
        {
            return new \Shopware\Core\Framework\Plugin\Context\InstallContext();
        }
    }

    class PluginPostActivateEvent
    {
        public function getContext(): \Shopware\Core\Framework\Plugin\Context\ActivateContext
        {
            return new \Shopware\Core\Framework\Plugin\Context\ActivateContext();
        }
    }

    class PluginPostDeactivateEvent
    {
        public function getContext(): \Shopware\Core\Framework\Plugin\Context\DeactivateContext
        {
            return new \Shopware\Core\Framework\Plugin\Context\DeactivateContext();
        }
    }

    class PluginPostUninstallEvent
    {
        public function getContext(): \Shopware\Core\Framework\Plugin\Context\UninstallContext
        {
            return new \Shopware\Core\Framework\Plugin\Context\UninstallContext();
        }
    }

    class PluginPostUpdateEvent
    {
        public function getContext(): \Shopware\Core\Framework\Plugin\Context\UpdateContext
        {
            return new \Shopware\Core\Framework\Plugin\Context\UpdateContext();
        }
    }
}

namespace Shopware\Storefront\Theme\Event {
    class ThemeCopyToLiveEvent
    {
    }
}

namespace Shopware\Core\System\SystemConfig {
    class SystemConfigService
    {
        public function get(string $key): mixed
        {
            return null;
        }
    }
}
