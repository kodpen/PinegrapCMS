---
name: bootstrap-6-redesign
description: Steps B10-B11 of plans/bootstrap-6-panel.md: token tuning and the redesign of legacy form screens and tables; both ready to start (owner, 2026-11-01)
---

## How are the tokens tuned (B10)?
- After B9, one PR per token group (colours, radius, spacing, focus), with before and after screenshots of the liked screens. The owner approves each before merge.
- Ready to start: radius, spacing, one claim each.
- Focus: #4465 (ring sizes on `--pg-focus-ring-width` / `--pg-focus-ring-offset`, `ui/panel-focus-ring.md`). Colours: #4836 (surfaces on the gray scale, text and lines Bootstrap 6's, rings on the ink; `ui/panel-css-tokens.md`). Open: radius, spacing.

## Which screens are redesigned (B11), and how?
- Legacy edit screens whose inputs spread over full-width rows or label/field tables. The B1b screenshot round lists them. Ready to start: the claiming session picks the next screen (most used first) and names it in its draft; the owner approves each PR. The liked screens (`bootstrap-6-panel.md`) are the reference and are not redone.
- Target: the product_builder pattern (`ui/edit-screen-wide-layout.md`: form column, sticky status column, sticky save bar) with v6 `form-field`, form-adorn and cards. One screen per PR, before and after screenshots, the owner's approval before merge.
- Screens redone: campaign profile, add and edit (#4830, `includes/forms/email_campaign_profile_form.php`: Enabled and Purpose in the status column).
- Tables: the owner finds their structure fine. `table.chart` and DataTables stay; density, header, row actions and empty states may change (`.table-card` once B0b is in).
