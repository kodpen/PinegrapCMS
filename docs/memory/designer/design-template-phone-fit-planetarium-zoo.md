---
name: design-template-phone-fit-planetarium-zoo
description: Templates Planetarium to Zoo on phones: the account bar row wraps (#9056), table wrappers hold hidden labels, lines that ran past 320 px; measuring overflow under mobile emulation
---

## Why does the account bar's row wrap?
- Issue #9056: with more than one site language, the bar's row (language switcher, cart link, login region) did not wrap. In the store, Log In and Sign Up broke onto two lines each at 360 and 375 px. The other sixteen templates already had `text-nowrap` on them, so Sign Up ran past the page instead (zoo 47 px, veterinary 42 px at 320).
- "Account Links" is now `d-flex flex-wrap align-items-center justify-content-end column-gap-1 column-gap-sm-2 row-gap-1 ms-auto`. The switcher takes `text-nowrap` (its cssClass lands on the dropdown), "Signed Out" `gap-1 gap-sm-2`, Log In, Sign Up and the signed-in Account Button `text-nowrap`.
- Where the row still does not fit, the login region moves under the switcher and cart as a whole, right-aligned. Store in its own look: one row from 375 px; in the luxe and precision looks two rows up to 414 px.
- The preview draws neither the switcher (one language in the sandbox) nor the cart link (its starter tree lives in the designer script): both were added to the page to measure.
- A site already built from a template keeps its saved account bar (a shared component): dev.pinegrap.com needs the same classes in its own bar.

## Why is a table's scroll wrapper position-relative?
- `visually-hidden` is `position:absolute`. Inside a static `.table-responsive`, a hidden column label in a table wider than the phone was placed against the card or the page and widened it: travel's deals page to 616 px, wedding's packages to 459 px, sportsclub's league table to 385 px, at every phone width. The wrapper takes `position-relative`, so the label stays inside the scroller.

## What else ran past 320 px?
- Store footer phone number (`text-nowrap` in a half column): its row is `flex-wrap column-gap-2`, the number moves under its icon.
- Veterinary footer hours (`.pvt-price` sets `white-space:nowrap`): `text-wrap` there, like restaurant's `prt-price`.
- Salon team portraits (144 px in a 128 px column): `img-fluid`.
- Wedding timeline's "See the planning services" pill: `flex-shrink-0` in place of `text-nowrap`. It keeps one line beside the note and wraps when stacked.

## How is overflow measured at phone width?
- With Playwright's `isMobile: true` the layout viewport widens to the content, so `innerWidth` grows too and `scrollWidth - innerWidth` stays 0. Compare `document.documentElement.scrollWidth` with the width you set. The first round of this work used innerWidth and missed the tables above.
- An absolutely positioned child escapes an `overflow-x:auto` wrapper that is not its containing block: look for `visually-hidden` and `position-absolute` inside table wrappers and scrolling strips.

## What tests it?
- `tests/design_template_fit_planetarium_zoo_test.php`: the account bar row wraps, and table wrappers with hidden labels are `position-relative`. See also [[design-template-fit-planetarium-zoo]].
