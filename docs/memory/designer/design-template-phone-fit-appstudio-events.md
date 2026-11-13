---
name: design-template-phone-fit-appstudio-events
description: Templates App Studio to Events, Bookshop and Boutique on a phone: the account bar's links wrap as a whole and keep each label on one line; the test that holds it
---

## What ran off the page?
- With a second site language the language switcher joins the account bar, and at 320 to 360 px Sign Up ran past the page: the Account Links row did not wrap.

## What do the templates write now?
- Account Links: `d-flex flex-wrap align-items-center justify-content-end column-gap-1 column-gap-sm-2 row-gap-1 ms-auto` (the Boutique's centred bar: `justify-content-center column-gap-2 column-gap-sm-3 row-gap-1`).
- `$lang_switcher('text-nowrap')`; Signed Out `gap-1 gap-sm-2` (Boutique `gap-2 gap-sm-3`); Log In, Sign Up and the Account Button `text-nowrap`.
- The same classes as the Planetarium to Zoo templates, so every template's bar behaves alike.

## Which test holds it?
- `tests/design_template_fit_appstudio_events_test.php`: for the sixteen templates, the row holding the language switcher wraps (`flex-wrap`, `row-gap-1`, to the end or the middle), and the switcher, Log In, Sign Up and the signed-in name keep `text-nowrap`.

## Was a hidden table label checked?
- None of these templates puts a `visually-hidden` header cell in a `.table-responsive`; Campsite's price table and Driving School's test sheet have visible headers only.

Related: docs/memory/designer/design-template-overview.md
