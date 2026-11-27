---
name: erp-quote-link-accept-lock
description: Guest link Sign and accept under the quote's row lock (#11860): one transaction, a refused move rolls back, a used link reads accepted only while the quote is accepted or invoiced
---

## What happens when the customer presses Sign and accept? (#11860)
- `erp_quote_link_accept()` (`includes/erp/quote_links.php`) opens a transaction (`erp_tx_begin()`), locks the quote row (`SELECT … FOR UPDATE`), then reads the link's state, the refusal and the shown hash.
- Under the lock: the link is claimed (`used_at`), the signature stored (`erp_signature_store()`), the quote moved with `erp_quote_set_status(…, 'accepted', 0)`. Its answer is checked.
- A refused move (or any refused step, or a failed commit) rolls back and the customer reads "This quote can no longer be accepted through this link." The panel's own refusal text is not shown: it speaks to staff.
- After the rollback the signature row, its `files` row, the drawing on disk and the claim are undone again by hand, for a table still MyISAM (data/table-engines-and-row-size.md).
- `erp.quote.signed` and the Site Log line come only after the commit.

## What was wrong before?
- The claim and the signature were written, then the move was asked and its answer ignored. A Cancel or Turn into an invoice on Edit Sales Quote landing between the check and the move: the guarded move (#11858) was refused, yet the signature stayed on the cancelled or invoiced quote, the link read "used", and the page said "This quote has been accepted. Thank you."

## What does a used link show now?
- `erp_quote_link_state()` answers `used` only while `erp_quote_state()` is `accepted` or `invoiced` (accepted, then invoiced). A used link of a quote cancelled, rejected or opened again reads `closed`: "This quote can no longer be accepted through this link." This also covers rows left by the old race.

## Does a retry write a second file?
- A rolled-back attempt deletes its drawing; a new attempt writes a new file anyway (`erp_archive_store(…, true)`).

Tests: tests/erp_quote_links_test.php (`test_erp_quote_link_used_follows_the_saved_state`, `test_erp_quote_link_accept_under_the_quote_lock`). Related: erp-quote-guest-link.md, erp-quote-double-invoice.md
