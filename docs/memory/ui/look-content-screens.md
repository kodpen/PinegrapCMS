---
name: look-content-screens
description: Comments, short links, the 404 log, the accessibility report and page tests in look A (S1, #9773): titles, field spans per field, inline sizes to classes, what stayed
---

## Which screens moved?
- Edit Comment, Create and Edit Short Link (`includes/forms/short_link_form.php`), Import Short Links, the 404 Log, the Accessibility report, Page tests (`page_experiments.php`).
- The lists (Pages, Comments, Short Links, Pop-ups) draw through the table driver; on them only toolbar buttons are old, which are not a group step's.

## What changed on them?
- Card titles: the upper-case `text-uppercase h5 fg-primary fw-bold` and the plain `card-header` titles are `h2.card-header.bg-reset.border-0.pg-title-section`. A Page tests experiment card's header holds its name, status, goal, dates and buttons: the header takes `pg-title-section`, the name loses `fw-bold`, the small lines `fw-normal`.
- Fields (`look-field-widths.md`): Display Name, short link Name, Destination URL and the page test's Name the whole row; How it opens and Valid for 6 + 6; How it answers, Destination Type, the page, product group, product and file pickers and Variant B alone at 6 (`pg-field-span-6`); Tracking Code 4 alone; the share of visitors (%) 3 alone; the CSV file chooser 6 alone.
- Page tests puts `pg-field-span-N` on the `.form-field` inside the column, since the label and help sit in that wrapper.
- Import Short Links' File Format card sits in a `position-sticky` box (top 3.3rem) instead of being sticky itself, so the status column draws it as the look card.
- Inline sizes to classes: the 404 Log's address and referrer cells use `.pg-404-cell-wrap` in `backend.src.css`; Edit Comment's rating stars' cursor and 16 px box moved into the screen's own `<style>` block.

## What stayed as it was?
- Add Page, Edit Page and `includes/forms/page_form.php` (owner: the classic page screens get no look work).
- Buttons (toolbar, bulk, row buttons, the Short Links / 404 Log switch), the Summary values, the catalog page / "or" / catalog detail page trio of the short link form (5 + 2 + 5 from lg), the review photo's 240 px thumbnail, the QR code's white well, the variant letter cell of Page tests.
