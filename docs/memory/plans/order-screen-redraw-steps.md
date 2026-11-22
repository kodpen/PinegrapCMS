---
name: order-screen-redraw-steps
description: Plan order-screen-redraw, steps OS1 to OS4: the head and its pills, the side panel and Payment, the shipment card with its lines, the later cards and close-out; how a step is checked
---

## Which steps are there? (all enter `view_order.php`, one worker at a time, each after the one before has landed)
- OS1 (M): the head (board O1 mark 1, O3). In `includes/fn/order_view.php`, pure and with red tests first: the three pills from the order row, its addresses and parcels; the head's grey line. One copy helper for the panel in `assets/js/backend.src.js` (`data-pg-copy`, the button says "Copied" for a moment, named for a screen reader), used by the order number. The toolbar moves to the head's right; the top bar's title stays.
- OS1 adds the patterns "Record head" and "Copy button" to Design examples.
- OS2 (M): the side panel and Payment (marks 4 and 6). The address block (pure: parts left out when empty, the country named as `pg_order_view_country()` does, escaped) and the equal-address check, with tests; Customer, Billing Information and Order Information with Source as `.pg-kv`; the totals move from the status column into the Payment card; the status column stops sticking. The count of the contact's orders is one query.
- OS2 adds the pattern "Address block".
- OS3 (M): the shipment card (marks 2 and 3, board O3). The address's lines move into its card, `pg_order_view_line()` is redrawn as the one row, the shipping fields go under the lines; "Items not shipped". No field's name, id or script changes: `update_order()`, `tagin`, the label form and the chooser are not touched. Pattern "Line row".
- OS4 (S): the later cards' place and two-across grid (mark 5), the phone round (board O4), and the close-out.

## What does the close-out do?
- Rewrites `commerce/order-view-screen-rows.md`, `order-screen-toolbar.md`, `order-screen-shipments.md` and `ui/look-order-screens.md` to what was built, with the PR numbers; corrects the order screen's lines of `plans/addon-hook-system-census-slots.md` and `ui/edit-screen-wide-layout.md` (the status column that does not stick).
- Keeps `commerce/order-screen-drawing/` as the reference picture and deletes the plan's files.

## What holds for every step?
- About 1,000 product lines at most; no schema; every value, field and button of today stays reachable, for every kind of order.
- The existing tests of the screen keep passing (`order_view_rows`, `order_view_form_gift_cards`, `view_order_toolbar`, `view_order_restore_button`, `view_order_vat_id_row`, `save_shortcut`); one that pins markup the step redraws is rewritten to the new markup, never dropped.
- Every new text through `lang()` with `php tools/tr_add.php`; today's keys are reused where they exist (Complete, Cancelled, Paid, Refunded, Shipped, Delivered).
- A step keeps `order-screen-redraw-states.md`, which holds what the boards cannot show.
- A step that needs a column, a right or an API field stops and asks the architect.

## How is a step checked?
- A screenshot round on the sandbox (`bash tools/setup_sandbox.sh`, `ui/panel-screenshots.md`) at 1440 and 390 px in both themes against the boards the step names; "Screen check" in the PR, the pictures as a private page, never committed.
- Orders looked at: one address, paid; two addresses with a line that does not ship; awaiting payment; cancelled; incomplete; one with a refund; and, where the sandbox can make them, a gift card line, a product form answer, Accounting on.
- Save is tried on the sandbox after OS3: a ship date, a tracking number, a carrier and a shipped quantity are written as before.
- Every new control is reached and worked from the keyboard.

Related: docs/memory/plans/order-screen-redraw.md, docs/memory/plans/order-screen-redraw-screens.md
