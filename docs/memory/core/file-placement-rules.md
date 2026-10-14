---
name: file-placement-rules
description: Where new PHP files go (root only for URL-called files, else includes/), include gate constants, root files that stay, integrity-hash scope.
---

## Where does a new file go?
Only URL-called files stay at the root. PHP files without UI that are not addressed directly (functions/helpers) go under `pinegrap/includes/`:
| What | Where |
|---|---|
| Files forming a subsystem together | `includes/<subsystem>/` e.g. `includes/api/`, `includes/migrations/` |
| A separate layer inside a subsystem | `includes/<subsystem>/<layer>/` e.g. `includes/api/resources/` |
| Standalone helper callable from different places | directly in `includes/` e.g. `includes/authentication.php`, `includes/db_guard.php` |
| Third-party library | `includes/<library>/` e.g. `includes/phpmailer/`, `includes/dompdf/` |

## What gate does every include file start with?
- Every include file starts with a gate; if the entry point's constant is undefined the file runs nothing:
```php
if (!defined('PG_API_ENTRY')) {
	exit;
}
```
- A file also called from the panel accepts both: `if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) { exit; }`.
- The `INSTALL_OR_UPDATE` gate in migration files is the same pattern.

## What about the existing root include files?
- ~85 include files at the root do not follow this rule (`get_*.php` content generators, `*_f.php` screen helpers, `seo*.php`, `shipping.php`, `chat.php`, third-party `pclzip.lib.php` / `excel_reader2.php`, etc.). They STAY there; moving them was shelved for security reasons: the file-integrity system watches them. The rule applies to NEW code only.

## How does placement interact with file integrity? (important)
- A new subsystem folder under `includes/` widens integrity coverage automatically: the generator walks the whole `includes/` tree.
- A new folder OUTSIDE `includes/` is NEVER checked unless added to the `hashed_subdirectories()` array in `_software_create_hash.php`.
- `data/` (config, cache, backup) and `install/` (operators delete it after setup) are deliberately out of scope.
- For the same reason an embedded library cannot write into `includes/` at runtime: dompdf's font cache and temp files live in `data/cache/dompdf/`; a library writing into `includes/` looks "tampered" every hour.

Related: docs/memory/core/file-integrity-check.md
