---
name: campaign-profile-flow-email
description: An e-mail campaign profile's purpose, consent, recipient, fields, HTML page and sender laid against the flow's e-mail box (MA9a, #NNNN): what each does today and where they differ
---

## Purpose and consent?
- `transactional` → the e-mail box's Why `info`; `commercial` → `marketing`.
- Profile, commercial: skips a contact whose `opt_in` is not 1 and, for "joined a group", one opted out of that group (`pg_auto_campaign_group_opted_out()`); an address with no contact is sent to. The campaign send drops İYS refusals (commercial) and bounce-blocked addresses (any purpose).
- Flow, marketing (`ws_flow_mail_marketing_refusal()`): a contact with the address and `opt_in` 1, not refused in İYS, not blocked; no group opt-out. Stricter for an address without a contact, looser for a group opt-out.

## The e-mail itself?
- To: the action's contact's address, else the address passed (billing, booking, card holder). Flow `record_person`: the record's address (`ws_flow_mail_person_source()`); `calendar_event.reserved` is about a calendar event, so it has nobody.
- Fields: the profile fills the action's `^^fields^^` at the action; at send `email_campaign_job.php` adds the recipient's `^^name^^`, `^^first_name^^`, `^^last_name^^`, `^^action_date_and_time^^`, a reservation's event times, an abandoned order's reference code. A flow fills `{{…}}` in subject and plain text only.
- HTML page: the profile renders it with the action's `data` for the page's PHP, a base tag and absolute links, `?t=` codes on same-site links, the organization's address and the preferences links when commercial, and "View this email at our site". A flow renders the page with no data and fills nothing in it; marketing adds only the unsubscribe footer.
- From: the profile's `from_name`, `from_email_address`, `reply_email_address`, `bcc_email_address`; a flow always ORGANIZATION_NAME and EMAIL_ADDRESS, no reply-to, no BCC.
- A profile mail is an automatic `email_campaigns` row (history, delivery counts, bounces by campaign, `campaign.sent`); a flow mail is a box result sent with `email()`.
- Where the gaps are closed: [[campaign-profile-flow-gaps]]; the start and the wait: [[campaign-profile-flow-map]].
