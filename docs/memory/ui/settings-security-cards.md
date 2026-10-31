---
name: settings-security-cards
description: Accounts and sign-in and Firewall and HTTPS (keys security, firewall): where the encryption key, blocked e-mail addresses and trusted proxies sit and which save writes them.
---

## What changed with the names?
- #3858: Security became "Accounts and sign-in", Firewall "Firewall and HTTPS". The keys `security` and `firewall` stay, so old links and `settings_security.php` / `settings_firewall.php` work as before.

## Where is the encryption key?
- The Encryption Key card (`pgset-encryption`, post `ecommerce_reset_encryption_key`) came from Ecommerce › Payment Methods; `security.save.php` writes config.php, re-encrypts the card numbers and seals the gateway secrets again (`pg_gateway_secret_for_save()` with an empty post). It is always drawn now, not only with Enable Commerce and Credit/Debit Card on: two-step verification needs the key, and the MFA hint links the card.

## Where are the blocked e-mail addresses?
- Blocked Email Addresses (`banned_email_addresses`) sits on Spam Protection (`pgset-captcha`), out of Firewall › IP Lists; `security.save.php` writes it (same normalising, same column guard) and ends the matching members' sessions. `firewall.save.php` no longer touches it.

## Where are the trusted proxies?
- Trusted Proxies (`pgset-proxies`) is its own card after Firewall, no longer inside the Enable Firewall popover; `firewall.save.php` still writes `waf_trusted_proxies` in the firewall block, so nothing is saved there before the 2026.2.4 columns exist, as before.
