---
name: bootstrap-6-panel-done
description: Finished steps of the Bootstrap 6 panel plan with their PRs and topic files; the open steps are in bootstrap-6-panel-steps.md
---

## Which steps are done?
- B0a References: the four skills, `ui/bootstrap-6.md`, the plan files. Done (#1643).
- B0b `assets/lib/bootstrap-6` holds the npm 6.0.0-alpha.1 dist (CSS, bundle and standalone JS, maps; `document.txt` names version and hashes). Done (#1654).
- B1a Tools: `tools/bs6_rules.php`, `tools/bs6_migrate.php`, `tools/check_bs6.php` with `tests/bs6_migrate_test.php` (`ui/bootstrap-6-codemod.md`). Done (#1670).
- B1b Screenshots: `tools/screenshots/shoot.js` with `seed.php` and `screens.json` on a `tools/setup_sandbox.sh` instance, images on a private page (`ui/panel-screenshots.md`). Done (#1690).
- B2a The `pgUI` adapter (`assets/js/pg_ui.js`, `ui/pg-ui-adapter.md`) with `window.pgBootstrapReady`, loaded after Bootstrap's bundle. Done (#1677).
- B3 Token layer: panel CSS reads `--pg-*` tokens, mapped to v5 variables in one block (`ui/panel-css-tokens.md`). B3a the block, the v5 `-rgb` uses and the accent role variables (#1694); B3b the neutral variables in backend.src.css (#1711); B3c the other panel stylesheets and inline style blocks (#1723).
- B5 Server: `validate_time()` and `prepare_form_data_for_input()` accept 24-hour time, with tests; a JS port of `validate_password_strength()` tested against the PHP cases through node. Done (#1698): `ui/form-time-24-hour.md`, `ui/password-strength-js.md`.

## When does a step move here?
- When its last part is done: its line moves from `bootstrap-6-panel-steps.md` with its PR numbers. A step done in parts (B2b, B3) stays there until then. Keeps the steps file under 4 KB, which parallel step PRs all edit.

Related: docs/memory/plans/bootstrap-6-panel-steps.md, docs/memory/plans/bootstrap-6-panel.md
