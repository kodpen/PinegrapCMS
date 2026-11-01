---
name: forms-api-request-checks
description: get_forms / get_form panel actions refuse a malformed limit, offset, fields, filter or sort value with a JSON error before any query
module: forms
---

## Where are get_forms requests checked?
- `get_forms()` (root `forms.php`, also behind `get_form`; panel actions `get_forms` / `get_form`, `includes/panel/content.php`) calls `pg_forms_request_problem()` (`includes/fn/forms.php`) right after the custom form check and answers its message with `error_response()`.
- A limit or offset must be a positive whole number in digits (an int, or a string of digits only); 0 or empty means not given. `fields` is a list of names; each `filters` / `sort` entry is an object whose `field` is a name; a single `sort` and `sort_order` are strings. The LIMIT clause casts both values with `(int)`.

## What did it fix?
- `is_numeric()` let `1.0`, `1e1` and `+5` through into `LIMIT`, where the query failed and the JSON endpoint printed an HTML error page. A string `fields` reached `in_array()`, and an array `field` was used as an array key: both threw a TypeError on PHP 8. `tests/forms_api_request_test.php`.
