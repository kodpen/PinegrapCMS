---
name: share-card-steps
description: Plan share-card, steps OG1 to OG3: the card and its address, the switch and the page, close-out
---

## Which steps are there?
- OG1 (M) The card and its address. No schema.
  - Existing files: `job.php` (the sweep).
  - New: `includes/fn/share_card.php` (the card's document for a title, logo, name and colours, the wrap, the signature and its check, the file's place, the draw under a lock, the sweep), the root file `get_share_card.php` reached by its address so `router.php` does not change, `tests/share_card_test.php`. No page prints the address yet.
  - Tests, red first: the wrap (a short title, three full lines, a word longer than a line), the signature changing with the title, a stale signature and a private item answering 404, a host that cannot draw.
  - Features: S4, S5, S7 to S10, S12.

- OG2 (S) after OG1: the switch and the page.
  - Existing files: `includes/settings/prep.php` and `includes/settings/seo.php` (the Social Sharing part), `includes/settings/seo.save.php` (its save), `get_page_content.php` (one call just before `$og_image = pg_og_default_image()`, and the width, height and type), the SEO check and the warning of S14.
  - Tests: no card with the switch off, an item's own picture and a custom meta tag winning, the served language's title in the signature.
  - Pictures: the settings part off, on and on a host that cannot draw; the sample cards. To the owner; not marked ready before his look.
  - Features: S1, S2, S3, S6, S11, S13, S14.

- OG3 (S) close-out: every S number checked against the code with its PR; `platform/share-card.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.
