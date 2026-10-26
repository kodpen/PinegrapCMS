---
name: bootstrap-6-switch
description: Step B8 of plans/bootstrap-6-panel.md (claim #1866): building a rehearsal, the hand-fix list, where the next rehearsal starts, the owner's check, the merge and the memory rewritten with it
---

## How is the switch rehearsed?
- Rebuild `feature/bootstrap-6` (#1866, a draft until the owner says go) on the latest `development`, comment the result there. Tool commits first, the generated commit last, pushed only when every check passes: drop it (`git reset --hard HEAD~1`), `git rebase origin/development`, `php tools/bs6_migrate.php --all --write`, `--switch --write`, `php tools/build_js.php`, every CI check, commit, `git push --force-with-lease`.
- Hand fixes a script can make are code in `tools/bs6_switch.php` (`--switch`, tests `tests/bs6_switch_test.php`), never a patch file: head flip and bridge `assets/js/pg_bs6_bridge.js` (no v5 aliases, `dialog-open` follows open modal dialogs, `pg:bootstrap-ready`), own heads, panel sheets and listed `<style>` blocks into `@layer custom`, vendor sheets in a `vendor` layer below it, navbar z-index, the `modal-open` repair dropped, check/radio/switch markup (`.form-field`, `.check`/`.radio` by type, `.switch` box, liveform calls too), selectors on renamed classes, unit tests pinned to v5 classes (`bs6_switch_test_pins()`).

## What is still on the hand-fix list (rehearsal 2, 2026-10-26)?
- Pushed as the generated commit; every check passes. `check_bs6.php` in panel files: modal 512, dropdown 314, link colour helpers 274, classes inside code 219, CSS selectors 114 (modal/menu/drawer ones), light/dark 112, offcanvas 83, class built in code 54, print utilities 32, others under 25.
- `check_bs6.php` still counts v6 sizes (`p-5`) as v5 classes to rewrite (4,637): it must read a converted tree before `--strict` goes into CI.
- Media queries at 992/1200 px (18), one decision. Loading curtain and tour bubbles over an open dialog. `CUSTOM_CSS` stays unlayered. `d-none` on `.pg-sm-help`, `#sd-publish-page`. Empty `class=""` left by dropped check classes.

## Where does the next rehearsal start?
- Rebuild as above; then `check_bs6.php` on a converted tree, then the dialog shell (modal markup to `<dialog class="dialog">` where `pgUI.dialog.build()`'s shape fits).

## What does the owner check?
- B1b screenshots of the liked screens, the secure screens and the visual editor (3 widths), before and after, on one private page the PR links. Then the branch runs on dev.pinegrap.com (a linked session checks it out) and the owner says go.

## What happens at and after the merge?
- In the same PR: CLAUDE.md's "Bootstrap 5 + jQuery", the ui and designer key rules, `php tools/check_bs6.php --strict` in php-checks.yml, CLAUDE.md's check list and pre_push.sh; the body names the changelog's `[NEW] Added …` line; new `lang()` keys go in with `php tools/tr_add.php`. The merging session merges it; tag the merge `bs6-switch`.
- A PR whose merge-base is older than the tag: merge `development`, run `php tools/bs6_migrate.php --write --diff=origin/development` (only its own added lines: a second run corrupts converted sizes), then `check_bs6.php`. CI fails such a PR until then. Revert only during the freeze window; after it, fix forward.

## Which memory files are rewritten with it?
- ui: panel-layout-pitfalls, step-rail, user-permission-rows, settings-modal-card-dialogs, settings-modal-navigation, settings-modal-layout, settings-card-chrome, command-palette, own-menu-dropdown-keydown, account-menu, panel-hint-tooltip-or-popover, code-editor-escape-in-dialogs, main-menu-states, loading-curtain, list-and-table-screens, edit-screen-wide-layout, admin-screen-toolbar, pg-ui-adapter (the bridge); designer: designer-settings-modal-sizing; workflow: project-overview.
