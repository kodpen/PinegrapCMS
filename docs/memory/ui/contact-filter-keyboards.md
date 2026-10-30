---
name: contact-filter-keyboards
description: Contacts Advanced Filters: no address autofill, phone and web address keyboards; Submitted Forms Referring URL keyboard
---

## Why `autocomplete="pg-no-autofill"` on the Contacts filters?
- `view_contacts.php` is a GET form whose box names are `first_name`, `last_name`, `company`, `business_/home_address_1/2`, `_city`, `_state`, `_zip_code`, `_country`, the phones and `email_address`. Chrome offered the admin's own saved address; picking it filled the whole section and the list found nothing (the Orders symptom, `order-filter-names.md`). `off` is ignored by Chrome (`settings-filter-autofill.md`).
- The person (first and last name), company, address, phone, fax and e-mail boxes carry the token.

## Which keyboards?
- Business, home and mobile phone and both fax boxes: `inputmode="tel"`. E-mail keeps `inputmode="email"` and its inputmask.
- Website, and Referring URL (`http_referer`) on Submitted Forms: `inputmode="url"`, `spellcheck="false"`, `autocapitalize="off"`.
- Zip codes keep the text keyboard (postcodes hold letters).

## Why do they stay `type="text"`?
- Matching is `LIKE` (`includes/fn/contact_list.php`), so a partial number or address must still filter; `type="tel"`/`"url"` would add nothing and `url` would refuse a partial value.

## What was left?
- Submitted Forms Contact Name and Username: too few address-type boxes and no password box, so no autofill is offered.
- The Add/Edit Contact form (`includes/forms/contact_form.php`) phone and website boxes.

Test: `tests/contact_filter_keyboards_test.php`.
