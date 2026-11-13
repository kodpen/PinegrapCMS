---
name: messages-area-decided
description: The architect's decisions on the proposals of MA0 and MA4 (#9081, #9083): the shell, the Sends list as a union with its measure, e-mail source, rights, the e-mail design page
---

## What is decided after MA0? (architect, 2026-11-13, #9081)
- Shell: row 9 under Marketing titled "Communication" ("İletişim"); one file and gate per tab with a shared tab strip; `view_email_campaigns.php` stays the Campaigns tab; the other old list addresses become doors, no file is deleted.
- Sends list: one `UNION ALL` derived table in the list table driver's `from`, sid = id * 8 + branch (branches 1 to 7; an eighth source is asked), the period always set inside every branch on its indexed time and never switched off from the address.
- MA2 measures a batch with 500,000 rows per queue inside the period and writes the figures in its PR; over 300 ms the keyset bound is pushed into each branch (a driver change, its own step) before the tab opens to everyone. A table filled by every sender is refused.
- Source: a `source` key in `mail_outbox.properties`, its kind copied into `mail_type`; filter and search read `mail_type` only. The flow E-mail box queues with `flow:<id>` and the callers' outcome hook.
- Rights as today: queue rows for role <= 2, a role 3 user their own campaigns, Channels for role <= 2.
- Automatic campaigns leave the Campaigns list and show in Sends as single messages with their profile as source; the profile screens go only with MA9.
- Not asked now: the columns `email_recipients.sent_at` and `mail_outbox.source`; a need comes back as a question for the owner.

## What is decided after MA4? (architect, 2026-11-13, #9083)
- A designed body is an e-mail design: a page in the private "E-mail designs" folder, `props.pgEmail` on its root, a 600 px canvas with a 375 px Phone view, mail blocks in the palette.
- A mail block's stored tree stays semantic (classes and props, no inline style); its inline look is written when drawn, by one map per block kind in PHP with its canvas twin. The mail pass is one pure, tested function used by every designed e-mail body.
- The body is stored at each save of the campaign; the content step says when the design changed after it.
- Pasted HTML stays its own body (`page_id` 0), cleaned at save by a named outgoing mode of `includes/fn/mail_html.php` with its own tests; the incoming mode's lists are not loosened.
- Five ready designs in code as page trees (part of this plan, not design templates), the template's e-mail pages and earlier campaigns beside them; the body area is `_editable`.
- Preview in the guarded frame's mail mode; "Send a test to me" through the campaign mail server, never counted, limited per user (MA5 names the limit in its PR).
- Open and click tracking stays out: a product and consent question for the owner.

## Where are the proposals?
- MA0's census and proposals are the messages-area-census files of `platform/`, MA4's the campaign-email-body files of `designer/`, both landed by #9074; each says what the step that builds it keeps.
