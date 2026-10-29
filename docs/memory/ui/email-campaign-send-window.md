---
name: email-campaign-send-window
description: The Send E-mail Campaign pop-up draws once and sends its batches over fetch (?format=json) instead of a meta refresh; its bar is named, the count and finish announced
---

## How does the send window send the next batch?
- `send_email_campaign.php` draws the progress screen once (it sends the first batch while drawing). An inline script then fetches `send_email_campaign.php?id=…&format=json&token=…` one second after each answer, the pace the refresh had. Each JSON request sends the next 10 recipients (same query, same order) and answers `{sent, total, done, error, percent, percent_text, count}`; `percent_text` and `count` are formatted by the server (`lang('{var:1}%')`, `pg_format_number()`).
- `done` is true on the request that finds no recipient left: that request runs the finish (status `complete`, activity log, auto campaigns), as the last reload did.
- Without script the old `<meta http-equiv="refresh" content="1">` still reloads the window: it sits inside `<noscript>`.

## Why not the refresh?
- A 5,000-recipient campaign was 500 full reloads, one a second: a screen reader restarted at the top and keyboard focus restarted each time, so Tab could hardly reach Stop & Close (WCAG F41, 2.2.1 / 3.2.5).

## How does the JSON mode refuse?
- In JSON with a status (`core/json-endpoint-rules.md`): a bad or missing token 403 (compared with `hash_equals()` against `$_GET['token']`, not `validate_token_field()`), an unknown campaign 404, a cancelled campaign or a role-3 user who is not its creator 403 (`output_error_in_popup()` answers JSON in that mode). The script stops on any `error` or on an answer that is not JSON (a login redirect, a PHP error) and shows the message in a `role=alert` box.
- `validate_user()` and `validate_email_access()` still answer HTML; the script shows its generic "server did not answer" text then.

## What does a screen reader hear?
- The bar (`role=progressbar`) is named by the "Sending Campaign Now…" heading (`aria-labelledby`); its percent is a span beside the bar, not inside it (Bootstrap 6 `.progress-bar` clips with `overflow:hidden; white-space:nowrap`).
- A visually hidden `role=status` line gets "x of y" once per tenth of the campaign, and "Campaign Sent. …" at the end. On done the finished block replaces the progress, Close (reloads the opener) replaces Stop & Close and takes focus; the header X reloads the opener too.

Related: docs/memory/ui/print-views-light-paper.md, docs/memory/core/json-endpoint-rules.md
