# WSC | FrankenPHP Utils

Shopware 6 Plugin zur automatischen Verwaltung von FrankenPHP-Workern bei Plugin-Lifecycle-Events und Cache-Operationen.

---

## Hintergrund

FrankenPHP betreibt PHP im **Worker-Mode**: PHP-Prozesse bleiben dauerhaft im Speicher und werden nicht nach jedem Request neu gestartet. Das sorgt für hohe Performance — bringt aber einen entscheidenden Nebeneffekt mit sich.

Wenn Shopware Cache leert, Plugins installiert oder Themes kompiliert, können die laufenden FrankenPHP-Worker diese Änderungen nicht automatisch übernehmen, weil sie noch den alten Zustand im RAM halten. Dieses Plugin automatisiert den graceful Worker-Neustart direkt aus Shopware heraus — über die Caddy Admin API.

---

## Voraussetzungen

- Shopware 6.6.x oder 6.7.x
- PHP 8.2+
- FrankenPHP mit aktivierter Caddy Admin API (Standard: `http://localhost:2019`)
- Empfohlenes Docker-Image: `ghcr.io/shopware/docker-base:8.x-frankenphp`

---

## Was das Plugin kann

### Automatisch — Plugin-Lifecycle-Events

Bei folgenden Shopware-Events führt das Plugin automatisch die konfigurierten Aktionen aus:

| Event | Auslöser |
|---|---|
| Plugin installiert | `PluginPostInstallEvent` |
| Plugin aktiviert | `PluginPostActivateEvent` |
| Plugin deaktiviert | `PluginPostDeactivateEvent` |
| Plugin deinstalliert | `PluginPostUninstallEvent` |
| Plugin aktualisiert | `PluginPostUpdateEvent` |
| Theme live geschaltet | `ThemeCopyToLiveEvent` *(nur mit Storefront-Bundle)* |

Konfigurierbare Aktionen pro Event (alle einzeln ein-/ausschaltbar):
- Shopware-Cache leeren (`cache:clear`)
- Theme kompilieren (`theme:compile`) — standardmäßig deaktiviert, da zeitintensiv
- FrankenPHP-Worker neu starten

### Automatisch — Konsolen-Befehle

Das Plugin lauscht auf folgende direkt ausgeführte Konsolenbefehle und startet die Worker danach neu:

| Befehl | Status |
|---|---|
| `bin/console theme:compile` | ✅ Funktioniert |
| `bin/console cache:warmup` | ✅ Funktioniert |
| `bin/console cache:clear` | ⚠️ Unzuverlässig — siehe bekannte Probleme |

### Manuell — Admin-Panel

Unter **Erweiterungen → WSC FrankenPHP Utils** stehen folgende Aktionen bereit:

| Aktion | Beschreibung |
|---|---|
| FrankenPHP Signal senden | Nur Worker-Neustart |
| Full-Deploy | Cache leeren + Theme kompilieren + Worker-Neustart |
| Cache leeren + Workers neu starten | Cache leeren + Worker-Neustart |
| Cache leeren | Nur `cache:clear` |
| Theme kompilieren | Nur `theme:compile` |
| Workers neu starten | Nur Worker-Neustart |

Die Seite zeigt außerdem eine **Status-Karte** mit dem Ergebnis der letzten Aktion (Zeitpunkt, Auslöser, Einzelergebnisse, Fehlerdetails) und aktualisiert sich automatisch alle 10 Sekunden.

---

## Konfiguration

Einstellungen unter **Einstellungen → Plugins → WSC FrankenPHP Utils**:

| Schlüssel | Typ | Standard | Beschreibung |
|---|---|---|---|
| `adminApiUrl` | string | `http://localhost:2019` | URL zur Caddy Admin API |
| `timeout` | int | `5` | Timeout in Sekunden |
| `autoRestartOnPluginEvent` | bool | `true` | Worker-Neustart bei Plugin-Events |
| `autoCacheClearOnPluginEvent` | bool | `true` | Cache leeren bei Plugin-Events |
| `autoThemeCompileOnPluginEvent` | bool | `false` | Theme kompilieren bei Plugin-Events |
| `enableLogging` | bool | `true` | Logging aktivieren |

---

## Logging

Das Plugin schreibt in zwei Dateien:

| Datei | Inhalt |
|---|---|
| `var/log/wsc_swplugin_frankenphputils.log` | Alle Plugin-Aktionen mit Zeitstempel und Kontext |
| `var/log/wsc_swplugin_frankenphputils_status.json` | Letzter Aktionsstatus (wird von der Admin-Seite gelesen) |

---

## API-Endpunkte

Alle Endpunkte erfordern einen gültigen Shopware API Bearer Token.

| Methode | Route | Beschreibung |
|---|---|---|
| `POST` | `/api/wsc-frankenphp/restart` | Worker neu starten |
| `POST` | `/api/wsc-frankenphp/cache-clear` | Cache leeren |
| `POST` | `/api/wsc-frankenphp/cache-clear-restart` | Cache leeren + Worker-Neustart |
| `POST` | `/api/wsc-frankenphp/theme-compile` | Theme kompilieren |
| `POST` | `/api/wsc-frankenphp/full-deploy` | Cache + Theme + Worker-Neustart |
| `GET` | `/api/wsc-frankenphp/status` | Letzten Status abrufen |

---

## Build — Admin-Assets

Das Plugin enthält ein **vorkompiliertes Vite-Bundle** unter `src/Resources/public/administration/`. Dieses wird bei `assets:install` (z. B. durch Plugin-Aktivierung) automatisch bereitgestellt — kein Node.js auf dem Server erforderlich.

Das Bundle muss **nur neu gebaut werden**, wenn Änderungen an den Admin-Quelldateien (`src/Resources/app/administration/src/`) gemacht werden:

```bash
# Im Shopware-Root-Verzeichnis ausführen:
./bin/build-administration.sh

# Gebautes Bundle ins Plugin-Source-Verzeichnis kopieren:
cp public/bundles/wscswpluginfrankenphputils/administration/assets/*.js \
   custom/plugins/WSC_SWPlugin_FrankenPHPUtils/src/Resources/public/administration/assets/

# Manifest-Dateien ebenfalls kopieren:
cp -r public/bundles/wscswpluginfrankenphputils/administration/.vite \
   custom/plugins/WSC_SWPlugin_FrankenPHPUtils/src/Resources/public/administration/
```

---

## Bekannte Probleme / TODO

### `bin/console cache:clear` löst keinen Worker-Neustart aus

**Status:** Offen — muss noch untersucht werden.

`bin/console cache:clear` feuert unter bestimmten Bedingungen kein `ConsoleTerminateEvent` mit dem richtigen Befehlsnamen. Als Workaround greift der Subscriber bereits auf `cache:warmup` zurück (das Shopware intern nach `cache:clear` ausführt), aber das Verhalten ist nicht in allen Setups konsistent.

**Workaround:** Nach einem manuellen `cache:clear` den Worker-Neustart über den Admin-Button oder direkt über die Caddy Admin API auslösen:
```bash
curl -X POST http://localhost:2019/frankenphp/workers/restart
```

---

## Lizenz

MIT — © Christian Säum, [Web-SEO-Consulting.eu](https://www.Web-SEO-Consulting.eu)
