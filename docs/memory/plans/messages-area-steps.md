---
name: messages-area-steps
description: Steps MA0-MA11 and MA2b of the messages area plan (#8961): census, shell, sends list, campaigns, e-mail bodies, templates, channels, automatic sends, profiles, incoming e-mail
---

## Which steps? (rules: `messages-area.md`)
- Every step that builds or changes a screen here builds from the drawing (`messages-area/messages-area.html`) with look A's recipes and posts real screenshots in the agent channel for the owner's approval before it goes ready; an old screen is never moved in as it is (#9295, `workflow/architect-asking.md`).
- MA0 (S, done #9074) The census (`platform/messages-area-census-*.md`), answered on #9081.
- MA1 (M, done #9182) The shell: the menu row, the five tabs with their gates, old addresses leading in.
- MA2 (M, done #9252) Sends: the list, tiles, filters, row buttons and e-mail sources built behind `pg_messages_sends_list_open()` (`platform/messages-area-sends.md`).
- MA2b (M, done #9275) The list table driver puts a source's keyset bound and batch limit into each branch and counts per branch; then the Sends list opens, `mail_queue.php` becomes a door (`pg_messages_sends_queue_door()`) and automatic campaigns leave the Campaigns list.
- MA3 (M, done #9215) Campaigns: one list for e-mail and SMS campaigns; "New campaign" asks the channel first, then recipients, content, time.
- MA4 (S, done #9074) E-mail body as a page: the way MA5 builds (`designer/campaign-email-body-*.md`), #9083.
- MA5 The campaign's content step (`plans/messages-area-decided.md`), in five PRs:
  - MA5a (S, done #9413, `designer/campaign-email-body-mail-pass.md`) The mail pass and the outgoing mode of `mail_html.php`.
  - MA5b (M, done #9426, `ui/campaign-content-step.md`) The content step, drawn anew: design, pasted HTML or plain text; "Take the design again"; wide and phone preview.
  - MA5c (M, done #9465, `designer/email-designs.md`) E-mail designs: folder, `props.pgEmail`, 600/375 px canvas, six mail parts, the mail pass on their body.
  - MA5c2 (S) The other mail blocks (header, footer, product card, merge fields) and the check line: `designer/email-designs.md`.
  - MA5d (M) Five ready designs, the template's and earlier campaigns' as copies; e-mail designs in Templates.
  - MA5e (S) "Send a test to me": to oneself and up to five addresses, never counted, a limit per user.
- MA6 (M, SMS texts done #9294, WhatsApp templates #9355 (WA3b), `ui/messages-area-templates-tab.md`) Templates: e-mail designs (with MA5), SMS texts and WhatsApp templates in one tab.
- MA7 (S, done #9200) Channels: e-mail sender, SMS, WhatsApp and İYS as cards with their state, each leading to its settings.
- MA8 (M, done #9431, `ui/messages-area-automatic-tab.md`) Automatic sends: the flows that send a message, their recipes, the way into the quick setup; the Flows screen opens with the Workspace off since #9154.
- MA9 Profiles into flows (M2b), MA9a to MA9f, and MA10 Incoming e-mail, MA10a to MA10c: `messages-area-steps-2.md`.
- MA11 (S) Close-out: the lasting rules into `platform/` and `ui/`, an overview file, this plan deleted.
- MA12 (M, done #9363, `ui/messages-area-look.md`) The look of what is built (#9295); old screens left inside listed for MA5 and DL5.

## In which order?
- MA0 first, then MA1; MA2, MA3, MA6, MA7 after MA1 in any order; MA2b after MA2. MA4 may run beside MA1; MA5 after MA4, MA3 and MA12.
- MA8 after MA12; MA10b after MA1, MA10c after MA10b; MA9b and MA9c side by side, MA9d after both, MA9e after MA9d and the owner's look at its report, MA9f after MA9e.
- Claim as `[plan:messages-area] <step> — <topic>`.

## What is left for the next session? (2026-11-14, after #9363)
- MA5c2 after MA5c, then MA5d and MA5e in order.
- Left open by MA2b (#9275): Sends' slow search and sorts at 500,000 rows a queue (`platform/messages-area-sends.md`).
- The shell, the doors and the tabs so far: `ui/messages-area-shell.md`, `ui/messages-area-campaigns-tab.md`.

## What is asked of the owner along the way?
- The dry-run report on a real site's profiles before the update step (MA9e) lands.
