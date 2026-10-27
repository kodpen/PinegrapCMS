---
name: bootstrap-6-switch
description: Step B8 of plans/bootstrap-6-panel.md (claim #1866): building a rehearsal, the hand-fix list, where the next rehearsal starts, the owner's check, the merge and the memory rewritten with it
---

## How is the switch rehearsed?
- Rebuild `feature/bootstrap-6` (#1866, a draft until the owner says go) on the latest `development`, comment the result there. Tool commits first, the generated commit last, pushed only when every check passes: drop it (`git rebase --onto <gen>~1 <gen>`), `git rebase origin/development`, `php tools/bs6_migrate.php --all --write`, `--switch --write`, `php tools/build_js.php`, every CI check, commit; then `git merge -s ours origin/feature/bootstrap-6` (keeps the old history, the tree stays the rebuild) and a plain `git push`. Never commit the sandbox's `data/config.php`.
- Scriptable hand fixes are code in `tools/bs6_switch.php`, `bs6_switch_shell.php`, `bs6_switch_tokens.php`, `bs6_switch_stems.php` (tests `tests/bs6_switch*_test.php`); a fix found by its exact text goes into `tools/bs6_switch_texts.txt`. Never a patch file. What each fix does: the file headers and `ui/bootstrap-6-codemod.md`.

## What is still on the hand-fix list (rehearsal 7, 2026-10-27)?
- `check_bs6.php`: 13 panel findings, each reviewed and left (theme or `start`/`end` stems, a `removeClass` naming two themes, `page_designer.js`'s site preview, `.was-validated`, `.btn-outline-light/-dark` selectors no element carries). Shared: `get_login()` and liveform's own fields (site output).
- Look: no 1400 px breakpoint (`xxl` to `2xl`, 1536: owner question); the official `fs-1` to `fs-4xl` makes the search and new-design dialogs' empty-state icon 8 px larger.
- `sweep.js` on all 262 panel screens: only `dropdown-toggle` (DataTables Buttons adds it to the column button, which also carries `pg-menu-caret`) and `collapsed` (a state class) are left undrawn; both harmless. The visual editor's canvas code stays v5 on purpose.

## Where does the next rehearsal start?
- Rebuild as above; run `sweep.js`, `open_toggles.js` (1440, `--width=834`, `--width=390`), `open_dialogs.js` and `settings_panes.js` on both servers (`ui/panel-screenshots.md`) and compare.
- Not reachable in the sandbox: the image editor (licensed plugin), marketplace pickers (no marketplace rows), the barcode list (barcodes off). Ready for the owner's check: dev.pinegrap.com, then the owner says go.
- Open owner questions in #1866's body: own media queries on 1024/1280 (keep until answered) and the lost 1400 px breakpoint.

## What does the owner check?
- The before/after page of each round (https://claude.ai/artifact/Xdh6cnCar778Z77jphtzS4): liked, secure and visual editor screens at 1440, 834 and 390 px, light and dark (`ui/panel-screenshots.md`). Then the branch runs on dev.pinegrap.com and the owner says go.

## What happens at and after the merge?
- In the same PR: CLAUDE.md's "Bootstrap 5 + jQuery", the ui and designer key rules, `php tools/check_bs6.php --strict` in php-checks.yml, CLAUDE.md's check list and pre_push.sh; the body names the `[NEW]` line; new `lang()` keys go in with `php tools/tr_add.php`. The merging session merges it; tag the merge `bs6-switch`.
- A PR whose merge-base is older than the tag: merge `development`, run `php tools/bs6_migrate.php --write --diff=origin/development` (only its own added lines: a second run corrupts converted sizes), then `check_bs6.php`. CI fails such a PR until then. Revert only during the freeze window; after it, fix forward.

## Which memory files are rewritten with it?
- ui: panel-layout-pitfalls, step-rail, user-permission-rows, settings-modal-card-dialogs, settings-modal-navigation, settings-modal-layout, settings-card-chrome, command-palette, own-menu-dropdown-keydown, account-menu, panel-hint-tooltip-or-popover, code-editor-escape-in-dialogs, main-menu-states, loading-curtain, list-and-table-screens, edit-screen-wide-layout, admin-screen-toolbar, pg-ui-adapter (the bridge); designer: designer-settings-modal-sizing; workflow: project-overview.
