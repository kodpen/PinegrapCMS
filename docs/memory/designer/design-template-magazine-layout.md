---
name: design-template-magazine-layout
description: Magazine look and shapes: pm- User Styles, fonts and serif body, color-mix() paper, rust accent, sticky pull quote, drop cap resets, vanishing bands, preview placeholders.
---

## How does the look ship?
- One User Styles entry: the block rules `pg-snap`, `pg-snap-item`, `pg-circle-60`, `pg-circle-160` plus own `pm-` rules (no other block class; a copied news `pg-ratio-16x9` frame became `div.ratio.ratio-16x9.pm-media`). Fonts from the picker: Fraunces 400/600 (headings, never italic) and DM Sans 400/500/700 (labels, controls).
- Reading text is the editorial look's system serif (Iowan Old Style, Palatino, Georgia): the font loader asks `:wght@` only, so a web body font would fake every italic.
- Accent: `--pm-accent:var(--pg-secondary-fg,var(--bs-secondary-text-emphasis))` (rust on sage), on `bg-body` only; a kicker on paper or `bg-body-tertiary` turns ink.
- Paper: `var(--bs-tertiary-bg)` first, then under `@supports` `color-mix(in srgb,var(--pm-accent) 6%,var(--bs-body-bg))`: it follows any palette in both themes. Links on paper are ink and underlined. Test 15: no hex, rgb(), hsl() or named colour.
- Text never sits on a photo (no scrim): the cover is split, captions sit under pictures. It reads as the magazine under `modern-soft` too.

## Why is the pull quote a sticky column, not a float?
- `.pm-body{position:relative;contain:paint}` (author HTML stays in its box) makes the body a block formatting context: a float beside it would narrow the whole body. The quote is `div.col-xl-3.order-first.order-xl-last` > `sticky-xl-top`: an epigraph above the text below xl, a column that travels with the opening from xl; in the source it follows the text.
- It repeats a sentence of the body, so the figure is `aria-hidden="true"`. The home quote band is not hidden (not repeated there) and credits the story, never a person.

## Why `p:first-of-type` / `p:last-of-type` with resets?
- `::first-letter` on the body would land on an `h2` or a figure; `p:last-child::after` would mark every quotation and list item. The resets for `blockquote`, `li`, `td`, `th`, `dd` and `div` repeat the pseudo-class: (0,2,3) beats the main rule's (0,2,2); without it a reset is (0,1,3) and loses. A body pasted inside one `div` loses its drop cap: none beats one per block.
- No `:first-child` or `:last-child` anywhere: an editor's edit pencil comes first in a record (news lesson). The river's first story is `article:first-of-type`.

## Why do the quote and letter bands vanish when empty?
- `.pm-quote-band:has(.pg-sw-empty),.pm-letter-band:has(.pg-sw-empty){display:none}`: a reader never sees "mark a story as…" in a hero-sized band. An older Firefox without `:has()` shows the sentence.

## How do the bands keep their rhythm?
- `.pm-band{padding-block:clamp(3rem,2rem + 4vw,6rem)}`, never with `py-*`; a band after another on the same surface is `pt-0`; headers `pb-4`; the story's reading row `pt-4`.
- Page links of a list land inside its loop parent (no `pagination` slot in a list tree): `.pm-river .pg-sw-pagination` and `.pm-issue-grid .pg-sw-pagination` make them span.

## Which other shapes are worth knowing?
- The sign-in forms sit in a `pm-band pb-4` (the other pages' distance from the masthead); the paper panel is a band under them (their `col-md-6 col-lg-5` card would be 230 px in a column).
- The drawer title and "The magazine" are `p`: nothing before the h1 is a heading. The letters h2 closes the record view (`record_found`); from xl the text column is `offset-xl-1` with or without the optional quote, in line with the letters.
- The editors' access note is its own band `#how-the-desk-works` after the desk lists.

## Why does the installer preview say "12 min read" and "Date"?
- The DB-less preview prints static text and keeps `pg-hide-if-empty` elements: a "12" reading time, the word Date on issue cards (news previews print "min read" too). The site prints the values and hides an empty time (no sample has one). No calendar date as placeholder: it goes stale on the canvas.
