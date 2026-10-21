---
name: social-sign-in
description: Sign in with Google, Microsoft and Apple: the flow, how an account is matched, where the buttons come from, and the failure screen (no more "404" on a refused sign-in)
---

## Where is the code?
- `includes/fn/signin.php` (functions; pure ones tested in `tests/signin_test.php`), root `signin.php` (start and callback), `google_auth.php` (Google's return address, kept so Google Cloud needs no change; it defines `PG_SIGNIN_PROVIDER` and runs `signin.php`).
- Providers are `includes/connect/providers/*.php` with a `signin` block. Google's app and switch are `config.oauth_google_*`; the others are `config.signin_providers` + `connect_apps`. Table `user_identities` (10.74, Google moved in 10.75).

## How is an account matched?
- By the identity (`user_identities`), else by the verified e-mail of an account that has no identity at that provider yet (it is tied then), else an account is opened where the screen allows it (`create` on registration, `membership` hands over to the membership form, `none` on every login screen).
- An e-mail the provider does not vouch for never matches. An account whose address matches but is tied to another person at the provider is neither taken over nor opened a second time.
- A provider account whose address differs from the account's is tied by connecting it while signed in ("Connect with ...", [[social-sign-in-connect]]); signing in with it before that is refused.

## What does a refused or failed sign-in show?
- Its own screen, `pg_signin_fail()` (`output_header_secure()` + `pg_signin_failure_card()`): title, reason, "Geri Dön" (the button's page from the same-site Referer, `pg_signin_referer_path()`; else the entrance page) and "{provider} ile tekrar dene".
- Before (#467): every refusal went through `output_error()`, i.e. the site's error page. Most sites design that page as "page not found", so a refused sign-in looked like a 404 and the reason was easy to miss (reported on kodpen.com 2026-10-16: an administrator whose Google address was not the account's address).
- The no-account message names the address the provider reported and the two ways out. A missing state (cookies blocked or cleared, finished in another browser or app, return address reopened) says so instead of "could not be verified", and is written to the activity log.
- A cancel at the provider (`access_denied` and friends) shows the screen as a notice instead of silently returning to the login page, which read as "the button did nothing".
- The response code stays 200, as `output_error()` without a code gave; the screen sends `X-Robots-Tag: noindex` and `Cache-Control: no-store`.

## Where do the buttons come from?
- One place: `pg_signin_buttons()`, a `pg-signin-buttons` block with one `pg_signin_button_html()` per enabled provider (Google, Microsoft, Apple; same markup and width). Every entrance screen, the designer sections `google_signin`/`google_signup` and custom layouts (`$google_signin`) print it; `pg_google_signin_button()` is a Google-only wrapper. The `__google_*_url` tokens stay Google only.
- A provider shows only when switched on with a complete app (`pg_signin_enabled()`; Apple also needs Team ID + Key ID). Before (PR #469) an incomplete one hid silently ("Microsoft and Apple do not come after Google"). Now Settings › Security says which buttons visitors see and which are on but hidden (`pg_signin_button_state()`), and the designer block's hint lists them (`siteFlags.signin_providers`).
- Starter-site entrance layouts (`data/backups/*/layouts/895, 126, 982.php`) printed no button; they print `$google_signin` now. Existing modified layouts need `<?=$google_signin ?? ''?>` added by hand.

## Old browsers?
- The buttons are plain links (no JavaScript, no popup). An old browser that "does nothing" fails at the provider (Google can refuse outdated browsers and in-app webviews) or comes back cancelled, which now shows the screen above.

Related: docs/memory/core/social-sign-in-providers.md (adding a provider)
