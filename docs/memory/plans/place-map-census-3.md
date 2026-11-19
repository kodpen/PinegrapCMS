---
name: place-map-census-3
description: Census MP1 of place-map, part 3: how the cookie notice lists a service (pg_consent_page_services(), pg_consent_catalogue()), which class the map could go under, and the questions for the architect
---

## Which classes does the cookie notice have?
- Two optional ones, `analytics` (shown as statistics) and `marketing` (`pg_consent_category_letters()`, `includes/fn/consent.php`), plus `necessary`. The `pg_consent` cookie holds only those (`1.a1.m0`) and `pg_consent.js` `start()` accepts only those two names. A third class would change the cookie's format and the script.
- An optional class with no row on the page is not shown at all.

## How is a service listed?
- Two ways. A held tag in the page: `<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="…">`, found by `pg_consent_page_services($content)` (comments dropped, the class lowercased, each name once) and started in place by `pg_consent.js` when its class is allowed.
- Or a row the software writes in `pg_consent_catalogue($content)`: `array(label, is cookie name, purpose)`, as the CAPTCHA provider is listed (`necessary`, label from its definition, false for "not a cookie"). The software's own held tags carry `data-pg-consent-builtin` and are listed this way, never by the page scan.
- The map sets no cookie: what the notice must say is that the visitor's address and the page reach the tile host. A catalogue row, added when `$content` holds `data-pg-map` and the switch is on, fits that; its label would be the tile host's name from the setting.
- When the class is allowed, `pg_consent.js` fires `pg:consent` with the choices, so `pg_map.js` can draw the map then without a reload. An element with `data-pg-cc-open` (or a link to `#cookie-settings`) opens the notice: "Show the map" (X9) can be one.

## Which class would the map go under?
- Not statistics: it measures nothing. Marketing is the class that already stands for third parties a visitor may refuse; an embedded map has no class of its own in this notice. Proposal: `marketing`, purpose line "Shows the map; the map service receives your address on the internet and the page you are on." The class is the architect's.

## Questions for the architect
- Q1. Which providers may "Find from the address" use? The census says: Photon yes; Google never (30 days, and no non-Google map); Mapbox only with `permanent=true` and only if the site's tiles are Mapbox's. Proposal: Photon only in MP2, the button not drawn for a Google or Mapbox account, with a line saying why.
- Q2. The class of the map in the notice: `marketing` (proposal) or something else?
- Q3. The header change: none with the built-in policy. Proposal for MP4: X14 reads "the headers are unchanged"; Firewall settings show a copy line (like the CAPTCHA's) when the site's own policy lacks the tile host. Is that the shape?
- Q4. For the owner's tile question: OSM's tiles allow a site's map with the credit shown, but no promise and possible withdrawal for heavy commercial use. Does that keep the proposal (OSM by default, a provider's key as the option)?
