---
name: mail-suggest
description: Recipient suggestions in To, Cc and Bcc of New e-mail and Reply by e-mail (IB5, #9509): the typed word, contacts then known addresses, ten at most, the bounce mark, the list's keys
---

## What is suggested?
- The word after the last comma or semicolon of the field, two characters at least (`ws_mail_suggest_prefix()`), matched at the start of an address or of a word of a name.
- First the contacts the person may open (`ws_mail_suggest_contacts()`: every contact for staff, a role-3 person's contact groups by `pg_contact_owner_sql()`, none without the contacts right), by address, first or last name.
- Then the addresses the boxes of `ws_mail_send_boxes()` know (`ws_mail_suggest_heard()`): senders (`from_email`, `from_name`, newest first) and the To and Cc of their last 500 items (`ws_mail_suggest_list_addresses()`).
- Each address once, lower case, at most `WS_MAIL_SUGGEST_MAX` (10) (`ws_mail_suggest_merge()`); one with `mail_bounces.suppressed = 1` is marked "Bounced address" (its own key: "Bounced" reads as a returned cheque).

## Where, and who?
- Panel action `ws_mail_suggest` (`q`: the field's whole value), read only, module `includes/workspace/mail_suggest.php`. A guest never reaches it (`ws_handle_action()`); before the sending tables it answers no items. No schema, no API route or MCP tool.
- `suggest(h, field)` in `workspace_mail.js`: To, Cc and Bcc of New e-mail; Cc and Bcc of the reply drawer. 200 ms after typing pauses; only the latest answer is drawn. A combobox with a listbox (the Workspace picker's `.ws-picker` look, `.ws-mail-suggest` opens it under the field, as wide as it: architect, channel 3093); arrow keys, Enter or a click take the address in place of the word and add ", "; Escape and leaving the field close it.

## Tests?
- `tests/workspace_mail_suggest_test.php`: the word, list parsing, order, cap, bounce mark, the action and the script's wiring. The lookups against a database and the list in a browser are reported in #9509.
