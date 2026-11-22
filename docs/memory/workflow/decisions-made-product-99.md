---
name: decisions-made-product-99
description: Decisions list, product part 99 (2026-11-22 on): the starter sites' three dead Twitter feeds leave both dumps (social links in the footer, two regions emptied); running sites only where untouched
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-98.md`.

## What takes the place of the starter sites' Twitter feeds? (owner, 2026-11-22, in the architect's window, bug #11055)
- Asked: the three Twitter feeds of the starter content load no tweet any more, leave an empty space and make a Twitter error and CSP reports on every page; what comes in their place?
- "Sosyal bağlantılar": in both dumps the footer column `twitter-feed` becomes a widget with the heading "Bizi Takip Edin" / "Follow Us" and a row of social icons, from the markup the starter content already uses for its social lists; no request to an outside service, the footer keeps its four columns.
- `home-firm-twitter-feed` and `home-event-twitter-strip` are emptied: the rows stay, so the layouts keep their region tags and the pages close up.
- Not chosen: "Üçü de boşaltılsın" (the footer would show three columns and an empty quarter); "Olduğu gibi kalsın".
- This is an exception to "the dumps' content is not touched" (`decisions-made-data.md`), for these three regions and what only they use (the `twitterfetcher.min.js` row of the home sets, the Twitter start in the theme's `scripts.js`).

## What happens to the three regions on running sites? (owner, 2026-11-22, same question)
- "Dokunulmamışsa değişsin": an idempotent data step of the open version gives a region the new content only where its content is still the stock Twitter markup of one of the two dumps, byte for byte; a region the site's owner edited stays as it is.
- The same rule as the AddThis code (`platform/starter-site-defaults.md`, #3567).
- Not chosen: "Yalnızca yeni siteler" (running sites would keep the empty feeds and the errors until their owner removed them by hand).
- Why: a feed that cannot load is a fault on the page, not content the owner chose; the byte-for-byte match keeps whatever a person wrote.
