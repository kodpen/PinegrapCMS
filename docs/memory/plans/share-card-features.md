---
name: share-card-features
description: Plan share-card, features S1 to S14: the switch, where a card applies, what it shows, its address and signature, how it is drawn and kept, when the default picture stays, the SEO checks
---

## When does a page get a card?
- (S1) A switch "Share card" in Site Settings, SEO, Social Sharing, off on every site, with a background colour and a text colour. Kept as settings texts.
- (S2) A card applies only where the page's `og:image` choice would end at the Default Share Image or at nothing: a page, a record with no share or media picture, a catalog item with no picture. An item's own picture and a custom `og:image` meta tag win as today.
- (S3) Nothing is guessed: the card never carries a picture taken from the item's content (#2891).

## What does the card show?
- (S4) A PNG of 1200 by 630: the background colour, the Organization Logo when one is set, the title as `og:title` prints it, the site's name. Nothing else.
- (S5) The title in at most three lines, wrapped by measured width with the bundled font, the last line cut with an ellipsis, a word longer than a line cut (a pure function).
- (S6) On a page served in another language the title is the one served in that language.

## How is it addressed, drawn and kept?
- (S7) The address is `get_share_card.php` with the item's kind, its id and a signature. No text travels in it. The signature covers the kind, the id, the title, the logo's stamp, the colours and the site's name, so a changed title is a new address.
- (S8) The file answers only an item every visitor may open and only the signature of the item as it is now; anything else is 404.
- (S9) Drawn once, under a lock and inside the image engine's encode slots and time budget, into `cache/share/`; after that the file is served with a long cache lifetime. Requests that would draw are rate limited per address.
- (S10) Card files not asked for in 30 days are removed by the general job.
- (S11) With a card the page also prints the picture's width, height and type.

## When does the default picture stay?
- (S12) When the server cannot draw text (`pg_image_draw_can()`: no Imagick, or its check fails) or the title holds letters the font files lack, the page prints the Default Share Image as today. The switch's part says which of the two applies on this host.

## What else changes?
- (S13) The Social Sharing part shows a sample: the home page's card in the chosen colours, or the reason of S12.
- (S14) The SEO card's Default Share Image warning and the SEO check that looks for a share image count a drawn card as a share image.
