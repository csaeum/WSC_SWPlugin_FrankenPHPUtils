# AGENT.md – WSC_SWPlugin_FrankenPHPUtils

## Hintergrund & Problemstellung

FrankenPHP betreibt PHP im Worker-Mode: PHP-Prozesse bleiben dauerhaft im Speicher und werden nicht nach jedem Request neu gestartet. Das ist der Grund für die hohe Performance — bringt aber einen entscheidenden Nebeneffekt mit sich.

Wenn im laufenden Shopware-Container Änderungen vorgenommen werden — sei es über die Konsole oder das Admin-Backend — kann FrankenPHP diese Änderungen **nicht automatisch erkennen**, weil die Worker-Prozesse noch den alten Zustand im Speicher halten. Es existieren somit Caches, die Shopware selbst nicht erreichen und leeren kann.

### Konkrete Auslöser

Folgende Aktionen können dazu führen, dass FrankenPHP einen veralteten Zustand ausliefert:

**Konsole:**
```bash
bin/console cache:clear
bin/console theme:compile
bin/console plugin:install
bin/console plugin:update
bin/console plugin:activate
bin/console plugin:deactivate
bin/console plugin:uninstall
```

**Shopware Admin-Backend:**
- Plugin installieren, aktualisieren, aktivieren, deaktivieren, deinstallieren
- Theme-Änderungen speichern

### Symptome

Die resultierenden Fehler sind oft schwer zuzuordnen — Seiten laden nicht korrekt, CSS/JS-Änderungen greifen nicht, neue Plugin-Funktionen fehlen oder es treten PHP-Fehler auf, obwohl der Code korrekt ist. In der Vergangenheit wurden viele Stunden mit der Fehlersuche verbracht, bis sich herausstellte, dass ein einfacher Container-Neustart das Problem behoben hätte.

---

## Lösung

FrankenPHP stellt eine interne **Caddy Admin API** bereit, über die alle Worker graceful neu gestartet werden können — ohne den Container komplett neu zu starten, laufende Requests werden dabei noch sauber abgeschlossen.

```bash
# Manuell aufrufbar direkt im Container:
docker exec <container-name> \
  curl -s -X POST http://localhost:2019/frankenphp/workers/restart
```

Dieser Endpoint ist in den verwendeten Shopware FrankenPHP Docker Images (`ghcr.io/shopware/docker-base:8.x-frankenphp`) standardmäßig aktiv und intern erreichbar.

---

## Plugin-Konzept

Dieses Plugin automatisiert den Worker-Neustart direkt aus Shopware heraus, sodass weder Entwickler noch Admins daran denken müssen.

**Name:** `WSC_SWPlugin_FrankenPHPUtils`  
**Namespace:** `WSC\WSC_SWPlugin_FrankenPHPUtils`  
**Vendor-Prefix:** WSC  
**Lizenz:** GPL-3.0-or-later  
**Kompatibilität:** Shopware 6.6.x, 6.7.x

### Automatisch (Event-gesteuert)

Das Plugin lauscht auf alle Plugin-Lifecycle-Events in Shopware und führt danach automatisch die konfigurierten Aktionen aus:

| Event | Shopware-Klasse |
|---|---|
| Plugin installiert | `PluginPostInstallEvent` |
| Plugin aktiviert | `PluginPostActivateEvent` |
| Plugin deaktiviert | `PluginPostDeactivateEvent` |
| Plugin deinstalliert | `PluginPostUninstallEvent` |
| Plugin aktualisiert | `PluginPostUpdateEvent` |

Konfigurierbare Aktionen pro Event (alle ein/ausschaltbar):
- `cache:clear` ausführen
- `theme:compile` ausführen *(standardmäßig deaktiviert, da zeitintensiv)*
- FrankenPHP Workers neu starten

### Manuell (Admin-Panel)

Unter **Einstellungen → WSC FrankenPHP Utils** stehen folgende Buttons bereit — nutzbar ohne Konsolenzugang:

| Button | Aktion |
|---|---|
| Cache leeren | `bin/console cache:clear` |
| Theme kompilieren | `bin/console theme:compile` |
| Workers neu starten | POST `/frankenphp/workers/restart` |
| **Full-Deploy** | Alle drei in Sequenz |

---

## Konfiguration

Alle Einstellungen unter **Einstellungen → Plugins → WSC FrankenPHP Utils**:

