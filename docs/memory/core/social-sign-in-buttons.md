---
name: social-sign-in-buttons
description: Where the social sign-in buttons come from (pg_signin_buttons()), when a provider shows, the Settings and designer hints, starter layouts, and what an old browser does.
---

## Where do the buttons come from?
- One place: `pg_signin_buttons()`, a `pg-signin-buttons` block with one `pg_signin_button_html()` per enabled provider (Google, Microsoft, Apple; same markup and width). Every entrance screen, the designer sections `google_signin`/`google_signup` and custom layouts (`$google_signin`) print it; `pg_google_signin_button()` is a Google-only wrapper. The `__google_*_url` tokens stay Google only.
- A provider shows only when switched on with a complete app (`pg_signin_enabled()`; Apple also needs Team ID + Key ID). Before (PR #469) an incomplete one hid silently ("Microsoft and Apple do not come after Google"). Now Settings › Security says which buttons visitors see and which are on but hidden (`pg_signin_button_state()`), and the designer block's hint lists them (`siteFlags.signin_providers`).
- Starter-site entrance layouts (`data/backups/*/layouts/895, 126, 982.php`) printed no button; they print `$google_signin` now. Existing modified layouts need `<?=$google_signin ?? ''?>` added by hand.

## Old browsers?
- The buttons are plain links (no JavaScript, no popup). An old browser that "does nothing" fails at the provider (Google can refuse outdated browsers and in-app webviews) or comes back cancelled, which now shows the screen above.

Related: docs/memory/core/social-sign-in.md
