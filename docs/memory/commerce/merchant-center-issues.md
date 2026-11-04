---
name: merchant-center-issues
description: Google Merchant Center product issues: when they are read, how the summary merges contexts, the System Status row
module: merchant_center
---

## When are the issues read?
- `accounts.aggregateProductStatuses.list` by `pg_merchant_refresh_issues()`: from `product_feed_job.php` (daily) when the reading is older than `PG_MERCHANT_ISSUES_TTL` (12 h), and at once on "Check now" or when an account is chosen. Never on a panel load.
- `pg_merchant_issue_summary()` merges reporting contexts and countries: an issue (code + attribute) keeps its worst severity and the largest `productCount`; stats take the largest figure; an unknown severity counts as NOT_IMPACTED; help links are kept only as https on a Google host (`pg_merchant_help_url()`).

## What does System Status show?
- A "Google Merchant Center" row in the actions column only once an account is chosen (`pg_merchant_health()`): red for disapproving issues or a failed registration, yellow for demoting issues, a failed reading or no data source yet, green otherwise. Detail rows are Merchant Center's descriptions (raw; the widget escapes) with product counts; the row opens the account's diagnostics.

Related: docs/memory/commerce/merchant-center.md
