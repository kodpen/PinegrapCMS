---
name: submitted-form-layout
description: The submitted form create and edit screens on the wide edit layout: Form Data, a sticky Summary/Form Editor/Details column, the offline payment button's own form
---

## How are the screens laid out?
- The wide edit layout (`edit-screen-wide-layout.md`, B11 of the Bootstrap 6 redesign). `includes/forms/submitted_form_form.php`: `pg_submitted_form_layout($form_column, $status_column, $buttons)` puts the Form Data card (`pg_submitted_form_data_card()`, a loaded revision's notice above the fields) in the form column, cards from `pg_submitted_form_status_card()` with `pg_submitted_form_status_row()` lines in the sticky status column, and the buttons in the sticky save bar. Tests: `tests/submitted_form_form_test.php`.

## What does each status column hold?
- Edit: Summary (status, reference code, custom form, quiz score, payment, submitted and last modified, the workspace button), Form Editor (the username box), Details (address name, tracking and affiliate codes, referring URL, member id, IP address). The former full-width Form Properties card and the reference/submitted lines above the form are gone. The revision history stays full width under the form.
- Create: Summary (custom form) and Notifications (the send switch). The first step, picking the custom form, keeps its one card.

## Why does Record an offline payment carry a form attribute?
- It now sits inside the screen's own form, and a form inside a form is dropped by the browser. The button posts the empty `#pg_record_offline_payment` form printed after the screen's form (`form="pg_record_offline_payment"`), with the token and the id.
