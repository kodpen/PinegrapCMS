---
name: connections-lead-keeps-pane
description: A lead from the Site Settings dialog to a Connections service page keeps the pane's unsaved fields and open drawers in sessionStorage and puts them back when the pane comes again (TR3b)
---

## What is it?
- Step TR3b of `plans/settings-tidy-round.md`, the rule's "Nothing is lost" part (`plans/settings-crossings-rule.md`; owner poll 232). Before it a lead dropped the pane's unsaved choices without asking: Payment Methods' gateway, Shipping's service, SMS and Address Autocomplete providers, an agent's drawer (census paths 7 and 8).

## What happens on the click?
- `pgSettingsModal` in `backend.src.js` listens to clicks in the dialog. A plain click (no modifier, no `target="_blank"`) on a link `pgSettingsLead()` reads as a lead (`connections.php?…&from=<category>/<card>`) keeps, in `sessionStorage` `pg_settings_kept`: the pane, the card, the open drawers in order (a drawer opened from another drawer too) and every field whose value differs from the markup's own.
- A lead inside a drawer gets the drawer's id added to `from=` (`<category>/<card>/<drawer>`), which `pg_connections_back()` already accepts; a lead that names one is left as it is.
- Never kept (`pgSettingsKeptSecret()`): password, hidden and file fields, `autocomplete` password or one-time-code, names with a part pass, passwd, password, secret, token, key or apikey; a code field's hidden textarea. Nothing to keep: the record is removed.

## When does it come back?
- `paint()` of a pane not read back after a save calls `putBackKept()`: `pgSettingsKeptDue()` wants the same category and a record under one hour old. The record is read once and removed.
- Fields are matched by name and place among same-named fields, set, then `input` and `change` are dispatched so the card's scripts follow (a gateway's connection block). The held values (password-manager guard, `settings-modal-dirty-tracking.md`) take the put-back values, the pane is marked changed, the first field's folded row opens.
- The drawers open again with `reopen()`; its button is looked for in the pane and in the drawers, the one inside the drawer open now first, so closing returns to the drawer below.

## Which leads changed tab?
- Mailboxes' box set-up drawer (`ws_mail_connection_html()` `$in_box`) and Claude's set-up step 4 ("Open Claude in Connections") open the page in the same tab.

Tests: `tests/settings_lead_kept_test.php`.
