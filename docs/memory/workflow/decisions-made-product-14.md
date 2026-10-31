---
name: decisions-made-product-14
description: Decisions list, product part 14 (2026-10-31 on): own image editor, nothing alone under a screen's title, no PDF of ours for a purchase invoice, Site Settings regrouped
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Which image editor does the software use? (owner, 2026-10-31)
- Decided (owner, 2026-10-31; `plans/image-editor.md`): its own, written in this repository with no outside engine, open-source ones included: an engine's model and limits would bind every later feature. It replaces the bundled third-party editor, which goes with its loader.
- A core part: an in-page modal with a full-screen button, callable from any screen with a subset of tools (arrows only for a marking task); the full-page editor retires. Every text through `lang()`.
- The server does the work it does efficiently (the original's pixels, geometry, redaction, encoding, optimisation, SVG cleaning); what would be slow there runs in JS. No video. Optimisation (format, quality, size, metadata) is part of the editor.
- Texts and shapes stay editable after a save (a versions table keeps each document), so the editor is also a canvas: File Manager "New image" and Workspace canvases. Ready-made shapes, corner rounding and Illustrator-like artboard sizing belong to it.

## What may sit under a screen's title? (owner, 2026-10-30)
- Decided (owner, 2026-10-30, with a screenshot of Calendar subscription's lone "Work Calendar" button): no button, link or short text alone under the title. Everything a screen does is in one toolbar there (`ui/admin-screen-toolbar.md`); the way back is only the back arrow and the breadcrumb (`ui/back-arrow-no-copies.md`). Done #3912-#4100. The bar does not stick on phones (#3837, part 12).

## What is a purchase invoice's PDF, and what goes into the accountant pack for it? (#4104)
- Decided (architect, 2026-10-31, bug #3997): nothing of ours. The archive keeps a rendering only for `direction = 'sales'` (`erp_event_invoice()` defers it, `erp_archive_invoice()` refuses a purchase); the pack's `belgeler/Alis_faturalari/` holds only the provider's `edoc` copy of a purchase, never the `invoice` copy or a render; `edit_erp_invoice.php` draws no PDF / Download for a purchase invoice (its e-document block already leads to the supplier's inbox document); `get_erp_invoice_pdf.php` answers a purchase invoice with a notice page for PDF, download and `?html=1`. Kept purchase PDFs on running sites stay in `files`, unused. No schema or data step. Refused: printing it as a "Purchase invoice" with the supplier as issuer: a second document beside the supplier's, which is the one that counts, and every custom template would need the branch. Re-ask if accountants want a per-purchase record printout.

## How is Site Settings grouped? (owner, 2026-10-30; built #3795-#4135)
- Decided (owner, 2026-10-30): one place per setting. The tools that leave the dialog live on the System tools screen (one row at the list's end); Accounting is a category; Security is Accounts and sign-in, Firewall is Firewall and HTTPS; API is Integrations; Visitors and privacy, Updates and maintenance are categories; the encryption key sits in Accounts and sign-in; Private Label is an Appearance card.
- Kept: one dialog, one Save per pane; `#settings/<cat>/<card>` and `settings_<cat>.php` links, old card ids mapped by `pg_settings_legacy_anchors()`; every field's column and save rule. Category keys stay when a label changes.
- Headings over the list (#3879): Site, Security, Selling, Running the site. Map: `ui/settings-modal-architecture.md`.
