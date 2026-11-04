---
name: kodpen-vendor-api-client-steps
description: Steps P1-P8 of plans/kodpen-vendor-api-client.md: changes, files, tests, memory, size, order; claim as [plan:kodpen-vendor-api-client] P<n>
---

## Which steps are there?
- S under 400, M about 1,000 product lines; lines of 2026-10-29. Every step: v1 code only behind the switch, api2 as today; tests red first; PHP 7.1 syntax, optional APIs behind `function_exists()`; `tr_add`; memory (`platform/` unless named) in the PR; no changelog line before P8. Done: `P<n> done (#n): <topic file>`.

- Done: P1 #3203, P3 #3255, P4 #4381, P5a #3282, P5b #3632, P6 #3755, P7 #4395.

- P2 (S, `.github/`, owner's machine) Release packages: `release-packages.yml` (`-design-update.md`); `docker-publish.yml` skips pre-releases. Test: actionlint; a local `v2026.4.10` build equal to the vendor's. Memory: `release/` new `release-packages.md` (tool sha256), `release-status.md`, `version-numbering.md`; `docker-image-publishing.md`; `workflow/ci-workflows.md`.

- P5c (S, the owner's public keys) Keys in `pg_vendor_release_keys()`. Test: two P-256 keys, two lower-case ids. Memory: their fingerprints in `vendor-api-update.md`. After P5b.

- P8 (one line, owner's machine) Switch on, with its pin test, at the owner's word once the plan's gate holds. PR body: `[NEW] Added signed software updates and a new license check that shows the license type and its site rights.` Memory: `vendor-api-client.md`; the plan trimmed to the api2 removal.
