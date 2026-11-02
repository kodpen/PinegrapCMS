---
name: small-features-2026-11-steps-2
description: Steps SF4-SF8 of plans/small-features-2026-11.md: product questions, pre-order, EU VAT checks, the panel offline page, Slack, Teams and Telegram channels; claim as [plan:small-features-2026-11] SF<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `small-features-2026-11-steps.md`; all run side by side.

- SF4 (M) Product questions. After question 2.
  - On the comments system: a question is a comment of kind `question` on the product (`comments-widget-product-page.md` thread), a staff reply of kind `answer` with `answer_of`; the Comments widget gains a "Questions" tab beside "Reviews" (the product page's two threads), unanswered questions on the panel bell and in the comments moderation screen (`comments-moderation-counters.md`), the asker mailed when answered (`comments-watcher-mail.md`), the question and its answer in the product's structured data (`Question`/`Answer`), the API's `comments` resource exposing `kind`. The marketplace question screens stay separate. Tests: the thread split by kind, the structured data. Memory: `designer/product-questions.md`.

- SF5 (S) Pre-order. After question 3.
  - Migration sub-step for the two product columns (`install_add_column()`, rerun twice); the product screen: "Pre-order until <date>" beside the backorder switch; the catalog widgets and the product page show "Pre-order, ships from <date>" through the `__out_of_stock` token family (`commerce/products-variant-model.md` names the readers), the cart line and the order mails carry the note, the order's requested arrival date (`commerce/checkout-requested-arrival-date.md`) is not before the date; the pre-order flag ends itself on the date (the general job clears it when stock is in); the API `products` fields. Tests: label and date rules. Memory: `commerce/product-preorder.md`.

- SF6 (M) EU VAT checks. After question 4.
  - `includes/fn/vat_check.php`: `pg_vat_id_clean($id)` (pure: country prefix, allowed characters per member state), `pg_vat_check($id)` through `pg_curl_run()` against the VIES REST check (`check-vat-number`), the answer cached a day in `data/cache/vat/`; the billing information widget and the express order check the typed id when the billing country is an EU member and differs from the site's, show "VAT id verified" or the refusal, and, per question 4, set `tax_exempt` so the existing totals path charges no VAT; the order and the ERP invoice carry the id, the verification time and the reverse-charge note (`erp/` texts for the invoice line); the account card's tax number box gets the same check; VIES down: the order proceeds with VAT and a note. Tests: the clean function per country, the response mapping, the exemption decision. Memory: `commerce/eu-vat-check.md`.

- SF7 (S) Panel offline page and install hint. done (#5064); the site manifest needs nothing: Site Icon already serves `/site.webmanifest` (#5065, `platform/site-icon.md`). Memory: `ui/panel-offline-and-install.md`.

- SF8 (M) Chat notification channels. `free`.
  - Settings › Notifications: targets (kind Slack incoming webhook, Teams incoming webhook, Telegram bot token and chat id; the URL or token sealed), each with the operations alert codes (`pg_ops_alert_codes()`) and the Workspace kinds it carries (a mention of the site's app, a flow run failed, an approval requested); `includes/fn/notify_channels.php`: one builder per kind (`pg_notify_slack_body()`, `_teams_body()` as an Adaptive Card, `_telegram_body()`), pure and tested on each alert code; delivery through the outbound webhook queue (`api_webhook_enqueue()` with a target row), retries as webhooks have; a Test button per target. Memory: `platform/notification-channels.md`.
