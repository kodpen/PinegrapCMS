---
name: bootstrap-6-sibling-toggles-fullscreen
description: v5 toggle buttons (input.btn-check before its label.btn) and full-screen dialogs on Bootstrap 6: the shell rules that give the label its checked look and take a full-screen dialog's corners away
---

## Why did checked and unchecked toggle buttons look the same?
- v5 markup puts a hidden `input.btn-check` before its `label.btn`. v6 styles only a `label.btn-check` that wraps its input (`:has(input:checked)`), and the shell rule only hid the input. Symptom: Workspace working days, the repeat editor's weekdays, the product builder's option chips, add order / ERP quick account Person-Company and the designer's new-design Look picker showed no state.
- The shell rules beside `input.btn-check` in `backend.src.css` (mirrored in `bs6_switch_shell_rules()`, `tools/bs6_switch.php`) give `input.btn-check:checked + .btn` the `--bs-btn-active-*` colours, `:focus-visible + .btn` the panel ring (`--pg-focus-ring-*`), `:disabled + .btn` v6's disabled look. The markup stays v5; a new toggle may use either form.

## Why did the first toggle of a group lose its start corners?
- The hidden input matches v6's `.btn-group > [class*=btn-]`, so the first label is not `:first-child` and gets the inner-button radius 0 and -1px margin. `.btn-group > input.btn-check:first-child + .btn` puts margin 0 and `--bs-btn-border-radius` back.

## Why did full-screen dialogs keep rounded corners?
- The panel's `.dialog`, `.dialog-header` and `.dialog-footer` radius rules sit in `@layer custom`, above v6's components layer where `.dialog-fullscreen` and `.<bp>-down:dialog-fullscreen` reset the radius; the backdrop showed in the corners on phones, and the `-down` variants (v6 does not zero their border) kept a 1px border.
- Beside the dialog shell block: `.dialog.dialog-fullscreen` and its header/footer radius 0; `sm-`, `md-`, `lg-down:dialog-fullscreen` in `max-width` 575.98/767.98/1023.98px queries with radius and border width 0. `.pg-settings-modal`'s own rules are untouched.

## How was it checked?
- Computed style in headless Chromium on a page with the v6 dist and `backend.src.css`: checked label filled, first label radius 8px and margin 0, disabled 0.65, `.ws-ctx` opacity 1, full-screen and `md-down` at 600px radius 0 and border 0. Pinned as strings by `tests/bs6_workspace_overlays_test.php`; a unit test cannot compute cascade layers.

Related: docs/memory/ui/bootstrap-6.md, docs/memory/ui/bootstrap-6-codemod.md, docs/memory/ui/bootstrap-6-runtime-markup.md
