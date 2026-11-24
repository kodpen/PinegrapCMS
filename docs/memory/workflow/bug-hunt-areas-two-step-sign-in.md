---
name: bug-hunt-areas-two-step-sign-in
description: Bug hunt area 45, two-step sign-in (2026-11-24, PR #11452): the two faults fixed, what read clean, issue #11454, two questions for the architect
---

## What was the area?
- Panel sign-in with two-step verification: `mfa.php`, `includes/fn/mfa.php`, the gate's callers (`index.php`, both entrances, `signin.php`, `device_limit.php`, the remember-me branch of `initialize_user()`), Edit User's reset, `account_security.php`'s `mfa_` actions, `/auth/login`, api.php's password sign-in, `shipworks.php`.

## What did it fix? (PR #11452 lists each with file and line)
- A passkey-only account that turned on an authenticator key got ten new recovery codes on the session alone, voiding the owner's; new codes for it need the password and an e-mailed code. It now keeps unused codes (`pg_mfa_setup_keeps_codes()`).
- Turning two-step on or off deleted the other remember-me tokens with a bare DELETE, so their browsers kept getting device notifications; each now goes through `pg_auth_token_revoke()`.

## What was read clean?
- TOTP: ±1 step of 30 s, `last_step` moved forward by a conditional UPDATE, so a code works once even in a race; setup's first code becomes `last_step`.
- Recovery codes: SHA-256 of the normalized code, spent by `used_at = 0` UPDATE with affected-row check; a code typed where only TOTP counts is refused before it is spent.
- SMS sign-in code: hashed in the session, 10 minutes, cleared when used, fifth miss ends it.
- Throttle: 5 per account and 30 per address in 10 minutes, counted before each check on every path (`mfa.php` verify, passkey, SMS, setup; `/auth/login`; account page); a right password does not reset it.
- The gate runs before any token is minted; the pending record lives 600 s; the session id is renewed at the gate and at sign-in; a disabled account is refused at completion.
- Trusted device = the remember-me token minted after the second step; it ends at its lifetime, at a two-step change on another browser, a password change or an administrator reset (all tokens and API devices, OAuth connections included).
- Edit User's reset: CSRF token, own account or a lower role (administrators any), not while signed in as another user.
- Reset-link sign-in (`pg_post_password_signin()`) refuses an account with a factor; api.php and ShipWorks refuse password-only requests for one.

## What was left?
- Issue #11454: `get_file.php`'s own remember-me sign-in skips the block list and the required-role setup that `initialize_user()` applies.
- For the architect: `/auth/login` looks at TOTP only (`passkey-decisions.md`), so a passkey-only account signs in a team device with the password alone, and one whose role requires two-step is refused `mfa_setup_required` though it has a factor.
- For the architect: a passkey-only account adds an authenticator key with the session alone, while adding a passkey asks the password and an e-mailed code.
- As designed: Edit User resets one's own two-step without proofs (behind the panel's role gate); a required role whose factor was reset sets one up at the next sign-in with the password alone; a correct code clears the address counter too.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/two-step-sign-in.md
