---
name: social-sign-in-connect
description: Connecting Google, Microsoft or Apple to an account that is already signed in ("Connect with ..." buttons), whatever its e-mail address, and the rules that refuse it
---

## What does it do?
- A signed-in person ties a provider account to their own account from "Connected sign-ins": the account page (system screen `pg_account_security_section()`, designed widget `account_security`) and an administrator's own user screen in the panel (`edit_user.php`, own record only). After that they can sign in with the provider, whatever e-mail address either side has.
- Before (PR #484): an identity was tied only during a sign-in whose verified address matched the account. A person whose provider address differed (an administrator signing in with a personal Google account) was refused, and the only way out was changing the account's e-mail.

## How does the flow work?
- One "Connect with {provider}" button per provider that is on, complete and not yet connected (`pg_signin_connect_buttons()`, the sign-in button markup, left-aligned). The link is `signin.php?provider=…&connect=<token>&send_to=<page>` (`&from=panel` on the panel screen).
- The token is per session (`pg_signin_connect_token()`), not the CSRF token. No schema change: the intent travels in the session's pending sign-in (`connect_user`, `panel`), so the provider's return address stays the same.
- On return, `pg_signin_connect_finish()` replaces matching/opening/signing in. The block list still applies. Success goes back to `send_to`; the account pages get a notice in the `my_account_profile` messages (the system profile screen prints notices now, not only errors); the panel shows the new "Connected · address" row.

## What is refused, and where?
- Start (`pg_signin_connect_problem()`): nobody signed in; an operator signed in as the person (`logged_in_as_different_user`: the provider account would be the operator's, a lasting way into the person's account); a token not from this session; a provider already connected (disconnect first).
- Return (`pg_signin_connect_outcome()`): another account signed in meanwhile; the identity already held by another account here (refused on the sign-in failure screen, never taken over; logged); the account holding another person at the same provider. Already held by this account: a notice, nothing changes.
- The failure screen says "Connecting did not complete/cancelled"; "Go Back" is `send_to`, the retry is another Connect link.

## What about disconnecting?
- Unchanged and already there: account page (`account_security.php`, `unlink_<provider>`) and `edit_user.php` "Manage" panel (any user below the editor's role). Refused while the provider is the only way in (no password, no other provider).
- `array` parameters count as not given: `connect[]=` starts an ordinary sign-in.

## What does the designer need for it?
- Widget `account_security`: flag `signin_connectable`, raw token `^^__signin_connect_buttons^^`; the default tree has a "Connected sign-ins" block. Pages designed earlier need the block added by hand.
