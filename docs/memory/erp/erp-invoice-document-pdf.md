---
name: erp-invoice-document-pdf
description: ERP invoice document layer (includes/erp/document.php): template syntax, placeholders, HTML/PDF endpoint, template editor, seller identity, dompdf traps.
---

## What does `includes/erp/document.php` provide?
- `erp_template_render($template, $data)`: mustache-style; `{{field}}` escaped, `{{{field}}}` raw, `{{#section}}…{{/section}}` loop/condition, `{{^section}}…{{/section}}` inverse, dotted keys (`satici.vkn`).
- `erp_invoice_document_data($invoice_id)`: what the template sees: `seller` / `account` / `invoice` / `lines` / `totals` / `label` / `language` / `generated_at`.
- `erp_invoice_document_labels()`: the `label.*` placeholders, every heading/column/footnote label via `lang()` in the site language. The two labels naming the home currency (`label.exchange_rate`, `label.grand_total_base`) resolve `{var:1}` here, since a template placeholder takes no argument. The same array feeds the *Etiketler* group of the placeholder reference in `erp_settings.php`.
- `erp_invoice_template()`: `config.erp_invoice_template` if filled, else `includes/erp/templates/invoice_default.html`.
- `erp_invoice_html($invoice_id)` and `erp_invoice_pdf($invoice_id)`; the latter loads dompdf via `includes/dompdf/autoload.inc.php` (needs PHP 7.1).

## What is the PDF endpoint?
`get_erp_invoice_pdf.php`: `?id=` inline PDF, `&download=1` download, `&html=1` shows HTML. A POST `template` field previews an UNSAVED template and requires CSRF + the `settings` right. The *PDF* and *İndir* buttons in the top toolbar of `edit_erp_invoice.php` go here.

## What is `erp_settings.php` now?
- A template editor: save, preview, placeholder reference, back to default. An empty template or one equal to the default is written as `NULL`; "customized" means only "differs from the default".
- Seller identity lives in the E-Ticaret `pgset-erp` card: `config.erp_seller_vkn` / `erp_seller_tax_office`, constants `ERP_SELLER_VKN` / `ERP_SELLER_TAX_OFFICE` (read defensively; may be undefined on old installs). Title and address come from `ORGANIZATION_NAME` / `merchant_*`.

## What rules apply to the built-in template?
- It has no vocabulary of its own (issue #109): every label is `{{label.*}}` from `tr.json`; `<html lang="{{language}}">` prints `SOFTWARE_LANGUAGE`; the file contains no Turkish literal or comment.
- A saved custom template stays as the operator wrote it; it must add placeholders, plain text also works.
- Turkish-specific term keys: `VKN / TCKN` (seller/buyer), `Tax ID` → "VKN" (carrier), `VAT` → "KDV", `Discount` → "İndirim" (not the old template's "İskonto"; same as the product-wide translation).
- Never use `text-transform: uppercase` in template CSS: dompdf confuses dotless/dotted I (`i` → `I`). If capitals are needed, write the text in capitals.
