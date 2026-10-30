---
name: contact-filter-keyboards
description: Contacts Advanced Filters: no address autofill, phone and web address keyboards, an unmasked e-mail box; the panel's permissive Inputmask email alias; Submitted Forms Referring URL keyboard
---

## Why `autocomplete="pg-no-autofill"` on the Contacts filters?
- `view_contacts.php` is a GET form whose box names are `first_name`, `last_name`, `company`, `business_/home_address_1/2`, `_city`, `_state`, `_zip_code`, `_country`, the phones and `email_address`. Chrome offered the admin's own saved address; picking it filled the whole section and the list found nothing (the Orders symptom, `order-filter-names.md`). `off` is ignored by Chrome (`settings-filter-autofill.md`).
- The person (first and last name), company, address, phone, fax and e-mail boxes carry the token.

## Which keyboards?
- Business, home and mobile phone and both fax boxes: `inputmode="tel"`. E-mail keeps `inputmode="email"` with `spellcheck="false"` and `autocapitalize="off"`, and has no input mask: the stock mask posted `gmail.com@_._` for a typed `gmail.com` (or `@gmail.com`), and the `LIKE` filter escapes `_`, so a domain or partial search found nothing.
- Website, and Referring URL (`http_referer`) on Submitted Forms: `inputmode="url"`, `spellcheck="false"`, `autocapitalize="off"`.
- Zip codes keep the text keyboard (postcodes hold letters).

## Why do they stay `type="text"`?
- Matching is `LIKE` (`includes/fn/contact_list.php`), so a partial number or address must still filter; `type="tel"`/`"url"` would add nothing and `url` would refuse a partial value.

## What does the panel's `email` alias do?
- Inputmask 5.0.7's stock alias is the fixed mask `*{1,64}[.*][.*][.*]@-{1,63}.-{1,63}[.-][.-]` with `_` placeholders. It rewrote any stored or typed address with a fifth domain label or a fifth local-part dot (`ali@mail.cs.hacettepe.edu.tr` → `…edutr`, `first.middle.last.name.x@site.com` → `…namex@…`); `validate_email_address()` accepted the rewritten form, and an edit screen rewrote the stored address as it drew, so a Save without touching it wrote the wrong one back.
- `backend.src.js` (beside the `currency` override, before the binding masks on document ready) redefines it as the regex `[^\s]*`, no placeholder, not greedy: any run of non-space characters, whitespace refused, lowercase fold and `inputmode="email"` kept from the stock alias. The server check stays `validate_email_address()`. Every panel `data-inputmask-alias="email"` box gets it.
- Checked on a scratch page in headless Chromium with the repo's jQuery, `jquery.inputmask.min.js`, the binding and the override: both addresses above keep every dot, typed `gmail.com` and `@gmail.com` stay as typed, `Ali Veli@x.com` becomes `aliveli@x.com`. Not checked on a running panel.

## What was left?
- Submitted Forms Contact Name and Username: too few address-type boxes and no password box, so no autofill is offered.
- The Add/Edit Contact form (`includes/forms/contact_form.php`) phone and website boxes.

Test: `tests/contact_filter_keyboards_test.php` (filter boxes; the email alias override in `backend.src.js` and `backend.min.js`).
