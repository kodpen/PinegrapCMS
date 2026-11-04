---
name: settings-security-cards
description: Accounts and sign-in and Firewall and HTTPS (keys security, firewall): where the encryption key, blocked e-mail addresses and trusted proxies sit and which save writes them.
module: encryption_key
---

## What changed with the names?
- #3858: Security became "Accounts and sign-in", Firewall "Firewall and HTTPS". The keys `security` and `firewall` stay, so old links and `settings_security.php` / `settings_firewall.php` work as before.

## Where is the encryption key?
- The Encryption Key card (`pgset-encryption`, post `ecommerce_reset_encryption_key`) came from Ecommerce › Payment Methods; `security.save.php` writes config.php, then `pg_reseal_run()` seals again what `pg_reseal_registry()` lists (today the card numbers and the gateway secrets; `platform/encryption-key-reseal-registry.md`). It is always drawn now, not only with Enable Commerce and Credit/Debit Card on: two-step verification needs the key, and the MFA hint links the card.

## What does the card say before a key reset?
- `prep.php` counts the non-empty sealed values per area (`pg_encryption_key_reset_counts()` over `pg_encryption_key_reset_source()`, `includes/fn/encryption_key.php`): connect_accounts + connect_apps `secret`, `marketplace_accounts.credentials`, `erp_edoc_providers.credentials_enc`, config `parasut_credentials_enc`, `backup_remote_settings`, the `product_feeds` formats' `token_secret`, `oauth_google_client_secret`, `user_mfa` keys with `enabled_at > 0`, and `api_devices` whose refresh token still runs (area `devices`). A missing table or column counts as nothing.
- `pg_encryption_key_reset_split()` cuts them into two lists under the switch: "sealed again with the new key in the same save" (an area `pg_reseal_registry()` has an entry under, by the same area name) and "cannot be carried over" (signed-in devices, whose tokens are HMACs, and any area the registry does not list). The split follows the registry, so the card stays true whichever module entries are in (`plans/encryption-key-reseal.md` RK2 names its areas after these counts: connect, marketplace, edoc, parasut, backup, product_feeds, google, mfa).
- Only the second list carries the box "I understand these will have to be set up again"; with it not empty, `security.save.php` refuses a reset with `mark_error('ecommerce_reset_encryption_key_confirm')` until it is ticked. The check runs before `check_form_errors()`, so a refused save changes no key.

## What does the save say after a reset?
- A notice "The encryption key has been reset. Sealed again with the new key: <label>: <done>, …" from `pg_encryption_key_reset_summary()` over `pg_reseal_run()`'s areas (labels from `pg_encryption_key_reset_labels()`, which also names `cards`, `gateway`, `subscriptions`); values that did not open with the old key (skipped) are not mentioned. Values that opened but could not be sealed again come as a warning naming area and count. Both reach the screen and the settings modal through the liveform.

## Where are the blocked e-mail addresses?
- Blocked Email Addresses (`banned_email_addresses`) sits on Spam Protection (`pgset-captcha`), out of Firewall › IP Lists; `security.save.php` writes it (same normalising, same column guard) and ends the matching members' sessions. `firewall.save.php` no longer touches it.

## Where are the trusted proxies?
- Trusted Proxies (`pgset-proxies`) is its own card after Firewall, no longer inside the Enable Firewall popover; `firewall.save.php` still writes `waf_trusted_proxies` in the firewall block, so nothing is saved there before the 2026.2.4 columns exist, as before.
