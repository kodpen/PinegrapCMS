---
name: request-lists-get-parameters
description: GET parameters sent as a list (?x[]=1) on the carrier scorecard, ShipWorks, the checkout return mode and two Workspace screens are dropped, never cast; Edit Page properties; the tests that list them
---

## Which screens drop a GET parameter sent as a list?
- `shipworks.php`, `workspace_mcp.php` and `workspace_channel_export.php` call `pg_request_single_values()` (`core/request-parameter-arrays.md`) at the top with every one-value parameter they read. A list counts as not given: ShipWorks answers as without the field, the MCP screen opens no server, the channel export answers "That channel could not be found." (404).
- Carrier Scorecard reads `figure` only when it is text (`is_string()`). Edit Page compares `properties` with `=== '1'` and no cast, so a list is not the pane request (PR #11076).
- `pg_payment_is_return_mode()` (`includes/fn/payments.php`) takes anything but text as no return mode; `pg_delivery_scorecard_month()` (`includes/fn/delivery_scorecard.php`) takes it as a broken key, which is the current month.

## Why?
- A sandbox probe of every root screen that reads a GET parameter (365 files), with each parameter sent as a list, found only these (PR #PRNUM). Each wrote "Array to string conversion" to the PHP log: Edit Page `properties`, Express Order and Order Preview `mode`, ShipWorks `username`, Carrier Scorecard `month` and `figure`, the MCP screen `tool`, the channel export `from` and `to`. No screen answered 500.
- `(int)` of a list is 1: the channel export opened channel 1 for `channel[]=x`, and the MCP screen server 1 for `server[]=x`.
- ShipWorks: `password[]=x` with a known user name reached `pg_password_verify()` and logged the same warning.

## Which tests guard them?
- `tests/panel_single_value_params_test.php` lists each screen with the names its `pg_request_single_values()` call drops, and fails when the screen reads one before the call.
- `tests/panel_string_params_test.php` lists Carrier Scorecard `figure`.
- `tests/payment_stripe_test.php` and `tests/delivery_scorecard_test.php` pass a list to the two helpers.
- A screen parameter read with a cast goes into one of the two lists.
