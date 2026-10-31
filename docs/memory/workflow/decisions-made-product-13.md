---
name: decisions-made-product-13
description: Decisions list, product part 13 (2026-10-31 on): Site Settings group headings, no secret-address feed for Workspace notes, own image editor
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Does the Site Settings list get group headings? (#3879)
- Decided (architect, 2026-10-31, settings recategorization R8): yes, four, drawn like the main menu's (`.pg-sm-group` label, not a link, no collapse, only over categories the reader may open, also over a single one; a group with no row is skipped): Site (General, Appearance, Features, SEO and sharing, Visitors and privacy, Languages and translation, Communication), Security (Accounts and sign-in, Firewall and HTTPS), Selling (Ecommerce, Accounting), Running the site (Integrations, Updates and maintenance, the System tools row). The groups follow today's list order, so no category moves and no `#settings/<key>/<card>` address changes; a `group` key per category in `registry.php`, labels through `tools/tr_add.php`. Refused: a flat list (13 rows plus a link without landmarks, most of all in the phone drawer).

## Do Workspace notes get a read-only secret-address feed? (#3880)
- Decided (architect, 2026-10-31, notes sync; `workspace/workspace-notes-sync-decisions.md`): no. A note holds private text and attachment links; a bearer address pasted into another app is a long-lived secret with no scope or expiry that the OAuth revocation hooks do not cut. The calendar feed exists only because calendar apps subscribe by address; note sync apps do OAuth 2.1 and have the REST notes routes (paging, `updated_since`, revisions, the 30-day deletions list, sources, all MCP tools) and the export vault. No schema; the plan closes (N4 done: Notion #3212, OneNote #3291).

## Which image editor does the software use? (owner, 2026-10-31)
- Decided (owner, 2026-10-31; `plans/image-editor.md`): its own, written in this repository with no outside engine, open-source ones included: an engine's model and limits would bind every later feature. It replaces the bundled third-party editor, which goes with its loader.
- A core part: an in-page modal with a full-screen button, callable from any screen with a subset of tools (arrows only for a marking task); the full-page editor retires. Every text through `lang()`.
- The server does the work it does efficiently (the original's pixels, geometry, redaction, encoding, optimisation, SVG cleaning); what would be slow there runs in JS. No video. Optimisation (format, quality, size, metadata) is part of the editor.
- Texts and shapes stay editable after a save (a versions table keeps each document), so the editor is also a canvas: File Manager "New image" and Workspace canvases. Ready-made shapes, corner rounding and Illustrator-like artboard sizing belong to it.
