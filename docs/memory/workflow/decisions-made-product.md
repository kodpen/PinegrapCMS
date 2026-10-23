---
name: decisions-made-product
description: Decisions list, product part: express order and order preview totals, cookie window as the only translated UI text, Lorem ipsum, publish-only editing, robots.txt vs noindex, legacy layer, ERP limits.
---

## Which checkout and storefront behaviour stays?
- `get_express_order.php` and `get_order_preview.php`: their calculations are not reworked or tidied. Live sites use that feature; a change breaks their totals. Exception (owner decision, 2026-10-24): a figure the checkout charges follows `submit_order.php`'s rule, so the page shows what is charged: the order VAT per rate (#1168), the order charges' VAT (#1211), the card surcharge (#1388).
- The legacy custom style layer (`get_catalog.php`, `get_shopping_cart.php`, the custom style templates) is production: not touched, and the yardstick for correct system widget behaviour. The system widget layer, in turn, keeps no dead code for backward compatibility (`designer/frontend-conventions-layers.md`).
- A page blocked by `robots.txt` never has its `noindex` tag read by the crawler: a known trade-off of the noindex switch, which writes both (`visitors/page-noindex-switch.md`), not a bug.

## Which translation decisions stand?
- `lang()` asks `pg_tr_ui_text()` (`includes/fn/translate.php`): on a front-end language with no language file, only the cookie window's texts (`pg_consent_ui_keys()`) come from the translation store, translated in Translations › "Cookie window". Owner decision (2026-10-06, narrowed 2026-10-09): no language file is added for a new language; the software's interface language comes from the settings and is not translated. That is why `lang()` reads the database, only for those keys, once per request. Do not widen the list or bring the toolbar or the dynamic code block into translation (`platform/cookie-consent-notice.md`).
- `pg_tr_placeholder_latin()` (`includes/translate/store.php`): Lorem ipsum placeholder text is never translated and does not show in the list or in engine jobs; not an "untranslated text" finding (owner decision, 2026-10-06).

## How does the visual editor save a live page?
- A published page has no "Save": its one button is "Publish" and the change goes live at once. A new page starts as a draft, with Save and Publish (the WordPress model). A separate working copy for a live page (the Webflow model) was deliberately not built; "Save goes live" is not a finding (owner decision, 2026-10-06; `designer/page-revisions.md`).

## What about ERP features that do not exist?
- What ERP deliberately does not do today (automatic settlement matching, e-waybill, one order over several invoices, a payment link on invoices, payout imports, a bank API, an official income statement) is a phase limit, not a gap: `erp/erp-not-built-on-purpose.md`.
