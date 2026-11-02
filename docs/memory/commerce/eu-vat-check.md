---
name: eu-vat-check
description: EU VAT ids checked against VIES: the clean function and patterns per member state, the REST request, answer mapping, a day's cache, the reverse-charge decision; what SF6b still wires
module: vat_check
---

## What does includes/fn/vat_check.php hold?
- `pg_vat_id_clean($id, $country)` (pure): drops spaces, dots, dashes, slashes, underscores, upper-cases, reads the prefix from the id or takes the billing country's, checks the number against `pg_vat_id_patterns()`. Errors: `empty`, `characters`, `country`, `format`.
- Prefixes are VIES's, not ISO: Greece is `EL` (a typed `GR…` id and the ISO country `GR` both become `EL`); `XI` (Northern Ireland) is checkable by a typed prefix only, since a billing country `GB` cannot tell it. `pg_vat_vies_prefix($iso)` and `pg_vat_eu_member($iso)` take ISO codes and refuse `EL`, `XI`, `GB`.
- `pg_vat_check($id, $options)`: malformed ids answer `status = 'malformed'` with no call; otherwise one POST through `pg_curl_run()` (10 s, connect 5 s, 64 KB cap) to `PG_VAT_VIES_URL` (`…/vies/rest-api/check-vat-number`, JSON `countryCode`, `vatNumber`). No key: VIES is free and open. Option `requester` (the site's own VAT id) adds `requesterMemberStateCode`/`requesterNumber`, and VIES answers a consultation number (`request_id`), the auditor's proof. Option `http` replaces the transport in tests.

## How is an answer read?
- `pg_vat_check_map($response, $now)` (pure): `valid`, `invalid` (VIES says no such id) or `unavailable`. VIES reports refusals in the body, with any status: `errorWrappers[0].error` or a `userError` other than `VALID`/`INVALID` (`MS_UNAVAILABLE`, `MS_MAX_CONCURRENT_REQ`, `TIMEOUT`, `INVALID_INPUT` …) become `unavailable` with that code; no answer is `network`, an error status `http_<n>`, a body without `valid` `answer`. `"---"` (name or address not disclosed) becomes ''.
- `checked_at` is the time of the VIES call; a cached answer keeps it.

## What is cached?
- Valid and invalid answers, a day (`PG_VAT_CACHE_TTL`), one JSON file per id under `data/cache/vat/` (sha1 name, written to a temporary file and renamed, a stale file removed on read). `unavailable` is never kept: the next try asks again. `fresh` skips the cache.

## When is an order tax-exempt?
- `pg_vat_exempt_decision($check, $billing_country, $site_country)` (pure, owner decision #5047 question 4: a VIES-confirmed id sets the flag on its own): exempt only when VIES said valid, the billing country and the site's are both member states and differ, and the id belongs to the billing country. Reasons otherwise: `no_check`, `not_eu`, `same_country`, `other_country`, `unavailable` (VIES down: the order is taxed and carries a note), `invalid` (malformed too). A site outside the EU never exempts, so nothing changes there.

## What is still to wire (SF6b)?
- The billing information widget, the express order and the account card's tax number box calling the check, the texts, `orders.tax_exempt` set from the decision, the ERP invoice's reverse-charge note, and where the id and check time are written on the order (`orders` has no tax column, `erp-order-tax-number-difference.md`): a schema question.

## Which tests cover it?
- `tests/vat_check_test.php`: every prefix's pattern, wrong shapes, prefix from country (GR/EL), the request with and without requester, valid/invalid/unavailable mapping, every decision reason, no call for a malformed id, the cache's day and no caching of `unavailable`.
