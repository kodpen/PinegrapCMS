---
name: mail-merge-values-once
description: replace_variables() fills every ^^field^^ in one pass; a filled-in value is never read again as a field.
module: mail
---

## How does replace_variables() fill the fields?
- Conditionals (`[[…^^field^^…||…]]`) are resolved first, on the template.
- Then one `preg_replace_callback()` pass over the content fills every
  `^^field^^` / `^^field^^%%format%%` with its own prepared value (escaped
  for HTML, dates formatted). A name that is not one of the message's
  fields stays as written.

## Why one pass?
- It used to collect the variables, then replace each one with
  `preg_replace(..., 1)` on the content filled so far. A value holding
  `^^code^^` (a gift card buyer's sender name, a form answer, a customer
  name) was filled in before the template's own `^^code^^` and then took
  that field's data, while the real `^^code^^` place reached the recipient
  raw (fixed in #PR).
- A value is printed as typed, `^^…^^` included; test
  `tests/mail_merge_values_once_test.php`.
