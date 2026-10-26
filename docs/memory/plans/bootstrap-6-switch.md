---
name: bootstrap-6-switch
description: Step B8 of plans/bootstrap-6-panel.md (claim #1866): building a rehearsal, the hand-fix list, where the next rehearsal starts, the owner's check, the merge and the memory rewritten with it
---

## How is the switch rehearsed?
- Rebuild `feature/bootstrap-6` (#1866, a draft until the owner says go) on the latest `development`, comment the result there. Tool commits first, the generated commit last, pushed only when every check passes: drop it (`git rebase --onto <gen>~1 <gen>`), `git rebase origin/development`, `php tools/bs6_migrate.php --all --write`, `--switch --write`, `php tools/build_js.php`, every CI check, commit, `git push --force-with-lease`. Never commit the sandbox's `data/config.php`; `git checkout -- pinegrap` also resets it.
- Scriptable hand fixes are code in `tools/bs6_switch.php` and `tools/bs6_switch_shell.php` (tests `tests/bs6_switch_test.php`, `bs6_switch_shell_test.php`); a fix found by its exact text goes into `tools/bs6_switch_texts.txt`. Never a patch file. What each fix does: the file headers and `ui/bootstrap-6-codemod.md`.

## What is still on the hand-fix list (rehearsal 3, 2026-10-26)?
- `check_bs6.php` reads the converted tree (309 findings): class API calls that would split 61, classes built in code 59, modal markup left 33 (class strings and the open sheets' own JS), surfaces with no v6 class 27, `display-*`/`lead` 23, text colours 22, dropdown markup left 12, CSS selectors 10, accordion 9, others under 15.
- Look (B1b round of rehearsal 3): the panel's own button variables (v5 `--bs-btn-*`) no longer reach v6 buttons (pill toolbar buttons, the header's account and "Siteye Git" buttons; the header is 6 px taller); taller table rows; DataTables still writes v5 classes (length select, header checkbox); dialogs are centred.
- The visual editor's canvas-facing code in `style_designer.js` is left on v5 on purpose; only its own dialogs are converted.
- `forms.php` and the other shared files (`bs6_scope_kind()`) are not converted yet.

## Where does the next rehearsal start?
- Rebuild as above. Then map the panel's button and table variables to v6 tokens (backend.src.css token block), the DataTables class overrides in backend.src.js, then the class API calls and the code-built classes from `check_bs6.php --verbose`.
- Open question for the owner: own media queries now follow v6's breakpoints (lg 1024, xl 1280); `bs6_switch_breakpoints()` is dropped if the owner keeps 992/1200.

## What does the owner check?
- The before/after page of each round (rehearsal 3: https://claude.ai/artifact/Xdh6cnCar778Z77jphtzS4): liked, secure and visual editor screens at 1440, 834 and 390 px, light and dark (`ui/panel-screenshots.md`). Then the branch runs on dev.pinegrap.com (a linked session checks it out) and the owner says go.

## What happens at and after the merge?
- In the same PR: CLAUDE.md's "Bootstrap 5 + jQuery", the ui and designer key rules, `php tools/check_bs6.php --strict` in php-checks.yml, CLAUDE.md's check list and pre_push.sh; the body names the changelog's `[NEW] Added …` line; new `lang()` keys go in with `php tools/tr_add.php`. The merging session merges it; tag the merge `bs6-switch`.
- A PR whose merge-base is older than the tag: merge `development`, run `php tools/bs6_migrate.php --write --diff=origin/development` (only its own added lines: a second run corrupts converted sizes), then `check_bs6.php`. CI fails such a PR until then. Revert only during the freeze window; after it, fix forward.

## Which memory files are rewritten with it?
- ui: panel-layout-pitfalls, step-rail, user-permission-rows, settings-modal-card-dialogs, settings-modal-navigation, settings-modal-layout, settings-card-chrome, command-palette, own-menu-dropdown-keydown, account-menu, panel-hint-tooltip-or-popover, code-editor-escape-in-dialogs, main-menu-states, loading-curtain, list-and-table-screens, edit-screen-wide-layout, admin-screen-toolbar, pg-ui-adapter (the bridge); designer: designer-settings-modal-sizing; workflow: project-overview.
