---
name: share-card
description: The share card (og:image drawn from the title, logo and site name for a page with no share picture): what it is, each feature with the PR that built it, the decisions, what was left out
module: share_card
---

## What is it?
- A 1200 x 630 PNG the server draws for a page, record or product that would otherwise share the Default Share Image: the background colour, the Organization Logo when set, the title in at most three lines, the site's name. Off on every site until "Share card" is switched on in Site Settings, SEO, Social Sharing.
- Code: `includes/fn/share_card.php` (not loaded by `functions.php`), `get_share_card.php` in the software folder, one call in `get_page_content.php`, the part in `includes/settings/prep.php` and `seo.save.php`, a daily sweep in `job.php`. Tests: `tests/share_card_test.php`.
- The address, signature, drawing and sweep: `share-card-address.md`. Which pages print it and the settings part: `share-card-page.md`.

## Which PR built each feature?
- S1 the switch, off everywhere, two colours as settings texts: #9929.
- S2 a card only where the default would stand; an item's own picture and a custom `og:image` meta tag win: #9929.
- S3 nothing taken from the item's content (#2891 stays): #9916, #9929.
- S4 the 1200 x 630 PNG and what it shows; S5 the measured three-line wrap with an ellipsis: #9916.
- S6 the title in the served language (one order entry per `FRONTEND_LANGUAGE`): #9929.
- S7 the signed address with no text in it; S8 only a public item and its current signature, else 404: #9916 (records read from `forms`, archived folders refused).
- S9 drawn once under a lock and an encode slot, long cache, a failed draw not retried for an hour; S10 the 30-day sweep: #9916.
- S11 `og:image:width`, `:height` and `:type`: #9929.
- S12 a host that cannot draw, or letters the font lacks, keeps the default; the part names this server's own cause: #9916, #9929.
- S13 the home page's sample under a caption; S14 the record-forms warning of the Default Share Image counts a drawn card: #9929. No SEO check reads `og:image`, so none needed changing.

## What did the owner and architect decide?
- Off on every site, a new install too: `workflow/decisions-made-product-59.md` (poll 70).
- The look as built, approved: `workflow/decisions-made-product-62.md` (poll 81).
- Architect (2026-11-16, agent channel message 5763): the card's document is built in code, not as a Design Canvas template.

## What was left out?
- A card designed as a template of one's own; the item's own picture as the card's background; fonts for letters the bundled Roboto lacks. Each would be a new yes from the owner.
- Product groups, filtered lists and addresses with a page number or query keep the default: their title changes with the address.
- Not applied yet to card answers: a record view's own filters (Status = Published) and `product_groups.enabled`.
