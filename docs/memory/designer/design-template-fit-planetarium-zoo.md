---
name: design-template-fit-planetarium-zoo
description: Templates Planetarium to Zoo: header menu opens at 1200 px, smaller links to 1399 px, row wraps, image sizes in props, restaurant hours, social counts; the numbers
---

## Why does the header menu open out at 1200 px?
- Eleven headers had `navbar-expand-lg` (portfolio, recruitment, restaurant, school, skiresort, sportsclub, theatre, travel, veterinary, wedding, zoo): from 992 px the name, six to eight links and one or two buttons were laid out in the 960 px container. In Turkish the home page scrolled sideways by 112 to 375 px at 1,000 px (veterinary the most) and by up to 190 px at 1,200 px.
- Now `navbar-expand-xl` with only `-xl-` / `-xxl-` utilities in the header. Planetarium, podcast, realestate, salon and social already opened at 1200 px.
- The navbar's container has `flex-xl-wrap`, so a long site name moves the row under it. Social is the exception: its header search field gives up its width first, and wrapping there put the links under the name at every width.

## What keeps the row inside 1140 px from 1200 to 1399 px?
- The menu's own class (`ppm-nav`, `ppd-nav`, `pdp-nav`, `pre-nav`, `prc-nav`, `prt-nav`, `psh-nav`, `psk-nav`, `psp-nav`, `pth-nav`, `ptv-nav`, `pvt-nav`, `pwd-nav`, `pzo-nav`) sets `--bs-nav-link-font-size:.875rem` and `--bs-navbar-nav-link-padding-x:.375rem` there; the header button takes `px-xl-3 px-xxl-4`. Planetarium's eight links keep the smaller size from 1200 px on, and its button is left out of the bar from 1200 to 1399 px (it shows in the collapsed menu and from 1400 px).
- Recruitment's second button (Hire Staff) stays in the collapsed menu only. Salon (five links) and social need no rule.
- Shorter link words where English still wrapped: veterinary Pets and Vets, sportsclub News and Events, realestate Agents and News (existing keys).
- Room left on the row, template look, Turkish, at 1200 / 1400 px: theatre 77 / 81, veterinary 51 / 59, zoo 54 / 93, school 115 / 139, others more. All fourteen looks fit; the tightest are zoo in modern-sharp (37) and theatre in editorial (23) at 1400.

## Why is an image's size a prop?
- The page writes `width` / `height` from the image's props only ([[image-size-props]]). Eighteen round portraits in twelve of these templates had them in `_attrs` (testimonial faces of 48 px, team and guide portraits of 80 to 160 px) and showed at their file's size, twice that. They are props now; `loading` stays in `_attrs`.

## What else was fixed?
- Restaurant footer: the opening hours (`prt-price`, `white-space:nowrap`) ran past their column at 1,000 to 1,060 px; that span takes `text-wrap`.
- Social: the tile counts (`psc-tile-info`) show on hover only; under `@media (hover:none)` they stay in view.
- Colours on grey and soft-tint sections: [[design-template-contrast-planetarium-zoo]]. Phones (account bar row, table wrappers, footers): [[design-template-phone-fit-planetarium-zoo]].

## What is still open?
- Heading skips h1 to h3 on list pages (card titles right under the page title).
- Theatre's "180,000" and podcast's "1,200" figures keep the English thousands comma in Turkish; Courses writes such a figure through `lang()` with its own key.
- Shared, not template files: the luxe and elegant looks set `btn-sm` text at 10.5 px.

## What tests it?
- `tests/design_template_fit_planetarium_zoo_test.php`: header `navbar-expand-xl` with no `-lg-` class, container `flex-xl-wrap` (social not), the menu class rule, no image size in `_attrs` (store too), and the colour rules.
- Measured in headless Chromium on the sandbox: every preview page at 320 to 1,440 px, Turkish and English, light and dark; phone widths against the set viewport, not innerWidth ([[design-template-phone-fit-planetarium-zoo]]).
