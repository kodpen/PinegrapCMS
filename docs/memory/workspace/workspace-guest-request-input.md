---
name: workspace-guest-request-input
description: How the public guest page reads the strings of its JSON body and its cookie
---

# Guest page request input

## What does `workspace_guest.php` take from a request?

The JSON body (`action`, `token`, `csrf`, `signature`, `text`, `emoji`,
numbers and `option_ids`) and the `pg_ws_guest` cookie. The page is open to
anyone, so every value may be a list, an object or missing.

## How is a string of it read?

With `ws_guest_request_text()` (`includes/workspace/guests.php`): a string as
it is, a number as its digits, anything else as `''`. A `(string)` cast of a
list raised "Array to string conversion" on every crafted request, before
any token or session was checked. A new string field of the guest page is
read the same way. Tests: `tests/workspace_guest_request_test.php`.
