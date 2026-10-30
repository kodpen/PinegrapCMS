---
name: form-data-output-escape-flag
description: prepare_form_data_for_output() escapes for HTML by default; a value bound for a query, JavaScript or h() passes false as the third argument (saved report filters, quiz Correct Answer, PR #3544)
module: forms
---

## What does prepare_form_data_for_output() return by default?
- `prepare_form_data_for_output($data, $type, $prepare_for_html = true, $date_format = '')` (`includes/fn/forms.php`) formats dates, times and signatures and then, with the default `true`, runs `h()` and `nl2br()`. The result is ready to print into HTML and nothing else.
- With `false` it only formats: dates still come back in the panel's date format, `html` values are filtered by `pg_sanitize_rich_text()`, everything else is returned as stored.

## When must a caller pass false?
- When the value goes on to a SQL operand (through `prepare_form_data_for_input()`, which does not decode entities), into JavaScript (`escape_javascript()`, then a script that escapes it for its own box), or into `h()`. Each of these needs the raw text; the default would escape it a second time.
- Callers that pass `false`: `edit_form_list_view.php` (filters to JavaScript), `view_order_report.php` and `view_visitor_report.php` (saved filters), `includes/forms/field_form.php` (quiz Correct Answer inside `h()`).

## What did the default break before PR #3544?
- A saved Order or Visitor Report filter such as `Billing Company contains Ali'nin & Co` was loaded escaped, so the report searched for `Ali&#039;nin &amp; Co` and found nothing, while the same filter typed fresh worked. `backend.src.js` `prepare_content_for_html()` escaped it again in the value box, and Save stored the entities.
- The Edit Field quiz Correct Answer was printed as `h(prepare_form_data_for_output(...))`, escaped twice: `Ali'nin` showed as `Ali&#039;nin`, `edit_field.php` saved what the box held, `custom_form.php` never matched the visitor's answer, and each save added a layer.
- The Order Report breadcrumb label also lost its `<strong>` markup in the same PR: `output_header()` escapes breadcrumb labels, so markup in a label shows as text.
