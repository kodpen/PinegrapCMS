---
name: communication-settings-tidy-steps
description: Plan communication-settings-tidy, steps CM1 to CM3: the pane as folded rows with Chat split in two, the message cards' switches, close-out
---

## Which steps are there?
- CM1 (M) first: Communication as folded rows (`pg_settings_fold()`); the Chat card rebuilt as "Chat" (`pgset-chat`) and "Chat on the site" (`pgset-chat-site`, new) with no popover; SMS, SMS messages, WhatsApp, WhatsApp messages and İYS wrapped as rows around what their functions draw.
- In CM1 the bubble's fields' anchors (`chat_site_enabled`, `chat_operator_user_id`, `chat_captcha`) open "Chat on the site" (`pg_settings_legacy_anchors()`); the registry keywords split between the two rows. Fixes: H1 to H5.
- Tests of CM1: `tests/settings_card_ids_test.php` and `settings_card_names_test.php` with the new id; every chat field posts under its old name, `contact.save.php` unchanged; a row an anchor names arrives open.

- CM2 (S) after CM1: every `div.switch` of the message rows becomes `pg-switch` (`includes/fn/sms.php`, `sms_messages.php`, `whatsapp_messages.php`), labels in sentence case; what each function posts does not change. Fixes: H7.

- CM3 (S) close-out: `ui/settings-communication.md` written (rows, what moved, what stayed); memory naming the Chat card's popovers corrected; item 6 of `plans/settings-tidy-census-order.md` removed; this plan's files deleted. Chat Retention Period's removal stays VP3's (`plans/privacy-settings-tidy-steps.md`): whichever lands second keeps the other's change.

## Which steps run side by side?
- None: CM2 draws inside the rows CM1 makes. CM1 and VP3 both edit `contact.php`: one at a time.

## How is a step checked?
- A step copies its parts from the Design examples screen and writes no recipe of its own; it renders the pane at 1440 and 390 px before ready, with Chat off and on, and writes "Screen check" in the PR.

Related: docs/memory/plans/communication-settings-tidy.md, docs/memory/plans/communication-settings-tidy-screens.md
