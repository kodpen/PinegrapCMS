---
name: launch-summary-agency
description: The agency summary (SJ5): the set-up card's "Agency summary" drawer, the customer's signed page launch_summary.php, text answers, the launch_answer bell line, withdraw
module: launch_summary
---

## Where is it?
- Code `includes/fn/launch_summary.php`; drawer in `assets/js/pg_dash_cards_launch.js` (card menu "Agency summary", labels from `cards/launch.php`); panel action `launch_summary` in `includes/panel/dashboard.php` (general gate, token, role 0 in the handler); page `launch_summary.php` (root, in `waf_sensitive_scripts()`). Tests `tests/launch_summary_test.php`.
- Lines are rows of `launch_summary_lines` (`launch-summary-lines.md`), at most 50, a line at most 500 characters, an answer 5,000.

## What is the link?
- Token: HMAC-SHA256 of `launch-summary:<issued_at>` under `ENCRYPTION_KEY` (`pg_booking_key()`). Stored only as sha256 in the `summary` key of the `launch_journey` config_text row: `issued_at`, `hash`, `expires_at`, `withdrawn_at`. No schema; `pg_launch_journey_stored_change()` keeps the key.
- One link at a time: "Make a new link" (7, 14, 30 or 90 days, 30 picked) ends the old one; the URL is rebuilt from the key, so Copy works again later. A site with no key has no link (the drawer says so).
- "Withdraw link" (danger, confirm) sets `withdrawn_at`, keeping the hash so the page says "withdrawn". Both changes write a Site Log line.

## What does the customer's page show?
- `pg_launch_summary_link_state()`: `invalid` 404, `withdrawn` 403, `expired` 410 (secure card, one sentence), else the page.
- Shell `output_header_secure()`: no panel menu or top bar, not the site's theme. The juror page's shell was taken over `output_header()`'s `hide_menu`, which draws the signed-in panel's bar parts.
- One `.pg-card`: the site's name as head, "Set-up steps done" (names of done steps only, `pg_launch_summary_done_steps()`, never a line of state or a setting's value), then "What we need from you": each line with its answer in a textarea and "Send".
- An answer is a POST with the form token; an unchanged answer writes nothing ("This answer was already sent.").

## Who hears an answer?
- Bell action `launch_answer`, addressed (`target_user_id`) to the line's `created_by`, `reference_id` the line, title the line's text; at most one per line an hour (`waf_rate_exceeded` scope `launch-summary-bell`). Its link `welcome.php#launch-summary` opens the drawer.
- No e-mail and no device banner.

## Not seen
- Drawn and tested without a database or a browser when written: the drawer, the page and the bell row were not opened on a sandbox.

Related: docs/memory/ui/launch-summary-lines.md, docs/memory/ui/launch-journey-setup-card.md
