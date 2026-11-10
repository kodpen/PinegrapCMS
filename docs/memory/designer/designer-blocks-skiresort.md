---
name: designer-blocks-skiresort
description: The Ski Resort block family (skiresort- prefix, its own palette group): the five blocks, what each draws, its pg-ski-* rules, why the pass cards are not a pricing or compare block, its test
---

## Where does it live?
- `assets/js/style_designer_blocks_skiresort.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `skiresort-`, palette group "Ski Resort" (`bi-snow`). Built for the Ski Resort template's pages ([[design-template-skiresort]]), usable by any mountain or outdoor site.

## Which blocks does it hold?
- `skiresort-conditions`: today's figures on a dark band (`data-bs-theme="dark"` + `bg-body text-body`): slopes and lifts open as `{var:1} / {var:2}`, snow depth, new snow, temperature, wind, an "open" pill, the avalanche danger line, links to lift status and snow report.
- `skiresort-lifts`: a card per area, a `list-group` row per lift (kind icon, name `h4`, kind · ride time · seats, status pill).
- `skiresort-slope-map`: a `figure` with the map picture and a PDF link, the difficulty legend (green circle, blue square, red and black diamond, each named in words with its slope count), area buttons, "All slopes".
- `skiresort-passes`: three pass cards; each has a `dl` price line per age group (adult, young, child), what it includes, a buy link; the middle card `border-primary` with a "Most chosen" badge.
- `skiresort-snow`: depth per station (`progress` with `w-75`/`w-50`/`w-25`), new snow over 24 h, 48 h, 7 days, last snowfall in words, the five-level avalanche danger scale with today's level `aria-current`.

## Why are the pass cards not a reused block?
- `pricing-tiers` holds one price per plan; a lift pass has a price per age group, and `compare-table` / the launch spec table compare features in rows. The pass card is its own structure, so it is a family block, not a copy.

## What do its rules and texts follow?
- Rules: `pg-ski-board` (tabular numbers on the board, lift and snow blocks) and one colour rule per legend grade (`pg-ski-green`, `-blue`, `-red`, `-black`: `var(--bs-success)`, `--bs-blue`, `--bs-red`, `--bs-emphasis-color` so black stays visible in dark mode). Each `pg-*` class the file writes is a rule key (the family-file check demands it).
- A status pill is always an icon plus a word (Open, Closed, On Hold). Several numbers in one text use `{var:1}`, `{var:2}`: a bare `{var}` repeats the first value.
- Shared keys with a wrong Turkish value for this sector ("Black" → "Siyah (900)", "Moderate" → "Yönet") are not reused: the legend says "Green slope"…, the scale "Low danger"…. Prices are their own `_sdT('$58')` keys.
- Motion: the figure, lift-area, pass and new-snow rows carry an entrance (`props._fx` slide-up on view, stagger 90); nothing moves by itself. No date or year in the sample: the last snowfall is "last night".
- No live feed, no weather service, no interactive map: every value is text the staff edit.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_skiresort_test.php` runs the kit and the file under Node: a section with one `h2` and no skipped level per block, no fixed colour or `sk-`/`psk-`/`skr-` class, every `{var}` filled, no year, entrances kept by `_sdFxNormalize()`, the dark band, the status pills, the price lines, the legend and the rules reaching the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/design-template-skiresort.md
