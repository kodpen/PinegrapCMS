---
name: share-card
description: Plan (owner 2026-11-16, agent channel message 5667): a share image (og:image) drawn from the title, logo and site name for a page or record with none of its own; steps OG1 to OG3
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. It was item 10 of agent channel message 5657.
- A shared link shows a picture. A page with none of its own gets a card with its title instead of the same default picture on every link.

## What is there today?
- `get_page_content.php` picks `og:image` by page type: a catalog item's picture, a record's share image field then its media field (`pg_resolve_submitted_form_og_image()`), else `pg_og_default_image()` (Site Settings, Social Sharing, Default Share Image). A custom `og:image` in a page's or design's meta tags wins.
- No picture is guessed for an item that has none: refused in #2891 (`designer/record-media-field-share-image.md`).
- The server draws text only through `pg_image_draw_document()` (`includes/fn/image_draw.php`): Imagick only, the bundled Roboto or a font in `data/fonts`, letters that need no shaping; `pg_image_draw_can()` says what this host draws.
- The model of a drawn, stamped picture served without the router is the site icon (`platform/site-icon.md`).

## What is built?
- The numbered features: `share-card-features.md` (S1 to S14). Steps: `share-card-steps.md`. Claim as `[plan:share-card] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: with the switch on, a page that would print the default picture prints the address of its own card; the card is drawn once from the title, the logo and the site's name and kept as a file. A host that cannot draw it keeps today's picture.

## What binds it?
- `workflow/decisions-made-seo.md`: a default keeps a running site's output as it is, so the switch starts off; no guessed picture (#2891).
- `platform/image-derivatives.md`: locks and encode slots for every drawn picture.
- `ui/settings-public-image-pickers.md`: only pictures every visitor may open.

## How does the switch start? (owner, 2026-11-16, agent channel poll 70)
- Owner (agent channel poll 70, message 5756): "Her yerde kapalı başlasın": off on a new install too, since it changes what a shared link shows (S1).

## What does the owner still decide?
- The card's look, from OG2's samples.

## What does the architect decide?
- Whether the card's document is built in code (the plan) or read from a Design Canvas template.

## What is left out?
- A card designed as a Design Canvas template of one's own; the item's own picture as the card's background; fonts for letters the bundled ones lack. Each is a later yes.

## Whose look?
- The owner's: the card is what every shared link of the site shows. Sample cards from OG2: a short title, a long one, with and without a logo, two colour pairs.
