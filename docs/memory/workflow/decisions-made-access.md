---
name: decisions-made-access
description: Decisions list, access part: public pi.php/si.php and test_secure_mode.php, guest order linking, backup, dashboard and role 3 gates, secrets shown on screen, the TLS escape flag, escape_url().
---

## Where do these come from?
- The security review's hand-off issues #59, #62, #67, #68, #70: the owner closed these items on 2026-09-20 by keeping the current behaviour; later rows carry their own date. The list's rules: `decisions-made.md`.

## Which doors stay open?
- `pi.php` (`phpinfo()`) and `si.php` (system info, embeds `pi.php`): public. No session or role gate is added; an audit finding them "open without authentication" is expected (#68 items 5-6). The router's error page (`router_output_error()`, drawn when the database cannot be reached) links `pi.php` as "PHP Check".
- `test_secure_mode.php` loads no `init.php` and has no session or key gate: it has to answer while Secure Mode locks the site out, and a key would tie it to a link from the panel nobody can reach then (#70 item 8).
- `submit_order.php`: with auto-registration on, a guest order whose billing e-mail matches an existing account is connected to that account without verification. A feature (order history); no sign-in or e-mail verification is required (#67 item 8).

## Which gates stay as they are?
- `backups.php` and the `software_backup` action (`pg_panel_software_backup()`, `includes/panel/software.php`): manager (role <= 2) and up, the written "manager and above" policy (#62 item 3).
- `update_dashboard_appearance`: not exempt in `includes/panel/actions.php`, so behind the general gate (signed in, role <= 1), the reach of the Dashboard card of Site Settings > Appearance (`pg_dashboard_appearance_allowed()`, `ui/settings-dashboard-card.md`), which writes the same columns through its own save; a site-wide write. Since that card replaced the menu's selects nothing in the panel posts it any more; it stays for direct `api.php` callers until the owner decides otherwise. Its siblings (`update_dashboard_widgets`, the `push_*` actions) are exempt; "siblings exempt, this one not" is not a finding (2026-10-08; `core/panel-action-table.md`).
- `edit_calendar.php`, `edit_contact_group.php`: a role 3 user may rename, and when empty delete, a calendar or contact group assigned to them. Creating one stays refused (`add_calendar.php`, `add_contact_group.php`), a separate decision (#59 item 3).
- `config.passkey_signin` (passwordless sign-in) defaults to off: an upgraded site keeps its sign-in screen until a manager turns the button on (2026-10-26, #1882).

## Which secrets and TLS choices are deliberate?
- `includes/settings/prep.php` renders the Google Client Secret (stored encrypted) back into the Security form: operator preference (#62 item 13), the written exception to the never-shown-back rule of `core/security-helpers-and-secret-storage.md`.
- `editor_select_image.php` prints `UNSPLASH_ACCESS_KEY` into the page: by design, the Access Key is Unsplash's client-side `client_id`; the Secret Key is the secret (#62 item 11).
- `ALLOW_INSECURE_UPDATE_TLS` in `data/config.php` turns verification off in `pg_curl_tls()` for every call that uses it: the version and licence checks and, since PR #173 (2026-09-18), the card gateways of `submit_order.php` and `recurring_payment_job.php`, Givex and the carriers. It stays the operator's one explicit last resort; no separate helper without the flag (#70). Stripe and iyzico verify inside their bundled libraries, which the flag does not reach. Which calls use it: `platform/update-tls-verification.md`.
- `pg_write_permission_repair()`: folders 0777, files 0666, because two users apply updates (panel = web server, FTP = hosting account); see `release/stale-migration-file-protection.md` (#59 item 7).
- `escape_url()` validates for display and accepts `//`, `http://`, `https://` on purpose; tightening it breaks the screens that print `http_referer` (`platform/redirect-validator-context.md`).
