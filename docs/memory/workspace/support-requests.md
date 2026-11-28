---
name: support-requests
description: Support requests (help centre) as a whole: a support-box mail conversation, its doors, what the customer sees, privacy, what was left out, which file holds each part
---

## What is a support request?
- Owner (2026-11-27, agent channel poll 246, `workflow/decisions-made-product-117.md`): the product gets a knowledge base and support requests. The articles side: `designer/help-centre-collections.md`.
- Architect (2026-11-27): a request is a conversation of a support box (`mail-support-box.md`), never a table of its own. State, holder, first-answer target, rules, notices, `workspace.mail.closed`, the survey and the API are already there; a table of its own would build each again and give the team a second list and clock.
- Its number is the conversation's id, shown as `#T<id>` and carried as `[#T<id>]` in every answer's subject; no number column.

## Which doors open one?
- E-mail, WhatsApp and SMS to a support box: as any mail (`mail-ingest.md`, `mail-phone-decisions.md`).
- A custom form with "Support Request" on: `support-form-request.md`. Architect (2026-11-27): a form with no e-mail address field cannot be a request.
- The panel's chat window, "Make a support request": `support-chat-request.md`.
- My Account "My requests" and the signed page's Reply box: `support-request-page.md`.
- Every door ends in `ws_mail_ingest()` with a Message-ID of its own, so a repeat is a duplicate, and the automatic answer carries the number and the signed link.
- Not moved: the App Studio template's Support Requests stay form records worked on the panel's forms screen (`designer/design-template-appstudio.md`); a site built from it keeps them.

## Which states, priority and times?
- Architect (2026-11-27): states stay Open, Waiting for the customer, Closed (`mail-status.md`): no "Solved", no change of the `status` ENUM.
- Architect (2026-11-27): the resolve-by time is computed from the box's hours as the first answer is, and its clock does not stop while waiting for the customer (`mail-resolve-by.md`).
- Priority and source columns (owner's yes, `workflow/decisions-made-schema-54.md`): `mail-priority-source.md`.

## What does the customer see?
- Architect (2026-11-27): their own requests on My Account and by a signed link for a guest, the mails in and out only, never a channel note or another customer's mail; every read is tied to the conversation's `user_id` or the signed link (`support-request-page.md`).

## How does a request leave with the customer's data?
- Architect (2026-11-27): a person's requests join the personal data export (`core/personal-data-export-support-requests.md`).
- Architect (2026-11-27): on erasure a request stays as mail does (`workflow/decisions-made-workspace-28.md`), also when a form or a chat opened it, although the form or chat itself is deleted; no keep period of its own.

## What is left out, to ask before building?
- The external API and MCP neither read nor write priority or source; the resolve-by time is not on the channel card, the API or any notice or rule.
- No rule action sets a priority; a mail rule assigns and closes only (`mail-rules.md`).
- No `account.requests` list source for the designer; a request made for someone else's address reaches no account.
- How many sites keep a support box was not counted (the code holds no such figure).

## How is a part added?
- A new door builds a mail and hands it to `ws_mail_ingest()` with `source` and `source_id` and a `tie` only when the address matches (`pg_support_form_tie()`); it never writes `ws_mail_conversations` itself.
- A screen word for requests asks `ws_mail_box_is_support()`; a customer-facing read goes through `pg_support_request_item_visible()`.
