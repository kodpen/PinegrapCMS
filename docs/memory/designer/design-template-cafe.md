---
name: design-template-cafe
description: Cafe and Clinic templates: event weekdays, price hint, rating numbers, guests pattern, consent privacy link, subject dates, booking links that fill the form.
---

## How are the sample event dates chosen?
- `$event_on($weekday, $weeks)` in `pinegrap-cafe.php`: the first day strictly after today with that ISO weekday (1 = Monday), plus `$weeks` weeks, so every event is still to come and lies within three weeks.
- Each event falls on the day its title and café need: Acoustic Friday on Friday, the Business District workshop on a weekday (that café is closed at weekends), the brunch on Sunday (#3573). Before, fixed day offsets put "Acoustic Friday" on any weekday.

## Why does the menu price field have a hint?
- Price stays free text (no data-model change), so `$field(..., $hint)` draws a `form-text` under the control (`<id>-help`) and the input names it with `aria-describedby`: write the price with the currency, as the menu shows it.

## Why do rating numbers go through lang()?
- "4.8" (Cafe) and "4.9" (Clinic) are written with a decimal comma in Turkish ("4,8"), so they are `lang()` keys.

## How is the catering guest count checked?
- `cf-quote-guests` carries `pattern="[1-9][0-9]{0,3}"` and `_cf.validation_message`; the form keeps them as the field's validation regex and message, and the server refuses 0, negatives and decimals (#3572 item 2). The browser shows `min`/`step` for the number input.

## What does "privacy notice" in the consent boxes point at?
- Both templates carry a sample `privacy` page (`privacy-policy`, sample note, one section `#your-data`, in the search map), and `$agree(..., true)` adds a `{{page:privacy}}` link after the label: the Cafe reservation and quote boxes, the Clinic appointment box. Neither template has a legal-links footer row, so no footer link.

## Which Turkish values were corrected?
- Coffee Break price suffix, the book-club summary, the captcha question, and the "Please login or register to add your …" line, which uses its second variable once because `|c` cases every occurrence ([[lang-function-syntax]]).

## Where are its comment threads and reviews?
- On the Comments widget since 1.1.0 (Café and Clinic), with Google Reviews beside the sample testimonials: [[design-template-threads]].

## How is the date written in a notification subject?
- A bare `^^<date>^^` printed plain `7/11/2026` while the body showed `07.11.2026` (#4545). The Café, Clinic and Salon subjects go through `pg_cf_subject_date_format(lang(...), array('<date field>'), pg_sw_default_date_format('date'))` at publish (PR #4567): the format the body uses is added to the token. Running sites keep their stored subjects.

## Which buttons open a booking form with the choice made?
- Clinic (PR #4567, #4547): a doctor profile's "Book an Appointment" sends `?department=<service title>&doctor=<name · specialty>`; "Book this check-up" sends `?reason=<package title>` (the form has no package field). The form's Specialty, Doctor and Reason fields carry `_cf.url_param` ([[custom-form-url-prefill]]).
- Café: an event card's "Book a Table" is a `__page_link` with `cafe` and `day` (row tokens `cafe`, `event_date`; `date` is a reserved name) and the reservation form's Café and Date fields read them.
