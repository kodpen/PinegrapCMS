---
name: barcode-scanner-detection
description: The jQuery Scanner Detection listener on the barcode stock screens and Create Order ignores keys typed into fields, scans in an open dialog and blank codes
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
