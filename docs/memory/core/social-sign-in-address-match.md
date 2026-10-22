---
name: social-sign-in-address-match
description: Social sign-in ties a verified provider address only to an account whose stored address is the same apart from letter case; accent-folded look-alikes are refused, not tied and not opened (#1196)
---

## Which account does a verified provider address tie to?
- One whose stored `user_email` equals the reported address apart from letter case and that has no identity at that provider yet. `pg_signin_match_user()` (`includes/fn/signin.php`) reads the candidate rows, then `pg_signin_same_address_user()` keeps only one that passes `pg_email_address_matches()` (`includes/fn/auth.php`).
- The same helper decides the password reset address: `pg_password_reset_address_matches()` (`includes/fn/widgets_account.php`) is a wrapper kept for that name ([[account-email-pages]]).

## Why is the SQL lookup not enough?
- `user.user_email` uses an accent-folding collation (`utf8mb4_unicode_ci`): `jane@sirket.com` finds the account of `jane@şirket.com` and the other way round, two different domains. Before, a provider account at the look-alike domain was tied to the other person's account and signed in to it (takeover). The installer accepts an administrator address with such letters, so the stored case is real.
- The collation itself is not changed.

## What does a look-alike get?
- `signin.php` asks `pg_signin_address_refusal()` over `pg_signin_address_accounts()` when nobody matched: `connected` (an account has the very address, tied to another person at the provider) keeps its old refusal; `in_use` (only a look-alike has an account) shows its own refusal screen and log line. Nothing is tied, and no second account is opened beside it: the sign-up forms count a folded address as "in use" too, so one account per address holds.
- The way out for the real owner is signing in with the password and connecting the provider from the account page ([[social-sign-in-connect]]).

## What about other lookups by e-mail?
- Form auto-registration (`custom_form.php`) finds a folded row and only skips registration: the submission is not bound to that account and the contact is not connected. A guest order is linked to the account found by billing address (an owner decision). If either ever gains an exact check, a folded but not exact row must refuse like `in_use`, never fall through to creating an account: a second account folding to the victim's address would make the victim's own reset and e-mail login pick an arbitrary row.
- Duplicate checks on sign-up and profile forms fold too, which is stricter (they refuse). Logins by e-mail still need the password.

## Where are the tests?
- `tests/signin_test.php`: the pure decision (`pg_signin_same_address_user()`, `pg_signin_address_refusal()`, `pg_email_address_matches()`) and a source check that `pg_signin_match_user()` uses the exact-match helper.
