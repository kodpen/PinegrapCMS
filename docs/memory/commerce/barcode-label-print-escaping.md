---
name: barcode-label-print-escaping
description: The barcode label print page (LabelEditor buildPrintHTML() in backend.src.js): barcode and template values read as numbers, CSS values or escaped text before they reach the print window
---

## Why does the print page escape its values?
- `buildPrintHTML()` writes the label's page into a window opened with `window.open('')`, which shares the panel's origin. The barcode is free text (a CODE128 code takes any character) and the label template (`config.barcode_label_template`) is JSON saved by anyone with the product screens (roles 0-2, or role 3 with `manage_ecommerce`).
- Before the fix a barcode holding `</script><script>…` ended the drawing script, and a template colour, weight, background or barcode type ended its attribute or the `<style>` element: script ran in the panel's origin when an administrator printed the label.

## How is each value written now?
- Numbers (`x`, `y`, `width`, `height`, `rotation`, `scrollX`, `fontSize`, `borderWidth`, `borderRadius`, label `width`/`height`) through `_num()`: a value that is no finite number takes the default.
- CSS values (`color`, `fontWeight`, `fill`, `borderColor`, label `background`) through `_css()`: only `# a-z 0-9 . , % ( ) -` and spaces, else the default.
- Text and attribute values (`barcodeType`, the text, the image `src`) through `_esc()`.
- The barcode inside the script: `JSON.stringify()` with `<` written as `<`.
- Test: `tests/barcode_label_print_escape_test.php` (runs the method in Node).
