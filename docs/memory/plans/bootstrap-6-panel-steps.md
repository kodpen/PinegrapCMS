---
name: bootstrap-6-panel-steps
description: Steps B0-B12 of plans/bootstrap-6-panel.md with dependencies and status: B0a done (#1643), B0b done (#1654), B1-B12 open; claim as [plan:bootstrap-6-panel] B<n>
---

## Which steps run before the switch (on 5.3.8, nothing visible changes)?
- B0a References: the four skills, `ui/bootstrap-6.md`, the plan files. Done (#1643).
- B0b `assets/lib/bootstrap-6` holds the npm 6.0.0-alpha.1 dist (CSS, bundle and standalone JS, maps; `document.txt` names version and hashes). Done (#1654).
- B1 Tools. `tools/bs6_migrate.php`: the migration skill's rules as pure functions with `tests/bs6_migrate_test.php`; `--check`, `--files`, and `--diff <base>` converting only added lines; a second run must change nothing. `tools/check_bs6.php`: the skill's step 6 list plus `bootstrap.X` calls, jQuery `.modal()/.tooltip()/.popover()` and `*.bs.*` listeners outside `pgUI`; in php-checks.yml, CLAUDE.md and pre_push.sh, exit 0 until B8. The panel/site map, by function for shared files. A screenshot script (sample records, light/dark, wide/phone): images never committed, shown on a private page the PR links.
- B2 `pgUI` adapter and `window.pgBootstrapReady`: dialog (with `build()` for JS-built dialogs), drawer, menu, tooltip, popover, toast, collapse, tab, `overlayHost()`, and `pgUI.on()` listening to both event names. Move calls and listeners onto it, fix the 3 parse-time scripts (welcome.php, api_settings.php, erp_lists.js), read open state with `isOpen()`, add `preventDefault()` to the settings drawer's Escape handler. Leave style_designer.js to B7.
- B3 Token layer: panel CSS reads `--pg-*` tokens, mapped to v5 variables in one block.
- B4 Collisions: drop `class="menu"` from `#menu`, rename `<span class="btn-text">` and the own `.submenu`; `data-bs-display="static"` on the account and notification toggles.
- B5 Server: `validate_time()` and `prepare_form_data_for_input()` accept 24-hour time, with tests; a JS port of `validate_password_strength()` tested against the PHP cases through node.
- B6 Wrappers with a switchable engine (v5 engine now): `pgChips`, `pgCombobox`, `pgDatePicker`, `pgDateTimeField`; call sites move to `data-pg-*` hooks.
- B7 Visual editor stage 0: `/* @pg-site-output */` on the 54 site-output members of style_designer.js with a CI test, the chrome look pinned in style_designer.css, editor calls through `pgUI`.

## What happens at and after the switch?
- B8 Switch: `bootstrap-6-switch.md`.
- B9 Component swaps on v6, one claim each (`bootstrap-6-components.md`): B9a Chips, B9b Combobox, B9c Datepicker and time, B9d OTP, B9e Range, B9f form-adorn, B9g Stepper, B9h hover-lift, B9i Strength, B9j Avatar, B9k research proposals.
- B10 Token tuning, B11 redesign, B12 jQuery UI out of the panel: `bootstrap-6-redesign.md`.

## Where do I start?
- After the claim check, take the lowest open step whose dependencies are done, one at a time unless the owner assigns parallel sessions; a step over about 1,000 product lines goes in several PRs, one after the other (`workflow/working-mode.md`). B1-B5 are independent; B6 needs B2; B7 needs B1 and B2; B8 needs B1-B7; B9 needs B8 (B9c also B5); B10 needs B9; B11 needs B10; B12 needs B9c.
- Pre-switch PRs show their screens unchanged on the B1 screenshots; later PRs show before and after. Mark a step done here with its PR numbers.
