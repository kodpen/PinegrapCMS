---
name: legacy-bootstrap-twins
description: Theme parts as designer twins on a trial design (LB6, #9308): photo backgrounds, sliders as carousels, lightbox as Fancybox; what stays with its script, rows switched off
module: legacy_bootstrap_twins
---

## Where does it run?
- `pg_lbs_trial_convert_tree()` (`legacy_bootstrap_trial.php`): `pg_lbs_tree()`, then `pg_lbs_twins()`, then the places pointed at the trial's copies. Used by Make a trial design (parts and the design's own tree), the preview (`pg_lbs_trial_page_code()`) and Move pages (`legacy-bootstrap-move.md`). Module `includes/fn/legacy_bootstrap_twins.php`, pure.
- Make a trial design also counts the twins of the Legacy pages (`pg_lbs_trial_page_twins()`) and lists the counts as a notice.

## Photo background?
- A `.background-image-holder` holding one image: class `legacy-photo fadeIn`, the image gone, `style="background-image: url(...); background-size: cover; background-position: center"` (the Background panel edits it).
- Why a new class: scripts.js sets every holder's background from its `<img>`; with none it writes `url("undefined")` over the photo.
- The trial's sheet copies and inline sheets get a `.legacy-photo` copy of each holder selector (`pg_lbs_css($css, 'pg_lbs_twin_selectors')`), except inside `:not()` and under `.parallax` (the offsets would push a static photo up).
- Kept: a holder with no single image (a video player), and a holder inside `.local-video-container` (Play hides it through scripts.js).

## Slider?
- `.slider-all-controls` (arrows and dots), `.slider-paging-controls` (dots), `.slider-arrow-controls` (arrows) over a `ul.slides` of `li`: a Bootstrap carousel (`carousel slide`, id `legacy-slider-<hash>` unless it has one, `ul.slides.carousel-inner`, `li.carousel-item`). The trigger class goes, the controls' labels come from `lang()`. It never plays by itself (`designer-block-motion.md`).
- Kept with flexslider: `.logo-carousel`, `.tweets-slider`, `.slider-thumb-controls`, a slider of another shape, a slider name in HTML text.
- Compatibility sheet: the list loses bullets and indent; arrows and dots get z-index 10 (the theme lifts a slide's content to 3 and covered them).

## Lightbox?
- A link with `data-lightbox`, or any link inside `.lightbox-grid`: `data-fancybox="<name>"` (the attribute's value, else the grid's `data-gallery-title`, else `gallery`). The same in HTML text (`pg_lbs_twin_html_links()`).
- The trial gets Fancybox's rows (`pg_lbs_trial_fancybox_rows()`: sheet, `fancybox.umd.js`, a bind row) after the theme's.

## Which script rows go?
- `pg_lbs_trial_assets(..., $twins)`: flexslider's row (`*flexslider*.js`) or the lightbox row (`lightbox(.min).js`) kept switched off when that part has twins and none is left without one. scripts.js guards its flexslider calls with `$.fn.flexslider`.
- A trial made before LB6 has no twins: delete it and make it again.

## Sandbox (turkish_default)
- home-1 set: 8 photos, 2 sliders twinned, flexslider off; photos show (Legacy loses them to the holder fault, `legacy-overview.md`); both carousels move on Next; phone fine.
- home-2 set: 80 photos, 7 sliders, 8 lightbox links; 3 sliders kept, so flexslider stays; a grid photo opens Fancybox's gallery.

Tests: `tests/legacy_bootstrap_twins_test.php`, `test_lbst_assets_twins`.
