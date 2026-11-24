---
name: erp-document-template-css
description: CSS of the ERP document templates that dompdf reads its own way: overflow-wrap anywhere for long words (#11434), never text-transform uppercase
---

## Why do the line cells carry `overflow-wrap: anywhere`?
- Line cells and the buyer block carry `overflow-wrap: anywhere` (invoice and delivery note templates, #11434): dompdf's table widens a column to its longest word, so a description without a space pushed the amount columns off the page; `break-word` does not change that width, `anywhere` does. A normal name wraps as before. Custom templates and kept PDFs are not changed.

## Why is `text-transform: uppercase` never used?
- Never use `text-transform: uppercase` in template CSS: dompdf confuses dotless/dotted I (`i` → `I`). If capitals are needed, write the text in capitals.

Related: docs/memory/erp/erp-invoice-document-pdf.md, docs/memory/core/dompdf-embedding.md
