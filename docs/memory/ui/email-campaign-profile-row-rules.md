---
name: email-campaign-profile-row-rules
description: How campaign profile values are checked on the form and the CSV import, midnight, length, required pickers
---

## How does the import check a row?
- `import_email_campaign_profiles.php` lays the file's cells over the stored row (or a new profile's defaults: enabled 1, plain text, days, commercial) and runs `pg_email_campaign_profile_values_problem($values, $commerce)` (pure: name ≤ 100 characters, enabled 0/1, known action/format/unit/purpose, item id for item actions, page for HTML, valid e-mail addresses, time, length, calendar period/base) then `…_access_problem()` (calendar event, custom form edit access, a basic user's own followed profile, HTML page view access). A refused row goes to `pg_csv_problem_list` as "Line n: reason"; nothing is written.
- Then `pg_email_campaign_profile_text_problems($values)` (pure, field => message): subject and from name required (a new profile's defaults hold them empty, so a file without those columns is refused) and each text column within its column length in characters: name, from name and the three addresses 100, subject 255. The form runs the same function in `pg_email_campaign_profile_form_validate()`, so a POST past the HTML `maxlength` is refused instead of cut. Before, the import stored rows without subject or from name (enabled by default, so they mailed customers with both empty) and cut long cells silently.
- The export's read-only columns (`action_item`, `page_name`, `created`, `created_username`, `last_modified`, `last_modified_username`) are skipped without the "unknown columns" warning, so a re-imported export reads clean.
- Every action but a calendar event reservation is written with `schedule_period = after`, `schedule_base = action`, as the form saves it. Before (#5537) cells were written as they were: an unknown ENUM value became `''`, the column default `before` made order profiles send before the action, and a name over 100 characters was cut and created a new copy on every import.

## How is midnight stored?
- `00:00:00` means "any time", so `pg_email_campaign_profile_schedule_time()` stores a chosen midnight as `00:01:00`, both "12:00 AM" and the 24-hour "00:00" (before, only "12:00 am" was caught, #5539). A blank time stays "any time". The import uses the same function.

## Which lengths are valid?
- `pg_email_campaign_profile_schedule_length_valid()`: empty or 1–9 digits. The form refuses others ("Please enter a valid number of days or hours.") and draws the box with the `integer` mask without minus. Before (#5538) the decimal mask let "-3.75" through and the non-strict SQL mode saved it as 0.

## Which pickers are required?
- Action is drawn `required`. The trigger item picker of the chosen action and, with HTML, the Page picker are `required` only while shown: the server sets it from `pg_email_campaign_profile_action_rows()`, `change_email_campaign_profile_action()` and a `format` change listener (`backend.src.js`) toggle it; their labels carry a static `.pg-required-marker` because the load-time marker script does not see later changes (#5542).
- The HTML body Page picker's preview reads the page address by page id from the frame's `data-page-urls` (built with `encode_url_path()`, like its server-side source); the placeholder blanks it to `about:blank`. Before, the onchange built the address from the option label, so the placeholder requested `/-Select Page-` (a fake 404 in the Not Found log) and an `[ARCHIVED]` label a wrong page.
- The calendar before/after and base pickers sit in their own `.input-group` beside the length group (wrap on narrow screens) and open with "-Select …-" placeholders (#5541).
