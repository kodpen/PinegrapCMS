---
name: contact-lead-source
description: contacts.lead_source picked from referral_sources on the add/edit contact screens (#3427, decided #3416 3(a)); old free text kept; the same check on the external API (#4043); writers left as they were
module: contacts
---

## What do the contact screens show?
- The shared form (`includes/forms/contact_form.php`, `pg_contact_form_lead_source_field()`) draws a select: an empty choice, then each `referral_sources` row (`pg_referral_sources()`, `sort_order, name`), value = `code`, label = `name`. The code is stored because the billing information and checkout already write the code into `contacts.lead_source`.
- A stored value that is no code of the list (free text from before, a deleted source, a name typed by hand) is one more selected choice with a note under the field (`pg_contact_lead_source_options()`, `kept`). Saving keeps it; nothing is migrated or rewritten.
- Before #3427 it was a free text input.

## What does the check do?
- `pg_contact_lead_source_check($value, $sources, $current)` (includes/fn/contacts.php): '' empties; a code or a name (trimmed, any case) is stored as the code; the contact's current value is kept as it is; anything else is refused with a translated message naming it.
- The screens do not call it on save (the select limits the choices; edit_contact.php was held by another PR). The external API calls it.

## What does the external API do? (#4043)
- `POST /customers` and `POST /customers/{id}` take `lead_source` (body string, `max_length` 50 = the varchar(50) column). `api_customer_lead_source()` (includes/api/resources/customers.php) runs the check against `pg_referral_sources()` in `api_customer_set_clauses()`, before `api_dry_run_stop()`: create with current '', update with the stored value read by `api_customer_reachable_or_fail()`. A refusal is 422 `validation_failed`, field `lead_source`, with the check's message.
- `GET /customers`, `GET /customers/{id}` and both writes return `lead_source` (`string?`, the stored value, old free text included). The `customer.created` / `customer.updated` payloads are unchanged.
- Before #4043 the API neither took nor returned it.

## Which writers do not use the check?
- Writers that keep writing what they wrote: `import_contacts.php` (any text), Workspace lead intake and the Workspace contact change (`includes/workspace/changes.php`, text 50), billing information and checkout (the code), forms with the `lead_source` contact field.

## Tests?
- tests/contact_lead_source_test.php: the options, the kept old value, the refusal of an unknown new value, the field markup.
- tests/api_customer_lead_source_test.php: both route rows, the stored value or refusal per input, the Customer presenter keys = schema keys.
