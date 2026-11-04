---
name: email-campaign-profile-row-rules
description: How campaign profile values are checked on the form and the CSV import, midnight, length, required pickers
---

## How does the import check a row?
- `import_email_campaign_profiles.php` lays the file's cells over the stored row (or a new profile's defaults: enabled 1, plain text, days, commercial) and runs `pg_email_campaign_profile_values_problem($values, $commerce)` (pure: name ≤ 100 characters, enabled 0/1, known action/format/unit/purpose, item id for item actions, page for HTML, valid e-mail addresses, time, length, calendar period/base) then `…_access_problem()` (calendar event, custom form edit access, a basic user's own followed profile, HTML page view access). A refused row goes to `pg_csv_problem_list` as "Line n: reason"; nothing is written.
- A bad cell names its column in quotes: `The "{var:1}" column has an invalid value.` ("\"enabled\" sütununda geçersiz bir değer var."), for enabled, action, action_item_id, format, the three e-mail addresses, schedule_time/unit/period/base and purpose; the API gets the same text. Before (#6705) the raw column name stood in "Please enter a valid {var:1}." as if it were a field label ("Lütfen geçerli bir enabled girin.").
- Every action but a calendar event reservation is written with `schedule_period = after`, `schedule_base = action`, as the form saves it. Before (#5537) cells were written as they were: an unknown ENUM value became `''`, the column default `before` made order profiles send before the action, and a name over 100 characters was cut and created a new copy on every import.

## How is midnight stored?
- `00:00:00` means "any time", so `pg_email_campaign_profile_schedule_time()` stores a chosen midnight as `00:01:00`, both "12:00 AM" and the 24-hour "00:00" (before, only "12:00 am" was caught, #5539). A blank time stays "any time". The import uses the same function.

## Which lengths are valid?
- `pg_email_campaign_profile_schedule_length_valid()`: empty or 1–9 digits. The form refuses others ("Please enter a valid number of days or hours.") and draws the box with the `integer` mask without minus. Before (#5538) the decimal mask let "-3.75" through and the non-strict SQL mode saved it as 0.

## Which pickers are required?
- Action is drawn `required`. The trigger item picker of the chosen action and, with HTML, the Page picker are `required` only while shown: the server sets it from `pg_email_campaign_profile_action_rows()`, `change_email_campaign_profile_action()` and a `format` change listener (`backend.src.js`) toggle it; their labels carry a static `.pg-required-marker` because the load-time marker script does not see later changes (#5542).
- The calendar before/after and base pickers sit in their own `.input-group` beside the length group (wrap on narrow screens) and open with "-Select …-" placeholders (#5541).
