---
name: log-warnings-forms-default-screens
description: Form, calendar and default screen warnings of the PHP 8.5 error log fixed by cause (plan php-error-log-clean, EL5): what each screen now does when a row, a page or a posted key is missing
---

## What does the default View Order screen draw when the site has no View Order page?
- `get_view_order_screen()` (`includes/fn/forms.php`) calls `get_view_order_screen_content(array())`. With no page, `get_layout_type('')` is empty, so the screen took the custom layout branch: `render_layout()` built the template name from an unset `$page_type` and included `includes/templates/_system.php`, which does not exist. The order was never shown.
- Now `if ($layout_type == 'system' || !$layout_type)`: the system layout draws the order, as the default login, registration entrance, membership entrance, My Account profile and address book screens already did.
- Both default screens (View Order and Registration Entrance) read `page_id` and `device_type` with `?? ''`.

## What does Edit Field do with a field left without its form?
- A field with no page, no product and no variant set template matched none of the branches in `edit_field.php`, so none of their access checks ran and the screen was drawn with an empty heading. It now answers "Record not found." (404), the same as a missing id.

## What do the other screens read when nothing is chosen or a row is gone?
- `delete_fields.php`: Delete with no box ticked posts no `fields` list; it reads an empty list (only an array counts).
- `add_field.php`: a post without `information` stores an empty text.
- `view_submitted_forms.php`, Export Forms with no form chosen: no `custom_forms[0]`, no `custom_form_pages` row, so no quiz column.
- `edit_submitted_form.php`: a posted `form_item_view_page_id` with no form item view row reads every setting empty (no form matches, no edit allowed, no hook); a send-to address without a query string adds none.
- `create_or_update_page_type_record()`: a form list or item view record without `collection` gets `a`.
- `prepare_form_data_for_output()`, type `date`, no format: a value that is not `Y-m-d` (typed or imported text) reads its missing parts empty and prints as before (`//17.11.2026`).

## Which date does a recurring event instance get when its recurrence kind is unknown?
- `get_calendar_event()` (`includes/fn/calendar.php`) sets `$event_start_date = $start_date` before the recurrence switch. Before, an unknown `recurrence_type` (or monthly kind) left it unset, and `strtotime(' ' . $start_time)` read today's date: the instance showed on the day the page was opened. Now it stays on the event's own date.

## Where are the tests?
- `tests/forms_missing_rows_warnings_test.php`.
