---
name: registration-gate-form-submitter
description: The registration folder gate in get_page.php lets a visitor without a session reach a custom form's confirmation page with the code of a form this session submitted (PR #4959, bug #4804)
module: custom_form
---

## What does the registration gate let through without a session?

Only a custom form's confirmation page (`custom_form_pages.confirmation_page_id`
or `confirmation_alternative_page_id`, which `custom_form.php` sends to
instead when set), when the request carries `r=<code>` and that code is in
`$_SESSION['software']['submitted_form_reference_codes']`. Everything else
in the folder still goes to the sign-in page. Helpers:
`pg_cf_registration_gate_lets_submitter()` (reads the request and the
session, one query) and the pure `pg_cf_submitter_gate_pass()` (strict
string match; test `tests/registration_gate_submitter_test.php`).

## Why?

The templates' booking, appointment and contact forms open an account on the
first submission; a returning visitor with an account but no session was sent
to the sign-in page instead of "your request has been received" (#4804).
Owner decision #4938 (`workflow/decisions-made-product-18.md`).

## What still protects the record?

The record view on that page keeps its `submitter_only` rule
(`pg_sw_submitted_form_visible()` in `includes/fn/widgets.php`), so
only the visitor's own submission is shown. No other folder rule changed.
