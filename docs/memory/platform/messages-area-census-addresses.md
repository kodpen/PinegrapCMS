---
name: messages-area-census-addresses
description: MA0 census (2026-11-13): addresses never moved (web version, preferences, cron jobs, API), old panel addresses and the tab each leads to, and every link in the code to them
---

## Which addresses must never move?
- `view_email_campaign.php?r=<reference_code>`: the "View this email at our site" link in every HTML campaign, automatic campaign and order campaign ever sent (`add_email_campaign.php`, `includes/fn/mail.php`, `includes/fn/order_completion.php`). It stays public and where it is.
- `email_preferences.php?id=…` (Update email preferences or unsubscribe, in the same footer) and the one-click List-Unsubscribe address `email()` adds to a commercial campaign: outside the area, untouched.
- `email_campaign_job.php` and `mail_job.php`: cron lines on servers (`includes/settings/prep.php` prints them, `includes/fn/cron.php` lists them). Never renamed.
- The external API routes (`/campaigns`, `/campaigns/sms`, `/campaigns/profiles`, `api/api-campaign-*.md`) and the `campaign.sent` event: not screens, unchanged.

## Where would each panel address lead, and in which step (proposed, #9081)?
- `view_email_campaigns.php`: stays, it is the Campaigns tab (MA1, MA3).
- `view_email_campaign_history.php`: the Campaigns tab with the finished filter (complete, cancelled), MA3.
- `view_sms_campaigns.php`: the Campaigns tab with the SMS channel filter, MA3. `edit_sms_campaign.php` stays the SMS campaign's screen inside the shell.
- `add_email_campaign.php`, `edit_email_campaign.php`, `send_email_campaign.php`: stay the e-mail campaign's screens inside the shell; once MA3's "New campaign" asks the channel first, `add_email_campaign.php` opens it with e-mail chosen.
- `mail_queue.php` and `mail_queue.php?tab=sms`: the Sends tab with the e-mail or SMS channel filter, MA2. Its Run now, cards and Queue active line move with it.
- `view_email_campaign_profiles.php` and the profile screens: stay until MA9d, then lead to Automatic sends.

## How would an old address lead (proposed, #9081)?
- A screen whose job moved keeps its file as a door: `validate_user()`, then `go()` to the new address. The door carries only known keys across (`tab=sms` becomes the channel filter, `id`), never the whole query string, and asks no right of its own: the new screen's gate answers.
- No file is deleted: bookmarks, help pages, staff e-mails and the bell's old notices carry them.

## Which links in the code point at these screens?
- Menu panel and active-row map: `includes/fn/output.php` (row 9, row 25 for `mail_queue.php`).
- Panel search: the Email Campaigns and Add Email Campaign actions (`includes/panel/search.php`); profile results open `edit_email_campaign_profile.php` (`assets/js/backend.src.js` `email_campaign`, `includes/dashboard/v2/personal.php`).
- Dashboard: widget 7's campaign links (`includes/dashboard/widgets/widget_7.php`), the Send newsletter figure (`includes/dashboard/v2/figures.php`).
- Site Settings: Jobs › Mail queue and its search words (`includes/settings/registry.php`); the SMS card's queue line (`includes/fn/sms.php`, `mail_queue.php?tab=sms`).
- The bell's `mail_failed` notice (`includes/notifications.php`) and the ops alerts (`includes/fn/ops_alerts.php`): `mail_queue.php`.
- Help pages: `get_help_url.php` maps each screen name to a pinegrap.com page (`campaigns`, `site-settings#mail-queue`); a new tab screen gets an entry.
- List names kept in the session: `includes/fn/list_table.php` (`view_email_campaigns`, `view_email_campaign_history`, `view_email_campaign_profiles`, `view_sms_campaigns`; the queue's `mail_queue`, `mail_queue_sms`).
- Redirects after a save inside the screens themselves (`header('Location: …view_email_campaigns.php')` and the profile and SMS equivalents) and the `send_to` return addresses.

Related: [[messages-area-census-screens]], [[messages-area-census-tabs]]
