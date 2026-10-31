---
name: cloudflare-tools-failed-reads
description: Cloudflare Tools says why a Cloudflare read or action failed: unread zone switches disabled, firewall and analytics reasons, Cloudflare's text on every failed action
module: cloudflare_tools
---

## How does the page tell a failed Cloudflare answer?
- `pg_cloudflare_answer_failed($answer, $graphql)` in `includes/fn/cloudflare_tools.php` (loaded by `cloudflare.php` only). REST fails on a status other than 200 or `success` false; GraphQL also fails on an `errors` list or `data` null, because Cloudflare sends a GraphQL refusal (no Analytics permission, a range the plan does not cover) as HTTP 200.
- `pg_cloudflare_answer_reason()` gives Cloudflare's `errors[0].message`, else the HTTP status, else "Cloudflare did not answer." (timeout). It is not escaped; callers use `h()`.

## What happens when a zone setting cannot be read?
- The five setting reads run in one loop before the `mark_error` loop: a failed one adds "{var:1} could not be read: {var:2}" to `$permission_errors` and its switch is drawn `disabled` with "Could not be read from Cloudflare." under it (`aria-describedby`). Before, it fell back to Off/medium silently, so a site in Under Attack mode looked off.
- The `disabled` attribute comes after `checked`, so `tests/cloudflare_names_test.php` and `tests/select_commit_test.php` still match `onchange="this.form.submit()"'.$switch_focus('cf_x').'`.

## Firewall table and analytics cards?
- A failed firewall read puts "Firewall events request failed: …" in the table row in `fg-danger`, as the audit log does, instead of "No firewall events found".
- `cf_read_analytics()` sends the three GraphQL reads once before the print (the old `getCloudflareAnalytics()` sent them five times, after `output_errors()`), `pg_cloudflare_analytics_totals()` sums them and keeps the reason; `pg_cloudflare_analytics_card()` prints a dash for a failed read and the reason goes under the cards. Visitors fail only when both the adaptive read and its daily fallback fail.

## Failed actions?
- Delete record, the four toggles and Clear Cache append `h(pg_cloudflare_answer_reason($resp))`, as Add/Edit Record and Rocket Loader already appended Cloudflare's message. The audit log reason is escaped too.

Pinned by `tests/cloudflare_failed_reads_test.php`.

Related: docs/memory/platform/cloudflare-dns-dialog.md, docs/memory/ui/site-log-action-lines.md
