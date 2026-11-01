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
  - Migration step for the decided storage, rerun twice on a dev DB; the sign-in upsert; `dashboard_switch`; the switch row on the old page (its only change) and the new one; the branch in `welcome.php`; `page.php` with part files, the greeting line and an empty grid; the card list `cards.php` with all 30 entries (built ones marked); empty `dashboard_v2.js` and stylesheet sections.

- N2 (M) Greeting card. Screen 01. done (#4766).
  - Sign-in chips per preset, the shortcut pool with gates, four tiles, `figures.php` with the eleven figures and their tests.

- N3 (M) System Status card and look. Screens 01, 12-14. done (#4756).
  - The current gauge moved out of `widget_2.php` into one function both call (owner, `new-dashboard-top.md`), check chips, Details; card styles, backdrops, compact, lights off, light and dark.

- N4 (L) Card frame and data. Screens 03, 17. done (#4768).
  - Grid and widths, card header (icon, title, chip, badge, period pill, "…"), `dashboard_cards` with batch and solo, the card contract, refresh, empty and error states; `visitors` (SVG line with compare) and `engagement`.

- N5 (L) Edit mode. Screens 05-08. `free` after N2, N3 and N4.
  - Edit bar, `presets.php`, cancel, save, `dashboard_layout_save` and its validation, the leave prompt; greeting and status edit parts; grip and arrow keys with announcements, drag with the touch hold, width, order, remove; card settings.

- N6 (M) Gallery and side panel. Screens 06, 09, 11. `free` after N5.
  - Gallery (search, categories, sketches, tags, add and remove), the Add card tile, top-section switches, the Look section.

- N7 (M) Visitor and content cards. Screens 03, 04, 18, 19. done (#4861).
  - `gsearch`, `ga`, `content`, `comments`, `files`, `activity`; `pg_dash_cards_content.js`.

- N8 (M) Security and team cards. Screens 04, 18. `free` after N4.
  - `firewall`, `team`, `mytasks` (tick through `ws_task_status`), `waiting`, `week`, `calendars`.

- N9 (M) Shop cards. Screens 16-18. `free` after N4.
  - `shop`, `salesmap`, `ops`, `orders`, `offers`, `fx`.

- N10 (M) Pipeline and queue. Screens 02, 03. `free` after N4.
  - `pipeline` per preset with sparklines, flags and filtered links; `queue` sources and actions; `visitmap` as question 2 decided.

- N11 (M) Assistant and flows. Screens 01, 02, 15. `free` after N4.
  - `ai` connected and not connected, proposals, giving a job, the daily summary as question 3 decided; `flows` tiles, the current run, last runs with Approve and Retry through the flow endpoints.

- N12 (M) Goals, pins, notes, performance, subscriptions. Screens 01, 04, 18, 19. `free` after N5.
  - `goals` editor and rings, `pins`, `notes`, `perf`, `subs` (hidden until the vendor licence answers).

- N13 (S) Share with the team. Screen 10. `free` after N6.
  - `dashboard_role_default`, the section for role < 2, the lock in read and save, the chip.

- N14 (M) Phone, keyboard and close-out. Screen 20. After N2-N13.
  - Phone pass (390x844, 844x390, touch), keyboard and screen reader, reduced motion, forced colours, every screen in light and dark; topic files under `ui/`, the decisions moved there, this plan deleted.
