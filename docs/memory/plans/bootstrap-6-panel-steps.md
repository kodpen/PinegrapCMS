---
name: bootstrap-6-panel-steps
description: Steps B0-B12 of plans/bootstrap-6-panel.md with dependencies and status (each done step names its PR); claim as [plan:bootstrap-6-panel] B<n>
---

## Which steps run before the switch (on 5.3.8, nothing visible changes)?
- Done: B0a, B0b, B1a, B1b, B2a, B2b, B3, B4, B5, B6, B7, B8 (`bootstrap-6-panel-done.md`).

## What happens after the switch?
- B8, the switch, is done (#1866, `bootstrap-6-panel-done.md`); B9 can start.
- B9 Component swaps on v6, one claim each (`bootstrap-6-components.md`): B9a Chips (done, #2208), B9b Combobox (done, #2243), B9c Datepicker and time (done, #2262), B9e Range (done, #2207), B9f form-adorn, B9g Stepper (done, #2206), B9h hover-lift (done, #2217, #2237), B9i Strength, B9j Avatar (done, #2216, #2242, #2256, #2339), B9k research proposals (slice 1 done, #2273; `.btn-ghost` slices done #2373, #2479, #2505, #2552; focus ring tokens done #PR; `workspace_flow.js`'s ghosts go with the flows work, the editor toast and dropping the `.btn-ghost` CSS follow, the rest waits for B11: `ui/bootstrap-6-own-widget-swaps.md`).
- B9i: done — the panel meter (#2211) and the installer meter (#2274), `ui/password-strength-js.md`.
- B10 Token tuning, B11 redesign, B12 jQuery UI out of the panel: `bootstrap-6-redesign.md`.

## Where do I start?
- After the claim check, take the lowest open step whose dependencies are done and that no open PR holds; independent steps run in parallel sessions, one draft PR each; a step over about 1,000 product lines goes in several PRs, one after the other (`workflow/working-mode.md`). B1-B5 are independent; B6 needs B2a; B7 needs B1a and B2a; B8 needs B1a-B7; B9 needs B8 (B9c also B5); B10 needs B9; B11 needs B10; B12 needs B9c.
- Pre-switch PRs show their screens unchanged on the B1b screenshots (`ui/panel-screenshots.md`); later PRs show before and after. Mark a step done here with its PR numbers; once all its parts are done, its line moves to `bootstrap-6-panel-done.md`.
