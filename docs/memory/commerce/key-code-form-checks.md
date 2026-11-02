---
name: key-code-form-checks
description: Key code create/edit form checks (includes/forms/key_code_form.php): 50-character codes, an offer's own code, expiration date read whole through pg_date_input_iso()
---

## What does the create/edit form check?
- `pg_key_code_form_validate()` (#5730): code and offer code at most 50 characters (`mb_strlen`), with the import's words; before, MySQL's loose sql_mode cut a 51-letter code to 50 and the clash check had read the uncut value.
- Expiration date (#5726): checked and stored through `pg_date_input_iso()` (`pg_key_code_form_expiration()`), the whole value one real date; `1/6/20271` was read by the unanchored `validate_date()` and stored as 0000-00-00 (never expires), while `15.06.2027` was refused. Empty still means never expires.
- A key code that is an offer's own code is refused (form, API, and the import since #5727): checkout applies the offer and never reads the key code.

## How are the key code Site Log lines written?
- Each starts with a capital and ends with a full stop ("Key code ({var:1}) was modified.", "All key codes were deleted."), like the create line (#5729); the old lowercase keys are no longer used.

Related: docs/memory/commerce/key-code-import.md
