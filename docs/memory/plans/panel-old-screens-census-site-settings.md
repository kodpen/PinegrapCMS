---
name: panel-old-screens-census-site-settings
description: Census LM1b against look A (S5): the Site Settings categories in includes/settings/ (dialog and settings_<key>.php pages): inline styles, old classes, rows without icon tiles
---

## How is a line read?
- `file`: lines / marker lines / `style=` / fields / cards → estimate without fields; the 32 px field and the switch come from the settings card CSS already (`ui/look-settings-screens.md`). Method in `panel-old-screens-census-site.md`.

## What is done, and what is left on every category?
- Done (DL5d, #8952): the dialog, its rail, head and foot; each card a title over an inner card of rows; fields and switches; the page at the edit measure with the floating save bar.
- Left, shared: no row has the 34 px icon tile; the segmented control and small quiet button as row controls are not used; buttons inside card headers keep Bootstrap classes; the page toolbar (category menu, chips) is unchanged.

## Which categories carry the most old markup?
- Commerce `includes/settings/commerce.php` 1,065 / 58 / 53 / 70 / 18 → 94, the most: 53 inline styles (18 `position`, 8 `text-align`, PayPal blocks shown or hidden with `style=`). `commerce_merchant.php` 166 / 5 → 5.
- Maintenance `maintenance.php` 975 / 38 / 0 / 46 / 14 → 66 (seven Bootstrap buttons, header button rows).
- Firewall `firewall.php` 666 / 24 / 16 / 18 / 12 → 48 (inline `text-align`, `position`); Security `security.php` 507 / 22 / 7 / 20 / 16 → 54.
- Accounting `accounting.php` 459 / 10 / 2 / 28 / 12 → 34; Bookings `bookings.php` 472 / 13 / 6 / 21 / 2 → 17 (the staff hours grid, two tables); Features `features.php` 609 / 7 / 3 / 19 / 12 → 31.
- Smaller: Privacy `privacy.php` → 25, Contact `contact.php` → 35, Languages `languages.php` → 18, SEO `seo.php` → 17, Team `workspace_team.php` → 25, Fediverse `fediverse.php` → 12 (three tables), Appearance → 19, API → 19, General → 16, `privacy_analytics.php` → 3.
- Shared code: `screen.php` (the page) → 7, `prep.php` 2,496 / 20 / 8 → 20; `modal.php`, `fragment.php`, `registry.php`, `search.php`, the `*.save.php` files and the Workspace assistant and channel cards draw nothing old.
