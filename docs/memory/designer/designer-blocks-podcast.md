---
name: designer-blocks-podcast
description: The Podcast block family (podcast- prefix): episode hero with chapters, sponsor placements drawn on an episode and a season, transcript with timed turns; blocks placed instead; tests
---

## Where does it live?
- `assets/js/style_designer_blocks_podcast.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `podcast-`, palette group "Podcast" (`bi-mic`). Built in step `podcast-1` (PR #8785) for the Podcast template ([[design-template-podcast]]); `podcast-2` places it on the pages.

## Which blocks does it hold?
- `podcast-episode` (Podcast: Episode Hero with Chapters): a square cover over a sound wave (`pg-podcast-cover`, `pg-podcast-wave`, `aria-hidden`), show, season and episode, the length as `<time datetime="PT58M">`, the episode's `h1`, Listen Now / Episode Notes, four app icon links with an `aria-label`.
- Under it "In This Episode": an `ol` of chapters (`pg-podcast-chapters`, `h2` Chapters), each start a `<time>` with its `PT…M…S` duration, and the guest card (`h2`, photo, role, a quote, the host's name). On load: cover grows, wave slides in, words rise; chapters follow one by one on view, the guest fades in.
- `podcast-sponsor` (Podcast: Sponsor Placements): `h2` and a line, then a drawing (`pg-podcast-track`, `aria-hidden`): one episode's bar with the opening and the middle mention marked, a season's ten episodes with the partner's mark in each; numbers 1–3 under them (`pg-podcast-no`). Then an `ol` of three cards: `h3`, the same number, length and place, "Starting from" a price per episode or season; an enquiry button.
- `podcast-transcript` (Podcast: Transcript): `h2`, a line, the two speakers, Read the Full Transcript; beside them a part of the transcript (`pg-podcast-script`): the chapter as an `h3` with its time, then six turns, each a `<time>` and the speaker's name over the words.

## Why is there no player?
- A palette block has no episode record behind it, so an `<audio>` would play nothing. Listen Now is a link; the episode record's page plays its Audio file in the template's own player (`$episode_player`, `pinegrap-podcast/parts.php`, a `src` binding).

## Why are these not reused blocks?
- `music-release` lists an album's tracks, not where a talk turns; `timeline-horizontal` is dated steps; `news-live-blog` has timed updates without speakers; `pricing-tiers` gives a price per tier, not where the mention plays.

## Which general blocks does a podcast site place instead?
- Hosts: `team-profiles` or `team-grid`. Listeners and downloads: `stats-icons`, `stats-counter`. Supporters: `pricing-tiers`. How to be a guest: `steps-numbered`. Questions: `faq-accordion`. Newsletter: `newsletter-split`. Listeners' and sponsors' words: `testimonial-grid`, `quote-pull`. Episodes and shows stay collection lists.

## How are the drawings made?
- Bars are background gradients; the middle mark's label is set in by `margin-left: 46%`, not positioned, so it lines up on the canvas too ([[designer-block-style]]).
- The wave is a striped gradient cut by a mask, with the `-webkit-` prefix beside `mask-image`.
- Eyebrows and chapter times use the theme's darker shade (`text-primary-emphasis`); secondary buttons `btn border link-body-emphasis`: small primary text and outline buttons are under 4.5:1 in dark mode ([[designer-block-theme-colours]]).

## Translation choices
- Chapters are "Kısımlar", Transcript "Metin dökümü", a mention "anons", host "sunucu", guest "konuk", Hero "Giriş". A price reuses "Starting from" ("Başlangıç fiyatı"): "From" is "Kimden".

## How is it tested?
- `tests/designer_blocks_podcast_test.php` (under Node): the shared checks plus no light primary text or outline button; per block its words, headings and entrances; six numbers, seven transcript rows; times, lists and hidden drawings read from the file; rules: theme variables, no `!important` or child selector, mask prefix, 46% alignment.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-overview.md
