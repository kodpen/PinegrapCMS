---
name: campaign-test-send
description: "Send a test to me" on Edit Campaign (MA5e): to oneself and five more, the stored body after a save, never counted, ten tests an hour per user, the sample photo warning
module: campaign_test_send
---

## Where is it, and what does it send?
- On Edit Campaign's content card, at the end of its side, while a manual campaign can still be saved (not a sent one, not an automatic one): "Send a test" with a field for up to five more addresses, a line saying where it goes, and "Send a test to me" (`pg_campaign_content_card()`, option `test`).
- The button posts the form: the addresses are checked first (`pg_campaign_test_addresses()`, pure: one's own first, each once, split on commas, semicolons and spaces), then the campaign is saved as Save does, then the stored body is sent and the screen stays open with a notice naming the addresses.
- A wrong address, six more addresses, or no address at all (a user without one and nothing typed) stops with the error and saves nothing.

## How is it sent, and what is it never?
- `pg_campaign_test_send()` (`includes/fn/campaign_test_send.php`): subject "[Test] …", merge fields from the user's contact (`user.user_contact`), or Jane Doe when there is none, through `email()` with `pg_email_campaign_mail_properties()`, type campaign, so the campaign mail server is the one tried.
- Never counted: no `email_recipients` row, no reference code, no bounce reference (the campaign is passed without its id), no BCC; the web version and preferences links lead nowhere in a test.
- A refused send is a warning naming the address and SMTP Settings; each send writes an activity line.

## What is the limit?
- Ten presses an hour per user (`pg_campaign_test_hourly_limit()`), each press one whatever the number of addresses: a `waf_rate` bucket `campaign_test|<user id>`, failing open without the firewall's tables. Over it, the campaign is still saved and the warning says so.

## What is the sample photo warning?
- While the stored HTML body holds a ready design's stand-in picture (`picsum.photos`, `placehold.co`, `pg_campaign_test_sample_photos()`, pure), an amber line above the test asks for one's own pictures; the send is not stopped.

Test: `tests/campaign_test_send_test.php`.

Related: docs/memory/ui/campaign-content-step.md, docs/memory/core/email-campaign-send.md
