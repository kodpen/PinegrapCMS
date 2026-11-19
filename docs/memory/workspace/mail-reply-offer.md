---
name: mail-reply-offer
description: Reply by e-mail for a mailbox that cannot send (#10083): drawn disabled with its reason on the card and in the E-mails pane, one test ws_mail_reply_blocked() shared with the send's check
---

## When is Reply by e-mail drawn disabled?
- When the conversation's box cannot send: gone or switched off ("The mailbox of this conversation is gone or switched off."), no customer address, or no route (`ws_mail_reply_route()`: "Enter the mailbox's sending server first.", the domain or connected-account reason).
- The button stays, `disabled`, its reason printed beside it in a `span` the button names with `aria-describedby` (`replyButton()` in `assets/js/workspace_mail.js`). Reply to all is left out of the pane then.
- A reader who may not write in the channel still gets no button at all (`reply` 0, `mail_reply` 0); status and holder controls are not touched.

## Where does the reason come from?
- `ws_mail_reply_blocked($box, $customer_email, $site_domain)` (`mail_reply.php`): '' or `no_box` | `no_customer` | the route's error. `ws_mail_reply_off()` turns it into `ws_mail_reply_error_text()`.
- The same test is what `ws_mail_reply_target()` checks when the drawer opens and when the answer is sent, so the offer and the refusal never disagree; the send-time check stays.
- The card: `ws_mail_items_map()` reads each box's `enabled`, `address`, `reply_via`, `settings` and sets `reply_off`; `ws_message_payload` sends it as `mail_reply_off`. The pane: `ws_mail_pane_tools()`'s `reply_off`.

## Why?
- Before #10083 the offer looked only at the right to write in the channel; pressing it on a switched-off or `.eml`-only box opened no drawer and only raised a notice.

## Tests?
- `tests/workspace_mail_reply_test.php` (`test_workspace_mail_reply_blocked`, `test_workspace_mail_reply_off_is_offered_disabled`).
