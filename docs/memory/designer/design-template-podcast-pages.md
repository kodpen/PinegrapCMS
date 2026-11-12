---
name: design-template-podcast-pages
description: Podcast Network 2.0.0: home, sponsorship, listening and hosts pages on the Podcast blocks and Stats, Steps, Team blocks; builders, limits, phone and dark fixes, test
---

## Which builders draw the blocks?
- Version 2.0.0: `podcast-1` (#8785) made the family ([[designer-blocks-podcast]]), `podcast-2` (PR #8845) the pages. Rules ship through `pg_design_template_user_styles(array('podcast-episode', 'podcast-sponsor', 'podcast-transcript'), $podcast_css)`; no `ppd-` rule names a `pg-podcast-` class.
- `parts.php`: `$fx`, `$rise($node, $stagger = 100)` (slide-up on view), `$fade_in` (fade on view, 700 ms), `$at($time)` (a `<time datetime="PT…M…S">`). The newest episode `kitchen-12` has a `guest` and a `script` (chapter 1, six turns); each of `$packages` has `when` and `at` (the track's mark labels).
- `pages_public.php`: `$episode_hero()` (the home's only `h1`, dark band), `$transcript_band()`, `$host_profiles()`; `$eyebrow()` is `text-primary-emphasis`. `sponsors.php`: `$sp_placements()`, `$sp_steps()`. `episodes.php`: the episode page's cover sits in `pg-podcast-cover` over a `pg-podcast-wave`.

## What does each page hold?
- Home: Episode Hero with chapters and the guest card (`pd-home-episode`), Latest Episodes, the figures as Stats: Counter, Podcasts, support band, News, ask band. The story band moved to the hosts page.
- Sponsorship: Who Listens, Sponsor Placements (`pd-sponsor-packages`), Steps: Numbered (`pd-sponsor-steps`), Past Sponsors, the call to action.
- How to Listen: steps, apps, Transcript (`pd-listen-transcript`), support band. Hosts: Team: Profiles (`pd-hosts`), then the be-a-guest band.

## Why do the pages differ from the blocks?
- Listen Now links `{{page:episodes}}` (newest first) and the second button is All Podcasts: a page cannot address a record that is only made when the template opens.
- Placement cards add the perks and an Ask About It link per package (`{{page:sponsor_enquiry}}?package=`).
- Team: Profiles has no intro (the page header holds it); roles use `text-primary-emphasis`.

## What could the editor not do?
- Hero, chapters, transcript and placements are page content written from parts.php; a changed episode is not seen on a site already made. A record link for the hero and the chapters bound as a list are left to the designer.
- Load effects (cover, wave) show on the canvas only with Play.

## What did the browser show?
- Header: six links and Support Us need about 1,100 px, so `navbar-expand-xl`; with lg the page scrolled sideways from 992 to 1199 px.
- Dark theme, tertiary bands: primary text 4.26:1, Sunset's purple secondary 3.97:1. Rules give `.text-primary`, `.link-primary`, `.btn-outline-primary` the emphasis tint and `.link-secondary`, `.btn-outline-secondary` `--bs-secondary-color`.
- A content image's size goes in its `width` / `height` props: the image renderer drops them from `_attrs`, so portraits showed at their file's size (240 px).
- The hero's app icons sit in a row of their own, so a phone wraps them together.

## How is it tested?
- `tests/design_template_podcast_blocks_test.php`: labels, search ids, one `h1`, rules ship, motion kept; hero facts, `PT58M`, chapter times, guest; placement marks and links; transcript turns; a profile per host; header xl, image size props, dark-band rules.

Related: [[design-template-podcast]], [[design-template-homeservices-fit]], docs/memory/designer/design-template-own-look.md
