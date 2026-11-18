---
name: customer-value-groups-csv-api
description: What the Customers Report hands on: a contact group from a cell of the groups table, the top customers CSV, GET /reports/customer-value and who gets names (#10297)
module: customer_values
---

## How is a contact group made from a cell?
- Each cell of the Customer groups table has a "Make a contact group" button for a user who may make groups: `pg_customer_value_may_make_groups()` = the audience columns are there (`pg_audience_ready()`), Manager role or above, and `validate_contacts_access($user, true)`. Others see no button and no form.
- The button posts `make_group=<age>-<band>` with the form token; `pg_customer_value_cell_from()` takes only `0-4` and `0-2`. The screen makes a dynamic group (`pg_customer_value_group_create()`) and opens `edit_contact_group.php?id=` with a notice; the scheduled job fills it.
- The group editor takes no rules from the address and was not changed: the rules are stored when the group is made.
- Name: the cell's group, the band and the age (`pg_customer_value_cell_group_name()`), with " (2)", " (3)" when taken (`pg_customer_value_free_name()`, case aside, at most 100 characters).

## Which rules does a cell hand over?
- `pg_customer_value_cell_rules($age, $band)`, match all: `last_order within` the row's upper bound (30, 90, 180, 365; none on the last row) and `last_order not_within` the row above's bound (none on the first row); `order_count eq 1`, `gte 2` + `lte 3`, or `gte 4`.
- The audience rules count every order but a cart, by contact; the report counts placed orders by e-mail or contact and leaves cancelled, refunded and other-currency orders out. So the group's size can differ from the cell, and both the screen and the notice say so.

## Who gets the top customers CSV?
- "Export top customers" (toolbar, `view_customer_report.php?csv=top`) shows only with a summary and for a reader who sees names; the address answers 403 to anyone else before a name is read.
- Columns `list,rank,customer,last_order_number,orders,value,currency`; rows of `all` then `year`; values in major units with two decimals; names through `csv_cell()` (formula guard). `pg_customer_value_top_csv()`.

## What does the API answer?
- `GET /reports/customer-value` (`reports.customer_value`, scope `orders:read`, document `CustomerValueReport`): the summary as it is, no order summed. Keys: ready, as_of, progress {read,total}, names_shown, currency, customers, orders, not_placed, apart, periods, months, cohorts, groups (15 cells with `last_order`, `orders`, `group`), top {all, year}.
- Names in `top` only when the key also holds `customers:read` (`names_shown`); otherwise `name` is null and the last order number stays. Before the first rebuild `ready` is false, lists are empty and `progress` tells the pass.
- Presenter `api_customer_value_present()` in `includes/api/resources/reports.php`.

## Tests?
- `tests/customer_values_groups_test.php`: every cell's rules (clean, compile), the cell value, names, the rights and token order, the CSV and its refusal, the route's scope and the answer with and without names.
