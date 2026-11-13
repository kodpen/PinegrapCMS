---
name: mail-domain-warning
description: The warning before a Workspace e-mail goes to a domain that does not exist: Send anyway, Change the address, "Did you mean …?", the API's 409 unknown_domain
---

## When is a send warned?
- `ws_mail_send_check()` (`mail-send.md`), after the address checks and limits, unless the send carries `send_anyway`: `ws_mail_domain_warning($addresses, $own_domain, $lookup)` with `ws_mail_domain_exists()`. Error `unknown_domain`, `detail` the words of `ws_mail_domain_warning_text()`, `domains` the list.
- Checked: To, Cc and Bcc of New e-mail and Forward as e-mail; an answer's added Cc and Bcc only (its To is the customer's own address). Not checked: incoming senders, campaigns, forms, the site's other mail. Owner (2026-11-13): a warning, never a refusal (#9267, `workflow/decisions-made-workspace-23.md`).

## What counts as "does not exist"?
- `ws_mail_domain_exists()`: true on any MX, A or AAAA record; false only when all three are empty and the resolver knows the top level's NS; null (no warning) without `dns_get_record()`, on a failed query or a resolver that answers nothing, as `pg_mail_dns_answers()` guards (`platform/mail-domain-health.md`).
- One lookup per domain, the first `WS_MAIL_DOMAIN_LOOKUPS` (5) domains of the send; address literals and dotless hosts skipped. Nothing is stored or cached.

## What does it say?
- "The domain … does not exist, so an e-mail to it will not arrive." plus "Did you mean …?" when `levenshtein()` to the sending box's own domain is at most 2 (two swapped letters are 2).

## How does the form show it?
- `workspace_mail.js` (bundled into `workspace.min.js`): the action's error carries `code` (`ws_mail_new`, `ws_mail_reply`); on `unknown_domain` `domainWarning()` draws a warning over the drawer's buttons with "Send anyway" (posts the same form with `send_anyway=1`) and "Change the address" (focus To, or an answer's Cc/Bcc). Strings in `ws_mail_send_js_strings()`.

## And the API?
- `POST /workspace/mail/messages` answers 409 `unknown_domain` (field `to`, in `api_error_catalogue()`); the same call with `send_anyway` true sends. The API's answer (`…/reply`) has no Cc, so nothing is looked up.

## Tests?
- `tests/workspace_mail_domain_warning_test.php`: a given lookup, no DNS. In the cloud sandbox the real lookup returned null (its resolver answers nothing); no browser run.
