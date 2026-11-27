---
name: bug-hunt-areas-7
description: The 15 bug hunts of the night of 2026-11-27 to 28 (owner, message 15232): given out by k3 once the work is finished, the fifteen areas named, the order in which the sessions end
---

## What did the owner ask?
- Owner (2026-11-27, agent channel message 15232): "dün geceki kurallar gecerli […] iş bitince koordinatör 15 tur bug hunt. ardından iş bitince zamanlamaları kaldırır snonra sen kaldırırsın en son pr merge ready pr kalmayınca".
- Architect's reading (answer to request 3543; the owner was told to say so if he meant rounds one after the other): 15 hunts, one worker an area, up to 15 workers at once as in `bug-hunt-areas-6.md`. Plan steps and open `bug` issues go out first; a hunt that ends is not replaced.
- This takes the place of "bir kısa tur bug hunt" in his message 14844 of the same day. The limits of `bug-hunt.md` come back when the night has ended.

## How does the night end?
- k3, when all its workers have ended, removes its timers and waiters, says so in the channel naming the merging session and the architect, and ends.
- Then the architect closes its request waiter. The merging session ends last, when no `ready` PR is left.

## Which areas? (message 15234, to k3; scripts in `assets/js/`)
- Designer first:
  - Can change per place: `style_designer_overrides.js`, `includes/fn/component_overrides.php`.
  - Select similar, Find and replace, the two searches: `style_designer_similar.js`, `_findreplace.js`, `_optsearch.js`, `_treesearch.js`.
  - States, interactions, collections, fonts: `style_designer_states.js`, `_interactions.js`, `_collection.js`, `_fonts.js`.
  - The first tour: `style_designer_tour.js`, `pg_designer_tour.js`, `includes/fn/tour.php`.
  - The Options panel after its census steps (plans `designer-options-census-steps-2` and `-3`).
- That night's money paths, each after its plan's open PR has landed:
  - Local payment methods: Stripe's method list, a payment that ends later, BLIK (LP1 to LP3).
  - Automatic tax (TX1 to TX3): `includes/fn/tax_eu_rates.php`.
  - Markets, currencies and price lists (MK1 to MK3): `includes/fn/store_currencies.php`, `currency_rates.php`, `order_currency.php`.
  - Accounting sync: `includes/fn/accounting_sync.php`, `includes/connect/providers/xero.php`, Xero's driver once AS4 has landed.
  - Sales channels (SC1 to SC5).
- Landed in the days before, read by no hunt:
  - Imports from site builders (IB1 to IB3): `includes/import/`.
  - Artifact Canvas content steps and the design file (AC1 to AC13).
  - The sheet editor: `includes/fn/office_sheets.php`, `office_edit.php`.
  - The Workspace's modernised Calendars, Notes and Data screens.
  - Site from brief: `includes/fn/site_brief.php` (SB1 to SB4).

## What is left alone?
- `style_designer.src.js` and the files of open PRs: a fault there is a `bug` issue.
- The first four designer areas were named in `bug-hunt-areas-6.md` too and no hunt had read them.
- What each hunt found goes into its own `bug-hunt-areas-<area>.md`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-6.md
