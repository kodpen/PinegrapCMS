---
name: submitted-form-token-pitfalls
description: Pitfalls when replacing submitted-form tokens in system widgets: single escape, single-pass replace, truncate before escape, submitted_form_info counters per page.
---

## When is a value escaped in token replacement?
- **Once**, inside `pg_sw_apply_tokens()` (`prepare_form_data_for_output()`). Escaping during indexing turns `&` into `&amp;amp;`.
- Truncating the comment preview and turning comment attachments into links happen **before** escape; truncating escaped text cuts `&amp;` in the middle (a bug of the classic screen).

## How must replacement run?
- **One pass per token** (`preg_replace(..., 1)`): the same name may appear with two different formats; a global replace would overwrite the second with the first's output.

## Which page do `submitted_form_info` counters belong to?
- Counters are kept **per form item view page**. In a list the widget's configured detail page is read; in detail view the page itself (`_pg_sw_current_page_id()`).
- If no detail page is selected the counter is empty; that is the correct answer.
- Designed detail pages write the view counters too (`record-view-counts.md`); the page prints the count before the current view.
- Moderation keeps the comment counters of designed detail pages as well ([[comments-moderation-counters]]).

Related: docs/memory/designer/submitted-form-system-fields.md
