---
name: content-translation-product-answers
description: How a product's texts reach a page drawn in another language after it is drawn: variant data in the page, get_product refreshes with pg_lang, pg_tr_product_fields()
---

## Which product texts does a translated page get after it is drawn?
- The page itself is translated as it goes out (`pg_tr_render_db_texts()`), including the variant chooser's data script (`window.pgCivVariants`, `pg_tr_render_json_strings()`). A JSON string with HTML blocks in it (a variant's two-paragraph description) is translated by its blocks with `pg_tr_render_html_value()`, the way it was stored (`pg_tr_html_segments()`); other strings as one text (`pg_tr_db_text()`).
- The variant chooser (`assets/js/pg_civ_variants.js`) refreshes the product (`get_product`) when the page opens with a complete selection and after every pick, and the cross-sell row (`get_cross_sell_for_product`). Both posts go to `apiUrl()`: `data.api_url` plus `pg_lang` from `<html data-pg-lang>`, the attribute frontend.js's jQuery prefilter reads for the software's other requests.
- api.php defines the language from `pg_lang` (`pg_tr_define_from_request()`); `pg_panel_get_product()` passes the row through `pg_tr_product_fields()` (`includes/fn/translate.php`): `short_description` as one text, `full_description`, `details`, `out_of_stock_message` by their blocks. `name` is the Product ID and stays. Unchanged in the source language or without a translation.

## What was wrong before?
- Before PR #1113 (issue #853) the refresh posted without `pg_lang` and `get_product` never translated, so on `/en/...` the product name and details went back to the source language a moment after the page opened and on every pick; the variant data's paragraph descriptions were looked up as one text and stayed in the source language.

## Tests?
- `tests/translate_product_answer_test.php`; checked in the sandbox with a translated product (`get_product` with and without `pg_lang`, the variant script).
