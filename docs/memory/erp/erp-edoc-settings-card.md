---
name: erp-edoc-settings-card
description: Where the e-document provider settings card lives (pgset-invoice), how it saves, the connection test endpoint, and the owner decision to keep it there.
---

## Where is the e-document settings card?
- Site Settings → E-Ticaret → `pgset-invoice` (E-Fatura), top block of the card.
- `includes/settings/prep.php` builds `$output_erp_edoc_options` (radio `edoc_provider` = `''|parasut|isbasi`, `collapse-switcher` + `data-bs-target="#edoc_fields_<code>"`, fields `edoc_<code>_<field>`); `commerce.php` prints it; `commerce.save.php` saves it with the dashboard's single Save.
- Save writes only the selected provider's fields (`erp_edoc_credentials_merge()`): a text/select box is stored as posted (emptied clears). A `password` field is never printed (#3955: the İşbaşı key and password sat in the pane's HTML): empty box, "Saved" placeholder when stored, and a "Remove the saved value" tick `edoc_<code>_<field>_clear`; a blank password box keeps the secret, only the tick clears it (the tick wins over a typed value). A selection change calls `erp_edoc_activate` + `log_activity`.
- If the table is missing (`waf_table_has_column('erp_edoc_providers','provider')`) the block is not printed.
- A field type `select` (`options` = value → label) becomes a `<select>` in `prep.php`.
- Every value is read only as one string (PR #476). `edoc_provider` missing or posted as an array leaves the provider as it is (it used to switch it off); a box missing or posted as an array keeps the stored credential (it used to store "Array" or clear it); a select value outside its `options` keeps the stored one. A normal save posts every box, so it behaves as before.

## How does "Bağlantıyı test et" work?
- `get_erp_edoc_test.php` (POST, same form via `formaction`, new tab; `validate_erp_access(..., 'settings')` + token).
- What is typed in the boxes overlays the stored credentials and is NOT saved. If the boxes were untouched, `erp_edoc_test()` runs; the "Son kontrol" line records only a test done with the stored credentials.

## What does the card show when the test or Connect is refused?
- `get_erp_edoc_test.php` and `parasut_connect.php` answer a `format=json` post before `validate_user()`: no session → 401 "Your session has expired. Reload the page and sign in again.", a token failing `hash_equals()` → 403 "Your session expired. Reload the page and try again.", both as `{tone:'danger', icon:'bi-x-circle', lines:[…]}`. The HTML path (direct post) and `pg_post_body_discarded()` are unchanged.
- The `button.pg-edoc-test` handler (`backend.src.js`) reads the JSON body whatever the status; a redirect or a body without `lines` prints `lang('An error occurred.')`. Before, it printed "HTTP Unexpected token '<'…" (`tests/bell_and_edoc_refusal_test.php`).

## Owner decision: where must the card stay?
- There is NO card in `erp_settings.php`; the Satıcı Bilgileri card has only a note with a `pg_settings_return_url('commerce','pgset-invoice')` link.
- Do not put the card back into `erp_settings.php`: keeping ERP-owned settings on the commerce dashboard is an owner decision.

Related: docs/memory/erp/erp-edoc-driver-layer.md
