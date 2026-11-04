---
name: fediverse-settings
description: Settings › Fediverse: account card and handle check, Look me up, what is published, followers and blocks, delivery log with Try again, the rel=alternate link
module: activitypub_settings
---

## Where does it live?
- Category `fediverse` under Running the site, after Integrations (`registry.php`): cards `pgset-fediverse`, `-publish`, `-followers`, `-deliveries` in `includes/settings/fediverse.php`, writes in `fediverse.save.php`, page path `settings_fediverse.php`, help row `settings_fediverse`. Helpers in `includes/fn/activitypub_settings.php`, pure ones tested in `tests/activitypub_settings_test.php`. Before the 2026.11.0 tables one card shows a note and the save writes nothing.

## What does the save store?
- The `config_text` row `ap_settings` (`platform/activitypub-actor.md`), built by `pg_ap_settings_build()`: `enabled`, `handle`, `type`, `name` (100 characters), `summary` (plain text, 500), `icon` (a public picture's file name, `pg_public_image_choice_ok()`), `publish.forms` / `publish.folders` (only ids the screen offered; lists declared in the registry). Keys it does not draw are kept from the stored row.
- The cards read the row with `pg_ap_settings_stored()`, not `pg_ap_settings()`: that one keeps a per-request copy and the pane is drawn again in the save's request.
- Empty `name` / `icon` fall back to the organization name / logo in `pg_ap_site()`.

## Which handles are refused?
- `pg_ap_settings_handle_error()`: empty while the switch is on, anything but `[A-Za-z0-9_]{1,64}`, and a panel user's `user_username` (case and spaces aside): `@<name>@<host>` would read as that person's account. A changed handle with followers warns that they do not move.

## What do the buttons do?
- Named submit buttons (`settings-card-own-button.md`): the settings are saved first, then the job. `ap_lookup` asks `https://<host>/.well-known/webfinger?resource=acct:<handle>@<host>` (`pg_ap_lookup()`, no `check_address`: it is the site's own configured host) and `pg_ap_lookup_verdict()` says found, unreachable, an HTTP status, not WebFinger, or another actor. `ap_block_add` (kind and value, checked by `pg_ap_block_normalize()` before the save writes), `ap_block_remove` = block id, `ap_retry` = delivery id (`pg_ap_delivery_retry()`: only a failed row, back to queued, attempts 0, due now).

## What do the lists show?
- Followers: count and the newest 200. Deliveries (`pg_ap_delivery_log()`): rows with an inbox, failures first, then the newest 50, type read from the activity id (`pg_ap_activity_key()`, Accept by `#accepts/`). There is no error column: the log shows the state and attempts only.

## Where do pages carry the account?
- `get_page_content.php` adds `<link rel="alternate" type="application/activity+json" href="<base>@<handle>">` before `</head>` (`pg_ap_alternate_link()`), not in mails; `activitypub.php` is loaded only when an `ap_settings` row exists.
