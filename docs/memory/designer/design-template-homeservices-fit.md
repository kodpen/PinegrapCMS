---
name: design-template-homeservices-fit
description: Home Services 2.0.0 browser fixes: the header opens out at 1200 px, the price tables' scroll boxes are positioned, dark tertiary bands take the emphasis tint; why and the numbers
---

## Why does the header open out at 1200 px?
- `navbar-expand-xl` (it was `-lg`): the brand, six Turkish links ("Hizmet Bölgeleri", "Müşteri Yorumları" …) and two buttons need about 1,280 px; from 992 to 1,399 px the bar overflowed its container and the page scrolled sideways (by 330 px at 1,009 px).
- From 1,200 to 1,399 px Get an Estimate is left out of the open bar (`d-xl-none d-xxl-inline-block`); it stays in the collapsed menu and returns from 1,400 px. Checked at 1,200 px with the looks modern-sharp, playful, luxe and bold (PR #8826).

## Why is a price table's scroll box positioned?
- The last column's heading is a `visually-hidden` span (`position:absolute`). In a `table-responsive` that is not positioned its containing block lies outside the scroll box, so the box does not clip it: the Prices page was 660 px wide at 375 px.
- `table-responsive position-relative` keeps it inside. The panel met the same trap ([[erp-bank-statement-matching]]); a block with hidden column headings in a scroll box can meet it too.

## Why do dark tertiary bands change the text colour?
- A palette's dark text colour (`--pg-primary-fg`, used by `text-primary`, `link-primary` and `btn-outline-primary`) is made for the page background; on `bg-body-tertiary` (#2b3035) Steel's #7f93a4 gives 4.2:1 (4.85:1 on the page).
- Small text there failed: the account bar's emergency number and the login region's sign-up button, the page headers' Home link (`link-secondary`, 4.3:1), the eyebrows of the grey sections.
- The template's User Styles: under `[data-bs-theme=dark] .bg-body-tertiary`, `text-primary` and `link-primary` take `--bs-primary-text-emphasis`, `link-secondary` `--bs-secondary-text-emphasis`, `btn-outline-primary` its colour from `--bs-primary-text-emphasis` (Steel: 4.8:1 and 5.2:1). Light was measured with no failure and is unchanged.
- Measured by setting `data-bs-theme` on the page with transitions off: a hidden browser pane does not run transitions, so a colour read right after the switch is still the old one.

## How is it tested?
- `test_homeservices_assembly_header_tables_and_dark_bands` (`tests/design_template_homeservices_assembly_test.php`).

Related: [[design-template-homeservices-pages]], [[design-colour-mode]]
