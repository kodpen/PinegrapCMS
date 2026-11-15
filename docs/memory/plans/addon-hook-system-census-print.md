---
name: addon-hook-system-census-print
description: Add-on hook system AH1 census, printed order slot places: how Print Order (print_order.php), the packing slip and the customer's printable invoice draw above, below the items and the foot
---

## How is the printed order drawn? (Printed order › above, below the items, foot)
- `print_order.php` (Print Order): inline echo between `output_header_secure()` and `output_footer_secure()`, own `<style>`, `window.print()`. Order: header table (number, date, status, organization), info table (billing, shipping, customer and payment), items `<table class="order_details">` (per ship-to cards via `pg_print_order_shipping_card()`), totals table written inline, then a foot strip of payment information, source, gift cards, affiliate, custom billing fields.
- `print_packing_slip.php` (per ship-to): header, `table.order_items`, nothing below.
- `order_invoice_print.php` (customer's printable invoice, from the site widget): plain PHP template: header, addresses, items table, totals by `pg_order_print_totals()`, a meta foot. No dompdf by choice.
- Only the ERP invoice PDF (`get_erp_invoice_pdf.php`, `erp_invoice_template()` in `includes/erp/document.php`) has an editable template.
