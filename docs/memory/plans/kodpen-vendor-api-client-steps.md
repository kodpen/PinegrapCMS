---
name: kodpen-vendor-api-client-steps
description: Open steps of plans/kodpen-vendor-api-client.md: P2 and P5c before the 2026.11.0 release branch, P9 (api2 client removal) in the next open version; claim as [plan:kodpen-vendor-api-client] P<n>
---

## Which steps are there?
- S under 400, M about 1,000 product lines; lines of 2026-10-29. Every step: tests red first; PHP 7.1 syntax, optional APIs behind `function_exists()`; `tr_add`; memory (`platform/` unless named) in the PR. Done: `P<n> done (#n): <topic file>`.

- Done: P1 #3203, P3 #3255, P4 #4381, P5a #3282, P5b #3632, P6 #3755, P7 #4395, P8 #8653.
- P8's PR body names its changelog line for the merging session: `[NEW] Added signed software updates and a new license check that shows the license type and its site rights.`

- P2 (S, `.github/`, owner's machine) Release packages: `release-packages.yml` (`-design-update.md`); `docker-publish.yml` skips pre-releases. Test: actionlint; a local `v2026.4.10` build equal to the vendor's. Before the 2026.11.0 release branch.
  - Memory: `release/` new `release-packages.md` (tool sha256), `release-status.md` (the release-gate bullet), `version-numbering.md`; `docker-image-publishing.md`; `workflow/ci-workflows.md`; `vendor-api-update-download.md` ("Which keys?", the release-branch line).

- P5c (S, the owner's public keys) Keys in `pg_vendor_release_keys()`. Test: two P-256 keys, two lower-case ids. After P5b; before the 2026.11.0 release branch.
  - Memory: their fingerprints in `vendor-api-update.md`; `vendor-api-update-download.md` ("Which keys?"); `update-beta-builds.md` (the Tests line); `release/rerun-upgrade-steps.md` (the Repair card); `release/release-status.md` (the release-gate bullet); this plan's "What must land" section.

- P9 (M, the next open version, #3205) Remove the api2 client branches, unreached since P8. What goes:
  - `license_check()` (`includes/fn/output.php`): the api2 `else` branch (payload, `api2?REQUEST=license`, the `file_get_contents` fallback) and its answer decoding; the `$license_v1` reads.
  - `includes/fn/update.php`: the api2 tail of `pg_update_server_version()`; `pg_license_request_options()`, `pg_update_request_key()`, `pg_update_package_file()`, `pg_update_version_newer()` (api2 callers only); the server-version part of `pg_update_repair_decision()` (its checks-off text stays, `includes/panel/software.php` uses it on both paths).
  - `software_update_check.php`: the api2 branch with its `messages` / `users_messages_xref` feed (written, read nowhere). `includes/panel/software.php`: the api2 check, the fixed-ZIP download, the switch conditions in backup and replace. The api2 sentences of the `software_repair.php` header and of the comments of `pg_update_server_version()` and the check step.
  - The switch reads in `includes/settings/general.php` and `maintenance.php` become unconditional; the switched-off refusal in `pg_vendor_api_request()` goes.
- P9 keeps the switch line `define('PG_VENDOR_API_V1', true);` and its pin (the hosting platform reads the line, `platform/vendor-api-client.md`) and `download_assistant.php` untouched (#3205).
- P9 tests: `output_test.php` `test_output_license_check_v1_branch_behind_the_switch` (no `kodpen.com/api2` left in `pinegrap/`), `update_test.php` (check source order, check step source), `update_check_state_test.php` (one `pg_update_check_answered();`), `curl_run_test.php` `test_curl_run_license_request_options`, `panel_message_lang_test.php` (the api2 download message), `software_update_check_notification_test.php` (anchors on the api2 branch).
- P9 memory: `vendor-api-client.md`, `license-check.md`, `vendor-api-update.md`, `vendor-api-update-download.md`, `update-channel-stable-beta.md`, `update-check-errors.md`, `update-status-tile.md`, `release/rerun-upgrade-steps.md`; the plan is deleted once P2, P5c and P9 are done.
