---
name: look-forms-files-screens
description: Form fields, the form list view, file, calendar event and booking screens in look A (S3, #10314): titles, group labels, field spans per field, what stayed and what waited for other PRs
---

## Which screens moved?
- Form fields (`includes/forms/field_form.php`), Edit Form List View, Import Submitted Forms; Create File, Edit File, Import ZIP; the calendar, location and event forms (`includes/forms/calendar_*form.php`); Edit Booking's New booking card titles and fields; the Bookings day cards' titles.
- The form item view, Upload (`add_file.php`) and the calendar lists were already look A but for buttons: untouched.

## What changed on them?
- Card titles: the upper-case `text-uppercase h5 fg-primary fw-bold` titles, the `div` titles with `pg-title-section`, and Edit Booking's `card-header > h2.h6` are `h2.card-header.bg-reset.border-0.pg-title-section`. A Bookings day card's `h2.h6` is `pg-title-section`.
- Group headings to `.pg-group-label`: Rows and Columns (field form), Filters, the advanced search fields and Select Fields (form list view), the event's two location and calendar lists, the group names of Edit File's Where is it used? answer.
- Whole row: a field's Name, Label and Default Value; the list view's search Label; File Name (Edit File); Tag (Import ZIP); calendar and location names; the event's Name, Short Description, special Location and Reserve Button Label.
- Side by side: Size 3 + Maximum Characters 3 + Position 6 (field form); the event's Start and End (a date with its time) 4 + 4; Reservation Product 6 + Next Page 6; Edit Booking's Staff 6 + Service 6, Date 3; E-mail 6 + Phone 4.
- Alone in their row (`pg-field-span-N`): Field Type, RSS / Search Element, Connect to Contact, Folder to upload File, Expand by Default and the ZIP file chooser at 6; the two Maximum Number of Results at 3 each (architect, channel 25 message 9274). The event's initial and remaining spots are counts at 3.
- The list view's filter table is `.pg-embedded-table` (`look-embedded-table.md`): its heads lost the inline 200 px minimum and carry `pg-embedded-table-text` (architect, 9274); the results help lines lost `text-end`.

## What stayed as it was?
- Buttons (toolbar, card and bar), the popovers' arrows and their `display` toggles (scripts read them), list boxes' scroll heights, Edit File's preview sizes and its accent ground, the coloured Form Fields / System Fields captions of the variable lists.
- Held by other open PRs and left for a later pass: Submitted Forms list, Add and Edit Submitted Form, `submitted_form_form.php`, Edit Field (#10304, #9898); the month view's `.monthly_calendar` rules in `backend.src.css`.
