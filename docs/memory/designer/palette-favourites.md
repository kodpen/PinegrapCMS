---
name: palette-favourites
description: Favourites in the designer's Components palette (DO24): the star on a row, the F key, the list kept per browser beside Recently Used, its order and limit, tests and sizes
---

## What does it do?
- A star on a row of the Components or Blocks list pins the row under "Favourites", the first group of the Components list, above "Recently Used". Pressing the star again, or on the row in Favourites, unpins it. New favourites go last. No bar button and no part: the group is drawn with the palette, as Recently Used is.

## Where is the list kept?
- In the browser's localStorage, key `pinegrap_designer_favourite_components_v1`, beside Recently Used (`pinegrap_designer_recent_components_v1`): per browser and person, across designs. No table, column or right.
- At most 30 rows (`MAX_FAV_COMPS`); a 31st shows the toast "At most 30 favourites." and is not added.
- A custom (non-Bootstrap) design hides the Bootstrap rows of both groups (`_sdPalForDesign()`), as before for Recently Used.

## Which rows can be pinned?
- The rows Recently Used keeps (`_sdPalKeepable()`): not a widget's part or value (`widget_part`, the "Parts of …" group) and not an item of My library. Shared and System rows are in other tabs and have no star.
- A row is known by `_sdPalItemId(type, extra)`, the same key Recently Used uses.

## How is the star drawn?
- One button (`.sd-fav-btn`, `tabindex=-1`, `draggable=false`, `aria-pressed`) moved into the row under the pointer or in focus (`_sdFavWire()`, a delegated `mouseover`/`focusin` per list), so the lists carry no button per row. Shown while the row is hovered or focused; yellow when on.
- F on a focused row toggles it (`_sdWirePaletteKeys()`). A double-click on the star inserts nothing.
- Recently Used is inserted under Favourites, and the "Parts of …" group under Recently Used, else under Favourites.

## Tests and sizes?
- `tests/designer_palette_favourites_test.php`: the key, the kept rows and the toggle with its limit under Node; source pins for the order, the star and the F key. No browser run: the star's hover and the group's drawing.
- Core `style_designer.min.js` +2,947 bytes (+924 gzipped), #11753.
