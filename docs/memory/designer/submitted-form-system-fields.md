---
name: submitted-form-system-fields
description: Single record of submitted-form system fields (get_standard_fields_for_view), pg_sw_* helper functions, date format suffix and order_by_field for system widgets.
---

## Where are a submitted form's system fields defined?
- One place: `get_standard_fields_for_view()` (`includes/fn/forms.php`). Classic Form List View / Form Item View resolve `^^reference_code^^`, `^^submitted_date_and_time^^`, `^^submitter^^`, `^^number_of_comments^^`, `^^newest_comment^^` ... against it; system widgets resolve the same record via `pg_sw_standard_fields()`.
- **Never open a second namespace.** A `__xxx` copy was once invented; the default template ended up bound to a nonexistent `__subject` and another widget's `__submitted_at`, both silently empty on the frontend.
- Four names with no counterpart in the record are defined in `pg_sw_standard_fields()`: `form_item_view` (detail link, classic screens' own token), `submitted_form_id`, `submitter_user_id`, `submitter_name`.
- New system field: check `get_standard_fields_for_view()` first (if present it arrives automatically); otherwise add it next to those four with a justification.
- **my_account**'s submission loop carries the same names, so a row design moves across three widgets.

## Which field is the author's name on a public page?
- `submitter_name` ("Author Name"): the record's contact (`forms.contact_id`) first + last name, else nickname, '' without; never `user_username`. SQL `pg_sw_submitter_name_sql()` (join `contacts AS submitter_contact`), the rule of `pg_submitted_form_author_name()` (BlogPosting).
- A form with its own `submitter_name` field (name or label slug) keeps it, decided per form (`pg_sw_form_yields()`, JS `_swStandardYields`): no contact column, filter or option; a record with no value prints ''.
- `submitter` / `last_modifier` are login usernames (a member's defaults to the e-mail's local part): staff pages only. No public or member page of a built-in template binds them (`tests/designer_submitter_name_test.php`); their post bylines bind `submitter_name` inside `pg-hide-if-empty`, so a sample signed by the setup account shows no author.
- Still printing a username: designs opened from a template before 2026.11.0 (stored trees are not rewritten; rebind by hand), comment names of a member who may not choose one (`add_comment.php` saves the username), the default sites (data/backups, frozen).

## Which helpers exist (`includes/fn/widgets.php`)?
| Function | Job |
|---|---|
| `pg_sw_standard_fields()` | record + JOIN dependencies + the four extra names |
| `pg_sw_template_tokens()` | names the template writes (format suffix is not part of the key) |
| `pg_sw_standard_sql()` | column and JOIN only for written fields |
| `pg_sw_index_form_data()` | form's own fields, with **raw** values |
| `pg_sw_apply_tokens()` | replacement; a standard field beats a same-named form field (except `pg_sw_form_field_wins()`) |
| `pg_sw_sweep_tokens()` | deletes unanswered names (with format suffix) |

## How does the date format suffix work?
- `^^submitted_date_and_time^^%%d.m.Y%%` and `%%relative%%` work as in classic screens.
- The format lives in the node's `props._bindFormats[<prop>]`, not in the token; `_apply_bindings()` (`includes/fn/designer.php`) appends it when producing the token and passes only characters `date()` reads.
- Designer: selecting a date field opens a format box under the Bind Data row (`_sdBindFormatRow()`, `SW_DATE_FIELD_TOKENS`).

## What does the sort setting use?
- `order_by_field` uses the same names: `submitted_date_and_time` | `submitted_form_id` | a form field name.
- Plus the counters `number_of_views` | `number_of_comments` | `recent_views` (with `order_recent_days`, 1-180, default 7), in both sort fields. They win over a same-named form field and need the list's detail page; without one the date order applies. See `record-view-counts.md`.

Related: docs/memory/designer/submitted-form-token-pitfalls.md
