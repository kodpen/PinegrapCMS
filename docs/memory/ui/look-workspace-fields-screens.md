---
name: look-workspace-fields-screens
description: WL9b: the Workspace PHP screens' fields and the command palette's search box on the look's field parts; what stayed as the Site Settings row grid needs it
---

## Which fields moved? (WL9b)
- The PHP screens: `workspace_wtables.php` (Data), `workspace_template.php` (channel template), `workspace_meeting_link.php`, `workspace_calendar_feed.php`, `workspace_mcp.php`, and `includes/workspace/` `wtable_query.php`, `wtable_channel.php`, `workdays.php`, `personal_ical.php`, `team_settings.php`, `mcp_form.php`, `notes.php` (the quick note), `agent_start_text.php`.
- The mapping of WL8 (`look-workspace-fields.md`): `form-control form-control-sm` is `pg-input pg-input-sm`, `form-control` is `pg-input`; `form-label` (with `small`, `fw-semibold`, `mb-1`) is `pg-field-label`; `form-text` is `pg-field-help`, keeping a placing utility (`mt-0`, `mb-3`).
- A `w-auto` select (Data's query row: column, comparison, join, sort, direction) keeps `w-auto`: Bootstrap 6's utilities layer beats `.pg-input`'s width.
- Data's currency field keeps its `input-group input-group-sm` with `input-group-text`, the field `pg-input pg-input-sm`.

## How are the odd ones drawn?
- The template's Icon field: `label.pg-input` holding the preview `<i id="ws_tpl_icon_preview">` and a bare input (no `form-adorn`, `form-adorn-text` or `form-ghost`); test `test_workspace_template_icon_field()` in `tests/bs6_form_adorn_test.php`.
- A meeting link's address shown whole is `div.pg-input h-auto py-2` so a long address wraps instead of being cut at 36 px.
- The command palette's search box (`workspace_palette.js`) is a bare `input.ws-palette-input` in the head, which draws the frame; its rule took flex, appearance, outline and colour that `form-control` gave.

## What stayed, and why?
- `includes/settings/workspace_team.php`, and the switch rows' help in `calls_settings.php`, `link_preview.php`, `bulk.php`: Site Settings cards, whose row grid matches `.form-label` + `.form-control` and `.form-field > .form-text` (`look-settings-screens.md`, `look-settings-mail-sending.md`).
- `mail_add.php`'s choice cards (`.pg-start-choice .form-text`), `mail_phone.php`'s channel field (`.pg-set-card .pg-f-md > .form-control`), `mail_list.php`'s toolbar search (the toolbars' search, `tests/bs6_form_adorn_test.php`).
- The department's colour (`form-control-color` in `team_settings.php`): no recipe draws a colour field.
- `workspace_guest.php` and `workspace_approval.php`: pages without the panel stylesheet (WL11 names them).
- `workspace_flow.js` (61): held by FE14 (#11475) then; WL9c in the plan.

Related: docs/memory/ui/look-workspace-fields.md, docs/memory/ui/look-workspace-fields-scripts.md, docs/memory/ui/look-check.md
