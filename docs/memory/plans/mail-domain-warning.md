---
name: mail-domain-warning
description: Plan: a warning before a Workspace e-mail is sent to an address whose domain does not exist, naming a near domain when one is known; one step MW1 (owner 2026-11-13, #9267)
---

## What was seen?
- Owner (2026-11-13, #9267), with the mail queue's row in state "Gönderildi": "eposta iletildi görünyor ancak eposta kutusuna düşmemiş". On dev a channel message was forwarded as e-mail to an address whose domain was mistyped, two letters swapped; that domain has no DNS record.
- The queue's "Sent" says the mail server took the message. An hour later the address had no `mail_bounces` row and the conversation no "Not delivered" line (`workspace/mail-sent-and-bounces.md`); it waits on an address nobody has.

## What is decided?
- Owner (2026-11-13), asked "Olmayan alan adına gönderimde uyarı veren küçük bir plan yazayım mı?": "Evet, yaz". Before a Workspace e-mail leaves, an address whose domain does not exist gets a warning. Record: `workflow/decisions-made-workspace-23.md`.

## What does the architect add?
- A warning, never a refusal: the same send repeated with "Send anyway" goes out.
- "Does not exist" is an answered lookup with no MX, no A and no AAAA record. A lookup that fails, or a host without `dns_get_record()`, warns of nothing (the guard `pg_mail_dns_answers()` has, `platform/mail-domain-health.md`).
- The warning names the domain; when it is within two letters (`levenshtein()`) of the sending mailbox's own domain it adds "Did you mean …?".
- Checked: To, Cc and Bcc a person types in New e-mail, in Forward as e-mail and in an answer's added Cc and Bcc. Not checked: the address an incoming mail came from; campaigns, forms and the site's other mail.
- One lookup per domain in a send, at most five domains; the rest go unchecked. Nothing is stored: no table, no column, no setting.

## Which steps are there?
- MW1 (S) The warning, as written above. Claim as `[plan:mail-domain-warning] MW1 — The warning`.
  - A pure function takes the addresses and a lookup it is given, so its tests run without DNS. `ws_mail_send_check()` calls it unless the send carries the "send anyway" mark (`workspace/mail-send.md`, `mail-compose.md`); the answer's route too (`mail-reply.md`).
  - The form shows the warning over its buttons, with "Send anyway" and "Change the address". The API route answers with the same warning and takes the same mark (`mail-api.md`). Texts through `lang()` and `tools/tr_add.php`.
  - First, on dev: why did conversation 48 get no "Not delivered" line, did a failure notice reach the box? What is found goes into `workspace/mail-sent-and-bounces.md`. A fix of that is not this step: it is told to the architect.
  - Close-out: the behaviour goes to `workspace/mail-domain-warning.md`; this plan is deleted.

## What is not in the plan?
- A change of the queue's word "Sent"; a check that the mailbox itself exists (no server tells it reliably); a list of well-known domains to compare with.
