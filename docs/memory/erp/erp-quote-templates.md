---
name: erp-quote-templates
description: Sales quote templates (#3717, step 11.0.63; API #3921): table, who sees and writes one, what it keeps, shared checks, events, the panel box, the API routes.
---

## Where are they kept? (`includes/erp/quote_templates.php`, `erp_quote_templates`)
- Columns: `name` (100), `scope` (`personal` | `site`, a VARCHAR so a new scope needs no migration), `user_id` (owner of a personal row, 0 for site), `form_data` (`{"lines": [...]}`), `created_at`, `updated_at`; KEY (`scope`, `user_id`). In `get_tables()`. Decision #3672.
- `erp_quote_templates_ready()` probes `form_data`; before the update the box is not drawn.

## Who sees and who writes one?
- `erp_quote_template_visible()`: a personal row only for its `user_id`, a site row for everyone with the ERP right. `erp_quote_templates_for_user()` reads the same rule in SQL.
- `erp_quote_template_write_refusal()`: the accountant's read-only right writes none; a personal row only by its owner; a site row by anyone who may write quotes (the same right as editing a quote).

## What does a template keep?
- Lines only, typed by `erp_quote_template_line_keys()`: product, description, quantity, unit, unit price (minor units), discount, VAT, second tax, withholding. Never the account, dates, currency, number, notes, an offer or a source line. Empty rows are dropped.
- Saving under a name that exists in the same scope (and owner) writes over it.

## Which checks and events do the panel and the API share? (#3921)
- `erp_quote_template_save_check()` (refusal, field, tidied name, `form_data`, `owner_id`, `replaces` = the same-name row; the lookup is injectable for tests) and `erp_quote_template_delete_check()` (404 not seen, 422 may not change). `erp_quote_template_save()` / `_delete()` call them first, so the panel and the API meet one set of refusals.
- After the write they announce `erp.quote_template.saved` / `erp.quote_template.deleted` (payload id, name, scope) through `erp_event()`: webhooks and the audit trail (label = name, no object-type filter row: a template has no edit screen). The panel still writes its Site Log line.

## Where is it used?
- add_erp_quote.php, a "Quote templates" card above the editor: "Fill the lines" (replaces the editor's lines, nothing else), "Delete", and a name + "Only me" / "Everyone" + "Save as template" that keeps the typed lines. Each button submits the editor's own form with `template_action`, so what was typed comes back after the redirect.
- External API (`includes/erp/api_quote_templates.php`, #3921): `GET /erp/quote-templates` (one page, every visible template), `GET /erp/quote-templates/{id}` (404 when not seen), `POST /erp/quote-templates` (name, scope, lines as `POST /erp/quotes` takes them; 201 new, 200 written over), `DELETE` + `POST …/delete`; writes `erp:write` + `dry_run`, 503 before the update. `erp_quote_template_lines_from_api()` maps the API lines (unit → unit_code). Object `ErpQuoteTemplate`: id, name, scope, `mine`, lines, updated_at; never the owner. No `template_id` on `POST /erp/quotes`: a client sends the lines itself.
- Tests: tests/erp_quote_templates_test.php, tests/erp_api_quote_templates_test.php. Not run against a database.
