---
name: erp-parasut-connect-card
description: The Paraşüt card of the commerce settings: status line, connect steps, secret and password boxes, default product help, what Connect answers, connecting another account
---

## What does the card show?
- A status line (`parasut_connection_line(parasut_connection_status())`, no call to Paraşüt), then "Connect in three steps" (client id + secret → authorization page → code + Connect; a `<details>` open while not connected, closed as "Authorize again" once connected), then "Advanced" (company number, the user name / password grant). Built in `includes/settings/prep.php`, printed in `commerce.php`.
- Connect answers `fill` (company number, emptied code and secret boxes) and `status`; `backend.src.js` writes them into the open card.
- The settings save keeps the saved company number when the box is empty (`parasut_company_id_for_save()`). Until 2026-10-19 a Save after Connect wrote the empty box back and orphaned the token (owner stamp).
- Client Secret and Password each show "Saved" and their own help only when that value is in `parasut_credentials_enc` (`parasut_credential_boxes(_parasut_credentials_decode())`). A code connection stores the secret alone; until #PR both boxes said "Saved" and the help said a password was stored, so the operator counted on a password fallback that was not there.
- Default Product/Service ID: its help says what the code does (#PR): only lines without a Paraşüt product card of their own go on it (shipping, charges, free text, rounding; `erp_edoc_parasut_line_product()`), every Pinegrap product gets its own card, and without it an invoice with such a line is refused. It used to say "used for all invoice line items".

## What happens on "Authorize again or connect another account"?
- The Company ID box holds the saved number, and Connect posts it. When the new user cannot see it and has exactly one company, that company is taken (`parasut_connect_company()`, #PR) and Connect's notice (`parasut_connect_lines()`, Connect and Save with a code alike) adds a line naming the number given up and asking to check the default product/service and warehouse ids. Before, the code was exchanged, then refused ("cannot see company"), and spent. A number typed in the box that the user cannot see is still refused, as is the saved number when the user has several companies.

## Why does Connect read the status with an owner?
- The token is stamped with md5(company|client id|) of the values Connect has just saved (a code token leaves the user name out, `erp-parasut-connection.md`); the request's own `PARASUT_*` constants still hold the settings from before (a client id typed but not saved, an empty company box). `parasut_connect_with_code()` returns that `owner` and `parasut_connect.php` reads `parasut_connection_status($owner)` (`_parasut_token_cache_read($owner)`); a page draw passes nothing and reads the saved settings.
- Until #1032 the first Connect with such boxes answered "Not connected to Paraşüt yet." beside the success notice (correct only after a reload), and pressing Connect again spent the one-time code ("Paraşüt did not accept the code").
