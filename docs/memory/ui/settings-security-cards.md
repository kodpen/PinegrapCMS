---
name: settings-security-cards
description: Accounts and sign-in and Firewall and HTTPS cards: encryption key and what its reset lists and asks to confirm, blocked e-mail addresses, trusted proxies, which save writes them
module: key_reset
---

## What changed with the names?
- #3858: Security became "Accounts and sign-in", Firewall "Firewall and HTTPS". The keys `security` and `firewall` stay, so old links and `settings_security.php` / `settings_firewall.php` work as before.

## Where is the encryption key?
- The Encryption Key card (`pgset-encryption`, post `ecommerce_reset_encryption_key`) came from Ecommerce › Payment Methods; `security.save.php` writes config.php, re-encrypts the card numbers and seals the gateway secrets again (`pg_gateway_secret_for_save()` with an empty post). It is always drawn now, not only with Enable Commerce and Credit/Debit Card on: two-step verification needs the key, and the MFA hint links the card.

## What does Reset Encryption Key warn about?
- Everything else sealed with the key stays under the old one and reads empty after a reset (owner, #6392; the full re-seal is `plans/encryption-key-reseal.md`). `includes/fn/key_reset.php`: `pg_key_reset_sealed_counts()` counts the non-empty sealed values per area (connect_accounts + connect_apps, marketplace_accounts, erp_edoc_providers, Paraşüt, backup storage, product feed tokens, the Google sign-in secret, enabled two-step keys; each guarded by `pg_schema_has()`), `pg_key_reset_sealed_list()` keeps the non-empty ones in card order (tests/key_reset_test.php).
- `prep.php` draws that list under the switch with a second box, `ecommerce_reset_encryption_key_confirm` ("I understand these will have to be entered again"); with nothing sealed neither is drawn. `security.save.php` counts again and refuses the reset with `mark_error()` on that box while the list is not empty and the box is unchecked, before any key is written. The old separate two-step note is gone: two-step keys are one line of the list.

## Where are the blocked e-mail addresses?
- Blocked Email Addresses (`banned_email_addresses`) sits on Spam Protection (`pgset-captcha`), out of Firewall › IP Lists; `security.save.php` writes it (same normalising, same column guard) and ends the matching members' sessions. `firewall.save.php` no longer touches it.

## Where are the trusted proxies?
- Trusted Proxies (`pgset-proxies`) is its own card after Firewall, no longer inside the Enable Firewall popover; `firewall.save.php` still writes `waf_trusted_proxies` in the firewall block, so nothing is saved there before the 2026.2.4 columns exist, as before.
