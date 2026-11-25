---
name: channel-info-agents
description: Plan (owner 2026-11-25): Channel info's two agent lists become one calm block, the same under both tabs, a row opening for its detail; one step, CI1, built board by board from the drawing
priority: 5
---

## What is asked?
- Owner (2026-11-25, agent channel, request 2951): the two tabs tell different things about the same agents; one agents part, a little more detailed, common to both.
- Owner (2026-11-25, request 2952): modern and calm; texts, buttons and badges must not crowd a row.
- Owner (2026-11-25, agent channel message 13505, on the second drawing): "çizimler bu sefer olmuş geliştirmeye gönderelim detaylı aciklamazsan işçiler çizime eşlemeyi atlayabilir o yüzden plan detaylı olsun". The drawing is accepted; the step is given out.

## Which files does the step read first?
- The drawing: `workspace/channel-info-agents-drawing.md` and beside it `channel-info-agents-drawing/agents.html` with both pictures. Open the sheet in a browser: its markup and `drawing.css` hold every measure. Where a line of this plan differs from the drawing, the drawing wins (`workflow/screen-follows-drawing.md`).
- `plans/channel-info-agents-parts.md`: every part both tabs have today and its new place.
- `plans/channel-info-agents-row.md`: the block's markup, a row at rest in each state, the opened row, the texts.
- `plans/channel-info-agents-true.md`: what the read gives, what must be true (each line a test), what is looked at before the PR is ready.

## What is there today?
- Info: `drawInfo()` in `assets/js/workspace.js` draws "Agents here · n" with `agentInfoRow()` over `channelAgents(channel)`: Claude and Pinegrap AI, then `channel.agents` (`ws_channel_member_agents()`).
- Activity: `drawSummary()` and `drawAgents()` in `assets/js/workspace_channel_activity.js` over `ws_channel_activity()`.
- The tabs show in a team reader's channel with a registry agent or a repository link (`activityTabsShow()`); Info's pane is the panel's body, Activity's pane a second body after it.

## Which step?
- CI1 (M, one PR, one worker; in work): the block on both tabs, the read's new fields, the old lists and their styles taken out, the tests. No schema, no setting, no new route.
- Order inside the step: the tests of `channel-info-agents-true.md` red first; the read's fields; the block's pure parts; the drawing of the block board by board (B1, S1, O1, M1, Z1); the old lists out; then the look in the sandbox.
- The PR body carries a table: each board of the drawing, what was built for it, what differs and why. A board with no line there is not built.
- A part of the screen none of the plan's files places is asked of the architect, never left where it was (`workflow/screen-description-checks.md`).
- Claim as `[plan:channel-info-agents] CI1 — one agents block for both tabs`.

## Which keys, what is read out?
- Tab reaches each row's button, "n more" and the manager's button; in an open row then the "Asked by" link. Enter and Space open and close. Esc closes the open row that holds the focus, the next Esc the panel.
- The ring is `role="img"`, named by its tip's words. A state that changes while the panel is open is said once in a polite live region as the name and the state's word ("Mimar: Working"); board Z1's sentence is an example of it.

## What closes the plan?
- The step's PR corrects `workspace/channel-activity-tab.md`, `workspace/channel-members-agents.md` (its "Manage" under the list is not in the code any more) and `workspace/workspace-channel-panels.md` to the block, moves what must stay from the three plan files beside this one into them, and deletes the four plan files; the drawing and its memory stay.
- After it lands the architect asks the test coordinator to open Channel info on dev at 1440 and 390 px, in both themes, against boards B1, S1, O1 and M1.
