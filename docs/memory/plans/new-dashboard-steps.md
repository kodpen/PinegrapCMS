---
name: new-dashboard-steps
description: Steps N1-N14 of plans/new-dashboard.md: size, screens, order, which run side by side, the files each owns; claim as [plan:new-dashboard] N<n>
---

## How are the steps taken?
- Sizes: S about 300, M about 600, L about 1,000 product lines. Claim with a draft `[plan:new-dashboard] N<n> — <topic>`. Read the step's screens (`new-dashboard-visuals.md`) and the prototype's part first.
- Side by side: N2, N3 and N4 after N1; N7 to N11 after N4. Each owns its page part (`page_<part>.php`), its script file, its stylesheet section and its card files; the shared lists (bundle, card list) get one appended line.
- Each UI step is tried in a browser at 1280 px and 390 px, light and dark; its PR says what was tried.

## Which steps?
- N1 (M) Switch and page shell. Screens 21, 22. done (#4692).

- N2 (M) Greeting card. Screen 01. done (#4766).

- N3 (M) System Status card and look. Screens 01, 12-14. done (#4756).

- N4 (L) Card frame and data. Screens 03, 17. done (#4768).

- N5 (L) Edit mode. Screens 05-08. done (#4856).

- N6 (M) Gallery and side panel. Screens 06, 09, 11. done (#4888).
  - Gallery (search, categories, sketches, tags, add and remove), the Add card tile, top-section switches, the Look section.

- N7 (M) Visitor and content cards. Screens 03, 04, 18, 19. done (#4861).
  - `gsearch`, `ga`, `content`, `comments`, `files`, `activity`; `pg_dash_cards_content.js`.

- N8 (M) Security and team cards. Screens 04, 18. done (#4865).
  - `firewall`, `team`, `mytasks` (tick through `ws_task_status`), `waiting`, `week`, `calendars`.

- N9 (M) Shop cards. Screens 16-18. `free` after N4.
  - `shop`, `salesmap`, `ops`, `orders`, `offers`, `fx`.

- N10 Pipeline and queue. Screens 02, 03. Cut in four, each `free` after N4 unless said.
  - N10a (M) `pipeline` per preset with sparklines, flags and filtered links. `free`.
  - N10b (S) `queue`: rows, tags, tones, actions; sources forms (24 h), comments waiting, critical SEO, accessibility, overdue tasks, approvals; renderer file `pg_dash_cards_work.js`. In #4895.
  - N10c (S) `queue` sources of the shop and accounting (unshipped orders, low stock, refund requests, overdue invoices) and drafts ready, through N9's Operations Desk helpers. `free` after N9.
  - N10d (S) `visitmap` as question 2 decided. Held: a raw `visitors` count clashes with the `visitors/` key rules; architect issue #4896.

- N11 (M) Assistant and flows. Screens 01, 02, 15. `free` after N4.
  - `ai` connected and not connected, proposals, giving a job, the daily summary as question 3 decided; `flows` tiles, the current run, last runs with Approve and Retry through the flow endpoints.

- N12 (M) Goals, pins, notes, performance, subscriptions. Screens 01, 04, 18, 19. `free` after N5.
  - `goals` editor and rings, `pins`, `notes`, `perf`, `subs` (hidden until the vendor licence answers).

- N13 (S) Share with the team. Screen 10. `free` after N6.
  - `dashboard_role_default`, the section for role < 2, the lock in read and save, the chip.

- N14 (M) Phone, keyboard and close-out. Screen 20. After N2-N13.
  - Phone pass (390x844, 844x390, touch), keyboard and screen reader, reduced motion, forced colours, every screen in light and dark; topic files under `ui/`, the decisions moved there, this plan deleted.

- N15 (L) Drawings round. Screens 01-20. After N14.
  - Owner (2026-11-02): the cards are built on the panel's theme first; once that is done, one round turns them into the drawings: the prototype's colours, card faces, type and spacing (its CSS), each card's content as drawn, and chart values shown on hover and keyboard focus (as text too, like the old dashboard's figures). The page stays `container-fluid` like the rest of the panel: a wider screen gets more columns, not wider cards.
