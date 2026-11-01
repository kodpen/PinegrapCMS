---
name: kodpen-vendor-api-client-steps
description: Steps P1-P8 of plans/kodpen-vendor-api-client.md: changes, files, tests, memory, size, order; claim as [plan:kodpen-vendor-api-client] P<n>
---

## Which steps are there?
- S under 400, M about 1,000 product lines; lines of 2026-10-29. Every step: v1 code only behind the switch, api2 as today; tests red first; PHP 7.1 syntax, optional APIs behind `function_exists()`; `tr_add`; memory (`platform/` unless named) in the PR; no changelog line before P8. Done: `P<n> done (#n): <topic file>`.

- P1 done (#3203): this plan, with the vendor's three vector files byte for byte in `tests/fixtures/vendor_api/`.

- P2 (S, `.github/`, owner's machine) Release packages: `release-packages.yml` (`-design-update.md`); `docker-publish.yml` skips pre-releases. Test: actionlint; a local `v2026.4.10` build equal to the vendor's. Memory: `release/` new `release-packages.md` (tool sha256), `release-status.md`, `version-numbering.md`; `docker-image-publishing.md`; `workflow/ci-workflows.md`.

- P3 done (#3255): `platform/vendor-api-client.md` (the request, options, problem reader, canonical name and `pg_vendor_api_site_local()` for P4's warning); `curl-run-wrapper.md` (`content_type`, `cap`, `sink`).

- P4 done (#4381): `platform/license-check.md`.

- P5a done (#3282): `platform/vendor-api-update.md` (read, offer, pin in `software.update` with `package_file`, flag, texts; the installed build is still '').

- P5b done (#3632): `platform/vendor-api-update-download.md`.

- P5c (S, the owner's public keys) Keys in `pg_vendor_release_keys()`. Test: two P-256 keys, two lower-case ids. Memory: their fingerprints in `vendor-api-update.md`. After P5b.

- P6 (S) Done (#3755). Beta builds. Build file, Q1, re-run, `build` in backup and rollback, integrity tag (`update.php`, `software.php`, `system_status.php`). Tests: `update_build_test.php`, `update_backup_test.php`. Memory: new `update-beta-builds.md`, `update-backup-rollback.md`, `update-channel-stable-beta.md`, `core/file-integrity-github-reference.md`. After P5b, Q1.

- P7 done (#4395): `ui/dashboard-widget-files.md`, `platform/vendor-api-client.md`. Widget 14 a stub, registry entry, Subscription ID field and save line out; `API=` out of the three api2 calls (api2 answers without it); test: no `api2?API=` in `pinegrap/`.

- P8 (one line, owner's machine) Switch on, with its pin test, at the owner's word once the plan's gate holds. PR body: `[NEW] Added signed software updates and a new license check that shows the license type and its site rights.` Memory: `vendor-api-client.md`; the plan trimmed to the api2 removal.
