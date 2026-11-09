---
name: design-template-music-pages
description: Music Artist template 2.0.0: home, discography and press kit on the Music blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8338 family, then its pages #8347): home, `discography` and `press` place the Music blocks ([[designer-blocks-music]]); their rules ship through `pg_design_template_user_styles(array('music-release', 'music-tour', 'music-presskit'), $music_css)`.
- `parts.php` (end): `$fx`, `$rise` (slide-up on view, stagger 80), `$release_hero($key, $id, $tag, $class, $buttons, $theme)` (a sample release of `$releases`: `pg-music-sleeve` with the `cover-<key>` picture and the hidden disc sliding out, the note as eyebrow, the title as `$tag`, format and song count, summary, `$buttons`, `$listen_links`, then `ol.pg-music-tracks` with each length as a `<time>` of `PT…M…S` and the credits; an `h1` comes in on load, otherwise on view; `$theme` sets `data-bs-theme`), `$press_kit($id, $class)` (one-sheet `dl.pg-music-sheet`: Genre, From, Line-up from `$members`, Record Label, Latest Release; Book the Band to `booking`, the press `mailto:`; three press photos whose Download link saves the large picture; a link to `#mu-press-files`).
- `$show_row` (the tour, past shows and home lists) carries `pg-music-day` on the date, so the cities line up as in the tour dates block; the block itself is not placed: its shows are static, the lists are the Shows collection.

## What does each page hold?
- Home: the release hero of the newest single (`mu-home-release`, the `$lead_key` choice, still on the dark `pmu-hero` band), next shows, releases, videos and press quotes rising, the band, the news.
- Discography: page header, the release hero of Night Bus Hymns (`mu-discography-album`, `h2`; buttons Tour Dates and Press Kit), the filtered list.
- Press: the press kit block (`mu-press-kit`) replaced the photos band; bios, quotes (rising), figures, line-up and the Press Files list stay.
- Videos, about, faq, the record pages and the tour pages are otherwise unchanged.

## How is it tested?
- `test_music_assembly_pages_on_music_blocks`: version, every `pg-music-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something moves and one `h1` on each of the three, load entrance, dark band, hidden disc, ISO durations, the five facts, three downloads, the press files anchor, `pg-music-day` in the tour list.

Related: docs/memory/designer/design-template-music.md, docs/memory/designer/design-template-own-look.md
