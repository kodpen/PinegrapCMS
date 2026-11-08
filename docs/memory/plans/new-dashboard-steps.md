---
name: new-dashboard-steps
description: Steps N1-N14 and the visual pass V of plans/new-dashboard.md: size, screens, order, which run side by side, the files each owns; claim as [plan:new-dashboard] N<n> or V<n>
---

## How are the steps taken?
- Sizes: S about 300, M about 600, L about 1,000 product lines. Claim with a draft `[plan:new-dashboard] N<n> — <topic>`. Read the step's screens (`new-dashboard-visuals.md`) and the prototype's part first.
- Each step owns its page part (`page_<part>.php`), its script file, its stylesheet section and its card files; the shared lists (bundle, card list) get one appended line.
- Each UI step is tried in a browser at 1280 px and 390 px, light and dark; its PR says what was tried.
- Every chart, ring, bar and sparkline shows its figure and label on hover and on keyboard focus (owner, 2026-11-01); a card step adds this to its own charts.
- Until V, a step keeps its look plain: features first, the look is matched in V.

## Which steps?
- Done: N1 #4692, N2 #4766, N3 #4756, N4 #4768, N5 #4856, N6 #4888, N7 #4861, N8 #4865, N9 #4881, N9b #5031, N10a #4973, N10b #4895, N10c #5017, N11a #4994, N11b #5013, N11c #5080, N12a #4981, N12b #5001, N13 #4975, N14 #6145, V0 #5054, V1 #6166, V2 #6263, N10d #6464, V3 #8222, V4 #8241, V5 #8315.

- V (visual pass, owner 2026-11-01). One step per screen group, M each.
  - V4-V6 are open to any worker, one per claim, in order; each V PR asks the owner through an approval issue (below).
  - Every V step: the screen opened in the sandbox beside its picture, every difference in colour, spacing, type, radius, shadow and icon fixed. The System Status gauge (SVG and animation) stays as it is.
  - V3 (M) pictures 05-11: edit, gallery, share and top options.
  - V4 (M) pictures 12-14: backdrops, compact and lights.
  - V5 (M) pictures 16-19: shop and other cards.
  - V6 (M) picture 20: phone.
  - Where V6 starts: picture 20, the phone layout (`ui/dashboard-v2-width.md`); the cards are V5's (`ui/dashboard-v2-cards-look.md`).
  - Every picture belongs to one V step. A part the sandbox has no data for is drawn from the picture's texts by its own renderer and compared too; a difference kept on purpose is listed on the step's comparison page.
  - Each V PR carries before/after/picture shots and asks the owner through an approval issue (`workflow/architect-asking.md`).
  - After V6: topic files under `ui/`, the decisions moved there, this plan deleted.
