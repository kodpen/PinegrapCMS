---
name: supplier-feed-screens
description: Plan supplier-feed, how its screens are put together (SF2): the Supplier feeds list, Add and Edit on the edit layout, the mapping grid, the test pull drawer; the Design examples section of each part
---

## Which screens, and by which rules?
- SF2: Products › Supplier feeds (list), Add and Edit supplier feed, the mapping and the test pull. SF4 adds one select to the Fields card (below); SF5 adds to the product's screen only.
- Product screen (SF3b): "Supplier feed" card [cards], side column under Stock by location, feeds with link, buy price, last seen, the switch "Price from the feed"; none when no feed carries it (architect, 2026-11-21, #10963).
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets (`workflow/screen-described-in-plan.md`).

## How is the list laid out?
- A list screen on the list table driver: name, host (never the address), format, every N hours, last pull with a state pill [status] (OK, Failed with the reason's first words, Never pulled), products matched.
- Toolbar: one fill, "Add supplier feed" [buttons]. Row actions quiet small: Edit, Pull now, Test pull; Delete in the row's menu [menus], danger, with a confirm saying its products stay and stop following the feed.
- Empty: one line on what a feed does and the Add button.

## How are Add and Edit laid out?
- The edit layout (`ui/edit-screen-wide-layout.md`): form column, status column, save bar. Cards [cards] in this order:
  1. "Feed": name, address, format (Automatic first), every N hours.
  2. "Sign-in": None, User and password, Key header, Key in the address as a segmented choice [segmented control]; only the chosen way's fields show. "Allow an address without https" is folded under "Advanced" with one line of warning.
  3. "Prices": the feed's currency, prices include VAT, the ERP supplier account (only with accounting on).
     From SF3 the price rule (F4) under them: margin %, fixed amount, rounding as segments (None, Whole, ,90, ,99), minimum margin % (the floor), with one line: "Buy price x (1 + margin) + fixed amount; never under buy price plus the minimum margin." Then "Rules by category or brand": rows [rows] of kind (Category, Brand), the category or brand, margin %, fixed amount; "Add a rule" quiet, a row's remove; one line says the most specific rule wins.
  4. "Fields": the mapping, shown once a first pull has read records.
- A new feed shows cards 1 and 2 and the button "Read the feed"; cards 3 and 4 open after the read. No empty mapping grid.
- Status column: state pill, last and next pull, the counts as a label and value list (`ui/look-kv-list.md`), Pull now and Test pull (quiet), Delete (danger) at its foot.
- SF3b: "Held prices", first card of the form column while a buy price is held: rows [rows] of last and held buy price, Accept and Reject quiet small; "Accept all" quiet at its head; 20 rows, the rest behind "Show all" in a drawer [drawer]. No filled button: the save bar holds the only one (architect, 2026-11-21, #10963).
- SF3b: "Pull history" (quiet) in the status column opens a drawer [drawer] of the last 30 pulls, like the test pull's (same approval).

## How is the mapping laid out?
- A mapping grid in its own narrow columns (`ui/look-mapping-grids.md`): a row a product field, a select of the feed's fields with the proposed one chosen, a sample value from the first record. The record path of XML and JSON is one select above it.
- Required fields first, the rest folded under "More fields". A proposed choice carries a "Suggested" pill until saved.
- SF4: under "More fields", "Drafts go into": a select of the browse product groups ("No group" first), help line "New products whose category matches no group are filed here." Kept in the mapping JSON as `draft_group` (F8's feed group); a read of the feed keeps it.

## How is the test pull shown?
- A drawer from the status column: four figures [cards: figure] (would be added, changed, closed, reopened), under each its first 20 rows as plain rows [rows]; one line says nothing was written. From SF3 a row shows buy price and the rule's selling price, "Floor" pill where the floor lifted it. Foot: "Pull now" fill, Close quiet.

## Which words?
- One line of help a card. On screen: "Supplier feed", "Pull", "Test pull", "Fields"; never "blob", "sync job" or a column's name.
