---
name: new-dashboard-steps
description: Steps N1-N14 and the visual pass V of plans/new-dashboard.md: size, screens, order, which run side by side, the files each owns; claim as [plan:new-dashboard] N<n> or V<n>
---

## How are the steps taken?
- Sizes: S about 300, M about 600, L about 1,000 product lines. Claim with a draft `[plan:new-dashboard] N<n> — <topic>`. Read the step's screens (`new-dashboard-visuals.md`) and the prototype's part first.
- Side by side: N2, N3 and N4 after N1; N7 to N11 after N4. Each owns its page part (`page_<part>.php`), its script file, its stylesheet section and its card files; the shared lists (bundle, card list) get one appended line.
- Each UI step is tried in a browser at 1280 px and 390 px, light and dark; its PR says what was tried.
- Every chart, ring, bar and sparkline shows its figure and label on hover and on keyboard focus (owner, 2026-11-01); a card step adds this to its own charts.
- Until V, a step keeps its look plain: features first, the look is matched in V.

## Which steps?
- Done: N1 switch and shell (#4692), N2 greeting (#4766), N3 System Status (#4756), N4 card frame (#4768), N5 edit mode (#4856), N6 gallery and side panel (#4888), N7 visitor and content cards (#4861), N8 security and team cards (#4865), N9 shop cards (#4881), N10a pipeline (#4973), N10b queue (#4895), N10c queue shop sources (#5017), N11a flows (#4994), N11b assistant (#5013), N12a goals and notes (#4981), N12b pins and perf (#5001), N13 share (#4975).

- N9b (S) `ops` card. Screen 17. `free` after N9.

- N10 Pipeline and queue. Screens 02, 03.
  - N10d (S) `visitmap` as question 2 decided. Held: a raw `visitors` count clashes with the `visitors/` key rules; architect issue #4896.

- N11 Assistant and flows. Screens 01, 02, 15.
  - N11c (S) `ai` daily summary as question 3 decided; storage decided #5014 (`config.ws_claude_summary_*` columns; the summary is Claude's message in a chosen channel). `free`.

- N12 (M) Goals, pins, notes, performance, subscriptions. Screens 01, 04, 18, 19. Cut in three.
  - N12c (S) `subs`, hidden until the vendor licence client answers (`plans/kodpen-vendor-api-client-steps.md`, P8).

- N14 (M) Phone, keyboard and close-out. Screen 20. After N2-N13.
  - Phone pass (390x844, 844x390, touch), keyboard and screen reader, reduced motion, forced colours, every screen in light and dark.

- V (visual pass, owner 2026-11-01). After N14. One step per screen group, M each.
  - V0 (S): hover and focus figures for the charts N4-N8 drew (`pg_dash.js` helpers). done (#5054); `ui/dashboard-v2-chart-tips.md`.
  - V1 01 top, V2 03/04 cards, V3 05-09 edit and gallery, V4 12-14 looks, V5 16-19 shop and other cards, V6 20 phone: the screen opened in the sandbox beside its picture, every difference in colour, spacing, type, radius, shadow and icon fixed. The System Status gauge (SVG and animation) stays as it is.
  - Each V PR carries before/after/picture shots and asks the owner through an approval issue (`workflow/architect-asking.md`).
  - After V6: topic files under `ui/`, the decisions moved there, this plan deleted.
