---
name: canvas-sample-gaps-account
description: CS0 census, account (CS4): my account, login, registration, password forms, preferences, address book, security, invoices, account e-mail card: canvas faults
---

## What is wrong in every account widget?
- `__site_name` draws "Example" (login, forgot, change and set password, logout, profile, e-mail preferences, address book, security, error page, login region, account e-mail, registration, membership); the page writes the site's name.
- `__password_rules` (change and set password, registration, membership) draws a ‹label› where the page writes the rules.
- `__opt_in_label` (registration, membership, billing) and `__member_id_label` draw "Example".

## What does the account e-mail card draw? (`SW_ACCOUNT_EMAIL_TOKEN_GROUPS`)
- The welcome and password reset e-mails (`account-email-pages.md`): only `__username` is a sample. `__user_name`, `__user_first_name`, `__site_name` "Example"; `__user_email` ‹label›; the links are addresses.

## What do the other account widgets draw wrong?
- My account: samples for the member's values and the last form; `__display_name` "Example"; `form_item_view` (old detail address name) ‹label›.
- Login region: `__user_name`, `__user_first_name` "Example"; ‹label› `__user_initials`, `__user_email`, `__user_badge`; `__user_avatar_url` the initials sample.
- E-mail preferences: ‹label› `__group_id`; the rest samples.
- Address book: `__recipient_name`, `__recipient_company` "Example", `__recipient_count` "1", ‹label› `__recipient_id`, `__recipient_address_html`. Entity values (`SW_ADDRESS_BOOK_ENTITY_TOKENS`): no sample; first and last name, city, state "Example", `__recipient_country` "1", ‹label› salutation, address lines, zip, address type.
- Account security: samples for the QR, the sign-in buttons and `security.passkeys`. `__device_label`, `__mfa_status_text` "Example"; `__device_count`, `__passkey_count` "1"; ‹label› `__device_user_agent`, `__device_ip`, `__device_last_used`, `__device_first_seen`, `__device_selector`, `__device_passkey`, `__mfa_enabled_since`, `__mfa_recovery_remaining`, `__mfa_recovery_left_text`, `__mfa_key`, `__mfa_key_uri`, `__recovery_codes`, `__passkey_notice`.
- Account invoices: no sample. `__account_title` "1" ("count" in "account"), `__document_count`, `__invoice_number`, `__invoice_order_number` "1"; `__balance`, `__overdue_total`, `__invoice_total` money; `__invoice_status` "Example"; ‹label› `__invoice_kind`, `__invoice_date`, `__invoice_due_date`, `__invoice_open`, `__invoice_overdue_days`.
- Member entity values (`SW_MEMBER_ENTITY_TOKENS`): no sample. `__member_tax_number`, `__member_tax_office` money; `__member_business_country` "1"; nickname, company, job title, business city "Example"; ‹label› reward points, expiry date, salutation, website, the three phones, time zone, affiliate code.
- Error page, cart link, affiliate sign-up: samples.
