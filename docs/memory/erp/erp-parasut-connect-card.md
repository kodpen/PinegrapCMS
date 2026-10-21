---
name: erp-parasut-connect-card
description: The Paraşüt card of the commerce settings: its status line, the three connect steps, what Connect answers (fill, status read with the token's owner), the company number on save
---

## What does the card show?
- A status line (`parasut_connection_line(parasut_connection_status())`, no call to Paraşüt), then "Connect in three steps" (client id + secret → authorization page → code + Connect; a `<details>` open while not connected, closed as "Authorize again" once connected), then "Advanced" (company number, the user name / password grant). Built in `includes/settings/prep.php`, printed in `commerce.php`.
- Connect answers `fill` (company number, emptied code and secret boxes) and `status`; `backend.src.js` writes them into the open card.
- The settings save keeps the saved company number when the box is empty (`parasut_company_id_for_save()`). Until 2026-10-19 a Save after Connect wrote the empty box back and orphaned the token (owner stamp).

## Why does Connect read the status with an owner?
- The token is stamped with md5(company|client id|user name) of the values Connect has just saved; the request's own `PARASUT_*` constants still hold the settings from before (a client id typed but not saved, an empty company box). `parasut_connect_with_code()` returns that `owner` and `parasut_connect.php` reads `parasut_connection_status($owner)` (`_parasut_token_cache_read($owner)`); a page draw passes nothing and reads the saved settings.
- Until #PR761 the first Connect with such boxes answered "Not connected to Paraşüt yet." beside the success notice (correct only after a reload), and pressing Connect again spent the one-time code ("Paraşüt did not accept the code").
