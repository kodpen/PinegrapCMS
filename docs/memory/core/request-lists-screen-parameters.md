---
name: request-lists-screen-parameters
description: One-value GET and POST parameters sent as a list (x[]=1) are dropped or read only as text on the screens a sandbox probe found; the tests that list them
---

## Which screens drop a parameter sent as a list?
- At the top, `pg_request_single_values()` (`core/request-parameter-arrays.md`) with every one-value name the screen reads: `shipworks.php`, `workspace_mcp.php`, `workspace_channel_export.php`; in the POST branch `connections.php` (`connections_action`, `connection_id`, `provider`), `search_console.php` (`search_console_action`, `connection_id`), `content_review_action.php` (`list_params`, `selection`) and `update_address_book.php` (`page_id`, `pg_widget_id`, `id`, `send_to`).
- A list counts as not given: ShipWorks answers as without the field, the MCP screen opens no server, the channel export answers "That channel could not be found." (404), the address book saves a new recipient.
- Read only when text (`is_string()`): Carrier Scorecard `figure`, the Workspace calendar feed's `feed_action`. Edit Page compares `properties` with `=== '1'` and no cast, so a list is not the pane request (PR #11076).
- `pg_payment_is_return_mode()` (`includes/fn/payments.php`) takes anything but text as no return mode; `pg_delivery_scorecard_month()` (`includes/fn/delivery_scorecard.php`) takes it as a broken key, which is the current month.
- `shopping_cart.php` reads the posted `page_id` with `?? ''` everywhere: a post without one goes to the home page without an "Undefined array key" warning. A list was already emptied there.

## Why?
- A sandbox probe (PR #PRNUM) sent each parameter as a list: the GET parameters of every root screen that reads one (365 files), then the POST fields of the 190 screens other than add_/edit_, all at once and one at a time. Each place above wrote "Array to string conversion" to the PHP log; no screen answered 500.
- `(int)` of a list is 1: the channel export opened channel 1 for `channel[]=x`, the MCP screen server 1 for `server[]=x`; the connections and Search Console writes would take connection 1, the address book recipient 1.
- ShipWorks: `password[]=x` with a known user name reached `pg_password_verify()` and logged the same warning.

## Which tests guard them?
- `tests/panel_single_value_params_test.php` lists each screen with the names its `pg_request_single_values()` call drops, and fails when the screen reads one before the call.
- `tests/panel_string_params_test.php` lists Carrier Scorecard `figure` and the calendar feed's `feed_action`.
- `tests/payment_stripe_test.php` and `tests/delivery_scorecard_test.php` pass a list to the two helpers; `tests/endpoint_request_values_test.php` fails on a cart read of `$_POST['page_id']` without a default.
- A screen parameter read with a cast goes into one of the two lists.
