---
name: bootstrap-6-switch
description: Step B8 of plans/bootstrap-6-panel.md (claim #1866): building a rehearsal, the hand-fix list, where the next rehearsal starts, the owner's check, the merge and the memory rewritten with it
---

## How is the switch rehearsed?
- Rebuild `feature/bootstrap-6` (#1866, a draft until the owner says go) on the latest `development`, comment the result there. Tool commits first, the generated commit last, pushed only when every check passes: drop it (`git rebase --onto <gen>~1 <gen>`), `git rebase origin/development`, `php tools/bs6_migrate.php --all --write`, `--switch --write`, `php tools/build_js.php`, every CI check, commit; then `git merge -s ours origin/feature/bootstrap-6` (keeps the old history, the tree stays the rebuild) and a plain `git push`. Never commit the sandbox's `data/config.php`.
- Scriptable hand fixes are code in `tools/bs6_switch.php`, `bs6_switch_shell.php`, `bs6_switch_tokens.php`, `bs6_switch_stems.php` (tests `tests/bs6_switch*_test.php`); a fix found by its exact text goes into `tools/bs6_switch_texts.txt`. Never a patch file. What each fix does: the file headers and `ui/bootstrap-6-codemod.md`.

## What is still on the hand-fix list (rehearsal 4, 2026-10-27)?
- `check_bs6.php` on the converted tree, 185 findings: modal markup left 33, surfaces with no v6 class 27, `display-*`/`lead` 23, text colours 22, RGB variables 14 (`design_themes.php`, -rgb triples), dropdown 12, CSS selectors 10, stems built in code 9 (values the code does not show), others under 8. `page_designer.js`'s `addClass('btn-danger open')` targets the site preview (v5) and stays.
- Look: v6 has no 1400 px breakpoint (`xxl` classes become `2xl`, 1536), so 1400–1535 px screens take the `xl` layout; a few px of height on cards and phone form rows.
- The visual editor's canvas-facing code in `style_designer.js` stays v5 on purpose; `forms.php` and the other shared files (`bs6_scope_kind()`) are not converted.

## Where does the next rehearsal start?
- Rebuild as above. Then open every dialog, menu and drawer of the liked screens in a browser (only the screenshot list and a few were opened), the modal (33) and dropdown (12) markup left, surfaces and text colours with no v6 class, the shared files.
- Open owner questions in #1866's body: own media queries on 1024/1280 (keep until answered) and the lost 1400 px breakpoint.

## What does the owner check?
- The before/after page of each round (rehearsals 3 and 4: https://claude.ai/artifact/Xdh6cnCar778Z77jphtzS4): liked, secure and visual editor screens at 1440, 834 and 390 px, light and dark (`ui/panel-screenshots.md`). Then the branch runs on dev.pinegrap.com and the owner says go.

## What happens at and after the merge?
- In the same PR: CLAUDE.md's "Bootstrap 5 + jQuery", the ui and designer key rules, `php tools/check_bs6.php --strict` in php-checks.yml, CLAUDE.md's check list and pre_push.sh; the body names the `[NEW]` line; new `lang()` keys go in with `php tools/tr_add.php`. The merging session merges it; tag the merge `bs6-switch`.
- A PR whose merge-base is older than the tag: merge `development`, run `php tools/bs6_migrate.php --write --diff=origin/development` (only its own added lines: a second run corrupts converted sizes), then `check_bs6.php`. CI fails such a PR until then. Revert only during the freeze window; after it, fix forward.

## Which memory files are rewritten with it?
- ui: panel-layout-pitfalls, step-rail, user-permission-rows, settings-modal-card-dialogs, settings-modal-navigation, settings-modal-layout, settings-card-chrome, command-palette, own-menu-dropdown-keydown, account-menu, panel-hint-tooltip-or-popover, code-editor-escape-in-dialogs, main-menu-states, loading-curtain, list-and-table-screens, edit-screen-wide-layout, admin-screen-toolbar, pg-ui-adapter (the bridge); designer: designer-settings-modal-sizing; workflow: project-overview.
