---
name: erp-parasut-connect-card
description: Paraşüt's boxes on its page of Connections: Connect in three steps, secret and password boxes, Save, Disconnect, which company Connect takes; Default Product/Service ID
---

## Where are the connect boxes?
- On Paraşüt's page of Connections (`connections.php?service=parasut`) since #11030 (`ui/connections-accounting-page.md`); before, on Settings › Accounting › E-Invoice. The card keeps a connection block with "Manage in Connections" and the Invoice Settings.
- Status shows the line `parasut_connection_line(parasut_connection_status())` (no call to Paraşüt). "Connection details" (`parasut_page_fields_html()`): "Connect in three steps" (client id + secret → authorization page → code + Connect; a `<details>` open while not connected, closed as "Authorize again or connect another account" once connected), then "Advanced" (company number, the user name / password grant), then Save (`parasut_page_save()`).
- Connect answers `fill` (company number, emptied code and secret boxes), `status` and `tone`; `backend.src.js` writes them into the open form (`.connections-app-form`).
- Save keeps the saved company number when the box is empty (`parasut_company_id_for_save()`). Until 2026-10-19 a Save after Connect wrote the empty box back and orphaned the token (owner stamp).
- Disconnect (`parasut_disconnect()`) drops the token, the client secret and the password; client id, company number and user name stay.

## How are the secret and password boxes drawn?
- Client Secret and Password each have a help text for a stored value and one for none; only the "Saved" placeholder needs a stored value. Each box reads its own value in `parasut_credentials_enc` (re-sealed on a key reset, `platform/encryption-key-reseal-registry.md`) (`parasut_credential_boxes(parasut_credentials_decode())`). A code connection stores the secret alone; until #1599 one "Saved" and one help served both boxes.

## What does Default Product/Service ID say?
- On the E-Invoice card's Invoice Settings: only lines without a Paraşüt product card of their own go on it (shipping, charges, free text, rounding; `erp_edoc_parasut_line_product()`), every Pinegrap product gets its own card, and without it an invoice with such a line is refused (#1599).

## Which company does Connect take?
- `parasut_connect_company()` (#1599), from the box as posted, the saved number and the user's companies: a number the user can see; an empty box keeps the saved number the user can see; else the user's one company; else the list. A typed number the user cannot see is refused.
- The box is drawn with the saved number, so "Authorize again" signed in as another Paraşüt user posts it. When that user's one company is another, the store moves to it, also from an emptied box. `parasut_connect_report()` makes this a warning naming both numbers (warning tone on Connect, `add_warning` on Save with a code) and asks to check the default product/service and warehouse ids; the activity log names both numbers. Product cards kept from the old company (`products.parasut_product_id`) are not cleared.
- Before #1599 the saved number was refused after the code was exchanged, and the code was spent.

## Why does Connect read the status with an owner?
- The token is stamped with md5(company|client id|) of the values Connect has just saved (`erp-parasut-connection.md`); the request's own `PARASUT_*` constants still hold the settings from before. `parasut_connect_with_code()` returns that `owner` and `parasut_connect.php` reads `parasut_connection_status($owner)`; a page draw passes nothing and reads the saved settings.
- Until #1032 the first Connect with such boxes answered "Not connected to Paraşüt yet." beside the success notice, and pressing Connect again spent the one-time code.
