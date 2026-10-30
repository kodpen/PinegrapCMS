---
name: widget-entity-fields-account
description: W8 account values (the member's own): which widgets offer them, the __member_ names, gates (reward and affiliate programs), country and time zone words, when they are read.
---

## Which account values are offered, and where?
- `__member_reward_points`, `__member_expiry_date`, `__member_salutation`, `__member_nickname`, `__member_company`, `__member_job_title`, `__member_website`, `__member_mobile_phone`, `__member_business_phone`, `__member_home_phone`, `__member_business_city`, `__member_business_country`, `__member_time_zone`, `__member_tax_number`, `__member_tax_office`, `__member_affiliate_code`. Editor group "More account details" (`SW_MEMBER_ENTITY_TOKENS`) in my account, the profile, the login region and the account e-mail.
- The prefix is `__member_` because `__account_title` is the account invoices widget's ERP account name; `__member_id` stays the order view's.
- Whose: the signed-in member's own (my account, profile, login region signed in), or the addressee of a welcome or password reset e-mail. Nothing in the e-mail's unknown-address answer or the Preview.

## How are they read?
- `pg_sw_entity_account_tokens($user_id, $tree_json)` (`includes/fn/widgets_entity_fields.php`): nothing, and no query, unless the tree binds one (the login region sits on every page). `pg_sw_entity_account_read()`: one user + contact query (tax columns probed with `pg_schema_has()`), one for the country's name.
- Reward points only while `ECOMMERCE_REWARD_PROGRAM` is on (`0` shows then); the affiliate code only for an approved affiliate while `AFFILIATE_PROGRAM` is on; the expiry date in the site's date format, empty for `0000-00-00`; the time zone is the member's, else the site's, by its pick-list label, as the classic my account page shows it.
- Salutations are stored abbreviations (Dr., Mr.) and the tokens print them as stored. The pick lists show translated labels (`get_salutation_options()` returns label => value, `lang('Mr.')` => `'Mr.'`, #3579) and still post the abbreviation.
