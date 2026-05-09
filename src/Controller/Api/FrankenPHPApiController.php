<?php declare(strict_types=1);

namespace WSC\WSC_SWPlugin_FrankenPHPUtils\Controller\Api;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use WSC\WSC_SWPlugin_FrankenPHPUtils\Service\FrankenPHPService;

#[Route(defaults: ['_routeScope' => ['api']])]
class FrankenPHPApiController
{
    public function __construct(
        private readonly FrankenPHPService $frankenPHPService,
    ) {
    }

    /**
     * Startet FrankenPHP Worker neu.
     */
    #[Route(
        path: '/api/wsc-frankenphp/restart',
        name: 'api.wsc_frankenphp.restart',
        methods: ['POST']
    )]
    public function restart(Context $context): JsonResponse
    {
        $success = $this->frankenPHPService->restartWorkers('admin_manual');

        return new JsonResponse([
            'success' => $success,
            'messageKey' => $success
                ? 'wsc-frankenphp-utils.notifications.restartSuccess'
                : 'wsc-frankenphp-utils.notifications.restartError',
        ]);
    }

    /**
     * Gibt den letzten FrankenPHP Utils Status zurueck.
     */
    #[Route(
        path: '/api/wsc-frankenphp/status',
        name: 'api.wsc_frankenphp.status',
        methods: ['GET']
    )]
    public function status(Context $context): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'status' => $this->frankenPHPService->getLastStatus(),
        ]);
    }

    /**
     * Leert den Shopware Cache.
     */
    #[Route(
        path: '/api/wsc-frankenphp/cache-clear',
        name: 'api.wsc_frankenphp.cache_clear',
        methods: ['POST']
    )]
    public function cacheClear(Context $context): JsonResponse
    {
        $success = $this->frankenPHPService->clearCache('admin_manual');

        return new JsonResponse([
            'success' => $success,
            'messageKey' => $success
                ? 'wsc-frankenphp-utils.notifications.cacheSuccess'
                : 'wsc-frankenphp-utils.notifications.cacheError',
        ]);
    }

    /**
     * Leert den Shopware Cache und startet FrankenPHP Worker neu.
     */
    #[Route(
        path: '/api/wsc-frankenphp/cache-clear-restart',
        name: 'api.wsc_frankenphp.cache_clear_restart',
        methods: ['POST']
    )]
    public function cacheClearRestart(Context $context): JsonResponse
    {
        $results = $this->frankenPHPService->runCacheClearAndRestart('admin_manual');
        $allSuccess = !in_array(false, $results, true);

        return new JsonResponse([
            'success' => $allSuccess,
            'results' => $results,
            'messageKey' => $allSuccess
                ? 'wsc-frankenphp-utils.notifications.cacheRestartSuccess'
                : 'wsc-frankenphp-utils.notifications.cacheRestartError',
        ]);
    }

    /**
     * Kompiliert alle Themes.
     */
    #[Route(
        path: '/api/wsc-frankenphp/theme-compile',
        name: 'api.wsc_frankenphp.theme_compile',
        methods: ['POST']
    )]
    public function themeCompile(Context $context): JsonResponse
    {
        $success = $this->frankenPHPService->compileTheme('admin_manual');

        return new JsonResponse([
            'success' => $success,
            'messageKey' => $success
                ? 'wsc-frankenphp-utils.notifications.themeSuccess'
                : 'wsc-frankenphp-utils.notifications.themeError',
        ]);
    }

    /**
     * Full-Deploy: Cache leeren → Theme kompilieren → Workers neu starten.
     */
    #[Route(
        path: '/api/wsc-frankenphp/full-deploy',
        name: 'api.wsc_frankenphp.full_deploy',
        methods: ['POST']
    )]
    public function fullDeploy(Context $context): JsonResponse
    {
        $results = $this->frankenPHPService->runFullDeploy('admin_manual');
        $allSuccess = !in_array(false, $results, true);

        return new JsonResponse([
            'success' => $allSuccess,
            'results' => $results,
            'messageKey' => $allSuccess
                ? 'wsc-frankenphp-utils.notifications.fullDeploySuccess'
                : 'wsc-frankenphp-utils.notifications.fullDeployError',
        ]);
    }
}
