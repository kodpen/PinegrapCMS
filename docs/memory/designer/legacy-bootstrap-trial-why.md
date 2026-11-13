---
name: legacy-bootstrap-trial-why
description: Why a Bootstrap 5 trial design keeps Bootstrap 3's script row switched off, and why only a design the card's test calls Bootstrap 3 gets a trial (architect, 2026-11-13, asked in #9159)
---

## Is Bootstrap 3's script row removed from a trial design?
- No (architect, 2026-11-13, asked in #9159). `pg_lbs_trial_assets()` keeps the `bootstrap.js` / `bootstrap.min.js` row right after the `bootstrap-js` bundle row, switched off (`enabled: false`).
- Why: the trial's pages do not load it, and the LB1 trial showed pages work without it, so off is enough. A designer who needs it switches it on in the editor; a deleted row would have to be typed again.
- The card's "Files left out" list names both files: that is what the trial does not load.

## Which designs get a trial?
- Those the card's test (`pg_lbs_design_assets()`) calls Bootstrap 3: a `bootstrap(.min).css` row whose address or banner does not name version 4 or 5. Any other design gets no "Make a trial design" button.
- Why (same decision): the conversion is written for Bootstrap 3's names, and a design that loads Bootstrap 4 or 5 under that file name has nothing for it to do. The card and the trial share one test, so they never disagree.

Related: `legacy-bootstrap-trial-design.md`, `legacy-bootstrap-card.md`.
