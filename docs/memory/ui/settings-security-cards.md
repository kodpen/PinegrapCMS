---
name: settings-security-cards
description: Accounts and sign-in and Firewall and HTTPS (keys security, firewall): where the encryption key, blocked e-mail addresses and trusted proxies sit and which save writes them.
module: encryption_key
---

## What changed with the names?
- #3858: Security became "Accounts and sign-in", Firewall "Firewall and HTTPS". The keys `security` and `firewall` stay, so old links and `settings_security.php` / `settings_firewall.php` work as before.

## Where is the encryption key?
- The Encryption Key card (`pgset-encryption`, post `ecommerce_reset_encryption_key`) came from Ecommerce › Payment Methods; `security.save.php` writes config.php, then, last in the save, `pg_reseal_run()` seals again what `pg_reseal_registry()` lists. It is always drawn now, not only with Enable Commerce and Credit/Debit Card on: two-step verification needs the key, and the MFA hint links the card.

## What does the card say before a key reset?
- Every value sealed with the key is re-sealed now (`platform/encryption-key-reseal-registry.md`), but the card still warns as before RK3: since #6392 `prep.php` lists, under the switch, the non-empty sealed values per area (`pg_encryption_key_reset_counts()` over `pg_encryption_key_reset_source()`, `includes/fn/encryption_key.php`): connect_accounts + connect_apps `secret`, `marketplace_accounts.credentials`, `erp_edoc_providers.credentials_enc`, config `parasut_credentials_enc`, `backup_remote_settings`, the `product_feeds` formats' `token_secret`, `oauth_google_client_secret`, and `user_mfa` keys with `enabled_at > 0`. A missing table or column counts as nothing.
- With the list not empty, `security.save.php` refuses a reset with `mark_error('ecommerce_reset_encryption_key_confirm')` until the box "I understand these will have to be entered again" is ticked; the check runs before `check_form_errors()`, so a refused save changes no key. The help text names devices pairing again and two-step keys reset from the user screen (the old separate two-step note is gone).
- Re-sealing everything instead is `plans/encryption-key-reseal.md` (RK3 turns this list into "will be re-sealed").

## Where are the blocked e-mail addresses?
- Blocked Email Addresses (`banned_email_addresses`) sits on Spam Protection (`pgset-captcha`), out of Firewall › IP Lists; `security.save.php` writes it (same normalising, same column guard) and ends the matching members' sessions. `firewall.save.php` no longer touches it.

## Where are the trusted proxies?
- Trusted Proxies (`pgset-proxies`) is its own card after Firewall, no longer inside the Enable Firewall popover; `firewall.save.php` still writes `waf_trusted_proxies` in the firewall block, so nothing is saved there before the 2026.2.4 columns exist, as before.
