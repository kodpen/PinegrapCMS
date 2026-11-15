---
name: look-marketplace-ranks
description: SH10 (#9755): the Marketplaces and Marketplace Products screens' button ranks (one fill per view, one quiet rank per card) and their views and filter rows as link segments
---

## Which button is filled on the Marketplace screens?
- Marketplaces (`marketplace_settings.php`): only the toolbar's Connect a Marketplace. The Connect dialog (Connect) and the Settings drawer (Save) each have their own fill beside a ghost Cancel.
- Marketplace Products (`marketplace_products.php`): each view's hero action, one per view: Read products (catalogue), Send all now (Products in this shop), Fetch categories (Listings). Accept all sure ones is quiet. The Attributes dialog's Save is its fill.

## What rank do card and row actions take?
- One rank, the same on every card, row and the status column: `pg-button pg-button-quiet pg-button-sm`, whatever the row's state (Accept, Pick a product, Not sold here, Remove match, Change code, Attributes, List on the marketplace…). Settings and Test connection in the status column are quiet too; Settings was a second fill before.
- Destructive ones are `pg-button-danger pg-button-sm`: Remove webhook, Send rejection, Remove them (stale matches), the drawer's Delete.
- The sticky bulk bar: quiet small actions, Clear as `pg-button-ghost pg-button-sm`. The inline code edit: Save quiet small, Cancel ghost small (`assets/js/marketplace_products.js`).
- Pager arrows and the search submit are `.pg-icon-button`. Icons inside a `.pg-button` carry no `me-*`: the button's gap spaces them.

## How are the views and filters drawn?
- The views (Catalogue on …, Products in this shop, Listings) and the three filter rows are `nav.pg-seg.pg-list-seg` with `a.pg-seg-item` (`is-active`, `aria-current="true"`), the count as `.pg-seg-count` keeping `data-mp-count` for the script. They scroll sideways on a phone. `tests/returns_commission_names_test.php` counts the three filter rows.

## What was left?
- The Matched products card's four header actions still wrap onto three lines on a phone. Architect (2026-11-15, channel message 5044): not this step's, left.
- The status column's count list is SH2's recipe.

Related: docs/memory/ui/look-shared-buttons.md, docs/memory/ui/look-list-title-settings-tile.md
