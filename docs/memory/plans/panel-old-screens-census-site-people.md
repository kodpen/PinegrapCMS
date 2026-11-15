---
name: panel-old-screens-census-site-people
description: Census LM1b, people screens against look A (S2): users, sessions, contacts, contact groups, privacy export and erase, email and SMS campaigns, Messages, WhatsApp templates
---

## How is a line read?
- `file`: lines / marker lines / `style=` / fields / cards → estimate; method in `panel-old-screens-census-site.md`.

## Which users screens, and what is old? (200; 4,620 lines)
- Users list `view_users.php` 142 / 3 / 0 / 2 / 0 → 19 (toolbar, filter select).
- Create User `add_user.php` 378 / 3 / 1 / 4 / 6 → 23; Edit User `edit_user.php` 1,502 / 38 / 10 / 6 / 14 → 78: 17 Bootstrap buttons, an upper-case `small fw-semibold` section label, inline `margin`, `flex-basis` and `--pg-perm-color` on the permission grid; `includes/forms/user_form.php` 773 → 14.
- Sessions `view_sessions.php` 554 / 17 / 7 / 0 / 3 → 35: not on the table driver (#9230), three own `table table-hover` tables in cards.
- Import Users `import_users.php` 1,274 / 7 / 1 / 3 / 10 → 33: upper-case card titles.

## Which contact and privacy screens, and what is old? (370; 5,060 lines)
- Contacts list `view_contacts.php` 1,150 / 26 / 2 / 47 / 5 → 142, the largest list here: its Advanced Filters panel draws 47 fields with `fg-success fw-bold` headings, 16 Bootstrap buttons.
- `add_contact.php` → 6; Edit Contact `edit_contact.php` 469 / 14 / 3 / 2 / 10 → 38 (nine Bootstrap buttons); `includes/forms/contact_form.php` 1,008 / 4 / 4 / 11 / 16 → 58.
- Contact groups: `view_contact_groups.php` → 13, `includes/forms/contact_group_form.php` 386 / 10 / 3 / 2 / 8 → 30; the add and edit wrappers clean. Import Contacts `import_contacts.php` 754 → 20.
- Privacy export and erase (`privacy_export.php`, `privacy_erase.php`) 275 and 268 / 14 / 1 / 2 / 6 → 30 each: upper-case titles, an own results table.

## Which campaign and Messages screens, and what is old? (150; 4,200 lines)
- The Messages area (MA12 #9363, `ui/messages-area-look.md`) drew its tab strip, filter segments and Sends; the screens it left inside are the rows below. `view_email_campaigns.php`, `view_email_campaign.php`, `messages_sends.php` → 12 each (toolbar only); `messages_mail.php` clean.
- `add_email_campaign.php` → 3 and `edit_email_campaign.php` → 10, `includes/forms/email_campaign_form.php` → 14, `edit_sms_campaign.php` → 9: still without `pg-measure-edit`, left to the field-width work (`ui/look-screen-widths.md`).
- Profiles: `view_email_campaign_profiles.php` → 15, add and edit → 1 and 2, `import_email_campaign_profiles.php` 474 → 20. WhatsApp template `whatsapp_template.php` 590 / 1 / 0 / 6 / 12 → 37.
- S2 is these three parts together: about 720 by the estimate, 13,880 lines.
