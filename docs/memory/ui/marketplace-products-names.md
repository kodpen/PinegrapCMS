---
name: marketplace-products-names
description: Marketplace products screen: row boxes named per row, focus kept on a redrawn row, disabled pager arrows as spans, status text in emphasis inks, counts formatted (#2988), read progress announced
---

## How are the row tick boxes named?
- Each is `Select {var:1}` with the row's name: the catalogue row's title, the shop row's name (`mp_remote_products_shop_name()`), the listing card's title. They were all "Select", so a page of rows read the same name over and over.

## Where does the focus go when a row is redrawn?
- `replaceRow()` in `assets/js/marketplace_products.js` swaps the row for the server's fresh markup. When the old row held the focus, the fresh row gives it to the same `data-mp-act` button, else its first enabled action, else its tick box (`rowFocusTarget()`); a row taken out of the view hands it to its neighbour. Before, the focused button left with the old row and the focus fell to `<body>`.
- The code box: Save and Cancel give it to the row's `[data-mp-act="code"]` button (Cancel rewrites the cell's markup).
- The product picker: after an accept it waits for `pgUI.dialog.hide()` and then focuses the fresh row, because the dialog's return target was the replaced row.

## How is the pager drawn?
- `mp_products_pager_arrow()` prints a link, or a `<span class="btn … disabled" aria-disabled="true">` when there is no page that way. Bootstrap 6 `.btn.disabled` only sets `pointer-events: none`, so a disabled `<a href>` stayed in the tab order and Enter followed it.

## Which colours carry the status text?
- Matched and ready figures, the matched/sure score and the "Sure" tag: `--pg-success-fg-emphasis`; not-sent figures and error lines (`.mpp-err`): `--pg-danger-fg-emphasis`. The fills read 1.85:1 and 2.74:1 as text. The meter, row tints and card borders keep the fills.
- A dismissed row's side is `--pg-fg-2`, no opacity (`panel-content-contrast.md`).

## Are the counts in the messages formatted?
- Yes, every step message (matched, removed, queued, not queued, listing changes, categories saved, queued for listing) passes `pg_format_number($n, 0)`, like the hero figures.

## Are the dialogs named?
- `pgUI.dialog.build()` gives the title `<id>-title` (or `pg-dialog-<n>-title`) and sets `aria-labelledby` on the root (`pg-ui-adapter.md`); the picker, category search and attributes dialogs are its only callers.

## How does a read say how far it is?
- `#mpp-meter` shows the matched share, or the read share while a read runs. `setMeter(value, name)` sets its width, `aria-valuenow` and `aria-label` together: `readFrom()` passes the read share and "Reading products", `setCounts()` the matched share and "Matched". Before, a read widened the bar but the meter still read the matched value under the name "Matched".
- `#mpp-progress-text` is `role="status"`; `announceProgress()` writes "x of y products read" only every 10% of the read or 10 s, so a long read is heard without a message per page. Failures go to `#mpp-flash`.

Test: `tests/marketplace_products_names_test.php`, `tests/pg_ui_test.php` (`test_pg_ui_build_dialog`).
