---
name: look-workspace-fields-scripts
description: WL9: the small Workspace scripts' form-control and form-label lines on pg-input and pg-field-label, the CSS that followed, the 16px tip fields, what stayed
---

## Which scripts moved? (WL9, #11417)
- Twenty `assets/js/workspace_*.js` scripts: acks, approvals, board_channel, channel_agents, customer, editor, highlights, mail, note_group_shares, note_groups, note_lock, page_card, recurrence, replies, status, task_approval, task_delegate, task_links, task_time, templates.
- The same mapping as WL8 (`look-workspace-fields.md`): `form-control form-control-sm` is `pg-input pg-input-sm`, `form-control` is `pg-input`; `form-label` (with `small`, `mb-1`) is `pg-field-label`; `mb-0`, `mt-3`, `mt-4` that place a caption stay.
- A field inside an `input-group-sm` (Repeat's "every" number and unit, a template's checklist line) is `pg-input pg-input-sm`, the old small size; the checklist line's remove button is `pg-button pg-button-quiet pg-button-sm`.

## Which CSS followed?
- A field's own rule that set a size the recipe also sets carries two classes so it still wins over `.pg-input` and `textarea.pg-input`, which come later: `.pg-input.ws-code-edit` (code and calculation areas), `.pg-input.ws-calc-live-input`, `.pg-input.ws-tpl-note-body`.
- `.ws-link-tip .pg-input`, `.ws-calc-settings .pg-input` and `select.pg-input` name the new class.
- `.ws-when .form-label` (0.8rem captions) is gone: Repeat's captions and the reminder's take the recipe's `pg-field-label`.

## How do the tip fields stay 16px on a touch screen?
- `pg-input` is 14px, so `tipFieldClass(true)` returns `pg-input ws-tip-touch`; the touch block gives `.pg-input.ws-tip-touch` `--pg-text-field-touch` (`workspace/workspace-composer-touch.md`, `tests/workspace_editor_tip_fields_test.php`, `tests/workspace_phone_touch_test.php`).

## What stayed?
- The writing box (`div.ws-rich form-control` in `workspace_editor.js`, and the mail's `classList.add('form-control')` on it): a rich text box, not a field; `.pg-input`'s 36px height and flex would break it.
- The task board's toolbar search (`form-control form-adorn rounded-pill pg-toolbar-search` in `workspace_board_channel.js`): the panel's toolbar search, the same as the other toolbars (`tests/bs6_form_adorn_test.php`).
- `form-text` help lines. The palette's search box and the PHP screens' fields moved in WL9b (`look-workspace-fields-screens.md`); `workspace_flow.js` is WL9c in the plan.

Related: docs/memory/ui/look-workspace-fields.md, docs/memory/ui/look-workspace-fields-screens.md, docs/memory/ui/look-check.md
