---
name: merchant-center
description: Google Merchant Center via the Merchant API (PR #3213): feed.php as a fetched data source, cached product issues on System Status, state in config.product_feeds
module: merchant_center
---

## What does it do?
- A site Google account connected for the purpose `merchant_center` (scope `https://www.googleapis.com/auth/content`, model A) picks one Merchant Center account on Settings › SEO › Product Feeds (`includes/settings/seo_merchant.php`). Saving registers the Google TSV feed address (`feed.php`) as a primary product data source Google fetches daily at 06:00 site time, and reads the product issues.
- Merchant API only (`merchantapi.googleapis.com`, `datasources/v1`, `issueresolution/v1`, `accounts/v1`); the Content API for Shopping shut on 2026-08-18.

## Where is the state kept, and why no table?
- Under the `merchant` key of `config.product_feeds`: connection id, account id and name, target country, data source name, registered_at / register_error, checked_at / check_error and the issue summary (counts per severity, product stats, top 5 issues). `pg_product_feed_settings_parse()` passes the key through untouched; `pg_merchant_state_parse()` types it. Writes go through `pg_merchant_state_save()` (fresh read, merge, `pg_product_feed_settings_save()`).
- No schema by decision (#3180): per-item push and a sync table are a separate schema question.

## How is registering kept idempotent?
- `pg_merchant_register()` lists the account's data sources; `pg_merchant_find_source()` keeps one whose `fetchUri` is the feed address, moves the one registered before (stored name) to a new address with PATCH `updateMask=fileInput.fetchSettings.fetchUri` (a renewed feed token), and only otherwise creates one (`pg_merchant_data_source_body()`). Google needs `contentLanguage` + `feedLabel` for a file input: the language is `TRANSLATION_SOURCE_LANGUAGE` cut to two letters, the label is the target country.
- The country comes from the card (two letters, field `merchant_center_country`, apart from the structured-data `merchant_country` in `config`: one shared name made every save overwrite the seller country with this one, #3557), offered first from `ORGANIZATION_COUNTRY` when it is a code, else from the base currency (`pg_merchant_default_country()`).

## When are the issues read?
- `accounts.aggregateProductStatuses.list` by `pg_merchant_refresh_issues()`: from `product_feed_job.php` (daily) when the reading is older than `PG_MERCHANT_ISSUES_TTL` (12 h), and at once on "Check now" or when an account is chosen. Never on a panel load.
- `pg_merchant_issue_summary()` merges reporting contexts and countries: an issue (code + attribute) keeps its worst severity and the largest `productCount`; stats take the largest figure; an unknown severity counts as NOT_IMPACTED; help links are kept only as https on a Google host (`pg_merchant_help_url()`).

## What does System Status show?
- A "Google Merchant Center" row in the actions column only once an account is chosen (`pg_merchant_health()`): red for disapproving issues or a failed registration, yellow for demoting issues, a failed reading or no data source yet, green otherwise. Detail rows are Merchant Center's descriptions (raw; the widget escapes) with product counts; the row opens the account's diagnostics.

## What is not verified, and what remains?
- Tests: `tests/merchant_center_test.php` (pure parts). The live run needs a Merchant Center account and the Merchant API enabled in the Google Cloud project of the site's OAuth client; checked on dev by the owner.
- Remaining: `plans/reverse-oauth-leftovers.md` (Merchant Center row).
