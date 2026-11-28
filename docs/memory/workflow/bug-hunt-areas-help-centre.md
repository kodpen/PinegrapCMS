---
name: bug-hunt-areas-help-centre
description: Bug hunt of the help centre (HC1–HC9, architect 2026-11-28): faults fixed, what was read clean, what was left
---

## What was hunted?
- The help centre of 2026.11.0, first hunt there: `support_request.php`, `kb_vote.php`, `article_suggest.php`; `includes/fn/support_requests.php`, `support_forms.php`, `kb_votes.php`, `widgets_support_requests.php`; `assets/js/pg_kb_vote.js`; `includes/workspace/mail_priority.php` and the resolve-by time in `mail_status.php`. Start files: `designer/help-centre-collections.md`, `workspace/support-request-page.md`.

## Which faults were fixed? (hunt PR, see the PR number in git log)
- An answer written on the request's page to a request whose subject is 252 characters or longer opened a new conversation, and the customer never saw it on the page. `ws_mail_reply_subject()` cut "Re: … [#T<id>]" to 255 characters and took the tag with it; the page's answer has no threading headers, so the tag is all that leads it back. The text now gives way to the tag (`workspace/mail-reply.md`).

## What was read clean?
- The signed link: token, check, the box's kind, the customer's address; the page changes nothing on GET, the answer posts with the form token, 303 back, limits per address.
- Which mails the page shows (customer's own in, answers sent or queued to them; drafts, later, failed and others' mails hidden), escaping of every value, the My Account rows and their links.
- The support form's mail: subject, body, sender name, header injection, the tie to user and contact, the duplicate refusal by Message-ID, the settings check and save.
- `kb_vote.php`: method, header, cookie, rate limit, collection record only; the cookie value and its 100 ids; the buttons' binding and the script's show, thank and retry; the record list's counts.
- `article_suggest.php`: query length, folder fence and visitor's folders, rows without files, the client script writing text only.
- Priority: filter, badge, change, hours per priority on the list, the channel card and the API; source label and its link. Resolve-by: due, overdue, input and save.

## What was left?
- Nothing asked. A vote's rows stay when its record is deleted (no personal data, counts read by record id only).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/support-request-page.md
