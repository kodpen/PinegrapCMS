---
name: account-profile-default-screen
description: The default My Account Profile screen (no page of that type): page id read with a default, labels through lang(), countries named and ordered in the site language (PR #4832)
---

## When is the default profile screen drawn?
- `my_account_profile.php` calls `get_my_account_profile_screen()` (`includes/fn/forms.php`). With a page of type "my account profile" it renders that page; without one (visual-design sites have none) it calls `get_my_account_profile()` (`get_my_account_profile.php`) with no properties and wraps it in the secure header and footer. That screen is then every member's profile form.

## What went wrong on it?
- `$page_id = $properties['page_id'];` wrote `Undefined array key "page_id"` to `php_errors.log` on every open (on screen where display_errors is on). It now reads `$properties['page_id'] ?? 0`, like `get_email_preferences.php`, `get_forgot_password.php` and `get_update_address_book.php`; `get_layout_type(0)` finds no page and the system layout is drawn, as before.
- "Salutation", "Suffix", "Fax" and "Organization" were plain English while the other labels went through `lang()` (an earlier fix of this screen's labels left these four). They now use `lang()`; the Turkish keys already existed.
- The Country select listed the stored English names in their order ("Tunisia, Turkey, Turkmenistan") while checkout said "Tunus, Türkiye". It now builds its options like `get_express_order.php` and `get_billing_information.php`: `pg_geo_country_label()` per row, `pg_geo_sort_by_label()`, a shared label gets " (CODE)", the option value stays the code (`commerce/country-names-display.md`).

## What guards it?
- `tests/my_account_profile_default_screen_test.php` (PR #4832, issue #4793): the defaulted page id, the four labels through `lang()` with Turkish texts, the country options from the helpers and none keyed by the stored name.
