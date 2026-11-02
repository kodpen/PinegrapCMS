---
name: sms-campaign-limit-and-labels
description: SMS campaign text limit and no maxlength, status labels, xref rows on group delete, save after the job claim, cancel confirm, read-only times, cut list name
module: sms_campaigns
---

## How long may a campaign text be?
- `pg_sms_campaign_body_limit($marketing)` = `pg_sms_max_length()` less `"\n"` + `pg_sms_marketing_footer()` (identity line + type label), the part `pg_sms_check()` appends before it applies the 1000 limit. The live "characters left" counter (red when over) and the save check (`pg_sms_campaign_decide()`'s `$limit`, counted through `pg_sms_text()`, drafts too) use it. Before, a 990-character text saved and every number was refused at queue time (#3956).
- The box has no `maxlength`: it counts UTF-16 units (an emoji is two) while the counter (`Array.from()`) and the server (`mb_strlen()`) count code points, so typing stopped while the counter showed room (#5698).

## What do the statuses read?
- `ready` is "Scheduled" (tr "Zamanlandı", shared with the e-mail campaign's `ready`), `queued` is "Queued for sending" (tr "Kuyruğa alındı"); "Queued" stays for the mail and SMS queues ("Sırada"). Before, tr read "Kuyrukta"/"Sırada", so a scheduled campaign looked queued (#3957).

## What goes when a contact group is deleted?
- Deleting a group (`edit_contact_group.php`, API `DELETE /contact-groups/{id}`) deletes its `contact_groups_sms_campaigns_xref` rows when `pg_schema_has()` finds the table, beside the e-mail xref (#3958).

## What do Delete Selected and Cancel Campaign do on a failed write?
- `view_sms_campaigns.php` stops with `Query failed.` when a DELETE fails; the xref DELETE runs only after the campaign row went, and a campaign is counted in the notice only after both succeeded.
- Cancel (`edit_sms_campaign.php`) stops on `=== false` and logs "SMS campaign cancelled" only when `mysqli_affected_rows()` is 1 (a job that claimed it first leaves nothing cancelled). Before #4132 both logged and announced success after a failed write. Pin: `test_sms_campaign_screen_writes_are_checked`.
- The Cancel Campaign button asks first (`data-confirm-content`: a cancelled campaign cannot be scheduled again); the list then shows "The SMS campaign was cancelled.", or an error when no row changed (#5698).

## What does a read-only campaign show?
- Status, then Send Time (`start_at`) and Queued for sending (`queued_at`, written by the job) through `get_relative_time()`, then the counts when queued (#5698).
- The list cuts a name over 50 characters with `…` and a `title` (panel cells do not wrap); the checkbox and Edit labels keep the whole name (#5698).

## What does Save do when the campaign was queued meanwhile?
- `pg_sms_campaign_save()` takes `mysqli_affected_rows()` right after its UPDATE (`WHERE status IN ('draft', 'ready')`); with no changed row it reads the status and returns `-1` unless the campaign is still `draft`/`ready` (`pg_sms_campaign_update_landed()`), before the xref rows are touched. The screen sends the user back to the list with "…before your changes could be saved. They were not saved." Before #4707 the groups of a queued campaign were rewritten and the edit reported as scheduled.

Related: docs/memory/platform/sms-campaigns.md, docs/memory/ui/contact-group-shared-form.md
