---
name: email-campaign-send-opt-out
description: The opt-out reading of a commercial campaign recipient at the send (Send now and the job) (#11402): global opt-in by contact or by address, the campaign's own lists unticked since it was made
module: email_campaign_send
---

## What is checked before a commercial campaign mail goes out?
- `pg_email_campaign_recipient_opted_out($campaign_id, $type, $contact_id, $address)` in `includes/fn/email_campaign_send.php`, asked by Send now (`send_email_campaign.php`) and the job (`email_campaign_job_prepare()`); a recipient it refuses is deleted, as before. Transactional campaigns are not asked (they reach opted-out contacts, decided in #5441).
- `pg_email_campaign_opt_out_rule()` (pure) picks the reading: a recipient with a contact needs some contact with the address opted in (`contact`); an automatic campaign's recipient with `contact_id` 0 is refused when contacts have the address and none is opted in (`address`, `pg_email_campaign_address_refused()`); an address the sender typed in ("Also send message to the following e-mail address", a manual campaign with `contact_id` 0) is sent as entered.

## How are the campaign's own lists read?
- For a `contact` recipient, `pg_email_campaign_list_counts_query()` counts in the campaign's included groups (`contact_groups_email_campaigns_xref`): `opted_out`, subscription groups where a contact with the address has an `opt_in` row of 0, and `still_in`, groups it still takes mail from as the pick reads them (member, `contacts.opt_in = 1`, and for a subscription group a row of 1 or none).
- `pg_email_campaign_left_lists()` (pure) drops the recipient only when `opted_out > 0` and `still_in = 0`. No refusal recorded (group deleted, member removed by staff) leaves it to the global opt-in.
- The job's legacy path (no retry columns) locks the four tables read here `READ` beside its own.

## What did it fix?
- A scheduled campaign to a list mailed a person who unticked that list on the e-mail preferences screen after the campaign was made: the send read only `contacts.opt_in`, while the pick at creation reads the group's `opt_in` row.
- An automatic commercial mail stored without a contact (the action's contact merged or deleted, only its address left) was sent to an address whose contacts had all unsubscribed: neither creation nor the send read the address.
- Send now left `^^order_reference_code^^` raw in an abandoned-cart campaign; it fills it from the campaign's order, as the job does.

## Tests?
- `tests/email_campaign_opt_out_send_test.php`.

Related: docs/memory/core/email-campaign-send.md, docs/memory/core/auto-campaign-group-opt-out.md, docs/memory/ui/email-campaign-recipients-by-purpose.md
