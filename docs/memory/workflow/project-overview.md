---
name: project-overview
description: What Pinegrap is, supported PHP range, stack, repo layout (pinegrap/ root), dev-only folders, files never in any branch
---

## What is Pinegrap?
- PHP monolithic CMS, developed since 2017 by Erdal Güral (Kodpen); a LiveSite fork with the 2019 LiveSite update merged in. jQuery, Bootstrap 6 (6.0.0-alpha.1, `assets/lib/bootstrap-6`) in the panel and Bootstrap 5.3.8 on sites; MySQL/MariaDB through `mysqli`.
- Goal: international-standard CMS with key integrations built in, near one-click site building.

## Which PHP versions?
- Minimum PHP 7.1 (since 2026.4.6), up to 8.5. Setup and System Status flag older PHP.
- Raising the minimum is always the owner's decision; a library choice may never raise it as a side effect (the dompdf chain behind the ERP invoice PDF needs 7.1; on older PHP only the PDF button would fail).

## Where is the code?
- This repo holds the product only, under `pinegrap/`. Paths in memory files are relative to that root: `includes/fn/core.php` means `pinegrap/includes/fn/core.php`.
- Strings: `includes/local/tr/` (the Turkish wording as sorted shards, `core/lang-files.md`) and `includes/local/en.json`. Config: `data/config.php` (runtime constant `CONFIG_FILE_PATH`); the real config is never committed, the repo copy is an empty template, sample values are in `data/config(default).php`.
- Third-party libraries are embedded under `includes/`; Composer is not used.

## What exists only on the development branch?
- `CLAUDE.md`, `.claude/`, `docs/`, `tools/`, `tests/` are tracked on `development` only; they never go to `main` or a release.
- `dev/` and the internal notes named in `.gitignore` (e.g. `docs/_software_create_hash.php`, `docs/_plan_barindirma.md`) are in no branch; they live only on the owner's machine. In a cloned session they are missing: do not treat that as a problem and do not guess their content; ask for the part you need.

Related: docs/memory/release/release-status.md
