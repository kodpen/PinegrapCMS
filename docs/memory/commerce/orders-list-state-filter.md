---
name: orders-list-state-filter
description: The orders list's Billing and Shipping State filters join states by code inside the address's own country (pg_order_list_state_join()), since a state code is unique only per country
module: order_list
---

## How do the State filters of view_orders.php find the state's name?
- `pg_order_list_state_join($alias, $state_column, $country_column)` (`includes/fn/order_list.php`) joins `states` by code and `country_id IN (SELECT id FROM countries WHERE code = <address country>)`. The country is a subquery, not a join, so no order row is repeated.

## Why not by code alone?
- `state_form.php` keeps a code unique only inside its country (WA is Washington and Western Australia). Joined by code alone, an order whose code two countries share came out twice in the CSV exports (they add `DISTINCT` only for the product and shipping joins), and a state name of one country found the other country's orders with the same code.

## What is still open elsewhere?
- `get_tax_rate_for_address()` (`includes/fn/forms.php`) and the shipping zone lookups in `includes/fn/ecommerce.php` also read `states.code = ?` without the country; left for a later fix.
