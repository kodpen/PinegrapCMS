---
name: legacy-layouts-bootstrap
description: Plan (owner 2026-11-12, #8942): the page types' custom layouts and built-in templates print Bootstrap 5.3.8 names on a design of that framework; census first, steps LL1-LL6
---

## What is asked?
- Owner (2026-11-12), offered a plan for moving the custom layouts as one of three ways to fill a week: "1 ve 3, rapor ekranlarını çiz" (3 was this plan; record `workflow/decisions-made-product-46.md`).
- A page of a Bootstrap 5.3.8 design whose page type prints its own markup (cart, checkout, sign-in, forms, catalog) looks right without the compatibility sheet of `legacy-bootstrap-helper-rules.md`.

## What is there today?
- A page type's markup comes from `includes/templates/<type>.php` or, once edited, from the page's `data/layouts/<id>.php` (`designer/layout-code-tab.md`). Both print Bootstrap 3 names: 79 of the starting sites' 88 layouts, `form-group` 629, `text-right` 345, `input-group-btn` 122 (`designer/legacy-bootstrap-census.md`).
- The same file serves a classic style, a Legacy design and a Bootstrap 5.3.8 design. For some page types the Page Design Studio offers a system widget instead (`designer/layout-widget-offer.md`).

## Which rules does every step keep?
- A classic style and a Legacy design get byte for byte what they get today: nothing is rewritten in place, no layout file changes without a copy kept, and "Send back to Legacy" (LB5) brings the old output back.
- One map: the class map of `includes/fn/legacy_bootstrap.php` (LB2), no second list of names.
- No schema. A need for a column (a layout that belongs to one design) comes back as an `architect` question.
- Seen in a sandbox, before and after, wide and phone, signed in and as a visitor: cart, checkout, sign-in, a custom form, a catalog page.

## Which steps?
- LL1 (S, no product code) The census. Done #8968 (`designer/legacy-layouts-*.md`); decided on #8969: map at output, `legacy-layouts-output-map.md`. Per page type: is there a system widget twin; how many layouts differ from their template; where the layout's output joins the page; which names are built in PHP rather than written in a `class` attribute; the cost of mapping the output per request against converting files.
- LL2 (M) Done #8986 (`designer/legacy-layouts-converter.md`). The layout variant of the map over a system region's output (`class` attributes, the `data-toggle` family), first the count of the names scripts use; pure functions with tests on the starting sites' layouts; a second pass changes nothing.
- LL3 (M) The pass in `get_page_content.php`: a page of a `bootstrap5` design gets 5.3.8 names from its page type; every other page is untouched.
- LL4 (M) What the map cannot rename: `glyphicon` to Bootstrap Icons, `input-group-btn` and `input-group-addon`, `checkbox` and `radio` blocks, `help-block`, `panel` where met.
- LL5 (S) The widget offer: where a page type has a system widget, the helper's report names it as the better move.
- LL6 (S) Close-out: the compatibility sheet leaves the trial design where LL3 covers the page type; the lasting rules into `designer/`; this plan deleted.

## When does it start?
- LL1 writes no product code and may run beside LB2 of `legacy-bootstrap-helper-steps.md`. LL2 waits for LB2 (the map). LL2 and LL3 keep the rules of `legacy-layouts-output-map.md`.
- Claim as `[plan:legacy-layouts-bootstrap] <step> — <topic>`.