| Schlüssel | Typ | Standard | Beschreibung |
|---|---|---|---|
| `adminApiUrl` | string | `http://localhost:2019` | URL zur Caddy Admin API |
| `timeout` | int | `5` | Timeout in Sekunden für den API-Call |
| `autoRestartOnPluginEvent` | bool | `true` | Auto-Restart bei Plugin-Events |
| `autoCacheClearOnPluginEvent` | bool | `true` | Auto-Cache-Clear bei Plugin-Events |
| `autoThemeCompileOnPluginEvent` | bool | `false` | Auto-Theme-Compile bei Plugin-Events |
| `enableLogging` | bool | `true` | Aktionen in `var/log/prod.log` schreiben |

---

## API-Endpunkte

Alle Endpunkte erfordern einen gültigen Shopware API Bearer Token (Scope: `api`).

| Methode | Route | Beschreibung |
|---|---|---|
| POST | `/api/wsc-frankenphp/restart` | Workers neu starten |
| POST | `/api/wsc-frankenphp/cache-clear` | Cache leeren |
| POST | `/api/wsc-frankenphp/theme-compile` | Theme kompilieren |
| POST | `/api/wsc-frankenphp/full-deploy` | Alle drei in Sequenz |

---

## Bekannte Probleme & offene TODOs

### 1. Logging zeigt Fehler obwohl Aktion erfolgreich war

Das Logging schreibt derzeit fälschlicherweise einen Fehler-Eintrag, obwohl der Worker-Neustart und alle anderen Aktionen erfolgreich abgeschlossen wurden.

**Zu beheben in:** `FrankenPHPService.php` → Methode `log()`

Die Ursache liegt vermutlich darin, dass `$this->logger->{$level}(...)` mit einem dynamischen Level-String nicht korrekt funktioniert. Auf explizite Aufrufe umstellen:

```php
// Vorher (fehlerhaft):
$this->logger->{$level}('[WSC_FrankenPHPUtils] ' . $message, $context);

// Nachher (korrekt):
match($level) {
    'error'   => $this->logger->error('[WSC_FrankenPHPUtils] ' . $message, $context),
    'warning' => $this->logger->warning('[WSC_FrankenPHPUtils] ' . $message, $context),
    default   => $this->logger->info('[WSC_FrankenPHPUtils] ' . $message, $context),
};
```

Zusätzlich prüfen ob der Logger-Service in `services.xml` korrekt injiziert ist — `id="logger"` ist der Monolog-Default-Channel, ggf. auf einen eigenen Channel umstellen.

Log-Ausgaben zum Debuggen direkt im Container prüfen:
```bash
docker exec <container-name> tail -f var/log/prod.log
```

---

### 2. Theme:compile über Backend — kein Hinweis auf FrankenPHP-Neustart

Wenn ein Admin das Theme über das Shopware-Backend kompiliert (Themes → Theme kompilieren), erhält er nach Abschluss keine Rückmeldung darüber, dass FrankenPHP ebenfalls neu gestartet wurde.

**Gewünschtes Verhalten:** Nach einem backend-seitig ausgelösten `theme:compile` soll im Admin-Panel eine zusätzliche Erfolgs-Notification erscheinen: *„FrankenPHP Workers wurden ebenfalls neu gestartet."*

**Zu implementieren:** Einen zusätzlichen Subscriber auf das Shopware Theme-Event anlegen:

```php
// In PluginLifecycleSubscriber.php ergänzen:
use Shopware\Storefront\Theme\Event\ThemeCopyToLiveEvent;

public static function getSubscribedEvents(): array
{
    return [
        // ... bestehende Events ...
        ThemeCopyToLiveEvent::class => 'onThemeCopyToLive',
    ];
}

public function onThemeCopyToLive(ThemeCopyToLiveEvent $event): void
{
    $this->handleLifecycleEvent('theme_copy_to_live');
}
```

Die Notification im Admin-Panel wird über die bestehende JavaScript-Komponente transportiert — der `/api/wsc-frankenphp/theme-compile`-Endpunkt gibt bereits eine Erfolgsmeldung zurück wenn der Plugin-Button genutzt wird. Für backend-seitig ausgelöste Kompilierungen greift der neue Subscriber und führt den Neustart serverseitig aus. Eine sichtbare Admin-Notification erfordert zusätzlich einen Notification-Store-Eintrag über die Shopware Admin API oder einen WebSocket-Push.

---

## Build & Release

```bash
# 1. Admin-Assets bauen (einmalig auf der Entwicklungsumgebung)
bin/build-administration.sh

# 2. Gebaute Datei ins Plugin kopieren
cp public/bundles/wscswpluginfrankenphputils/administration/js/wsc-swplugin-frankenphputils.js \
   custom/plugins/WSC_SWPlugin_FrankenPHPUtils/src/Resources/public/administration/js/

# 3. Datei committen → Plugin als ZIP weitergeben
#    → Kein Build-Schritt mehr nötig beim Installieren
```