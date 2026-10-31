---
name: sms-campaign-limit-and-labels
description: SMS campaign text limit with the appended sender identity (#3956), the scheduled/queued labels (#3957), xref rows deleted with a contact group (#3958)
module: sms_campaigns
---

## How long may a campaign text be?
- `pg_sms_campaign_body_limit($marketing)` = `pg_sms_max_length()` less `"\n"` + `pg_sms_marketing_footer()` (identity line + type label), the part `pg_sms_check()` appends before it applies the 1000 limit. The box's `maxlength`, the live "characters left" counter and the save check (`pg_sms_campaign_decide()`'s `$limit`, counted through `pg_sms_text()`, drafts too) use it. Before, a 990-character text saved and every number was refused at queue time (#3956).

## What do the statuses read?
- `ready` is "Scheduled" (tr "Zamanlandı", shared with the e-mail campaign's `ready`), `queued` is "Queued for sending" (tr "Kuyruğa alındı"); "Queued" stays for the mail and SMS queues ("Sırada"). Before, tr read "Kuyrukta"/"Sırada", so a scheduled campaign looked queued (#3957).

## What goes when a contact group is deleted?
- Deleting a group (`edit_contact_group.php`, API `DELETE /contact-groups/{id}`) deletes its `contact_groups_sms_campaigns_xref` rows when `pg_schema_has()` finds the table, beside the e-mail xref (#3958).

Related: docs/memory/platform/sms-campaigns.md, docs/memory/ui/contact-group-shared-form.md
