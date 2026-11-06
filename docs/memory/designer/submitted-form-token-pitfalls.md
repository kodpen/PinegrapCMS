---
name: submitted-form-token-pitfalls
description: Pitfalls of submitted-form tokens in system widgets: single escape, single-pass replace, truncate before escape, submitted_form_info counters per page and who refreshes them on designed pages.
---

## When is a value escaped in token replacement?
- **Once**, inside `pg_sw_apply_tokens()` (`prepare_form_data_for_output()`). Escaping during indexing turns `&` into `&amp;amp;`.
- Truncating the comment preview and turning comment attachments into links happen **before** escape; truncating escaped text cuts `&amp;` in the middle (a bug of the classic screen).

## What happens to a submitted value bound to a link's address?
- A record value that **starts** an `href`, `src`, `action`, `formaction`, `poster`, `cite` or `background` (`href="^^website^^"`) keeps only `http(s)`, `mailto`, `tel` or a scheme-less path; any other scheme (`javascript:`, `data:`) prints an empty address and the link text stays (`pg_sw_token_starts_address()`, `pg_sw_address_value_safe()`). Tabs, line breaks and control characters are removed before the scheme is read, as browsers do.
- A token after a scheme the designer wrote (`href="mailto:^^email^^"`) and renderer-built `$extra` values are not checked.
- Why: an `<input type=url>` is stored as a text box (`_pg_cf_type_by_input()`), so the server never checked the scheme; a seller applicant's website on the Marketplace template printed `href="javascript:…"` for staff to click (#7516).

## How must replacement run?
- **One pass per token** (`preg_replace(..., 1)`): the same name may appear with two different formats; a global replace would overwrite the second with the first's output.

## Which page do `submitted_form_info` counters belong to?
- Counters are kept **per form item view page**. In a list the widget's configured detail page is read; in detail view the page itself (`_pg_sw_current_page_id()`).
- If no detail page is selected there is no row: the token prints 0 (`pg_sw_apply_tokens()`), while a filter reads it as '' (`form-list-filters-fail-closed.md`, unanswered records).
- Designed detail pages write the view counters too (`record-view-counts.md`); the page prints the count before the current view.
- Moderation keeps the comment counters of designed detail pages as well ([[comments-moderation-counters]]).
- `submit_order.php` (a product's add-comment feature) ties its comment to the record only when the add-comment page is a classic form item view. On a designed record page it stores `item_type` '' and `item_id` 0, so the comment never belongs to the record and never counts there; no refresh changes that.

Related: docs/memory/designer/submitted-form-system-fields.md
