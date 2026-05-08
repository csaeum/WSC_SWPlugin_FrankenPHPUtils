# AGENT.md – WSC_SWPlugin_FrankenPHPUtils

## Plugin-Übersicht

**Name:** WSC_SWPlugin_FrankenPHPUtils  
**Namespace:** `WSC\WSC_SWPlugin_FrankenPHPUtils`  
**Vendor-Prefix:** WSC  
**Lizenz:** GPL-3.0-or-later  
**Kompatibilität:** Shopware 6.6.x, 6.7.x  
**Zweck:** Automatischer FrankenPHP Worker-Neustart und Cache-Management bei Plugin-Lifecycle-Events sowie manuelle Steuerung über das Shopware-Admin-Panel.

---

## Verzeichnisstruktur

```
WSC_SWPlugin_FrankenPHPUtils/
├── composer.json
├── AGENT.md
└── src/
    ├── WSC_SWPlugin_FrankenPHPUtils.php         # Plugin-Basisklasse
    ├── Service/
    │   └── FrankenPHPService.php                # Kernlogik: Restart, CacheClear, ThemeCompile
    ├── Subscriber/
    │   └── PluginLifecycleSubscriber.php        # Lauscht auf Plugin-Events
    ├── Controller/
    │   └── Api/
    │       └── FrankenPHPApiController.php      # REST-Endpunkte für Admin-Buttons
    └── Resources/
        ├── config/
        │   ├── config.xml                       # Plugin-Konfiguration (Admin-UI)
        │   ├── services.xml                     # Symfony DI
        │   └── routes.xml                       # Routing-Import für Controller
        └── app/
            └── administration/
                └── src/
                    ├── main.js
                    └── module/
                        └── wsc-frankenphp-utils/
                            ├── index.js         # Modul-Registrierung, Navigation
                            ├── snippet/
                            │   ├── de-DE.json
                            │   └── en-GB.json
                            └── page/
                                └── wsc-frankenphp-utils-index/
                                    ├── index.js
                                    └── wsc-frankenphp-utils-index.html.twig
```

---

## Kernfunktionen

### FrankenPHPService
- `restartWorkers(string $triggeredBy)` – POST zu `{adminApiUrl}/frankenphp/workers/restart`
- `clearCache(string $triggeredBy)` – führt `bin/console cache:clear` aus
- `compileTheme(string $triggeredBy)` – führt `bin/console theme:compile` aus
- `runFullDeploy(string $triggeredBy)` – alle drei in Sequenz, gibt `array{cacheClear, themeCompile, restart}` zurück

### Plugin-Konfigurationsschlüssel (Prefix: `WSC_SWPlugin_FrankenPHPUtils.config.`)
| Key | Typ | Default |
|-----|-----|---------|
| `adminApiUrl` | string | `http://localhost:2019` |
| `timeout` | int | `5` |
| `autoRestartOnPluginEvent` | bool | `true` |
| `autoCacheClearOnPluginEvent` | bool | `true` |
| `autoThemeCompileOnPluginEvent` | bool | `false` |
| `enableLogging` | bool | `true` |

### API-Endpunkte (alle POST, Scope: `api`)
| Route | Name |
|-------|------|
| `/api/wsc-frankenphp/restart` | `api.wsc_frankenphp.restart` |
| `/api/wsc-frankenphp/cache-clear` | `api.wsc_frankenphp.cache_clear` |
| `/api/wsc-frankenphp/theme-compile` | `api.wsc_frankenphp.theme_compile` |
| `/api/wsc-frankenphp/full-deploy` | `api.wsc_frankenphp.full_deploy` |

---

## Entwicklungskonventionen

- PHP 8.2+, readonly constructor properties, PHP Attribute Routing (`#[Route(...)]`)
- Keine direkten `shell_exec`/`exec`-Aufrufe – immer `Symfony\Component\Process\Process`
- Logging immer über den injizierten `LoggerInterface`, niemals `error_log()` oder `var_dump()`
- Konfiguration ausschließlich über `SystemConfigService`, nie über Hardcoding
- Admin-Komponenten: Shopware Vue 2 Options API, `sw-page` / `sw-card` / `sw-button` / `sw-alert`

## Lokale Entwicklung (DDEV)

Plugin-Quelle liegt unter:
```
~/Entwicklungsumgebung/PHPStorm/SW-Plugins/WSC_SWPlugin_FrankenPHPUtils/
```
Nie direkt in das DDEV-Shopware-Verzeichnis editieren.

Nach Änderungen im Admin-JS:
```bash
bin/build-administration.sh
# oder im DDEV-Kontext:
ddev exec bin/build-administration.sh
```
