---
name: file-placement-rules
description: Where new PHP files go (root only for URL-called files, else includes/), the gate constant each include starts with, root files that stay, file-integrity scope.
---

## Where does a new file go?
Only URL-called files stay at the root. PHP files without UI that are not addressed directly go under `pinegrap/includes/`:
| What | Where |
|---|---|
| Files forming a subsystem together | `includes/<subsystem>/` e.g. `includes/api/`, `includes/migrations/` |
| A separate layer inside a subsystem | `includes/<subsystem>/<layer>/` e.g. `includes/api/resources/` |
| Standalone helper callable from different places | directly in `includes/` e.g. `includes/authentication.php`, `includes/db_guard.php` |
| Third-party library | `includes/<library>/` e.g. `includes/phpmailer/`, `includes/dompdf/` |
| A new function | its topic's `includes/fn/<module>.php` (`functions-php-split-rules.md`) |
| A panel AJAX action / a dashboard widget | `includes/panel/<group>.php` (`panel-action-table.md`) / `includes/dashboard/widgets/widget_<id>.php` (`ui/dashboard-widget-files.md`) |

## What gate does every include file start with?
- The constant of the entry point that loads it; when it is not defined the file exits. A file without a gate runs on its own when someone calls its URL.
```php
if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}
```
| Loaded by | Gate |
|---|---|
| `functions.php` (every `includes/fn/` module, most helpers) | `PG_FUNCTIONS_DIR` |
| the external API (`integration.php`, `oauth.php`, `mcp.php`) | `PG_API_ENTRY`; `if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL'))` when its panel screens (`api_settings.php`, `api_docs.php`, `connected_apps.php`) load it too |
| the upgrade and installer | `INSTALL_OR_UPDATE` (migration files) |
| ERP (`includes/erp/bootstrap.php`) | `PG_ERP_ENTRY` |
| settings cards (`settings_pane.php`, `includes/settings/screen.php`) | `PG_SETTINGS_ENTRY` |
| `api.php` actions / dashboard widgets | `PG_PANEL_ACTIONS` / `PG_DASHBOARD_WIDGETS` |
| `init.php` | `PG_INIT_LOADED` |

## What about the existing root include files?
- ~85 include files at the root do not follow this rule (`get_*.php` content generators, `*_f.php` screen helpers, `seo*.php`, `shipping.php`, `chat.php`, third-party `pclzip.lib.php` / `excel_reader2.php`, etc.). They STAY there: the file-integrity check watches them and moving them was shelved for security reasons. The rule applies to NEW code only.

## How does placement interact with the file-integrity check?
- The check covers the files directly in the software directory plus the folders `pg_integrity_scope_directories()` returns (`includes/fn/system_status.php`; today only `includes`). The dev machine's reference generator reads the same function; never write the scope list a second time.
- A new subsystem under `includes/` is covered automatically. A new folder OUTSIDE `includes/` is never checked unless it is added to `pg_integrity_scope_directories()`.
- `data/` (config, caches, backups change by design) and `install/` (operators delete it) stay out on purpose.
- An embedded library cannot write into `includes/` at runtime (it would read as tampered every hour): its caches and temp files go to `data/cache/<library>/` (dompdf: `data/cache/dompdf/`).

Related: docs/memory/core/file-integrity-check.md
