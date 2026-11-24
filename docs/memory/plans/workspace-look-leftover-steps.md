---
name: workspace-look-leftover-steps
description: Steps WL1-WL11 of the plan workspace-look-leftover: the field count in the look check, the old buttons by file, the old fields, the Data list's bulk Delete in the selection bar, close-out
priority: 2
---

## What holds for every step?
- The recipes and ranks of `workspace-look-leftover.md`; a step changes classes and markup only, never what a control does.
- A bundled script is edited in its source (`CLAUDE.md`, Fixed rules 7); the count of its files in `tools/check_look_baseline.json` falls with `php tools/check_look.php --update` in the same PR.
- The PR body carries the counts before and after and each button left, with its reason; a filled button per view is named there.
- One file is one task: a step waits while an open PR of another plan holds its file (`workflow/claim-work.md`).
- S under 400 product lines, M about 1,000.

## Which steps are there?
- WL1 (S) Done (#11339). The field count. `pg_look_markup_counts()` gets a kind `field` for Workspace files (a path holding `workspace`): each `formRow(` call and each `form-control`, `form-label` or `form-text` class. The baseline takes the first counts; they may only fall. Tests in `tests/` beside the look check's. Topic file `ui/look-check.md`.
- WL2 (M) Done (#11365). `assets/js/workspace.js`: its 232 old buttons, 9 left (`ui/look-workspace-main-script.md`). The channel's head, tabs' panes, drawers and dialogs, the writing box's row, the lists of My Tasks and the settings parts it draws.
- WL3 (S) Done (#11360). The flow screens: `workspace_flow.js` (56), `workspace_flow_flag.js` (3).
- WL4 (S) Done (#11368). The writing box and what hangs on it: `workspace_editor.js` (28), `workspace_highlights.js` (7), `workspace_replies.js` (6), `workspace_voice.js` (6), `workspace_translate.js` (1). The toolbar's pressed state keeps its accent tint (`workspace/workspace-editor-toolbar.md`).
- WL5 (S) Done (#11363); First answer overdue and Plain text stay, pressed toggles. Incoming e-mail: `workspace_mail.js` (25), `includes/workspace/mail_list.php` (6), `mail_pane.php` (6), `mail_blocks.php` (1).
- WL6 (M) Done (#11370); 7 chips and toggles left, named in the PR. The small scripts, about 130 buttons: every `assets/js/workspace_*.js` holding an old button that WL2 to WL5 do not name (`workspace_page_card.js` 16, `workspace_templates.js` 13, `workspace_customer.js` 12, `workspace_task_time.js` 10 and fourteen smaller ones), with `includes/workspace/templates.php` and `workspace_template.php`.
- WL7 (S) Done (#11366). The PHP screens, about 75 buttons: `workspace_wtables.php` (26) and `includes/workspace/wtable_*.php`, `workdays.php`, `personal_ical.php`, `calendar_sync.php`, `tasks.php`, `agent_activity.php`, `bulk.php`, `notes.php`, `personal_plan_mail.php`, `screen.php`, `team_settings.php`; `workspace_mcp.php`, `workspace_calendar_feed.php`, `workspace_meeting_link.php`, `workspace_guest.php`, `workspace_approval.php`.
- WL7 left: `notes.php` (1, held by #11351 then), `workspace_guest.php` (4) and `workspace_approval.php` (2), pages without the panel stylesheet; WL11 names them.
- WL8 (M) Done (#11391). Fields, first part: `formRow()` builds its row from the form parts, so its 65 callers move with it; then the `form-control` and `form-label` lines of `workspace.js`. Waits for WL1 and WL2.
- WL9 (M) Done (#11417). Fields, second part: twenty `workspace_*.js` scripts (`ui/look-workspace-fields-scripts.md`).
- WL9b (M) Fields, third part: `workspace_flow.js` (50, held by #11410), `workspace_palette.js` (1, held by #11401), then the PHP screens' fields (`workspace_wtables.php` after WL10).
- WL10 (M) Bulk Delete on Data, not a look step. Waits for WL7. Described in `workspace-look-leftover-screens.md`.
- WL11 (S) Close-out: what stayed goes to `ui/look-open-work.md`, `ui/look-overview.md` takes the Workspace out of its "left out" line, `workspace/canvas-phone.md` loses its DL5f part, this plan is deleted.
- WL2 to WL7 touch different files and may run beside each other.

## Open questions
- None now.
