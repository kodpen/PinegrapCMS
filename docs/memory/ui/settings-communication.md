---
name: settings-communication
description: Site Settings › Communication as folded rows (CM1): Chat and Chat on the site split from the old two-popover card, each row's state line, what moved, what stayed (ids, field names, the save, anchors)
module: communication_settings_fold
---

## How is the pane drawn?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`) in this order: Chat, Chat on the site, SMS, SMS messages, WhatsApp, WhatsApp messages, İYS. Order, names, icons and lines: `includes/fn/communication_settings_fold.php` (`pg_contact_fold_order()`, `pg_contact_fold_face()`, `pg_contact_fold_<part>()`, pure but for `lang()`). Test: `tests/settings_communication_fold_test.php`.
- Purpose line (the registry's description): "How people reach the site, and how the site reaches them."
- No popover is left in Chat; its switches are `pg-switch`. The SMS to İYS rows hold what their functions draw (their `div.switch` drawings are CM2's).

## What does each row say?
- Chat (`pgset-chat`): "Team members chat in the panel" / "No chat shows anywhere", pill On/Off.
- Chat on the site (`pgset-chat-site`): with Chat off "Chat is off: nothing shows on the site"; bubble off "The bubble is off"; bubble on without an operator "Only signed-in members see the bubble: no one answers visitors", amber pill "No one answers", attention (the row opens first); else "On, answered by <name>".
- SMS: "On, sent through Netgsm"; switched on without a connection asks for attention; "Netgsm is connected; sending is off"; "Not set up".
- SMS messages and WhatsApp messages: "2 of 5 messages on", "No message is switched on", or "Not set up: no message is sent" while the channel does not send. SMS counts only the events the card offers (ERP, bookings).
- WhatsApp and İYS: Connected / Not set up.

## What moved where?
- Chat holds the switch "Chat" (`chat_enabled`, which also switches off the panel's team chat: `CHAT_ENABLED`) with a line saying so, and Folder for chat attachments.
- Chat on the site holds "Chat bubble on the site" (`chat_site_enabled`), "Who answers visitors" (`chat_operator_user_id`) with its line on visitors who are not signed in, the offline mail.
- Then the group "The bubble" (Bubble label, Welcome message; Theme, Colour, Icon side by side from 768 px) and the group "Visitors who are not signed in" (Images, Files, Voice messages, Images per conversation, "Ask a puzzle before the first message" with a link to `#settings/security/pgset-captcha`), ending "Team members and signed-in members may always send files."
- Its fields stay drawn while Chat is off; a line at its top says nothing shows on the site.

## What stayed?
- Every field's name and id, `contact.save.php` unchanged, one Save. Card ids `pgset-chat`, `pgset-sms`, `pgset-sms-messages`, `pgset-whatsapp`, `pgset-whatsapp-messages`, `pgset-iys`.
- Old anchors `chat_site_enabled`, `chat_operator_user_id`, `chat_captcha` open Chat on the site (`pg_settings_legacy_anchors()`); the client opens the row a card or field sits in (`pgSettingsFoldReveal()`).
- Search words split: Chat "chat, team chat, live chat, message, attachments, folder"; Chat on the site "live support, bubble, widget, operator, visitor, welcome message, captcha, puzzle, attachments, voice message, offline".

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/screen-composition-rules.md, docs/memory/core/privacy-rule-site-chats.md
