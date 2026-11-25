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
- One `.pg-card`: the site's name as head, "Set-up steps" with "{done} of {all} steps done" under it, then "What we need from you": a text line with its answer in a textarea and "Send", a file line with the files sent and a file field (`launch-summary-file-lines.md`).
- The steps list (`pg_launch_summary_steps()`, pure): the done steps with a teal "Done" pill, then the open ones with a gray "Waiting" pill, each group in the set-up card's order. Name and state only: never the row's line, a blocker's text, the reason or a setting's value. A step hidden on the card ("Not for this site") is not among the card's rows, so it is not listed or counted. Owner asked for the open steps in `workflow/decisions-made-product-101.md`; LS1, PR #11399.
- An answer is a POST with the form token; an unchanged answer writes nothing ("This answer was already sent.").

## Who hears an answer?
- Bell action `launch_answer`, addressed (`target_user_id`) to the line's `created_by`, `reference_id` the line, title the line's text, `form_id` the files a file line holds (0 for text); at most one per line an hour (`waf_rate_exceeded` scope `launch-summary-bell`). Its link `welcome.php#launch-summary` opens the drawer.
- No e-mail and no device banner.

## How was it checked?
- On the sandbox at 1440 and 390 px: drawer with lines, an answer and a working link; the page with done steps and two lines; an answer sent (one bell row to the writer), a bad token 404, withdrawn 403. Copy and the expired state were not clicked through.

Related: docs/memory/ui/launch-summary-file-lines.md, docs/memory/ui/launch-summary-lines.md, docs/memory/ui/launch-journey-setup-card.md
