---
name: api-openapi-time-formats
description: How a time field is declared in the API document: datetime (date-time) for a moment, date for a day, read from the presenter; the word map and its check
---

## Which word does a time field take?
- `'datetime'` (`'datetime?'` when it can be null) for a moment: the presenter writes it with `api_time()` or the same ISO form (`gmdate('Y-m-d\TH:i:s\Z')`, `date('c')`). The document says `type: string, format: date-time`.
- `'date'` / `'date?'` for a day: a DATE column, `Y-m-d` built in PHP, or a helper that cuts a stored date to ten characters. The document says `format: date`.
- Read it from the presenter, never from the field's name: `*_at` can be a day and `*_date` a moment.
- A time of day (`HH:MM`: `end_of_day`, `schedule_time`, `offered_on[].until`) or a minute without a zone (`shipping_cutoffs[].date_and_time`) is neither and stays `'string'`.

## Where are the words mapped, and what checks them?
- `api_openapi_field_words()` (`includes/api/openapi.php`) maps every word a field declaration may use: string, integer, number, boolean, object, array, datetime, date. `api_openapi_type_spec()` reads it.
- `tools/check_api_schema.php` reads the map's keys and refuses any other lower-case word in a `*_schema()` body. Symptom it closes: `User.last_online_at`, `created_at` and `updated_at` said `type: datetime`, which OpenAPI does not have.
- Parameters keep their own types (`api_openapi_type()`): a `'datetime'` parameter also takes a day or a unix timestamp, so it says no format.
- Tests: `tests/api_openapi_time_formats_test.php`.

## Which objects are done?
- PR #10151: the core objects of `includes/api/` (143 fields: 125 moments, 18 days) and `User`. `seo.issues` rows were declared by #10116.
- PR #10209: the Workspace, ERP and translation objects (175 fields: 117 moments, 58 days), with the `versions[].created_at` of the inline returns of `ws_api_canvas_returns()`, `ws_api_canvas_one_returns()` and `ws_api_page_returns()`.
- Left as plain strings, the source not read as a sure ISO form: `CommissionRecurringProfile.start_date`, `OrderReturnWithdrawal.delivered_on`, `Order.shipments[].parcels[].delivered_at`, `DesignAutoDialog.open_on`, `WorkspaceCustomerQuote.issue_date` (the quote row passed through unchecked: `''` or `0000-00-00` can reach it), `WorkspacePersonalPlan.time_day` and `repeat_until` (`''` when unset).
- Neither a moment nor a day, so strings: a month `Y-m` (`ErpProfitReport.months[].month`, `ErpVatReport.month`), a time of day (`ErpWaybill.ship_time`, `WorkspacePlanItem.start_time`/`end_time`, `WorkspacePersonalPlan.start_time`/`end_time`, `WorkspacePersonCardClock.time`), words (`WorkspaceAgentCardMessage.when`).
- Not yet (AM4c, the files were held by open PRs): `includes/workspace/api_resources.php` (51 time-like fields), `includes/workspace/api.php`, `includes/workspace/api_commands.php` and `includes/api/resources/design.php` (13).
