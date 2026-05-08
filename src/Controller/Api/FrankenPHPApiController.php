<?php declare(strict_types=1);

namespace WSC\WSC_SWPlugin_FrankenPHPUtils\Controller\Api;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use WSC\WSC_SWPlugin_FrankenPHPUtils\Service\FrankenPHPService;

#[Route(defaults: ['_routeScope' => ['api']])]
class FrankenPHPApiController extends AbstractController
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
            'message' => $success
                ? 'FrankenPHP Workers erfolgreich neu gestartet'
                : 'Fehler beim Neustart der FrankenPHP Workers',
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
            'message' => $success
                ? 'Cache erfolgreich geleert'
                : 'Fehler beim Leeren des Caches',
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
            'message' => $success
                ? 'Theme erfolgreich kompiliert'
                : 'Fehler bei der Theme-Kompilierung',
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
            'message' => $allSuccess
                ? 'Full-Deploy erfolgreich abgeschlossen'
                : 'Full-Deploy mit Fehlern abgeschlossen – Details im Log',
        ]);
    }
}
