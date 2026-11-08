---
name: designer-blocks-photo
description: The Photo block family (photo- prefix, its own palette group): filmstrip hero, proof contact sheet, behind the shot; the general blocks a photography site places instead; rules and test
---

## Where does it live?
- `assets/js/style_designer_blocks_photo.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `photo-`, palette group "Photo" (`bi-camera`). Built for the Photo template's polishing round ([[design-template-photo]], `plans/design-template-own-looks-order.md`, `photo-1`); usable by any photographer or studio.

## Which blocks does it hold?
- `photo-hero`: eyebrow, the page's `h1`, a lead, two buttons, centred; under them a filmstrip (`pg-photo-strip`: dark band in `--bs-emphasis-color`, sprocket holes as `::before`/`::after` gradients) holding an `ol` of five frames, each a `figure` with a picture and a `figcaption` of frame number (`01`…, monospace) and kind of work. The list scrolls sideways on a narrow screen and is a named region (`kit.region`). Text fades in, strip slides in, both on load.
- `photo-proofing`: "After the shoot", `h2`, a line, an `ol` of three steps (`h3` each), "Your selection: 04, 07, 11", the client area button; beside it a contact sheet (`pg-photo-sheet`, an `ol` grid of twelve proofs), each with its number as a badge; a picked one is ringed (`pg-photo-picked`, styled inside the sheet rule) and carries a heart with `visually-hidden` "Selected". Text rises, proofs fade in with a stagger, on view.
- `photo-shot`: one photograph as `figure`/`figcaption` (the photographer), "Behind the shot", `h2`, the story, "Camera settings" (`h3`) and a `dl` of six settings (lens, aperture, shutter, ISO, light, edit) in `pg-photo-exif` (tabular figures, values monospace), "See the gallery". Picture fades, text rises, on view.

## Why are these not reused blocks?
- The hero is not `hero-split` / `hero-image-bg` / `image-carousel`: a strip of numbered, captioned frames, all visible, under the text. The contact sheet is not `image-grid` or `gallery-polaroid`: numbered proofs, picks marked, beside how to send a selection. Behind the shot is not `features-alt`: one photograph with its camera settings as a description list.

## Which general blocks does a photography site place instead?
- Packages: `pricing-tiers` (or `catering-packages` for a price per guest). Add-ons: `services-price-list`. Prices and deposits side by side: `pricing-comparison` / `compare-table`. Categories: `services-icon-cards`. Steps of a shoot: `steps-numbered`. Preparing tips: `features-grid`. Galleries: `gallery-polaroid`, `image-masonry`, `bento-gallery`. Client area: `features-alt` / `showcase-floating`. Kind words: `testimonial-grid`. Booking call: `booking-split`. Questions: `faq-categorized`. No general block was missing.

## What do its rules and texts follow?
- Rules `pg-photo-strip`, `pg-photo-sheet`, `pg-photo-exif`; each colours with a theme variable. Sample texts reuse the template's keys where they exist ("Photos that feel like you", "Choosing your favourites"). Settings figures (`85 mm`, `f/1.8`) are plain text, not keys. No year, month or date; motion only as entrances.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_photo_test.php` runs the kit and the file under Node: a section per block, one `h1` in the hero and one `h2` in the others with no skipped level, no fixed colour or `pph-`/`ph-` class, `{var}` filled, no year, entrances kept by `_sdFxNormalize()`; the strip's five numbered frames, the sheet's twelve proofs and three named picks, the six settings as `dt`/`dd`; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
