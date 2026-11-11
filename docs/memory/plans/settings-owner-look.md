---
name: settings-owner-look
description: Plan (owner 2026-11-11, #8677): what the owner found on the settings screens: cards that stick to the card above, a page left open across an update; steps SG1 and SD3
---

## What is it for?
- The owner looked at the settings screens on dev (2026-11-11) and found two things. His words: below and #8677. A further finding of his on a settings screen becomes a step here.
- The move of the Workspace settings and the drawer's other dialogs are `plans/settings-workspace-move.md`; these two are apart from its order.

## Which steps are there?
- SG1 (S) The gap between cards.
  - Owner (a picture of Backups on "Updates and maintenance"): "birde ayarlarda bazı kartlar bu şekilde bir üsttekine yapışıyor. profesyonel durmuyor".
  - Today the gap rule (`.pg-set-card + .pg-set-card`, `backend.src.css`) fails when another element stands between two cards.
  - Find what stands between and make the gap hold whatever does (a gap on the cards' container); checked in every category, on the dialog and on the page path. `php tools/build_css.php`.
  - Topic: `ui/settings-card-chrome.md` names the rule.
- SD3 (S) A page left open across an update.
  - The owner's first look at the drawer: "baktım tıklayınca hiç bir tepki yok", a panel page left open across the deploy; after a full reload "Yeniledim, açılıyor" (`workflow/decisions-made-panel-3.md`).
  - The pane's answer (`ui/settings-modal-endpoint.md`) carries the panel bundle's `?v=` stamp (`core/js-bundles.md`); a page whose script has another stamp shows one line with a "Reload" button, not a pane it cannot serve.
  - Tests: the stamp compare as a pure function. Topic: `ui/settings-modal-endpoint.md` corrected.

## What does every step keep?
- No schema. Texts through `lang()`, keys by `php tools/tr_add.php`; `php tools/build_js.php` and `build_css.php` after the edits.
- Looked at in the sandbox (`ui/panel-screenshots.md`), wide and phone, linked in the PR. The look on dev is the owner's.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:settings-owner-look] <step> — <topic>`. The two do not wait for each other. Both edit files the open steps of `settings-workspace-move` may edit (`backend.src.css`, `backend.src.js`): the claim check holds. The last step to land deletes this file.
