---
name: agent-wakes-trial
description: The live trial of an app agent's scheduled wakes on dev (2026-11-10): /wake, the general job's wake requests, what was not tried
---

## What did the live trial show? (dev, 2026-11-10)
- `/wake @mimar` said the schedule to the writer only. `/wake @mimar every 1m <note>` in channel 25 set the schedule and left a system line; the general job (a tick a minute) made a wake request within 30 s of each of two due times, shaped as above. `off` cleared the three columns; the next tick made nothing. `/uyandır @mimar saat 00:30 <note>` made one request (kind `at`) 49 s after 00:30 and cleared itself.
- A wake shows nowhere in the channel until the agent writes.
- A wake's brief and `GET agent-wake` read with the agent's key: `agent-key-trial.md`; `POST agent-wake` and a text answer to a wake: `agent-key-trial-2.md`. Not tried: a knock from a wake, an agent in several channels, the card's schedule field.
