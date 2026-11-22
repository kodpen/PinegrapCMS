---
name: code-lookup-ignorable-characters
description: utf8mb4_unicode_ci weighs NUL, U+0001 and a zero-width space as nothing, so a lookup by a code column that can be empty matched them; the campaign web version checks the code's shape
---

## What is the trap?
- Under `utf8mb4_unicode_ci` (the collation of the starter tables) some characters weigh nothing: `'\0' = ''`, `CHAR(1) = ''` and a zero-width space `= ''` are true, as is any string made only of them. Trailing spaces are ignored too (PAD SPACE).
- So `WHERE code = ?` with such a value finds the rows whose code is still empty.
- A column that is UNIQUE and always filled (`orders.reference_code`, `forms.reference_code`) is safe; one that starts empty is not.

## Where did it apply?
- `view_email_campaign.php?r=` looks up `email_recipients.reference_code`. A recipient gets its code only when the campaign is sent (`generate_email_recipient_reference_code()`, called by `send_email_campaign.php` and `email_campaign_job.php`); until then it is empty.
- `?r=%00`, `?r=%01` or `?r=%E2%80%8B` showed a signed-out visitor an unsent campaign, such as a delayed gift card e-mail with its code (2026.4.10 too).
- The lookup now runs only when `pg_email_recipient_code_valid()` (`includes/fn/forms.php`) passes: one to ten letters and digits, as the generator writes them. The query also says `reference_code <> ''`. Anything else ends on "The e-mail campaign could not be found…".
- A test send fills the placeholder with nothing, so its link is `?r=`: the signed-in path, as before.

## What else changed with it?
- A recipient whose campaign is gone ends on the same error. The screen read the values of a missing row: 8 warnings and an empty page.
- The Turkish starter shipped 36 recipient rows for campaigns it does not ship, some with codes anyone can read in the dump. They are gone from the dump. Running sites keep theirs; no campaign has those ids, as the starter's campaign counter starts at 37.
- PR #PRNUM; `tests/email_campaign_web_version_test.php`.

## What should a new lookup by a code do?
- Check the value's shape, or add `<> ''` to the query, before looking up a column that may hold an empty code.

Related: docs/memory/data/sql-silent-wrong-results.md, docs/memory/commerce/order-reference-code-link.md
