---
name: erp-quote-templates
description: Sales quote templates (#3717, 2026.11.0 step 11.0.63): the table, who sees and writes one, what a template keeps, the box on add_erp_quote.php.
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
- No `erp_event()`: a template is no document; the Site Log gets a line. The API routes (`dry_run`) come in a later step with their events.

## Where is it used?
- add_erp_quote.php, a "Quote templates" card above the editor: "Fill the lines" (replaces the editor's lines, nothing else), "Delete", and a name + "Only me" / "Everyone" + "Save as template" that keeps the typed lines. Each button submits the editor's own form with `template_action`, so what was typed comes back after the redirect.
- Tests: tests/erp_quote_templates_test.php. Not run against a database.
