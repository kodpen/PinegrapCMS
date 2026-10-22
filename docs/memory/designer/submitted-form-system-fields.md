---
name: submitted-form-system-fields
description: Single record of submitted-form system fields (get_standard_fields_for_view), pg_sw_* helper functions, date format suffix and order_by_field for system widgets.
---

## Where are a submitted form's system fields defined?
- One place: `get_standard_fields_for_view()` (`includes/fn/forms.php`). Classic Form List View / Form Item View resolve `^^reference_code^^`, `^^submitted_date_and_time^^`, `^^submitter^^`, `^^number_of_comments^^`, `^^newest_comment^^` ... against it; system widgets resolve the same record via `pg_sw_standard_fields()`.
- **Never open a second namespace.** A `__xxx` copy was once invented; the default template ended up bound to a nonexistent `__subject` and another widget's `__submitted_at`, both silently empty on the frontend.
- Three names with no counterpart in the record are defined in `pg_sw_standard_fields()`: `form_item_view` (detail link, classic screens' own token), `submitted_form_id`, `submitter_user_id`.
- New system field: check `get_standard_fields_for_view()` first (if present it arrives automatically); otherwise add it next to those three with a justification.
- **my_account**'s submission loop carries the same names, so a row design moves across three widgets.

## Which helpers exist (`includes/fn/widgets.php`)?
| Function | Job |
|---|---|
| `pg_sw_standard_fields()` | record + JOIN dependencies + the three extra names |
| `pg_sw_template_tokens()` | names the template writes (format suffix is not part of the key) |
| `pg_sw_standard_sql()` | column and JOIN only for written fields |
| `pg_sw_index_form_data()` | form's own fields, with **raw** values |
| `pg_sw_apply_tokens()` | replacement; a standard field beats a same-named form field |
| `pg_sw_sweep_tokens()` | deletes unanswered names (with format suffix) |

## How does the date format suffix work?
- `^^submitted_date_and_time^^%%d.m.Y%%` and `%%relative%%` work as in classic screens.
- The format lives in the node's `props._bindFormats[<prop>]`, not in the token; `_apply_bindings()` (`includes/fn/designer.php`) appends it when producing the token and passes only characters `date()` reads.
- Designer: selecting a date field opens a format box under the Bind Data row (`_sdBindFormatRow()`, `SW_DATE_FIELD_TOKENS`).

## What does the sort setting use?
- `order_by_field` uses the same names: `submitted_date_and_time` | `submitted_form_id` | a form field name.
- Plus the counters `number_of_views` | `number_of_comments` | `recent_views` (with `order_recent_days`, 1-180, default 7), in both sort fields. They win over a same-named form field and need the list's detail page; without one the date order applies. See `record-view-counts.md`.

Related: docs/memory/designer/submitted-form-token-pitfalls.md
