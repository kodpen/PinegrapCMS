---
name: settings-general
description: Site Settings › General as folded rows: Organization, Site address (Hostname, Other host names and HTTPS value rows, Outgoing proxy), Software, Date and time; what moved, what stayed
module: general_settings_fold
---

## How is the pane drawn?
- Folded rows since #11013 (`pg_settings_fold()`, `ui/settings-fold-rows.md`), first row open, in this order: Organization, Site address, Software, Date and time. Order, names, icons and lines: `includes/fn/general_settings_fold.php` (`pg_general_fold_order()`, `pg_general_fold_face()`, `pg_general_fold_<part>()`, `pg_general_site_values()`, pure but for `lang()`). Test: `tests/settings_general_fold_test.php`.
- The purpose line is the category's description in `registry.php`: "Who runs the site, where it is, its licence and its clock." One Save, the pane's fill.

## What does each row say and hold?
- Organization (`pgset-organization`): line "name, city" or "Not filled". Three groups under `pg-group-label`: "Name and address" (name, two address lines, city, state, zip code, country); "Support E-mail Address" with its line "System mail is sent from this address; DKIM signs with its domain when none is set."; "Seller" (logo, tax number, tax office in Türkiye).
- Site address (`pgset-server`): line the Hostname or "No hostname set". Hostname with the meta line "This server's IP: …". Then value rows (#11071): "Other host names" (Redirected / Not redirected, Change to `#settings/seo/pgset-seo`) and "HTTPS" (Secure Mode on / off, Change to `#settings/firewall/pgset-headers`). Then "Outgoing proxy" (`proxy_address`) with its line linking Trusted proxies (`#settings/firewall/pgset-proxies`).
- Software (`pgset-software`): line "Licence set" / "No licence key", "· verbose database errors on" while on. Language, Subscription Key with its licence warning, "Verbose database errors" a `pg-switch` (`debug`) with its warning line.
- Date and time (`pgset-datetime`): line "time zone, today's date" in the chosen format. Current Site Time as a meta line, Timezone, Date Format and Time Format (`pg-seg`); under Date Format the line that a page in another language uses that language's format, linking `#settings/languages/pgset-languages` (`pg_sw_default_date_format()`).

## What moved, and why?
- The cards Organization, Server & Domain, Software, Date & Time became rows; Server & Domain is "Site address".
- "Proxy Address" is labelled "Outgoing proxy": it is `PROXY_ADDRESS` (payments, updates, Mailchimp, Cloudflare), not the proxies in front of the site, which Firewall's Trusted proxies holds. The IP was a disabled box.
- The value rows only show the redirect (SEO) and Secure Mode (Firewall and HTTPS) and write nothing: the site address was set in three panes and General named neither. Each setting stays in its pane (one place per setting, `workflow/decisions-made-product-14.md`).
- "Verbose Database Errors" in a `div.switch` is "Verbose database errors", a `pg-switch`.

## What stayed?
- Card ids, field names and `general.save.php`; `pgset-general` still lands on Site address. The client opens the row an anchored card or field sits in (`#settings/general/proxy_address`).
- The "Support E-mail Address" label (`workflow/decisions-made-product-11.md`).
- The search: Site address's keywords gained "site address", "outgoing proxy", "other host names", "redirect", "https" and keep "proxy address".
- Architect (2026-11-21): a relabelled field keeps its old label among the search words, so a person searching the name they knew still finds it.

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/screen-composition-rules.md, docs/memory/ui/settings-appearance.md
