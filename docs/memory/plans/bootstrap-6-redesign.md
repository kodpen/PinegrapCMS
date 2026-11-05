---
name: bootstrap-6-redesign
description: Steps B10-B11 of plans/bootstrap-6-panel.md: token tuning and the redesign of legacy form screens and tables (owner, 2026-11-01)
---

## Which screens are redesigned (B11), and how?
- Done: B10 #4465 #4836 #4867 #5356.
- Legacy edit screens (full-width rows, label/field tables; the B1b screenshot round lists them). Ready to start: the claiming session picks the next screen (most used first), named in its draft; no approval issue (owner, 2026-11-04, #6509): the screen lands when green; a change wanted later is a `bug` work item. The liked screens (`bootstrap-6-panel.md`) are the reference and are not redone.
- Target: the product_builder pattern (`ui/edit-screen-wide-layout.md`: form column, sticky status column, sticky save bar) with v6 `form-field`, form-adorn and cards. One screen per PR, before and after screenshots in the PR body.
- Screens redone, add and edit (forms in `includes/forms/`; `(#n: X)`: X sits in the status column; notes in `ui/`, mostly `<screen>-shared-form.md`; the earlier ones in `bootstrap-6-redesign-screens-1.md`): Cloudflare Tools 1/3 (#6913: Settings) and 2/3 (#6926: DNS card, Zone Logs, labelled date fields); Marketplaces part 1 (#6914: the account); bank statements (#6915: a Summary); API settings (#6911 part 1: a Summary; next parts: the drawer, then the dialogs and sheets); stock minimums (#6916: a Summary); Send E-mail Campaign window (#6886: the campaign's subject and addresses).
- Left: Cloudflare 3/3 the Add and Edit DNS Record dialogs.
- Marketplaces left: part 2, the Settings drawer and the Connect dialog in `form-field`.
- Tables: the owner finds their structure fine. `table.chart` and DataTables stay; density, header, row actions and empty states may change (`.table-card` once B0b is in).
