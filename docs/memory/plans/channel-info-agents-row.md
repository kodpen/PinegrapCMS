---
name: channel-info-agents-row
description: Plan channel-info-agents, step CI1: the agents block's markup, what a row's second line says in each state, the facts of an opened row in their order, the head
priority: 5
---

## Which markup? (the names are pinned by the tests)
- `section.ws-agents` > `div.ws-agents-head`: `h3.ws-panel-kicker` "Agents", `span.ws-agents-count` (a plain number, no `.pg-count` chip), `span.ws-agents-sum`, for a manager `button.pg-icon-button.pg-icon-button-sm.ws-agents-manage` (`bi-sliders`; its name and tip "Manage agents").
- `div.ws-agents-rows` > one `div.ws-agents-row.is-<state>` each (`is-open`, `is-assistant`): `button.ws-agents-rowhead` (`aria-expanded`, `aria-controls`), then `dl.ws-agents-facts`, hidden while closed.
- In the row's button, in order: the face (`h.agentFace()`, with `.ws-agent-dot.is-<state>` for working, waiting, late and paused), `span.ws-agents-l1` (`.ws-agents-name`, `.ws-agents-role`), `span.ws-agents-l2` (`em.ws-agents-word`, the second part, `.ws-agents-warn`), `svg.ws-agents-ring` (`is-high` from 90%), the chevron (`bi-chevron-down`; `bi-chevron-up` when open).
- No `.badge`, no `.pg-pill` and no `.pg-button` inside a row; no line between rows.
- Past six rows: the first six and `button.ws-agents-more` "3 more"; pressed, all rows show for as long as the panel is open.
- The styles go into `backend.src.css` beside today's `.ws-act-*`, with the measures of the drawing's `drawing.css` on the panel's own tokens.

## What does line 2 say at rest? (state key of `ws_agent_card_state()`)
- working: "Working" + the note (`request.note`); with no note "for {name}, since 14:32" (`request.asked_by`, `request.since`); with no asker of this channel `request.since` alone.
- waiting: "Waiting" + "2 requests in its queue" (`waiting`; "1 request in its queue").
- late: "Not seen" + `seen_rest` ("last knock 3 h ago", "not knocked yet").
- idle: "Ready" + the model; with no report "no report yet"; when `stale`, after the model " · " and `reported` in `.ws-agents-warn`.
- paused: "Paused" alone; the row at half strength.
- an assistant (Claude, Pinegrap AI, a model agent): "Assistant" + "always ready"; no dot, no ring.
- The word and the second part are joined by " · ". The word is blue for working, amber for waiting and late, plain else. Before the read lands a working or a ready row shows its word alone.

## Which facts does the opened row list, in this order?
- "Doing": the whole note, wrapped. Only with a note.
- "Asked by": the asker's name as a link to the message (`.ws-act-link`, as today), " · ", `request.since`. Only with an asker of this channel.
- "Queue": "2 requests in its queue". Only when `waiting` is over 0.
- "Handle": `@handle`. "Wakes": `wake_fact` ("When mentioned", "On every message", "On {prefix}", "On replies to it").
- "Model": the model. "Context": today's `bar()` and "412k of 1M · 41%", amber from 90%. "Branch": one line, cut, whole in its tip.
- "Seen": `seen` in today's words ("Last knock 7 min ago", "Always ready"), amber when `seen_state` is `late`.
- "Reported": the age ("2 min ago"), amber when `stale`; "Not yet" for an app agent with no report.
- An assistant has only "Handle", "Wakes" and "Seen". A fact with nothing to say is left out; no fact shows a dash.

## Which head?
- The count is every row, the assistants too. `.ws-agents-sum` is the read's `summary.words`, which now counts only working, waiting and not seen ("1 working · 1 waiting"); empty when there are none, and before the read lands.
- "Manage agents" opens Channel settings on its tab "People and agents" (`channelForm()` takes the tab to open on); only for `channel.can_manage`.

## In which order do the rows stand?
- Working, waiting, not seen, ready, paused (`ws_channel_activity_order()`), then the assistants; inside a state today's order. The six shown past six rows are the first six of that order.
