---
name: calls-hand
description: Raise hand / Lower hand in a Workspace call, the tile's Hand raised and Weak connection marks (getStats), "You are muted" while speaking muted (calls-next CN6, #11385)
---

## How does a raised hand reach the others?
- No schema: two signal kinds to nobody, `hand` and `unhand` (as the share marks, `calls-share.md`), written by the plain signal writer: each member's hand is its own, nobody lowers another's, and no lock is needed.
- `ws_call_hands_from()` (pure): each member's newest hand/unhand mark decides; a hand counts from a member still in the call, for `WS_CALL_HAND_LIFE` (75 s). The browser renews it with the keep-alive (`keepAlive()`: share and hand marks, else a ping).
- The live reads' call part names them: `call.hands` (member ids). `ws_call_hands()` reads the call's hand marks of the last 75 s on `idx_call`: one query more a read, only for a reader in a live call; an idle channel's cost stays (`calls-signalling.md`). The API's call route does not name the hands.
- A hand goes when its member leaves (its signals are deleted) and 75 s after a closed tab's last renewal. Marks count in the member's 300 signals a minute.

## Where are the button and the marks?
- In the call window's tools after Share screen: `pg-icon-button` `bi-hand-index` "Raise hand"; raised `is-active`, `aria-pressed="true"`, `bi-hand-index-fill`, "Lower hand". Shown to a guest too.
- Each tile carries `.ws-call-tile-marks` at its top left: "Hand raised" (`bi-hand-index-fill`, warning colour) and "Weak connection" (`bi-reception-1`, `is-weak`, danger colour), each a `role="img"` span with its label, `hidden` till on. One's own hand mark follows the window's own state, the others' the call part.

## When is a connection weak?
- `weak()` (pure): its `connectionState` is `disconnected` or `failed` (its ICE restart runs, `calls-endurance.md`), or `WEAK_LOSS` (5 %) or more of its incoming packets were lost since the last count. Every `STATS_EVERY` (2 s) each connected connection's `getStats()` inbound-rtp `packetsReceived`/`packetsLost` are summed (`inbound()`, `lossRatio()`). Only another member's tile is marked.

## How is "You are muted" found?
- A muted track gives silence, so one's own voice is measured from a clone of the microphone's track kept on (`selfListen()`, also after a microphone change), sent to no connection and stopped at the end.
- Muted and heard at the speaking level: the window shows "You are muted" with Unmute (an `alert theme-info small ws-call-notice`, as the share notice) until 3 s after the voice drops (`mutedHint()`). The own tile's speaking mark stays off while muted.

## How was it checked?
- Unit tests: `tests/workspace_calls_hand_test.php` (server rules, the pure browser parts under node, wiring, both stylesheets). No browser run with fake devices (Working mode 4).
