---
name: bug-hunt-areas-mail-campaigns
description: Bug hunt of bulk e-mail campaigns and their lists (area 25) (#11402): the three faults fixed, what was read clean, what was left as a decision question
---

## What was the area?
- Given by the architect: bulk mail and its lists. Read: `email_campaign_job.php`, `send_email_campaign.php`, `includes/fn/email_campaign_send.php`, `email_campaign_create.php`, `email_campaign_recipients.php`, `email_preferences.php`, `get_email_preferences.php`, `view_email_campaign.php`, `create_auto_email_campaigns()` and `replace_variables()` in `includes/fn/mail.php`, the counts in `messages_campaigns.php`, `mail_bounce_report.php`, `edit_email_campaign.php` and the API's `campaigns.php`.

## What did it fix?
- A scheduled campaign mailed a person who unticked its list after it was made; an automatic commercial mail kept with only an address reached an address whose contacts had all unsubscribed; Send now left `^^order_reference_code^^` raw. All three (#11402): `core/email-campaign-send-opt-out.md`.

## What was read clean?
- Sent twice: the claim UPDATE in both paths, the stale claim after 15 minutes, follow-ups and `campaign.sent` only from the run that finished the campaign (`core/email-campaign-send-claims.md`); the legacy path under `LOCK TABLES`.
- Bounced and complained addresses skipped whatever the purpose; an erased person's recipient rows deleted by contact and by address (`privacy.php`).
- The unsubscribe link: signed per address (HMAC over the lowercased address), refused without the signature; one-click works without sign-in; a signed-in visitor changes only their own account.
- The send limit per run: the setting refuses anything under 1, the job casts it into its LIMIT.
- Personal fields: `replace_variables()` escapes each value for an HTML body and fills every variable in one pass.
- Counts: delivered is `complete = 1 AND failed = 0` on the send window, the list cell and the API; the list's progress counts done, failed included, beside it. There is no open or click count for e-mail campaigns: the web version only ties the visitor (`campaign_click`).

## What was left as it is?
- An address the sender types into "Also send message to the following e-mail address" gets a commercial campaign even when its contact unsubscribed: the sender named it by hand, and the code has kept it so on purpose since the first version; a change is the owner's.
- A ready campaign whose every recipient row was deleted (all erased) stays ready, as the job only reads campaigns through their recipients; nothing is sent, so not changed.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/email-campaign-send-opt-out.md, docs/memory/core/email-campaign-send-claims.md
