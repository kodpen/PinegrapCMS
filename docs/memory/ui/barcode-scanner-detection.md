---
name: barcode-scanner-detection
description: Barcode stock screens and Create Order: the scanner listener ignores fields, dialogs and blank codes; Last Scans rows built as text; the stock screens' wide layout
---

## Which screens listen for a barcode scanner?
- `barcode_increase_inventory.php`, `barcode_decrease_inventory.php` (inline copy of jQuery Scanner Detection 1.2.1) and Create Order (`assets/js/add_order.js`, its own copy; not a `pg_js_bundles()` bundle). Each binds `scannerDetection()` on `document` with `timeBeforeScanTest: 200`, `avgTimeByChar: 100`, minLength 2.

## Why must the listener ignore fields?
- On `document` it sees every keypress, the panel header's too (Ctrl+K search `input#query`, Workspace quick note, Site Settings dialog). Two keys under 200 ms apart and a pause count as a scan. The stock screens then wrote the fragment into Last Scan and posted it: an alert "No product found with this barcode/sku/id." broke the typing, and a fragment equal to a SKU (`products.name`) really moved the stock. The plugin's keydown branch also swallowed a quick Tab or Enter after such a key.
- Now all three pass `ignoreIfFocusOn: 'input, textarea, select, [contenteditable="true"], [contenteditable=""]'`, so keys typed in a field are never collected. A scan with focus on the page or on a button works as before; the screens' own Last Scan / Quantity fields were already skipped.

## What does onComplete drop?
- A scan while a `pgUI` dialog is open (`window.pgUI && pgUI.anyOpen('dialog')` on the stock screens) and a code that is blank after `trim()` (Space or Enter pressed twice quickly on a focused button). On Create Order a blank code reset the quantity to 1 and beeped "Scan a barcode or type a product name.".

## What stays as is?
- The minified plugin line, the Read button busy state (`button-busy-state.md`), the server lookup and the decrease screen's answers.

PR #3377. Test: `tests/barcode_scanner_fields_test.php` (source pins).

## How is a Last Scans row built?
- Cell by cell in jQuery: name, short description and the three quantities with `.text()`, `response.data.scanned_at` (a `<time>` element from `get_absolute_time()`) with `.html()`. The `?request=action` answer stays raw JSON.
- Symptom fixed: the row was a concatenated string, so `Pantolon <Siyah>` showed as `Pantolon `, a `<b>` bolded the row and `<img src=x onerror=…>` in a product name ran in the scanning operator's session. PR #4121, test: `tests/barcode_scan_log_escaping_test.php`.

## How are the two stock screens laid out?
- The product_builder pattern (`edit-screen-wide-layout.md`, B11): the Scan card (Last Scan & Manual Read, Quantity, the stock location) and the Last Scans table in the form column; a sticky Summary (Increase or Decrease, this visit's scan count and units, the last product and its new quantity, counted in the browser from each answer); Read in the sticky save bar. The ids the scripts and tests read (`scanner_input`, `scanner_quantity_input`, `barcode_read_button`, `barcode_status`, `action_logs`) are unchanged.
