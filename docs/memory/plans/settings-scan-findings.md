---
name: settings-scan-findings
description: Faults found by the 2026-11-21 scan of the Site Settings panes and the step or PR that takes each; a step strikes its lines, the file is deleted when none is left
---

## What is this list?
- The research agent opened 19 panes (40 views) at 1440 and 390 px on 2026-11-21 (agent channel message 11676). The rules the faults broke: `ui/screen-composition-nesting.md`.
- A step named here fixes its lines in its own PR and deletes them from this file; a step already ready is not reopened, its lines go to the next step of its plan. The last line's PR deletes the file.

## What does the shared style PR take?
- One `[iş]` PR of the research agent after #11005 (folded rows lose the inner-card line): unit suffixes bigger than the number; pickers and tag fields 48 to 64 px beside 32 px fields, the old "Browse…" button; selects that cut their chosen value (Document numbers, Sending way, Channel, Required ones); bare browser lists (Pinegrap AI application, Voice provider); card-in-card frames left by the shared style.
- The panes without a plan, in the same PR or one after it a pane: Languages (double left edge, lower-case "ready"), SEO (the white colour box unseen, spacing), Channels (filled "New template", mixed labels), Team (the member table overflows a phone, label weights), Maintenance (the 10% bubble always open, a missing line, the mini map on a one-line command).
- A step PR that touches the same lines lands first; the shared PR is fitted to it.

## Which plan step takes which fault?
- General, GA4: Organization's 11 flat fields under group labels. The logo picker's size is the shared PR's.
- Appearance, GA3: the Extra CSS placeholder translated; Private label branding's help inside the row's padding; Editor version as a segmented control.
- Bookings, BT1 (#11008): "New service" is a card in a card and always open, it becomes the drawer the plan names. BT2: the staff list at full width.
- Communication, CM1: the channel list cut in two places; warning boxes in help size. CM2: WhatsApp's event name and general label in one size; SMS's 11 open text fields folded, one open at a time.
- Security, SE2: the Turnstile and hCaptcha headings as group labels. SE3: the lock-out fields' white card in a grey card to one surface; the blocked e-mail field's red frame only when refused; the "Device" help that explains itself rewritten or dropped; the key reset as a button with a confirm, not a switch.
- E-mail, EM3: the DKIM field group as one group with its label.

## Which panes still need a plan?
- SEO, Languages and Translation, Fediverse, Integrations, Channels, Team: folded-row plans by the research agent. E-commerce, Accounting and Maintenance wait for the CX11 steps.

Related: docs/memory/ui/screen-composition-nesting.md, docs/memory/ui/screen-composition-rules.md
