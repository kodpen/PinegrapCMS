---
name: login-as-sign-out
description: Sign-out after Login as User: the operator lands on Edit User of that account, signed back in by their cookie, with a notice; without one the logout page (#9007)
module: auth
---

## Where does the operator land?
- `logout.php` reads `pg_logout_login_as_return()` (`includes/fn/auth.php`) before `logout()` ends the session. When the session was a "log in as" one (`logged_in_as_different_user`) and the `software[auth]` cookie verifies (`pg_auth_token_verify()`) for an enabled account, the browser goes to `edit_user.php?id=<account left>` (`view_users.php` when that account is gone).
- A fresh session carries the notice "You signed out of {user} and are back in your own account." (liveform `edit_user` or `view_users`); the cookie sign-in on that request keeps it, since `pg_session_sign_in()` regenerates the id with the data.
- `send_to` is not followed then: it was the left account's own page.

## Why?
- `logout()` keeps the operator's cookie after "log in as" (`session-token-binding.md`). Sent to the site's logout page (`/cikis?logged_out=true`), the operator was already signed in as themselves there and asked "Do you want to log out?", which reads as the sign-out of the test account and signs the administrator out too (#9007).

## And without the cookie?
- No cookie, an invalid or expired one, or a disabled operator: both accounts are signed out and the logout page (or the site's designed logout page) follows as for any sign-out.
- A sign-in without remember me still sets the token cookie for the browser session, so the operator comes back the same way until the browser closes (sandbox, 2026-11-13).

## Tests?
- `tests/logout_login_as_test.php`: an ordinary sign-out and one without the cookie return nothing; `logout.php` reads the return before `logout()` and acts on it before `send_to`. The landing itself was checked on a sandbox (#9091).
