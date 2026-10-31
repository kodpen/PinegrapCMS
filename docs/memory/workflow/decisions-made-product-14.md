---
name: decisions-made-product-14
description: Decisions list, product part 14 (2026-10-31 on): own image editor, no PDF of ours for a purchase invoice
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Which image editor does the software use? (owner, 2026-10-31)
- Decided (owner, 2026-10-31; `plans/image-editor.md`): its own, written in this repository with no outside engine, open-source ones included: an engine's model and limits would bind every later feature. It replaces the bundled third-party editor, which goes with its loader.
- A core part: an in-page modal with a full-screen button, callable from any screen with a subset of tools (arrows only for a marking task); the full-page editor retires. Every text through `lang()`.
- The server does the work it does efficiently (the original's pixels, geometry, redaction, encoding, optimisation, SVG cleaning); what would be slow there runs in JS. No video. Optimisation (format, quality, size, metadata) is part of the editor.
- Texts and shapes stay editable after a save (a versions table keeps each document), so the editor is also a canvas: File Manager "New image" and Workspace canvases. Ready-made shapes, corner rounding and Illustrator-like artboard sizing belong to it.

## What is a purchase invoice's PDF, and what goes into the accountant pack for it? (#4104)
- Decided (architect, 2026-10-31, bug #3997): nothing of ours. The archive keeps a rendering only for `direction = 'sales'` (`erp_event_invoice()` defers it, `erp_archive_invoice()` refuses a purchase); the pack's `belgeler/Alis_faturalari/` holds only the provider's `edoc` copy of a purchase, never the `invoice` copy or a render; `edit_erp_invoice.php` draws no PDF / Download for a purchase invoice (its e-document block already leads to the supplier's inbox document); `get_erp_invoice_pdf.php` answers a purchase invoice with a notice page for PDF, download and `?html=1`. Kept purchase PDFs on running sites stay in `files`, unused. No schema or data step. Refused: printing it as a "Purchase invoice" with the supplier as issuer: a second document beside the supplier's, which is the one that counts, and every custom template would need the branch. Re-ask if accountants want a per-purchase record printout.
