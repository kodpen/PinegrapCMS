---
name: look-new-parts
description: A step's new parts in the panel and Workspace are built from look A's recipes (owner 2026-11-17): pg-button, pg-seg for tabs, no btn btn- or nav-tabs in an added line; the count before a look
---

## What did the owner say?
- Owner (2026-11-17, agent channel message 8553): "artık yeni tasarlanan her ekran yeni tasarım dilimiz ile yapılsın. özellikle tab lar ve düğmeler atlanıyor gibi geldi bana."
- The case that day: AU9 (#10225) drew its new form and sample card with `btn btn-sm btn-outline theme-*`, and CC7b (#10213) its Create button with the old pill. The architect had accepted both looks.

## What is the rule? (architect's working rule, 2026-11-17, agent channel message 8554)
- Whatever a step newly draws in the panel, the Workspace included, is built from look A's recipes (`look-type-and-recipes.md`), copied from the Design examples screen (`look-design-examples.md`): a screen, drawer, dialog, card, form or toolbar, and a new part of an old screen.
- Buttons: `pg-button` with `-fill` (one per view), `-quiet`, `-ghost`, `-danger`, `-sm`; icon-only `pg-icon-button`; the ranks as in `look-marketplace-ranks.md`. No `btn btn-…` in an added line.
- Tabs and view switches: the segmented control, `pg-seg` > `pg-seg-item` (`role="tablist"` and `aria-selected` for panes, links with `aria-current` for views, `pg-seg-icons` on a phone). No `nav-tabs`, `nav-pills` or `nav-underline` in an added line.
- Fields, cards and status: `pg-field-label`, `pg-input`, `pg-switch`, `pg-checkbox`, `pg-card`, `pg-pill`. A new Workspace form is built from the kit's form parts (`workspace/workspace-drawer-form-parts.md`), never from `formRow()`.
- A part no recipe draws: the step stops and asks the architect before building it.

## What is not covered?
- An old screen's untouched markup: a step does not restyle its neighbours; what the old-screens plan left is `look-open-work.md`.
- Site output (Bootstrap 5.3.8) and the installer.
- The visual designer: its language is the drawings and measures of the plan designer-panels.

## How is it checked before a look?
- The worker runs `git diff origin/development...HEAD -- pinegrap | grep -E '^\+' | grep -c -E 'btn btn-|nav-tabs|nav-pills|nav-underline'` and writes the count in the look's closing message; a line that stays is named with its reason (moved, not new).
- A set with an unexplained count gets no look from the architect, and no sheet goes to the owner. Drawings use the same parts.
- The count is of what the new part draws, not only of its added lines: old markup carried into a new drawer, dialog or card moves to the recipes with it. CN4 (#10237) carried the provider panel's application form into its drawer with the function, so the added-line count read 0 (Architect, 2026-11-17, agent channel message 8648).
- CI enforces it too: `tools/check_look.php` counts these classes per file of the panel's PHP screens and script sources, in a baseline that may only fall (`look-check.md`). The added-line count above still goes into the look's closing message.

## Where was it first applied?
- AU9 (#10225): the buttons it adds move to `pg-button` before its `ready` label. The application drawer's tab strip and its other buttons moved with S6b (#10534, `look-settings-pages-outside.md`).
- CC7b (#10213) was already in a train: #10234 moved the /oluştur line's cards to the recipes after it.
- A change of look does not change behaviour: in #10234 a text column stays a text box that takes lines (`textarea.pg-input`, one line high at rest, growing to eight), not a one-line input that drops a pasted address's line breaks (Architect, 2026-11-17, agent channel message 8593).
- A sheet drawn before the rule gives way to the ranks on file: in CN4's drawers one fill at the foot, Save quiet, Disconnect as `pg-button-danger`, where CN2's sheet drew two fills and a ghost (message 8648).

Related: docs/memory/ui/look-type-and-recipes.md, docs/memory/ui/look-check.md, docs/memory/ui/look-mark-over-picture.md, docs/memory/workflow/look-narrowest-column.md
