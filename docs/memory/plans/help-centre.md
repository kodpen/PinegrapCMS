---
name: help-centre
description: Plan (owner 2026-11-27, poll 246): a knowledge base and support requests; the census is done, HC2 to HC6 need no table and no column, HC7 and HC8 add the columns and the table he approved (poll 252)
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): a knowledge base and support requests. The list it belongs to and its rules: `plans/global-features.md`.
- What the code has today: `plans/help-centre-census.md` and its parts (HC1).

## What did the architect settle after the census? (2026-11-27; not the owner's words)
- A request is a conversation of a support box, never a thing of its own: state, holder, first-answer target, rules, notices, the survey and the API are there. Its number is the conversation's id, `[#T<id>]`.
- A form, the panel's chat window and My Account open one in a chosen box with the customer's address; a form with no e-mail field tied to the contact cannot be a request.
- States stay Open, Waiting for the customer, Closed: no "Solved", no change of the `status` ENUM. The resolve-by time is computed from the box's hours, as the first answer is; its clock does not stop.
- The customer sees their requests on My Account, the mails in and out only, never the channel's notes, and answers there; a guest gets a signed link in the automatic answer, as event tickets have one (`commerce/event-tickets-key.md`).
- Articles stay a collection: no article table.
- Privacy: a person's requests join the personal data export; on erasure a request stays as mail does (`workflow/decisions-made-workspace-28.md`), also when a form or a chat opened it; no keep period of its own.
- Owner (2026-11-27, poll 252, `workflow/decisions-made-schema-54.md`): the priority column, the source columns and the votes table are added.

## Which steps? (S under 400 lines, M under 1,000; each waits for the one before it)
- HC1 Census: done, `plans/help-centre-census.md`.
- HC2 (M) The article search: a "Search only in" setting of the Search Results widget (a folder), and a public read endpoint over `search_items` narrowed the same way, rate limited, that a form's subject field calls as the visitor types to offer matching articles.
- HC3 (M) A form opens a request: a "Support request" switch in the form's settings with the box and the e-mail field; the conversation is made in that box and the automatic answer goes out; the customer's reply threads back.
- HC4 (M) My Account "My requests": number, subject, state, last answer; one opens to its mails with a Reply box; the guest's signed link to the same page.
- HC5 (S) The panel's chat window: "Make a request" opens a conversation with the chat's text in a chosen box.
- HC6 (S) Requests in the personal data export; the resolve-by time shown on the Incoming e-mail screen from the box's hours.
- HC7 (M) Priority and source by a migration step: the Priority column, filter and select on the Incoming e-mail screen, answer hours per priority on the box form beside "First answer within (hours)", the source label and its link back.
- HC8 (M) "Was this helpful?": `kb_votes` by a migration step, Yes and No on an article's page, a cookie against a second vote, the two counts on the collection's record list.
- HC9 (S) A category tree in one widget, with breadcrumbs from the parent chain.
- HC10 (S) Close-out: the lasting rules to `workspace/` and `designer/`, a ready "Help centre" set of collections named for the templates, the plan and the census deleted.

## What does every step keep?
- Screens in the look; an old screen a step touches is brought over (`workflow/decisions-made-product-118.md`). A new setting sits beside its related one.
- The customer never sees a channel note or another customer's mail: every read is tied to the conversation's `user_id` or the signed link.
