---
name: application-access-permission-picker
description: Application Access permission picker: areas with icons, a row per scope group, None / Read / Write, search, counts, the four bulk buttons and their rule, the money pill
---

## Where is the picker drawn?
- `includes/api/app_permissions_view.php`: `api_settings_permission_rows($prefix, $id_prefix)` (moved out of `api_settings.php`) draws it for the drawer (`d_scope`) and New application's step 2 (`n_scope`); the first copy brings its `<style>` and the plain script `assets/js/api_app_access.js` (no bundle). Since #10826.
- Posted names stay `scope[<group>]`, so the save, the starting points (`START_CARDS`) and the drawer's `setRadio()` read them as before.

## How is it laid out?
- A sticky bar: "Search permissions", the count "{n} of {m} chosen", and All, None, All read, All write.
- One bordered section per area, in this order: Store, Content, Design, Workspace, Accounting, Site (`api_app_permission_areas()`); an area with no rows is left out. Its head: icon tile, title, "{n} / {m} chosen", and a `...` menu with the same four buttons for that area.
- A row: the group's icon, name, its line (`api_settings_group_text()`, also moved here), and a `pg-seg` radio group: None plus only the levels the row offers (Webhooks says Manage).
- `api_app_permission_area_keys()` names the area of every core and module group; a module group may carry `'area'`; an unknown key sits under Site. `tests/api_app_permissions_view_test.php` fails when a group has no area, icon or line.

## What do the bulk buttons tick?
- `api_app_bulk_choice($groups, $which)` (`includes/api/app_access.php`, pure; twin `pgAppAccess.bulkChoice()`, compared in Node by the test):
  - All: the highest level of every row (write where offered, else read).
  - None: nothing.
  - All read: read where offered; a write-only row gets nothing.
  - All write: write where offered; a read-only row gets nothing, so All and All write differ.
- With a search typed, a button acts only on the rows shown; an area's menu only on its rows.

## How are rows that move money shown?
- A row whose scope has a `moves_money` route (`api_app_permission_money_scopes()`: `api_schema()` and the modules' routes) carries the pill "Moves money"; `data-money` says whether its read or only its write moves money.
- All and All write show beside them how many such rows they would tick (`api_app_bulk_money_count()`, twin `pgAppAccess.moneyCount()`); after one is used the line "{n} permissions that move money are ticked." says it, until another application is opened or a starting point ticks its rows (`clearPickNote()`, #11826).

## What did it not take on?
- A permission the owner's account lacks is not shown off: the screen has never done so; the API caps the key at the owner's rights (`external-api-authorization.md`).
- The old `.api-seg` rules in `api_settings.php`'s style block no longer match any row; a later change can drop them.
