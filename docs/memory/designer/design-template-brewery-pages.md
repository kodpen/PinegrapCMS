---
name: design-template-brewery-pages
description: Craft Brewery template 2.0.0: home, taproom and beer page on the Brewery blocks, motion on about: the builders, each page's composition, what could not be done, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (`brewery-1` #8666 family, then its pages, #8714): the home, the taproom and the `beer_detail` widget place the Brewery blocks ([[designer-blocks-brewery]]); their rules ship through `pg_design_template_user_styles(array('brewery-hero', 'brewery-taps', 'brewery-flight', 'brewery-release'), $brewery_css)`, ahead of the `pbw-` rules.
- `parts.php` (beers.php uses them too): `$fx`, `$rise($node, $stagger = 90)` (slide-up on view), `$fade_in` (fade on view, 700 ms, for section headings).
- `pages_public.php`, names prefixed `$bw_` (the parts share one scope): `$bw_glass($tone, $class, $pour)` (an `aria-hidden` glass, tone by style: lager and wheat 1, pale and IPA 2, sour 3, stout 4).
- `$bw_hero` (the only `h1`, photo and a pouring glass, words rise on load), `$bw_release` (Low Tide Sour, limited: parallax picture with a Limited sticker, Release Facts strength, bitterness, style), `$bw_tap_board($id, $topics)` (a dark Brewery Tap Board: one numbered tap per beer with its mini glass, strength and `{var} IBU`), `$bw_flight($id, $topics, $class)` (four tasters rising one after another beside the Book a tasting link).

## What does each page hold?
- Home: hero, the release (`bw-home-release`), the tap board (`bw-home-taps`), Our Beers with `home_beers`, the flight (`bw-home-flight`), tours, events, reviews, news. The story band left the home; `about` keeps it.
- Taproom: header, the tap board (`bw-taproom-taps`), opening hours beside the address (`bw-taproom-visit`), food, getting there, the flight (`bw-taproom-flight`, links `{{page:book_online}}`), the ask band. Its reviews left (the home keeps them).
- Beer page: the picture stage (parallax, the picture fades in on load) with the availability sticker; the `h1` and summary come in on load; Release Facts bind style and strength in `pg-brewery-figure`; malts and hops, tasting notes, buttons. The stage is `pg-hide-if-empty`, judged by its picture, so the sticker leaves with it.
- About: the story band fades in, figures, steps and team rise.
- Beer names are never `text-uppercase`: the Turkish site would print LOW TİDE SOUR ([[uppercase-heading-brand-names]]). A tap's availability badge is `text-wrap`, or a long one holds the row wider than a 320 px phone.

## What could the editor not do?
- A glass colour of its own per beer: the tone is one of four, picked by style; a tone bound to a record field would follow the beer record.
- The pour is a load effect: on the canvas it shows only with Play.
- A live tap board from the Beers collection: the taps are page content written from the samples, edited by hand.
- Rotate and negative margins in the panel for the sticker, scroll-linked scenes.

## How is it tested?
- `test_brewery_assembly_pages_on_brewery_blocks` (`tests/design_template_brewery_assembly_test.php`): version, every block class's rule ships, interactions kept by `pg_fx_normalize()`, glasses hidden, motion on every tree (four effects but about), one `h1`, the block labels, search ids, a tap per beer with number, strength and bitterness, four tasters with rising delays, the sticker and figures bound on the beer page.
- The public test wants each beer's strength and `{var} IBU` on the taproom.

Related: docs/memory/designer/design-template-brewery.md, docs/memory/designer/design-template-own-look.md
