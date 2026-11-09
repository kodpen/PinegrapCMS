---
name: auto-campaign-group-opt-out
description: A commercial "joined a contact group" campaign profile skips a contact whose opt_in row for that subscription group is 0, as a manual campaign to the group does (#7596)
module: mail
---

## When does a commercial "joined a group" mail skip a contact?
- `create_auto_email_campaigns()` (`includes/fn/mail.php`), action `contact_group_joined`: besides `contacts.opt_in != 1`, a commercial profile is skipped when the group takes e-mail subscriptions and the contact's `opt_in` row for it says 0 (`pg_auto_campaign_group_opted_out()`, pure). A missing row is subscribed. Transactional profiles are not affected.
- Read once per call, before the profile loop: `contact_groups.email_subscription` with a LEFT JOIN of the contact's `opt_in` row.

## Why?
- The `opt_in` row survives leaving and rejoining a group (the contact screen, the API's `add` without `opt_in`), so a contact who unsubscribed from a group and was put back got the group's commercial welcome mail. A manual campaign to the group already leaves them out (`pg_email_campaign_opt_in_condition()`, `opt_in.opt_in = 1 OR IS NULL`).

## Tests?
- `tests/auto_campaign_group_opt_out_test.php`.

Related: docs/memory/ui/email-campaign-profile-shared-form.md, docs/memory/workspace/contact-group-joined-event.md
