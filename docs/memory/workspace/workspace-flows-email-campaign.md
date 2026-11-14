---
name: workspace-flows-email-campaign
description: Send an e-mail box as a campaign (MA9b, #9435, flow_mail_campaign.php): Sender (From, Reply-to, BCC), ^^fields^^, page body with base tag and data, marketing ?t= codes, footer, group opt-out
---

## What does the box's Sender set?
- E-mail box settings `from_name`, `from_email`, `reply_to`, `bcc` (`ws_flow_mail_sender_normalize()`, in `ws_step_config_normalize()`): single lines, at most 100 characters; empty sends as the site (ORGANIZATION_NAME, EMAIL_ADDRESS, no Reply-to, no BCC), so a box drawn before sends as it did.
- Checked as a campaign's sender: each address given must pass `validate_email_address()`, one address, no list (`ws_flow_mail_sender_problems()` in `ws_step_config_problems()`). A From name alone keeps the site's address.
- Canvas: a folded "Sender" part under the e-mail's text in `emailFields()` (workspace_flow.js), open when one is set. A role-3 personal flow has no e-mail box at all (`workspace-flows-member.md`), so it is never offered sender or BCC.
- `ws_scheduled_mail()` takes the sender as its 7th argument (`ws_flow_mail_sender_properties()`: only the set ones override).

## Which ^^fields^^ are filled?
- `ws_flow_mail_fields()`: the run's start values (`$values[WS_STEP_TRIGGER]`, scalar ones with names `[a-z0-9_]`), then `^^name^^`, `^^first_name^^`, `^^last_name^^` from the recipient's contact (`pg_mail_merge_contact_fields()` in fn/mail.php, shared with `email_campaign_job.php`). A start value of the same name wins, as a profile's action fields are filled first.
- Contact: the record person's `contact_id`, else the first contact holding the address; a typed-address box has none, so the name fields are blank and `[[…||…]]` falls back.
- Filled with `replace_variables()` in subject and body (plain or page) in `ws_scheduled_do()`, after the `{{…}}` values.

## How is a page body sent?
- `page_id` > 0 (set by the API or a converted profile; the canvas has no picker, it shows "Sends page #N …"): `get_page_content()` with `data` = the run's record (`ws_flow_mail_record()`: `order`, `form`, `booking` or `contact` row read with the owner's rights; a received e-mail gives none).
- `ws_flow_mail_page_html()`: `pg_mail_merge_address_hrefs()`, then `pg_mail_page_absolute_links()` (base tag, absolute links and images). Only for a flow's box; a scheduled action's page is sent as before.

## What does marketing add?
- Page: `pg_mail_page_tracking_codes()` (`?t=` page name and time on links to the site) and the commercial footer (`pg_mail_commercial_content_html()`: organization's address, preferences and unsubscribe links whose `<email_address_id></email_address_id>` `ws_scheduled_mail()` fills per address). No "View this email at our site" (decided, `core/campaign-profile-flow-gaps.md`).
- Plain text: `ws_flow_mail_unsubscribe_footer()` now ends with the organization's line (`pg_mail_organization_line()`) before the preferences link.
- A run started by `contact_group.joined`: `ws_flow_mail_group_refusal()` sends nothing to a contact opted out of that group's e-mail (`pg_auto_campaign_group_opted_out()`), the box done with `sent` 0 and the reason; Try says it too.

## Shared with campaigns?
- `create_auto_email_campaigns()` calls the same `pg_mail_page_absolute_links()`, `pg_mail_page_tracking_code()`, `pg_mail_page_tracking_codes()`, `pg_mail_commercial_content_html()`; `email_campaign_job.php` `pg_mail_merge_contact_fields()` and `pg_mail_organization_line()`. Behaviour unchanged.
- Tests: `tests/workspace_flow_mail_campaign_test.php`; parity line `email.sender`, `email.fields`, `email.data`.
