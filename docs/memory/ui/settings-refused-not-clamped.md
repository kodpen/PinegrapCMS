---
name: settings-refused-not-clamped
description: Site Settings boxes a save refuses instead of clamping or blanking: firewall and sign-in limits, tracking days, analytics IDs, merchant fields (pg_whole_number_in_range(), pg_ga_tag_id())
---

## Which whole-number boxes are refused instead of clamped?
- `pg_whole_number_in_range($raw, $min, $max)` (`includes/fn/ecommerce.php`, beside `pg_parse_whole_number()`) reads the same grouped figure ("2.400" on a Turkish panel is 2400) but gives null for blank, letters, a sign, a fraction or a figure out of range.
- The save marks the field with "{var:1} must be a whole number from {var:2} to {var:3}." (the box's own label) and returns before any write: the firewall limits in `firewall.save.php` (before the country block, which writes first), the sign-in throttle's attempts, minutes and lockout in `security.save.php`, Tracking Code Duration 1-365 (`privacy.save.php`), the merchant transit days 0-60 with max not below min (`seo.save.php`).
- Only a posted field is checked (`array_key_exists`), so a card not drawn on an older schema never blocks the save. The `pg_parse_whole_number()` clamps stay after the check (`tests/settings_whole_numbers_test.php` reads them).
- Why: an emptied box read as 0 was clamped to the strictest bound and the dialog said "saved": one wrong password locked an account, ten requests a minute for every visitor, a ban after one offence, 0 tracking days (an expired cookie, no attribution).
- Boxes where 0 means "off" are refused the same way, so only a typed 0 switches them off: Form Submission Limits 0-10000 (Per visitor address, Per e-mail address, Per e-mail domain) and Ask a Question After 0-100 (`security.save.php`). Before #9084 a blank box, or one a password manager filled with a user name, was read as 0 and the limit went off without a word.

## What else does a settings save refuse rather than change?
- Membership expiration warning switched on: a page and 1-999 days are required (`security.save.php`); 0 or negative days warned members already expired, no page sent nothing.
- Merchant country not two letters, return window not a whole number: refused (`seo.save.php`). Before, the country was blanked (no shipping or return markup site-wide) and "abc" days became 0, "returns not permitted" on every product.
- IndexNow: the trimmed key is stored, the key file uses the same; a stored key is trimmed when read, so its old file is removed on change.

## How are the analytics settings checked and printed?
- `pg_ga_tag_id()` (`includes/fn/consent.php`, always loaded): G-, GT-, AW-, DC- or UA- plus letters, digits, dashes, else ''. The save refuses anything else; `get_page_content.php` prints no gtag tag for a stored value it refuses, and prints the ID `rawurlencode()`d in the address and `escape_javascript()`d in the script.
- Website Analytics URL: http(s) only on save; the Visitors menu (`output_menu()`) links it only when it starts with http(s) and through `h()`.

Tests: `tests/settings_whole_numbers_test.php`, `tests/consent_test.php`.
