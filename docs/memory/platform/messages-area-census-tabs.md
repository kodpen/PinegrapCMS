---
name: messages-area-census-tabs
description: MA0 answers (2026-11-13) for the messages area's shell: the menu row's name and slot, what each of the five tabs takes from today's screens and settings cards, and who sees each tab
---

## Who settled these, and can they change?
- Step MA0 chose them on 2026-11-13, under the owner's rule of 2026-11-12 that a step decides such questions itself instead of asking the architect. They hold unless the owner changes them before MA1 lands. Facts: [[messages-area-census-screens]].

## What is the menu row?
- Row 9 keeps its slot and its place under Marketing; its title becomes "Communication" (the existing key, İletişim, the drawing's name). The Site Settings category of the same name keeps it: it holds the channels' settings the Channels tab leads to.
- Its gate stays `validate_email_access()`'s rule (role <= 2 or the Campaign Manager right). Its panel lists the tabs a person may open, and "New campaign".

## What does each tab take, and who sees it?
- Campaigns: e-mail and SMS campaigns sent once to many (MA3). Automatic campaigns, one row per profile send, leave this list: they are single messages in Sends with their profile as source. Gate `validate_email_access()`; a role 3 user sees their own rows. A WhatsApp campaign shows its place only (`plans/messages-area.md`).
- Automatic sends: the flows that send a message (MA8, after AF2), under the flows' gate (AF1, `plans/automation-flows-menu.md`); a role 3 member sees their personal flows. While MA9 runs, the profiles still running are counted here with a link to My Campaign Profiles.
- Templates: e-mail designs ([[campaign-email-body-way]]), the SMS texts of the SMS Messages card, WhatsApp templates once WA3 lands. Listed for `validate_email_access()`; each row opens under its own screen's right, so SMS texts and WhatsApp templates only for role <= 2. The SMS texts are still edited in their card.
- Sends: the one list ([[messages-area-census-sends]]). Tab for `validate_email_access()`; the queue rows (e-mail, SMS, WhatsApp) only for role <= 2, as the mail queue today; a role 3 user sees their own campaigns and those campaigns' recipients.
- Channels: cards E-mail sender (SMTP Settings, with the mail domain health line), SMS, WhatsApp and İYS, each with its state and a link to its settings; role <= 2 only, as the settings. Chat and MailChimp stay in Site Settings: a visitor chat and a list sync are no sending channel of this area.
- The Campaigns card (Opt-In Label, Plain Text Footer) stays in Site Settings; the Campaigns tab's toolbar leads to it for role <= 2.

## What does every tab screen share?
- One tab strip function draws the five tabs, leaving out a tab its viewer may not open; each tab is a screen of its own file with its own gate, as each Site Settings category keeps its `settings_<key>.php`. No tab is drawn by JavaScript alone: the address opens it.
- New files for the new tabs (Sends, Templates, Channels, Automatic sends); the Campaigns tab keeps `view_email_campaigns.php`, the most linked address ([[messages-area-census-addresses]]).
- Look A and the list table driver, as every new list (`ui/list-table-overview.md`); one list name per tab for `pg_list_state()`.
- The SMS screens take the area's shell in MA1; until MA1 lands WhatsApp's card, templates and queue sit beside the SMS ones (`plans/messages-area-steps.md`).
