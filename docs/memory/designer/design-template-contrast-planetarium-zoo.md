---
name: design-template-contrast-planetarium-zoo
description: Planetarium to Zoo: User Styles keep palette text readable on dark grey and soft-tint sections in every palette; vet red and zoo green readable; store's User Styles
---

## Why do grey sections need their own colours in the dark theme?
- A palette's primary and second text colours (`--pg-primary-fg`, `--pg-secondary-fg`) are made readable on the page background. On the lighter grey of `bg-body-tertiary` (account bar, page headers, grey bands, staff bar) they measured 3.9 to 4.3:1 in dark.
- The rules are the same as App Studio to Events (#8997): `text-primary`, `link-primary` and `btn-outline-primary` take `--bs-primary-text-emphasis`, mixed 80% with `--bs-emphasis-color` behind `@supports (color-mix)`; `link-secondary` and `btn-outline-secondary` take `--bs-secondary-color`. Computed on Bootstrap's dark grey (#2b3035) the mix gives 5.09:1 or more in all 22 palettes (Midnight 5.18; its emphasis tint alone is 3.85).
- Pills, tabs and underlined links (`.nav` there: the wine list's filter, social's topic links, the staff bar) read the link colour: `--bs-nav-link-color` takes the same tint.
- Podcast had the first two rules (Sunset) and gained the mix. The Online Store had no User Styles: it has them now (`$store_css`), with its account bar's Log In (`btn-link`) beside the outline buttons.
- Sportsclub's sponsor box link had no colour class (the plain link colour, 4.12:1): it is `link-primary`.

## Why does Planetarium have a rule of its own?
- Its account bar is dark on a light site too: `data-bs-theme="dark"` sits on the grey bar itself, so `[data-bs-theme=dark] .bg-body-tertiary` does not reach it ("Tonight's Sky" was 3.9:1 in the light site theme). `.bg-body-tertiary[data-bs-theme=dark]` repeats the rules.

## What changes on the primary's soft tint?
- `bg-primary-subtle` (the account card with its Log out button, soft bands such as realestate's valuation band and podcast's gift box): the primary's text colour measured 3.41 to 4.4:1 in light with Amber, Classic, Coral, Electric, Emerald, Lavender, Ocean, Sky, Sunset; the second colour 3.16 to 4.47:1 with Graphite, Onyx, Electric, Midnight, Aurora, Coral, Cocoa, Steel, Lavender.
- There `text-primary`, `link-primary`, `btn-outline-primary` and `btn-link` take `--bs-primary-text-emphasis`, `link-secondary` and `btn-outline-secondary` `--bs-secondary-text-emphasis`, Bootstrap's pair for a soft tint: 7.35:1 or more in light and 4.56:1 or more in dark for every palette.
- The account card is a widget the previews do not draw; computed from the palette files.

## What about Bootstrap's fixed red and green?
- Veterinary's emergency line (`link-danger` in the account bar) and band (`btn-outline-danger` on `bg-danger-subtle`): 4.3 and 3.39:1 in light, 2.94:1 on the dark grey. Zoo draws its green with `success` (`link-success`, `text-success`, `btn-outline-success`, `bg-success-subtle`): 4.3 and 3.49:1 in light, 3.4:1 on the dark page and 2.94:1 on the dark grey.
- In light, on the grey and the soft tint, the colour is mixed 80% with `black` (Bootstrap's hover shade: 6.1:1 on grey, 4.9:1 on the tint), the emphasis tint without `color-mix`; in dark, `--bs-danger-text-emphasis` / `--bs-success-text-emphasis` (5.3 and 5.7:1 on the grey). The assembly tests refuse hex colours in User Styles: use `black`/`white`.

## What is still open?
- Light grey: the palette generator makes `--pg-*-fg` readable on white, and on #f8f9fa it falls just short with Amber primary (4.49), Coral primary (4.46) and Electric second (4.44). That belongs to the generator (shared), not to each template.
- Zoo's green does not follow the palette: choosing another palette changes only its hero wash (`pzo-hero`, the primary). Moving it to the primary changes the template's look; not decided.

## What tests it?
- `tests/design_template_fit_planetarium_zoo_test.php` (dark grey rules with the `.nav` one, Planetarium's bar and the store, the red and green, the soft-tint rules), with [[design-template-fit-planetarium-zoo]].
