---
name: bootstrap-6-panel-steps
description: Steps B0-B12 of the Bootstrap 6 panel plan with dependencies and status; B0 in progress, the rest open; claim as [plan:bootstrap-6-panel-steps]
---

## Which steps run before the switch (on 5.3.8, nothing visible changes)?
- B0 References: the four skills, `ui/bootstrap-6.md`, these plan files. In progress.
- B1 Tools: `tools/bs6_migrate.php` (rules from the migration skill tables as pure functions with `tests/bs6_migrate_test.php`, `--check` and `--files`); `tools/check_bs6.php` (the skill's step 6 list, report-only until B8); the panel/site file map the codemod reads; a screenshot script on `tools/setup_sandbox.sh` and Playwright (light/dark, wide/phone) for the baseline.
- B2 `pgUI` adapter and a readiness promise (`window.pgBootstrapReady`): dialog, drawer, menu, tooltip, popover, toast, collapse, tab, and `pgUI.on()` listening to both event names. Move the 167 Bootstrap JS calls and 104 `*.bs.*` listeners onto it, fix the 3 scripts that touch `bootstrap` while parsing (welcome.php, api_settings.php, erp_lists.js), read open state with `isOpen()` instead of `.show`.
- B3 Token layer: panel CSS reads `--pg-*` tokens, mapped to v5 variables in one block (1,548 uses of variables v6 lacks, 79 `--bs-*-rgb`).
- B4 Collisions: drop `class="menu"` from `#menu`, rename `<span class="btn-text">` and the own `.submenu`; `data-bs-display="static"` on the account and notification toggles.
- B5 Server: `validate_time()` and `prepare_form_data_for_input()` accept 24-hour time (with tests) before any time input changes; a JS twin of `validate_password_strength()` with a test.
- B6 Wrappers with a switchable engine, v5 engine for now: `pgChips` (tagin), `pgCombobox` (select2, the native select stays the posted store), `pgDatePicker` and `pgDateTimeField`; call sites move to `data-pg-*` hooks.
- B7 Visual editor stage 0: `/* @pg-site-output */` markers on the 54 site-output members of style_designer.js with a CI test; the chrome look pinned in style_designer.css; editor calls through the adapter.

## What happens at and after the switch?
- B8 Switch: v6 CSS and the module bridge in `output_control_panel_header_includes()` and the 4 standalone screens; panel CSS into `@layer custom`; the codemod run; engines flipped; DataTables classes and dom string; select2 theme removed; a dialog `close` listener (Chromium double Escape leaves `dialog-open`); the browser notice; CI check on.
- B9 Finish the first-stage components on v6 (`bootstrap-6-components.md`).
- B10 Token tuning after the screenshot round. B11 Redesign: legacy form screens and tables. B12 jQuery UI out of the panel (sortable, legacy `.dialog()`).

## Where do I start?
- Run the claim check (CLAUDE.md) and take the lowest open step whose dependencies are done. B1, B2, B3, B4 and B5 can run in parallel. B6 needs B2, B7 needs B1, B8 needs B1-B7.
- Several PRs may serve one step. Mark the step done here with its PR numbers when its last PR goes up.
