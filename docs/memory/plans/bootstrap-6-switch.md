---
name: bootstrap-6-switch
description: Step B8 of plans/bootstrap-6-panel.md (claim #1866): building a rehearsal, the hand-fix list, where the next rehearsal starts, the owner's check, the merge and the memory rewritten with it
---

## How is the switch rehearsed?
- Daily for a few days: rebuild `feature/bootstrap-6` (#1866, a draft until the owner says go) on the latest `development` and comment the result there. Its tool commits come first, one generated commit last: drop that commit (`git reset --hard HEAD~1`), `git rebase origin/development`, then `php tools/bs6_migrate.php --all --write`, `--switch --write`, `php tools/build_js.php`, every CI check, commit, `git push --force-with-lease`.
- A scriptable hand fix is code in `tools/bs6_switch.php` (`--switch`, tests `tests/bs6_switch_test.php`), never a patch file. Made: head flip with the bridge `assets/js/pg_bs6_bridge.js` (sets `window.bootstrap` without Modal/Dropdown/Offcanvas aliases, `dialog-open` follows open modal dialogs on every `close`, fires `pg:bootstrap-ready`), own heads on the v6 sheet, panel sheets and listed screens' `<style>` blocks into `@layer custom`, jQuery UI/DataTables/select2 imported into a `vendor` layer below it (unlayered they would beat the panel's overrides), navbar z-index 1030 unlayered, the settings drawer's `modal-open` repair dropped.

## What is still on the hand-fix list (rehearsal 1, 2026-10-26)?
- Codemod reports in panel files: checkbox/radio/switch 2,672, modal 583, dropdown 317, link colour helpers 274, classes inside code 230, light/dark 119, offcanvas 83. Checks and radios are mostly class mapping by the tag's `type` (`form-check` to `form-field`, input `check`/`radio`, label class dropped); switches and dialogs change shape.
- Tests pinned to v5 panel markup: ai_fields, ai_write, catalog_bin, erp_edoc_service, parasut_order_gates, parasut_order_invoice, payment_drivers, signin, widget_fill (editor chrome).
- Own media queries at 992/1200 px (18) follow v6 or stay pinned, one decision. Loading curtain and tour bubbles over an open dialog. `CUSTOM_CSS` stays unlayered (the site's own). `d-none` on `.pg-sm-help` and `#sd-publish-page`. Inline `<style>` of panel files not in `bs6_switch_style_files()`. CSS selectors on renamed classes (about 240).

## Where does the next rehearsal start?
- Rebuild as above, then script the check/radio mapping (bs6_rules.php) and the CSS selectors on renamed classes; turn the pinned tests into `--switch` work or fix them per rehearsal.

## What does the owner check?
- B1b screenshots of the liked screens, the secure screens and the visual editor (3 widths), before and after, on one private page the PR links. Then the branch runs on dev.pinegrap.com (a linked session checks it out) and the owner says go.

## What happens at and after the merge?
- In the same PR: CLAUDE.md's "Bootstrap 5 + jQuery", the ui and designer key rules, `php tools/check_bs6.php --strict` in php-checks.yml, CLAUDE.md's check list and pre_push.sh; the body names the changelog's `[NEW] Added …` line; new `lang()` keys go in with `php tools/tr_add.php`. The merging session merges it; tag the merge `bs6-switch`.
- A PR whose merge-base is older than the tag: merge `development`, run `php tools/bs6_migrate.php --write --diff=origin/development` (only its own added lines: a second run corrupts converted sizes), then `check_bs6.php`. CI fails such a PR until then. Revert only during the freeze window; after it, fix forward.

## Which memory files are rewritten with it?
- ui: panel-layout-pitfalls, step-rail, user-permission-rows, settings-modal-card-dialogs, settings-modal-navigation, settings-modal-layout, settings-card-chrome, command-palette, own-menu-dropdown-keydown, account-menu, panel-hint-tooltip-or-popover, code-editor-escape-in-dialogs, main-menu-states, loading-curtain, list-and-table-screens, edit-screen-wide-layout, admin-screen-toolbar, pg-ui-adapter (the bridge); designer: designer-settings-modal-sizing; workflow: project-overview.
