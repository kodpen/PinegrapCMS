---
name: decisions-made-product-107
description: Decisions made, product part 107 (2026-11-24 on): Figma is connected by a personal access token pasted on Connections; Canva is an upload of exported pictures, with no Canva connection
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before is part 106.

## How does a site connect to Figma? (owner, 2026-11-24, in the architect's window)
- The census of ST1 (`api/figma-canva-census.md`) found that a public Figma app needs Figma's review and a fixed list of return addresses, a private app reaches only its own team, and every site has its own address.
- Asked among four ways (a pasted personal access token; the site's own Figma app; both; nothing now and one press after the launch through a broker), he chose "Kişisel erişim anahtarı".
- So the Figma card on Connections takes a personal access token the designer makes in Figma's settings. No Figma app, no return address, no review. ST3 of `plans/designer-start-import.md` builds this.
- The token is a secret: kept as Connections keeps a service's secret, never shown again, in no log; "Disconnect" removes it. An expired or refused token says so on the card and in the New design window, with where to make a new one.
- A one-press connection is not planned: it needs a shared broker, which the owner has not opened (his "Şimdi değil" to the same question for Gmail).

## How does a design come from Canva? (owner, 2026-11-24)
- The census found that Canva's terms forbid, without written consent, a use "primarily intended to export designs from Canva into a third-party design platform", that a public app needs Canva's review, and that no endpoint reads a Brand Kit's colours or fonts.
- Asked among three ways (an upload only; an upload now and Kodpen asking Canva for consent; the connection as planned), he chose "Yalnız yükleme".
- So the Canva start has no connection and calls no Canva API: the person exports the pages from Canva as PNG or PDF and uploads them in the New design window. They become reference pictures under Sketch frames (SK7), and the colours measured from them are offered for the variables.
- Fonts are not taken. The window says in one sentence that the pictures are a model to build on, not layers.
- This narrows his choice of part 105 ("pictures and brand"): the brand is the colours only. There is no Canva card on Connections; ST6 becomes this upload.

## What did the architect answer from the census, as technical calls?
- Figma variables are read only where the API answers (an Enterprise plan). On every plan ST5 takes colours and text styles from the nodes' values and the file's style names; the report names the source.
- Figma's limits are accepted: the Figma form says in one line that a Dev or Full seat is needed for more than a few imports a month; a file read is kept for the length of one import so a retry spends no call; a refusal for the limit shows its reason and when to try again.
- The plan file's lines for Figma, Canva, ST3 and ST6 are corrected to this by the next step's PR that touches it; until then this file binds.
