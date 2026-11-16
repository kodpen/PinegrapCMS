---
name: agent-card-behaviour
description: The agent card in a channel when its facts cannot be read or the list scrolls (#9730): the panel route of ws_agent_card, the refusal in place of Loading…, the drawing guard, which scroll closes it
---
## Why did the card write "Loading…" and close by itself? (#9730)
- `ws_agent_card` was in `ws_channel_agents_actions()` but not in the panel's action table (`pg_panel_actions()`, `includes/panel/actions.php`): every load was answered "Invalid action." and the failed load closed the card. Seen in a browser on a sandbox; `test_workspace_agent_card_action` now checks every action of the module list is routed to `pg_panel_ws`.
- A new `ws_*` action of a module's own list goes into that table too, or the panel answers "Invalid action." before the module sees it.

## What does the card do when its facts cannot be read?
- It stays open with the name, handle and role; the refusal's words replace "Loading…" in the same quiet text (`agentCardRefused()`). An answer without a card counts as a refusal and is not kept.
- The drawing runs in `agentCardGuard()`: an exception goes to the console, the card is drawn again from the member row and writes the panel's generic error in the quiet text.

## Which scroll closes it?
- Only the reader's own scroll of the list holding the anchor: a wheel or touch within 800 ms, a scroll key outside a field, or a held pointer (a dragged scrollbar). `agentCardScrollCloses()` and `agentCardScrollKey()` are pure (`tests/workspace_agent_card_js_test.php`).
- A scroll the screen makes for a new message moves the card along with its anchor, never from under the pointer reading it (leaving the card would close it).
- Mention puts `@handle ` into the composer (typed text wakes it too). Look: owner's layout of channel decision 3796; pictures `Approval: channel`.

## Why does leaving not close a card opened by a tap? (#9846)
- A tap brings mouse events with it; when the layout then moved the anchor (a message's tool bar opening under it) the card got a mouseleave and closed 250 ms later. `cardHook()` remembers whether the anchor's last pointer was a finger and then leaves the card open: it closes on a tap outside, Escape or the reader's scroll. Seen on the message chip's card in a 390 px browser (`message-reference-tag.md`).
