---
name: panel-background-polls
description: Every panel page's background polls (bell count, heartbeat, sitemap check): session lock released, timers paused in a hidden tab, the lazy My Account link, the idle settings bar
---

## Which background calls does every panel page make?
- `check_unread_notifications` on load and every 20 s, `user_online_check` (heartbeat) on load and every 50 s, `sitemap_check` once on load; all through `api.php` (`backend.src.js`).

## Why do their handlers call `session_write_close()`?
- PHP's file session runs one request of a session at a time. These handlers only read `$_SESSION`, but held the lock to the end, so a screen opened just after load, or from another panel tab, waited 90-150 ms behind a poll (#6078, PR #6124).
- `pg_panel_check_unread_notifications()` closes right after `validate_user()`, `pg_panel_user_online_check()` and `pg_panel_sitemap_check()` right after `validate_token()`. Anything written to `$_SESSION` after the close is silently lost: a handler that starts writing the session must move or drop its close.
- `pg_panel_get_widget_data()` did the same before.

## Why do the timers stop in a hidden tab?
- `pg_poll_while_visible(poll, ms)` in `backend.src.js` runs the interval only while `document.hidden` is false; on return to sight it polls once at once and restarts. The caller still makes the load call itself. A background tab used to send ~4 requests a minute for nothing (#6087, PR #6124).

## Why does the My Account row link to `my_account_link.php`?
- `get_page_type_url('my account')` falls back to `pg_sw_page_type_widget_url()`, which scans every designed page's tree (~19 ms, 1.6 MB on 286 pages) and was run on every panel request just to draw the row (#6084, PR #6124).
- The row now links to `my_account_link.php`, which checks the session, looks the page up and redirects with `go()`; no account page lands on the home page (`go('')`). The lookup rules (design order, reachable folder, #4451) are unchanged.

## Why is the settings modal's loading bar paused?
- The closed `#pg_settings_modal` stays `display: flex` (Bootstrap 6 `.dialog`), so `.pg-sm-progress::after`'s infinite `pg-sm-slide` ran on every panel page. It is `paused` by default and `running` only under `.pg-sm.is-busy` (#6063, PR #6124).
