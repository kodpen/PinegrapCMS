---
name: messages-area-census-screens
description: MA0 census (2026-11-13): today's campaign, campaign profile, SMS campaign and mail queue screens, their files, menu place and the right each asks, before the messages area replaces My Campaigns
---

## Which screens hold messages today?
- My Campaigns (`view_email_campaigns.php`): ready and paused e-mail campaigns, manual and automatic (a profile's send), printed whole on the list table driver. Toolbar: Create, My Campaign History, My SMS Campaigns (only while `pg_sms_ready()`).
- Create Campaign and Edit Campaign (`add_email_campaign.php`, `edit_email_campaign.php`, shared `includes/forms/email_campaign_form.php`), the Send window (`send_email_campaign.php`, a pop-up sending batches over fetch), Delete Selected (`delete_email_campaigns.php`).
- My Campaign History (`view_email_campaign_history.php`): complete and cancelled campaigns, the same list code (`includes/fn/email_campaign_list.php`).
- My Campaign Profiles (`view_email_campaign_profiles.php`, CSV export `?export`), Create and Edit Campaign Profile (shared `includes/forms/email_campaign_profile_form.php`), Duplicate (`duplicate_email_campaign_profile.php`), Import (`import_email_campaign_profiles.php`).
- My SMS Campaigns (`view_sms_campaigns.php`) and the SMS campaign screen (`edit_sms_campaign.php`): no menu entry, reached from My Campaigns' toolbar only.
- Mail queue (`mail_queue.php`): E-mail and SMS tabs (`?tab=sms`), count cards, Queue active or inactive, Run now, Retry, Delete. Reached from Site Settings › Jobs, the bell and the ops alerts, not from the menu (`ui/mail-queue-paging.md`).
- The web version of a sent campaign (`view_email_campaign.php?r=<reference_code>`): public, no sign-in.

## Where do they sit in the menu?
- Row 9 "My Campaigns" (Kampanyalarım) under Marketing (`pg_menu_categories()`, slots 6, 7, 9, 16), shown for role <= 2 or the Campaign Manager right. Its panel: My Campaigns, Create Campaign, My Campaign History, My Campaign Profiles, Create Campaign Profile (`includes/fn/output.php`).
- The active-row map in `output.php` lists the campaign and profile files under row 9 and `mail_queue.php` under row 25 (System). The SMS campaign screens are in neither.

## Which right does each screen ask?
- Every campaign, profile and SMS campaign screen: `validate_email_access()` (`includes/fn/auth.php`): role <= 2, or role 3 with the Campaign Manager right (`user_manage_emails`, "Send Campaigns" on the Users list).
- A role 3 user sees and changes only rows they created: `email_campaigns.created_user_id`, `email_campaign_profiles.created_user_id`, `sms_campaigns.created_by`. Checked in each screen and in `pg_email_campaign_list()`.
- Create Campaign also asks `check_view_access()` for the body page's folder and `validate_contact_group_access()` for each recipient group.
- Mail queue, SMTP Settings and the Site Settings dialog (the Communication cards): `validate_area_access($user, 'manager')`, role <= 2. The queue holds password resets and receipts, so a role 3 campaign maker never sees it.
- `email_campaign_job.php` and `mail_job.php` run from cron; opened in a browser they ask manager.
- The SMS campaign screens also need `pg_sms_campaigns_ready()` (the 11.0.137 tables).

## Which Communication settings cards are there?
- Site Settings category `contact`, "Communication" (İletişim), `includes/settings/contact.php`: Campaigns (Opt-In Label, Plain Text Footer), Chat, MailChimp, SMS (provider, sender, test message), SMS Messages (order and back-in-stock texts), İYS.
- The e-mail sender is SMTP Settings (`smtp_settings.php`, System row). WhatsApp's card comes with WA1 (`plans/whatsapp-channel-steps.md`).

Related: [[messages-area-census-tabs]], [[messages-area-census-addresses]], [[messages-area-census-sends]]
