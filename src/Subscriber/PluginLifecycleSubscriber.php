<?php declare(strict_types=1);

namespace WSC\WSC_SWPlugin_FrankenPHPUtils\Subscriber;

use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Storefront\Theme\Event\ThemeCopyToLiveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WSC\WSC_SWPlugin_FrankenPHPUtils\Service\FrankenPHPService;

class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FrankenPHPService $frankenPHPService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return array_merge(
            [
                PluginPostInstallEvent::class    => 'onPluginPostInstall',
                PluginPostActivateEvent::class   => 'onPluginPostActivate',
                PluginPostDeactivateEvent::class => 'onPluginPostDeactivate',
                PluginPostUninstallEvent::class  => 'onPluginPostUninstall',
                PluginPostUpdateEvent::class     => 'onPluginPostUpdate',
            ],
            class_exists(ThemeCopyToLiveEvent::class)
                ? [ThemeCopyToLiveEvent::class => 'onThemeCopyToLive']
                : []
        );
    }

    public function onPluginPostInstall(PluginPostInstallEvent $event): void
    {
        $pluginName = $event->getContext()->getPlugin()->getName();
        $this->handleLifecycleEvent('install:' . $pluginName);
    }

    public function onPluginPostActivate(PluginPostActivateEvent $event): void
    {
        $pluginName = $event->getContext()->getPlugin()->getName();
        $this->handleLifecycleEvent('activate:' . $pluginName);
    }

    public function onPluginPostDeactivate(PluginPostDeactivateEvent $event): void
    {
        $pluginName = $event->getContext()->getPlugin()->getName();
        $this->handleLifecycleEvent('deactivate:' . $pluginName);
    }

    public function onPluginPostUninstall(PluginPostUninstallEvent $event): void
    {
        $pluginName = $event->getContext()->getPlugin()->getName();
        $this->handleLifecycleEvent('uninstall:' . $pluginName);
    }

    public function onPluginPostUpdate(PluginPostUpdateEvent $event): void
    {
        $pluginName = $event->getContext()->getPlugin()->getName();
        $this->handleLifecycleEvent('update:' . $pluginName);
    }

    public function onThemeCopyToLive(ThemeCopyToLiveEvent $event): void
    {
        $this->handleLifecycleEvent('theme_copy_to_live');
    }

    private function handleLifecycleEvent(string $triggeredBy): void
    {
        if ($this->frankenPHPService->isAutoThemeCompileEnabled()) {
            $this->frankenPHPService->compileTheme('plugin_event:' . $triggeredBy);
        }

        if ($this->frankenPHPService->isAutoRestartEnabled()) {
            // restartWorkers() löscht var/cache intern – kein separates clearCache() nötig
            $this->frankenPHPService->restartWorkers('plugin_event:' . $triggeredBy);
        } elseif ($this->frankenPHPService->isAutoCacheClearEnabled()) {
            $this->frankenPHPService->clearCache('plugin_event:' . $triggeredBy);
        }
    }
}
