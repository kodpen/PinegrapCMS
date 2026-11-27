---
name: channel-info-agents-block
description: Channel info's one agents block over Info and Activity (#11617): where it stands, a row's two lines in each state, an opened row's facts, the head, six rows and "n more", what the read gives it
---

## Where does the block stand?
- Owner (2026-11-25, agent channel, requests 2951, 2952): one agents part, a little more detailed, the same under both tabs, calm; no badge, pill or button crowding a row. Drawing: `channel-info-agents-drawing.md`.
- With the tabs (`activityTabsShow()`): first in the pane shown. It is one node; `show()` moves it to the other pane, so open rows and "n more" keep their state. Without tabs (assistants only): after People (`PGWsChannelActivity.place(body)`, `drawInfo()`). No row: nothing.
- It replaced Info's "Agents here" and Activity's summary and list; "Project: …" is Activity's first line under it.

## What does a row show at rest?
- `assets/js/workspace_channel_activity.js`: a `button.ws-agents-rowhead` with the face (`.ws-agent-dot` for working, waiting, not seen, paused), line 1 name and role, line 2 the state's word, " · " and one thing (`agentsLine()`), a context ring (amber from 90%), a chevron only while pointed at, focused or open.
- Working: the note, else "for <asker>, since HH:MM", else since. Waiting: "2 requests in its queue". Not seen: `seen_rest`. Ready: the model, "· reported 7 d ago" amber when stale, "no report yet". Paused: the word, dimmed. An assistant (Claude, Pinegrap AI, a model agent): "Assistant · always ready", no dot, no ring.
- Before the read lands: `channelAgents()`, rows word only. After, `agentsRows()` joins by id and the read's agents are the registry rows (added ones show, removed go; assistants stay, #11817).
- Order: working, waiting, not seen, ready, paused, then the assistants. Past six: six and `button.ws-agents-more` "3 more" (`agentsShown()`).

## What does an opened row list?
- `agentsFacts()`, a fact with nothing to say left out: Doing, Asked by (link to the message · since), Queue, Handle, Wakes, Model, Context (bar and "412k of 1M · 41%"; none without a size), "Git branch" ("Branch" is "Şube"; cut, whole in its tip), Seen (amber when not seen), Reported (amber when stale; "Not yet" for an app agent with no report). An assistant: Handle, Wakes, Seen.
- Several rows may be open; Enter or Space toggles; Esc closes the focused open row, the next Esc the panel. The name opens no card here; the agent card stays on messages and the head's faces.

## What does the head say?
- "AI agents" (the bare "Agents" is the estate template's word, `workspace_agent_word_test.php`), a plain count of every row, then the read's `summary.words`, which names only working, waiting and not seen ('' when none), and for `can_manage` one icon button "Manage agents": Channel settings on "People and agents" (`h.channelSettings(channel, 'people')`).
- A changed state is said once in a polite live region ("Mimar: Working").

## What does the read give it, and when does it run?
- `ws_channel_member_agent()`: `wake_fact` ("When mentioned", "On every message", "On {prefix}", "On replies to it") and `seen_rest` ("last knock 3 h ago", "not knocked yet", "always ready").
- A working agent's `request`: asker, message and note only for this channel's request, else "since" (#12035).
- `ws_channel_activity_agent_row()` (pure): each read agent also carries `wake_fact`, `seen`, `seen_state`, `seen_rest`, `waiting`, `active` and `reported_ago`.
- The read runs on either tab while the panel is open in a channel with a registry agent, else only on Activity; today's brakes (15 s on sync, 60 s ticker). A redraw of the panel (`refreshInfo()`) draws from the kept answer; open rows, "n more" and the answer are dropped when the panel closes (`PGWsChannelActivity.forget()` in `closeInfo()`).
- Tests: `workspace_channel_activity_test.php`, `…_js_test.php`, `workspace_channel_info_test.php`.

Related: docs/memory/workspace/channel-activity-tab.md, docs/memory/workspace/channel-members-agents.md, docs/memory/workspace/agent-state-marks.md
