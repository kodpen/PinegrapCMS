---
name: communication-settings-tidy
description: Plan (owner 2026-11-21): Communication as folded rows, the Chat card's two popover levels split into "Chat" and "Chat on the site", each field saying whom it binds
priority: 8
---

## What is asked?
- Owner (2026-11-21): fix the settings screens' contradictions, gather spread settings, make old screens modern. Item 6 of `plans/settings-tidy-census-order.md`: Communication's Chat card.

## What contradicts today? (development 6169b52410)
- (H1) Two popovers deep: Enable Live Chat's popover holds the operator, Chat Retention Period and Enable Site Chat Bubble, whose own popover holds 13 more fields (`includes/settings/contact.php:39-130`).
- (H2) Enable Live Chat also turns off the panel's chat between team members (`CHAT_ENABLED` is absolute, `init.php:517-520`); its label speaks of live chat only.
- (H3) Site Chat Operator sits above the bubble switch but serves the bubble only: a visitor not signed in gets no bubble until an operator is chosen (`pg_chat_site_enabled()`, `chat.php:2003-2008`), a signed-in member gets the panel's chat without one; the card says neither.
- (H4) Image, file and voice attachments and Visitor Image Limit bind visitors not signed in only; members and staff send files whatever they say (`chat.php:2862-2871`); the labels do not say so.
- (H5) The visitor puzzle (`chat_captcha`) is here, the site's other abuse settings in Accounts and sign-in; neither names the other.
- (H6) Chat Retention Period saves a value nothing reads; it leaves with VP3 (`plans/privacy-settings-tidy-steps.md`, owner poll 215).
- (H7) `div.switch` drawings in Chat, SMS (`includes/fn/sms.php:1731`), SMS Messages and WhatsApp Messages; Title Case "Enable Live Chat", "Enable Site Chat Bubble".

## What does the plan settle itself?
- The pane becomes folded rows (`pg_settings_fold()`): "Chat", "Chat on the site", "SMS", "SMS messages", "WhatsApp", "WhatsApp messages", "İYS"; the last five hold what their functions draw today (`plans/communication-settings-tidy-screens.md`).
- "Chat" (`pgset-chat`): the switch named "Chat" with a line saying it is the panel's team chat and the site's; Folder for chat attachments; Chat Retention Period until VP3 takes it.
- "Chat on the site" (`pgset-chat-site`, new): the bubble switch, "Who answers visitors" (the operator) with its line on H3, the offline mail, the bubble's texts and look, a group "Visitors who are not signed in" holding the attachment switches, the image limit and the puzzle; the puzzle's line links `#settings/security/pgset-captcha`.
- With Chat off the bubble row's fields stay drawn, its line saying nothing shows on the site; every switch `pg-switch`.

## What binds it?
- Field names, ids, columns and what a save stores do not change; `pgset-chat` and the other card ids keep working, the moved fields' anchors open their new row; one Save a pane; no schema change, no new part.
- The chat bubble stays apart from the Workspace (`workspace/workspace-module-decisions.md`); chat behaviour does not change.

Related: docs/memory/plans/communication-settings-tidy-steps.md, docs/memory/plans/communication-settings-tidy-screens.md, docs/memory/plans/settings-tidy-census-order.md
