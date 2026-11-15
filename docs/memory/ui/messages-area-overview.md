---
name: messages-area-overview
description: Start file of the Communication area (İletişim, menu row 9, #8961): Incoming e-mail and the five tabs, their screens, and which memory file holds each part (ui, platform, designer, core, workspace)
---

## What is the area?
- Main menu row 9 under Marketing, "Communication", in place of My Campaigns: one place for campaigns, automatic sends, templates, every send and the channels, with the incoming e-mail first. Built by the messages-area plan, MA0 to MA13 (2026-11-12 to 2026-11-15). What binds it: [[messages-area-rules]].

## Which tabs, and where does each live?
- Incoming e-mail, `messages_mail.php`: [[messages-area-mail-tab]]; the card and the gate: `workspace/mail-area-proposals.md`, `workspace/mail-area-shell.md`, `workspace/mail-card-answered.md`, with the Workspace off `workspace/mail-workspace-off.md`, written as HTML `workspace/mail-html-written.md`.
- Campaigns, `view_email_campaigns.php`: [[messages-area-campaigns-tab]]; the content card [[campaign-content-step]], the test send [[campaign-test-send]].
- Automatic sends, `messages_automatic.php`: [[messages-area-automatic-tab]].
- Templates, `messages_templates.php`: [[messages-area-templates-tab]].
- Sends, `messages_sends.php`: `platform/messages-area-sends.md`, its speed `platform/messages-area-sends-speed.md`, its Calendar view [[messages-area-sends-calendar]].
- Channels, `messages_channels.php`: [[messages-area-shell]] (its cards).
- The shell, strip, gates and doors: [[messages-area-shell]]; the look: [[messages-area-look]]; the drawing: `messages-area-drawing/`.

## Where are the e-mail body files?
- `designer/campaign-email-body-way.md` (the way, kept on #9083), `-today.md` (facts before), `-mail-programs.md` (what mail programs take), `-mail-pass.md` (the mail pass, MA5a).
- `designer/email-designs.md` (folder, canvas, parts), `email-designs-parts.md`, `email-designs-editor.md`, `email-designs-ready.md` (five ready designs), `email-designs-copies.md`.

## Where are the campaign profile files?
- `core/campaign-profile-flow-map.md` (fields against boxes), `-flow-gaps.md` (what no box covers, what keeps running), `-flow-switch.md` (one flow per chain, the switch moment, the owner), `-flow-email.md`, `-flow-converter.md`, `-flow-dry-run.md` (the report), `campaign-profile-switch-writer.md` (the update step), `campaign-profile-screens-left.md` (the screens after it).

## Where is MA0's census?
- `platform/messages-area-census-tabs.md`, `-screens.md`, `-addresses.md`, `-sends.md`, `-sources.md`: the screens, addresses and send sources before the area, and MA0's proposals.

## What is still open?
- Sends' search and heading sorts at 500,000 rows a queue (`platform/messages-area-sends-speed.md`).
- The six profile actions with no flow start (`core/campaign-profile-screens-left.md`).
- Open and click tracking: out until the owner decides it (`designer/campaign-email-body-way.md`).
- Opening a WhatsApp template in the designer ([[messages-area-templates-tab]]).

## How is a new part added?
- A new tab: a row in `pg_messages_area_tabs()` with its own screen file and gate, drawn with `pg_messages_tab_page()`, and a line here.
