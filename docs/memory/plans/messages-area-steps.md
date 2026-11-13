---
name: messages-area-steps
description: Steps MA0-MA11 and MA2b of the messages area plan (#8961): census, shell, sends list, campaigns, e-mail bodies, templates, channels, automatic sends, profiles, incoming e-mail
---

## Which steps? (rules: `messages-area.md`)
- Every step that builds or changes a screen here builds from the drawing (`messages-area/messages-area.html`) with look A's recipes and posts real screenshots in the agent channel for the owner's approval before it goes ready; an old screen is never moved in as it is (#9295, `workflow/architect-asking.md`).
- MA0 (S, done #9074) The census (`platform/messages-area-census-*.md`); its proposals were answered on #9081.
- MA1 (M, done #9182) The shell: the menu row, the five tabs with their gates, old addresses leading in.
- MA2 (M, done #9252) Sends: the list, tiles, filters, row buttons and e-mail sources built behind `pg_messages_sends_list_open()` (`platform/messages-area-sends.md`).
- MA2b (M, done #9275) The list table driver puts a source's keyset bound and batch limit into each branch and counts per branch; then the Sends list opens, `mail_queue.php` becomes a door (`pg_messages_sends_queue_door()`) and automatic campaigns leave the Campaigns list.
- MA3 (M, done #9215) Campaigns: one list for e-mail and SMS campaigns; "New campaign" asks the channel first, then recipients, content, time.
- MA4 (S, done #9074) E-mail body as a page: a proposed way for MA5 (`designer/campaign-email-body-*.md`), answered on #9083.
- MA5 (M to L, shaped after MA4) The campaign's content step: edit the design, ready designs, paste HTML, phone preview, a test to oneself.
- MA6 (M, SMS texts done #9294, `ui/messages-area-templates-tab.md`) Templates: e-mail designs (with MA5), SMS texts and WhatsApp templates (after WA3, `whatsapp-channel-steps.md`) in one tab.
- MA7 (S, done #9200) Channels: e-mail sender, SMS, WhatsApp and İYS as cards with their state, each leading to its settings.
- MA8 (M) Automatic sends: the flows that send a message, their recipes, the way into the quick setup; the Flows screen opens with the Workspace off since #9154.
- MA9 Profiles into flows (M2b), MA9a to MA9f, and MA10 Incoming e-mail, MA10a to MA10c: `messages-area-steps-2.md`.
- MA11 (S) Close-out: the lasting rules into `platform/` and `ui/`, an overview file, this plan deleted.
- MA12 (M) The look of what is built (#9295): the tab strip as the pill switch the flow window has for Canvas and List (owner: "tablar boyle olsa ya"; today three lines at 390 px), Campaigns, Sends and Channels redone against the drawing. It lists the old screens still inside the area (campaign add and edit, SMS campaign, profiles) for MA5 and step DL5 of `panel-design-language-steps.md`.
  - Seen on dev: a card heading on its card's border, the Campaigns toolbar's two filters stacked, the SMS warning above the strip, Channels cards of uneven height.

## In which order?
- MA0 first, then MA1; MA2, MA3, MA6, MA7 after MA1 in any order; MA2b after MA2. MA4 may run beside MA1; MA5 after MA4, MA3 and MA12.
- MA12 is next; MA8 after MA12; MA10b after MA1, MA10c after MA10b; MA9b and MA9c side by side, MA9d after both, MA9e after MA9d and the owner's look at its report, MA9f after MA9e.
- Where WhatsApp's card, template list and queue sit follows this area once MA1 has landed; before that they sit beside the SMS ones.
- Claim as `[plan:messages-area] <step> — <topic>`.

## What is left for the next session? (2026-11-13, after #9294)
- Free now: MA12. MA5 (it also lists the e-mail designs in the Templates tab) and MA8 wait for MA12. MA6's WhatsApp templates wait for WA3.
- Left open by MA2b (#9275): Sends' search (3.2 s) and sorts other than Date or Channel (2 s) at 500,000 rows per queue a week (`platform/messages-area-sends.md`); asked only if a real site gets there.
- The shell, the doors and the tabs so far: `ui/messages-area-shell.md`, `ui/messages-area-campaigns-tab.md`.

## What is asked of the owner along the way?
- The dry-run report on a real site's profiles before the update step (MA9e) lands.
