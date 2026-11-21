---
name: privacy-centre-export-link
description: Privacy centre: the answer to an information request carries a signed download link valid 7 days for the export, never an attachment; what the link opens and what must be true
---

## What is decided?
- Owner (2026-11-21, `workflow/decisions-made-product-95.md`): the exported data reaches the person by a signed download link, valid 7 days, in the answer e-mail. This replaces "the export attached" in `privacy-centre-screens.md` (the Answer tab's third step).

## How does the link work?
- Sending the answer of an information request writes the export file to the request's private place (never under a web-served folder) and puts one link in the e-mail, under the text the kind's template gives.
- The link names the request and carries a signature made with the site's secret over the request, the file and the expiry (`hash_hmac`, compared with `hash_equals`); nothing of the person stands in the address.
- It opens a public script that checks the signature and the expiry, then sends the file as a download (`Content-Disposition: attachment`, `no-store`, `noindex`). A wrong or ended link reads one sentence, "This link has ended. Ask the site for a new one.", with status 410 and no word on whether a request exists.
- Every download is logged on the request (time, no IP kept beyond the site's own log rule); the panel's drawer shows "Downloaded" with the time, or "Not downloaded yet".
- The drawer has "Send a new link" (a new 7 days; the old link ends) and "Withdraw the link". The file is deleted when the link ends or is withdrawn, by the request's clean-up pass.
- No column is added for it if the request's row already keeps its steps as data; a step that needs a column asks first.

## What must be true? (tests)
- A link past 7 days, a changed signature and a withdrawn link all read the same sentence and send no byte of the file.
- The e-mail has no attachment; the external API and MCP never return the link, its signature or the file (as KV6 decided).
- The WAF's rate limit covers the script like the other public signed links.

## Which step builds it?
- The privacy centre step that sends the answer; where that step has landed with an attachment, a small step of its own after it. The coordinator holding the plan places it in `privacy-centre-steps.md`.

Related: docs/memory/plans/privacy-centre.md, docs/memory/plans/privacy-centre-screens.md, docs/memory/core/personal-data-export.md
