---
name: erp-product-suggestion-list
description: Invoice/waybill line editors: the product suggestion list leaves the lines table for the overlay host while open, fixed placement, keyboard, why (.table-responsive clipping).
---

## Where does the product suggestion list live?
- In its cell (`[data-erp-product-results]`, id `line_product_results_<n>`) only while closed. Open, `erp_invoice_editor.js` moves it to `pgUI.overlayHost(input)` and places it `position:fixed` from the search box's rect (`resultsPlacement()`: at least 320px wide within the viewport's 8px margins, at most 320px high with its own scroll via `--bs-menu-max-height` / `--bs-menu-overflow-y`, above the box when the room below is short).
- It follows page scroll and resize, closes on a scroll of the lines table, on focus elsewhere, on Escape and on a pick, and goes back to its cell on close. `resultsMenu(row)` finds it in either place.

## Why not leave it in the cell?
- The lines table's Bootstrap 6 `.table-responsive` has `overflow-x:auto` (so overflow-y computes to auto) and `container-type:inline-size`; layout containment makes it the containing block even for `position:fixed` children. A list left inside was clipped to a line or two of the up to 20 results (`get_erp_products.php`), with a scrollbar on the lines area. Do not remove the wrapper, use `data-bs-strategy="fixed"` or place the list fixed in place.

## How is it reached from the keyboard?
- The search box is a `combobox` with `aria-autocomplete="list"`, `aria-controls` and `aria-expanded` (set by `describeSearch()` on load and on a new line; liveform has no aria passthrough).
- ArrowDown goes from the box to the first result, the arrows move, ArrowUp on the first, Escape and Tab go back to the box.

## How is it tested?
- `tests/erp_product_results_test.php`: `resultsPlacement()` cut from the script and run under Node (skipped without Node), the wiring and the menu ids pinned in the source.

Related: docs/memory/erp/erp-invoice-editor-catalog-offers.md, docs/memory/ui/pg-ui-adapter.md
