---
name: decisions-made-product-16
description: Decisions list, product part 16 (2026-11-01 on): design templates finished (comments, events, testimonials); WebP at a picture's own address; the new dashboard's four questions
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Design templates finished: comment threads, events, testimonials (2026-11-01)
- Owner, 2026-11-01, so the templates ship with no temporary stand-ins. (a) Every template moves its comment threads, public and private (conversation, desk or submission, Café reservation and quote, Clinic appointment, the forum's three private pages), from the fixed comments block to the Comments widget; a comment e-mail page gets the widget's card and keeps its System region. (b) The bookshop's events become form records the business adds in the panel, as the Café's. (c) A template's hard-coded testimonials stay as sample text, with a Google Reviews widget beside them that shows once configured. (d) The forum template gets the standard blog pages. (e) The hello template's product page gets reviews.
- Installed sites keep their pages: rows are never reused, no migration. Shop templates: `designer/design-template-shop-comments.md`; hello: `designer/design-template-hello.md`.

## WebP at a picture's own address: default, convert doors, editor export (2026-11-01)
- Owner (2026-11-01) delegated the six questions of `plans/webp-same-address.md` to the architect. Decided: a new install has it on, a running site off until switched on (the upgrade note and System Status say so); the File Manager's batch becomes "Prepare WebP" and Edit File keeps "Create a .webp copy", no door renames a file; the image editor saves another format as a copy, Replace never changes a file's format; the switches are bits 4 and 8 of `config.image_derivative_formats`, no schema; AVIF at the plain address not now; Cloudflare Tools writes and checks the Cache Rule when its token may. Reason: a file's bytes match its name's extension, and an upgrade starts no encodes on a shared host.
