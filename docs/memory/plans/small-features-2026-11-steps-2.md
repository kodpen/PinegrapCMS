---
name: small-features-2026-11-steps-2
description: Steps SF4-SF8 of plans/small-features-2026-11.md: product questions, pre-order, EU VAT checks, the panel offline page, Slack, Teams and Telegram channels; claim as [plan:small-features-2026-11] SF<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `small-features-2026-11-steps.md`; all run side by side.

- SF4 (M) Product questions. Cut in two: SF4a (model, widget, moderation) done (#5351, `designer/product-questions.md`); SF4b (asker mail, structured data, API `kind`, templates' Questions section) done (#5385).
  - On the comments system: a question is a comment of kind `question` on the product (`comments-widget-product-page.md` thread), a staff reply of kind `answer` with `answer_of`; the Comments widget gains a "Questions" tab beside "Reviews" (the product page's two threads), unanswered questions on the panel bell and in the comments moderation screen (`comments-moderation-counters.md`), the asker mailed when answered (`comments-watcher-mail.md`), the question and its answer in the product's structured data (`Question`/`Answer`), the API's `comments` resource exposing `kind`. The marketplace question screens stay separate. Tests: the thread split by kind, the structured data. Memory: `designer/product-questions.md`.

- SF5 (S) Pre-order. After question 3. Done in #5550.
  - Memory: `commerce/product-preorder.md`.

- SF6 (M) EU VAT checks. Cut in two: SF6a (`includes/fn/vat_check.php`, tests, `commerce/eu-vat-check.md`) done (#5552); SF6b after #5559, decided: the id, check time and consultation number go in `orders.vat_id`, `vat_checked_at`, `vat_request_id` (`workflow/decisions-made-schema-8.md`).
  - SF6b1 done (#6410): columns, checkout check (`commerce/eu-vat-checkout.md`). SF6b2a done (#PRNUM): ERP invoice note, order screen/print/API, express box. SF6b2b free: the ERP account card's tax number checked against VIES (`includes/erp/account_form.php`, `add_`/`edit_erp_account.php`); a card may hold a national number that is no VAT id: refuse or only warn.
  - SF6b: checkout check, order and ERP invoice carry the id, check time and reverse-charge note; VIES down: VAT charged with a note (`commerce/eu-vat-checkout.md`).

- SF7 (S) Panel offline page and install hint. done (#5064); the site manifest needs nothing: Site Icon already serves `/site.webmanifest` (#5065, `platform/site-icon.md`). Memory: `ui/panel-offline-and-install.md`.

- SF8 (M) Chat notification channels. Done in #5075 (decided in #5076: targets in a sealed entry, queue rows with `webhook_id = 0`, written by `pg_notify_enqueue()`).
  - Settings › Notifications: targets (kind Slack incoming webhook, Teams incoming webhook, Telegram bot token and chat id; the URL or token sealed), each with the operations alert codes (`pg_ops_alert_codes()`) and the Workspace kinds it carries (a mention of the site's app, a flow run failed, an approval requested); `includes/fn/notify_channels.php`: one builder per kind (`pg_notify_slack_body()`, `_teams_body()` as an Adaptive Card, `_telegram_body()`), pure and tested on each alert code; delivery through the outbound webhook queue (`api_webhook_enqueue()` with a target row), retries as webhooks have; a Test button per target. Memory: `platform/notification-channels.md`.
