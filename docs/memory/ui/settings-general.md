---
name: settings-general
description: Site Settings › General as folded rows: the four rows and their state lines, what each row holds, the IP as a line, Outgoing proxy, what stayed (ids, field names, the save, the search words)
module: general_settings_fold
---

## How is the pane drawn?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`), first row open, in this order: Organization, Site address, Software, Date and time. Order, names, icons and lines: `includes/fn/general_settings_fold.php` (`pg_general_fold_order()`, `pg_general_fold_face()`, `pg_general_fold_<part>()`, pure but for `lang()`). Test: `tests/settings_general_fold_test.php`.
- The purpose line is the category's description in `registry.php`: who runs the site, where it is, its licence and its clock.

## What does each row say and hold?
- Organization (`pgset-organization`): line "name, city" or "Not filled". Three groups under `pg-group-label`: "Name and address" (name, two address lines, city, state, zip code, country); "Support E-mail Address" with its line (system mail's From, DKIM's domain when none is set); "Seller" (logo, tax number, tax office in Türkiye).
- Site address (`pgset-server`): line the Hostname or "No hostname set". Hostname with the meta line "This server's IP: …" (no box any more); "Outgoing proxy" (`proxy_address`, `PROXY_ADDRESS`) with its line linking `#settings/firewall/pgset-proxies` (Trusted proxies are those in front of the site).
- Software (`pgset-software`): line "Licence set" / "No licence key", "· verbose database errors on" while on. Language, Subscription Key with its licence warning, "Verbose database errors" a `pg-switch` (`debug`) with its warning line.
- Date and time (`pgset-datetime`): line "time zone, today's date" in the chosen format. The site time as a meta line, Timezone, Date Format and Time Format (`pg-seg`); under Date Format the line that a page in another language uses that language's format, linking `#settings/languages/pgset-languages`.

## What stayed?
- Card ids, field names and `general.save.php`; `pgset-general` still lands on Site address. The client opens the row an anchored card or field sits in (`#settings/general/proxy_address`).
- The search: Site address's keywords gained "site address", "proxy address", "outgoing proxy" and keep "proxy" (architect: an old label stays searchable).

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/screen-composition-rules.md, docs/memory/ui/settings-privacy.md
