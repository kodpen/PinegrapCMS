---
name: workspace-flows-email-group
description: Flow e-mail box to a contact group (post-digest DG2): one campaign through the campaigns' create path, the records block, nothing at count 0, Try mails only the trier
---

## What is it?
- E-mail box recipient `group` beside `typed` and `record_person` (`ws_step_config_normalize('email')`): `group_id`, and `records`, the id of a "Read the records of a list" box (`list_records`) whose records are drawn after the text. Both are 0 and '' for the other recipients, so a box saved before keeps its settings.
- Code: `includes/workspace/flow_mail_campaign.php` (group part); tests `tests/workspace_flow_mail_group_test.php`.

## How is it sent?
- One e-mail campaign, never single mails: `pg_email_campaign_pick_recipients($owner, purpose, [group => included], '')`, then `pg_email_campaign_insert()` with status `ready` and start time `1000-01-01 00:00:00` (send now, as the API does). The campaign job sends it, so opt-in, bounced addresses (`pg_email_campaign_skip_suppressed()`), İYS and the footer are the campaign's, and it stands in the campaigns list with its counts.
- Purpose: a marketing box is a commercial campaign (opted-in contacts only), an info box a transactional one.
- `includes/fn/email_campaign_create.php` is not loaded by functions.php: the group functions `require_once` it.
- Owner: role below 3 and a group that exists (`ws_flow_mail_group_refusal_for()`), at publish and at each run. A page body is refused for a group: the box writes its own text.

## What is in the body?
- `ws_flow_mail_digest_html()`: a whole HTML document, 600 px card: the box's text as paragraphs, then each record with its picture, linked title and summary; every text escaped, a picture or link only an http(s) address (`ws_flow_mail_digest_items()`). `pg_email_campaign_prepare_html_body()` adds the base, `?t=` codes and the campaign footer.
- The records come from the run's values of the records box (`records`, the JSON list). A records box that read none: the box is done, `sent` 0, no campaign (D9).

## Whose look?
- Owner (2026-11-16, agent channel poll 109, message 7241): the mail as drawn here, a 600 px card with the box's text and each record's picture, linked title and summary, approved: "Evet, böyle çıksın". A picture carries the title's link and alt; a tall one is drawn whole at the card's width.

## What does Try do?
- Mails the e-mail to the person trying alone, as a campaign's test (`pg_campaign_test_send()`, `campaign_test` rate limit of `pg_campaign_test_hourly_limit()` an hour) and says how many of the group a run would reach. No campaign is made; the records are the chosen run's, else what the records box would read now; nothing is written, so no window moves.

## What is not verified?
- No run on a database; the canvas recipient picker and the records box choice are DG3.
