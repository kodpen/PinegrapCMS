---
name: bootstrap-6-switch
description: Step B8 of plans/bootstrap-6-panel.md: rehearsal, hand fixes, the owner's check, merge and the bs6-switch tag, open PRs after it, memory rewritten with it
---

## How is the switch rehearsed?
- Daily for a few days before B8: build `feature/bootstrap-6` from the latest `development` (dist and loader flip, `bs6_migrate.php`, the hand fixes, `php tools/build_js.php`, every CI check) and comment the result on the B8 claim. A scriptable hand fix is code in `tools/bs6_migrate.php --switch`, never a patch file (line numbers drift); the rest is the list below.

## What is on the hand-fix list?
- The module bridge sits in `<head>` before every defer bundle; it sets `window.bootstrap` but never aliases Modal, Dropdown or Offcanvas (a v6 Dialog on a `<div>` throws).
- A dialog `close` listener clears `dialog-open` (Chromium double Escape). The settings modal's `modal-open` repair is dropped, not translated.
- `.software-navbar.fixed-top` keeps z-index 1030 (v6 1020 falls below the sidebar and the menu backdrop). Own media queries at 992/1200 px (18) follow v6 or stay pinned, one decision. Loading curtain and tour bubbles over an open dialog.
- Every panel stylesheet and inline `<style>` block into `@layer custom`, third-party sheets unlayered; check `d-none` on `.pg-sm-help` and `#sd-publish-page`.

## What does the owner check?
- B1b screenshots of the liked screens, the secure screens and the visual editor (3 widths), before and after, on one private page the PR links. Then the branch runs on dev.pinegrap.com (a linked session checks it out) and the owner says go.

## What happens at and after the merge?
- In the same PR: CLAUDE.md's "Bootstrap 5 + jQuery", the ui and designer key rules, `php tools/check_bs6.php --strict` in php-checks.yml, CLAUDE.md's check list and pre_push.sh; the body names the changelog's `[NEW] Added …` line for the merging session; new `lang()` keys go in with `php tools/tr_add.php`. The merging session merges it; tag the merge `bs6-switch`.
- A PR whose merge-base is older than the tag: merge `development`, run `php tools/bs6_migrate.php --write --diff=origin/development` (only its own added lines: `p-3`, `fs-*`, `rounded-*` mean other sizes in v6, so a second run corrupts converted lines), then `check_bs6.php`. CI fails such a PR until then.
- Revert only during the freeze window; after it, fix forward.

## Which memory files are rewritten with it?
- ui: panel-layout-pitfalls, step-rail, user-permission-rows, settings-modal-card-dialogs, settings-modal-navigation, settings-modal-layout, settings-card-chrome, command-palette, own-menu-dropdown-keydown, account-menu, panel-hint-tooltip-or-popover, code-editor-escape-in-dialogs, main-menu-states, loading-curtain, list-and-table-screens, edit-screen-wide-layout, admin-screen-toolbar; designer: designer-settings-modal-sizing; workflow: project-overview.
