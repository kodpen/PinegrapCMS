---
name: kodpen-vendor-api-client-steps
description: Steps P1-P8 of plans/kodpen-vendor-api-client.md: changes, files, tests, memory, size, order; claim as [plan:kodpen-vendor-api-client] P<n>
---

## Which steps are there?
- S under 400, M about 1,000 product lines; lines of 2026-10-29. Every step: v1 code only behind the switch, api2 as today; tests red first; PHP 7.1 syntax, optional APIs behind `function_exists()`; `tr_add`; memory (`platform/` unless named) in the PR; no changelog line before P8. Done: `P<n> done (#n): <topic file>`.

- P1 done (#3203): this plan, with the vendor's three vector files byte for byte in `tests/fixtures/vendor_api/`.

- P2 (S, `.github/`, owner's machine) Release packages: `release-packages.yml` (`-design-update.md`); `docker-publish.yml` skips pre-releases. Test: actionlint; a local `v2026.4.10` build equal to the vendor's. Memory: `release/` new `release-packages.md` (tool sha256), `release-status.md`, `version-numbering.md`; `docker-image-publishing.md`; `workflow/ci-workflows.md`.

- P3 done (#3255): `platform/vendor-api-client.md` (the request, options, problem reader, canonical name and `pg_vendor_api_site_local()` for P4's warning); `curl-run-wrapper.md` (`content_type`, `cap`, `sink`).

- P4 (M) Licence. `output.php`: pure `pg_license_map_v1()`, the v1 call in `license_check()` (4720-5163), card. Settings (`includes/settings/general*.php`): key field (`h()`, `backend.src.js` masks, `build_js.php`), save, local-name warning. Tests: `license_v1_test.php` (`licence_mapping.json`), `output_test.php`. Memory: `license-check.md`. After P3.

- P5a done (#3282): `platform/vendor-api-update.md` (read, offer, pin in `software.update` with `package_file`, flag, texts; the installed build is still '').

- P5b (M) Download checks. `download`, `backup`, `replace` (`software.php` 486-713); pure `pg_vendor_update_steps()` (`update.php`); empty `pg_vendor_release_keys()`. Tests: `vendor_update_steps_test.php` (`update_steps.json`, a test-time pair from `pg_openssl_key_new()`, a built ZIP, a file swapped before replace). Memory: `vendor-api-update.md`, `update-package-extraction.md`. After P5a.

- P5c (S, the owner's public keys) Keys in `pg_vendor_release_keys()`. Test: two P-256 keys, two lower-case ids. Memory: their fingerprints in `vendor-api-update.md`. After P5b.

- P6 (S) Beta builds. Build file, Q1, re-run, `build` in backup and rollback, integrity tag (`update.php`, `software.php`, `system_status.php`). Tests: `update_build_test.php`, `update_backup_test.php`. Memory: new `update-beta-builds.md`, `update-backup-rollback.md`, `update-channel-stable-beta.md`, `core/file-integrity-github-reference.md`. After P5b, Q1.

- P7 (S) Widget 14 and the API key out. `widget_14.php` a stub like `widget_6.php`; registry entry out, its comment after the architect's entry (`welcome.php`); `API=` out of `update.php`, `software_update_check.php`, `output.php`; Subscription ID field and save line out; Settings note. Tests: the three that read its source (`dashboard_test.php`, dark theme, pill contrast) on the stub; no `api2?API=` in `pinegrap/`. Memory: `ui/` `dashboard-widget-files.md`, `dark-theme-fixed-fills.md`, `dashboard-pill-contrast.md`; `curl-run-callers.md`. After P4, P6.

- P8 (one line, owner's machine) Switch on, with its pin test, at the owner's word once the plan's gate holds. PR body: `[NEW] Added signed software updates and a new license check that shows the license type and its site rights.` Memory: `vendor-api-client.md`; the plan trimmed to the api2 removal.
