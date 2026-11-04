---
name: decisions-made-product-30
description: Decisions list, product part 30 (2026-11-05 on): a loop in one flow: a Read a row box, Repeat N times under the 5-minute floor, the date words in a derived value box
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason. The three below are one aim: a counter line (read a row, raise it, write it, a round a minute) as one flow; today a loop needs two flows restarting each other through a row start, cut by the chain depth and hard to follow. Steps: `plans/flows-loop-in-one-flow.md`.

## Does a flow read state kept between runs? (#6845)
- Owner (2026-11-04, as recommended; the architect agrees): yes, through a "Read a row" box (`row_get`) beside `row_add` and `row_set`: settings `table_id` and `row` (a row id or one value); outputs the row's cells as `cell_<id>` like the row start box (`ws_flow_row_outputs()`), `row_id` and `found`; read with the owner's view rights (`ws_wtable_for()`); a missing row is done with `found` 0 and empty cells, so a first run can add it. No flow variables: values stored on the flow need a schema change, a screen, a concurrency rule and an API, and hide state a table shows and lets staff correct. Role-3 personal flows read tables in their reach, as the row start box (#3345, applied by the architect). No schema.

## Does a flow repeat part of itself, or run oftener than every 5 minutes? (#6846)
- Owner (2026-11-04, as recommended; the architect agrees): a "Repeat N times" mode of the For each box: a count (1 to 50, a number or one value) instead of a list, `index` 1…N; the End box, the pass rows, the 50-item cap, `WS_FLOW_MAX_BOX_RUNS` and the side-effect cap stay. The 5-minute floor (`WS_SCHEDULED_MIN_MINUTES`) stays and arrows never lead back: a 1-minute schedule is five times the cron's starts, and the runner's walk is built on passes. A faster pace is a Wait box inside the body (a minute a pass; `wake_at` wakes it at any pass). Architect: a derived or constants box may read a body box when every box reading it is in that body (today `bad_reference`); it is worked out at the reader's pass, as `ws_step_run_values()` reads. So the line is Every 5 minutes → Repeat 5 → Read row → values → condition → Write row → Wait 1 minute → End. No schema.

## Do the date words work in a derived value box or in table cells? (#6847)
- Owner (2026-11-04, as recommended; the architect agrees): the derived value box fills `{{date}}` (`Y-m-d`), `{{time}}` (`H:i`) and `{{weekday}}` in `a` and `b` on the typed text, before the run's values go in (a value holding the word is not expanded) and in the site's zone; the other words need the run's context and stay out. Table cells fill nothing: cells are JSON, a date column takes `Y-m-d`, and a value box now carries the date into any cell. A running site's value box that typed `{{date}}` gives the date instead of that text. The hint of #6844 names the three words for the value box again. No schema. Reason: a value box that cannot say today's date forced a Wait box as a clock.
