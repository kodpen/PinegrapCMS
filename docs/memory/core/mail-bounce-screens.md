---
name: mail-bounce-screens
description: Where the bounce record is shown: campaign delivery counts, the Suppressed contact view and export column, the contact card badge with Allow again, personal data export and erasure
module: mail_bounce_report
---

## Which screens show it?
- Helpers in `includes/fn/mail_bounce_report.php`; the record and the gate stay in `mail_bounces.php` (`mail-bounces.md`).
- The Campaigns tab (`view_email_campaigns.php`, rows in `includes/fn/messages_campaigns.php`): the delivery report under each e-mail campaign's Result.
- `pg_mail_bounce_campaign_counts()` reads the list in two grouped queries: recipients (`pg_mail_bounce_campaign_counts_sql()`: delivered = complete and not failed, suppressed = failed with `attempts = 0`; before the retry columns every complete row is delivered) and the bounce record (`pg_mail_bounce_campaign_bounces_sql()`: hard and soft count as bounced, complaints apart, manual not at all). `pg_mail_bounce_campaign_cell()` prints the delivered count and a badge per non-zero other count.
- Contacts: view "Suppressed" (`pg_contact_list_filters()`, every role; condition `pg_mail_bounce_suppressed_sql()`, `(1 = 0)` before the table exists). The CSV export ends with `mail_status`: the suppressing kind, empty while mailed (`pg_mail_bounce_status_sql()`, COALESCE). The import does not know the column and names it as not recognised.
- Contact card (`edit_contact.php`): under Opt-In a "Suppressed" badge with the kind and the last bounce time, and "Allow again" (a submit of the card's form, `submit_allow_again`). The POST lifts the block on the contact's stored address (`pg_mail_bounce_allow_contact()` → `pg_mail_bounce_clear()`, which logs), saves nothing else and comes back to the card. Staff in the panel is a proving source (#5366), so this needs no consent row.

## Why per address, not per send?
- `mail_bounces` holds one row per address with the campaign of its last bounce, so a campaign's bounced and complained counts are the addresses whose last bounce came from it; a later bounce from another campaign moves the address there. Suppressed is per send (the recipient rows).

## Personal data?
- Export section `mail_bounces` after `mail` (`personal-data-export.md`), by the person's addresses. Erasure deletes the rows (`pg_privacy_erase_tables()`, found by `address`): an erased person's address is mailed again if it comes back.

## How was it verified?
- `tests/mail_bounce_report_test.php` (the SQL builders, the cell and badge, the view, the call sites, the export and erase entries). No database: the screens were not opened.
