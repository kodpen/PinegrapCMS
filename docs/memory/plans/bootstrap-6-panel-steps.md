---
name: bootstrap-6-panel-steps
description: Steps B0-B12 of plans/bootstrap-6-panel.md with dependencies and status (each done step names its PR); claim as [plan:bootstrap-6-panel] B<n>
---

## Which steps run before the switch (on 5.3.8, nothing visible changes)?
- B0a References: the four skills, `ui/bootstrap-6.md`, the plan files. Done (#1643).
- B0b `assets/lib/bootstrap-6` holds the npm 6.0.0-alpha.1 dist (CSS, bundle and standalone JS, maps; `document.txt` names version and hashes). Done (#1654).
- B1a Tools: `tools/bs6_rules.php`, `tools/bs6_migrate.php`, `tools/check_bs6.php` with `tests/bs6_migrate_test.php` (`ui/bootstrap-6-codemod.md`). Done (#1670).
- B1b Screenshots: `tools/screenshots/shoot.js` with `seed.php` and `screens.json` on a `tools/setup_sandbox.sh` instance, images on a private page (`ui/panel-screenshots.md`). Done (#1690).
- B2a The `pgUI` adapter (`assets/js/pg_ui.js`, `ui/pg-ui-adapter.md`) with `window.pgBootstrapReady`, loaded after Bootstrap's bundle. Done (#1677).
- B2b and on: move the calls and listeners onto pgUI area by area (cold areas first; `check_bs6.php` counts what is left), fix the 3 parse-time scripts (welcome.php, api_settings.php, erp_lists.js), read open state with `isOpen()`, add `preventDefault()` to the settings drawer's Escape handler. Leave style_designer.js to B7.
  - Slice 1 (#1692): dashboard, offers, product builder, sale screen, ERP (`erp_lists.js`), translations, Cloudflare, form item view, AI marks, tour. Slice 2 (#1712): `api_settings.php`, code and image editor dialogs (calls only; their v5 markup waits), `image_editor_edit.php`, `designer_home.js`, `view_folders.php`.
  - Left: `workspace*.js`, `page_designer.js`, `seo.php`, `settings/appearance.php`, `backend.src.js`, the settings drawer's Escape.
- B3 Token layer: panel CSS reads `--pg-*` tokens, mapped to v5 variables in one block (`ui/panel-css-tokens.md`). B3a the block, the v5 `-rgb` uses and the accent role variables: done (#1694). B3b the neutral variables in backend.src.css. B3c the other panel stylesheets and inline style blocks.
- B4 Collisions: drop `class="menu"` from `#menu`, rename `<span class="btn-text">` and the own `.submenu`; `data-bs-display="static"` on the account and notification toggles.
- B5 Server: `validate_time()` and `prepare_form_data_for_input()` accept 24-hour time, with tests; a JS port of `validate_password_strength()` tested against the PHP cases through node. Done (#1698): `ui/form-time-24-hour.md`, `ui/password-strength-js.md`.
- B6 Wrappers with a switchable engine (v5 engine now): `pgChips`, `pgCombobox`, `pgDatePicker`, `pgDateTimeField`; call sites move to `data-pg-*` hooks.
- B7 Visual editor stage 0: `/* @pg-site-output */` on the 54 site-output members of style_designer.js with a CI test, the chrome look pinned in style_designer.css, editor calls through `pgUI`.

## What happens at and after the switch?
- B8 Switch: `bootstrap-6-switch.md`.
- B9 Component swaps on v6, one claim each (`bootstrap-6-components.md`): B9a Chips, B9b Combobox, B9c Datepicker and time, B9d OTP, B9e Range, B9f form-adorn, B9g Stepper, B9h hover-lift, B9i Strength, B9j Avatar, B9k research proposals.
- B10 Token tuning, B11 redesign, B12 jQuery UI out of the panel: `bootstrap-6-redesign.md`.

## Where do I start?
- After the claim check, take the lowest open step whose dependencies are done and that no open PR holds; independent steps run in parallel sessions, one draft PR each; a step over about 1,000 product lines goes in several PRs, one after the other (`workflow/working-mode.md`). B1-B5 are independent; B6 needs B2a; B7 needs B1a and B2a; B8 needs B1a-B7; B9 needs B8 (B9c also B5); B10 needs B9; B11 needs B10; B12 needs B9c.
- Pre-switch PRs show their screens unchanged on the B1b screenshots (`ui/panel-screenshots.md`); later PRs show before and after. Mark a step done here with its PR numbers.
