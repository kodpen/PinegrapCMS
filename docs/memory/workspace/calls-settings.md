---
name: calls-settings
description: The Calls card of Workspace settings (CL6, #PRNUM): off until turned on, STUN addresses, the TURN relay with its sealed secret, member-only time-limited credentials, the browser's connection test
---

## Where is the card and who changes it?
- Site Settings › Channels (`workspace_channels`), card `pgset-ws-calls` "Calls", after Link previews; saved with the pane's Save (`ws_calls_settings_save()` from `workspace_channels.save.php`).
- The switch and the STUN addresses: anyone with the Workspace settings right (`ws_calls_settings_allowed()`). The relay (TURN) part: an administrator only (`ws_calls_turn_allowed()`, role 0, as other services' keys); for others it is drawn disabled and a posted value is ignored.

## Where are the values kept?
- No schema: config_text `ws_calls` holds JSON `on`, `stun` (list), `turn_url` (list), `turn_user`; `ws_calls_turn_secret` holds the relay's shared secret sealed `<ciphertext>:<iv>` (`encrypt_string_with_iv()`), listed in `pg_reseal_registry()`.
- Nothing stored reads as off: an upgraded site, and a new one too (no install seeds the row), has no call buttons until the switch is turned on.

## What does off do?
- `ws_calls_on()` false: the screen config has no `calls` part, so `PGWsCalls` draws no call button; `ws_call_start()` and `ws_call_join()` refuse with "Calls are turned off in the Workspace settings." before any other check (the guest page's join too).

## Which STUN and TURN addresses are taken?
- `ws_call_ice_lines()`: one a line (comma or space also split), `stun:`/`stuns:` or `turn:`/`turns:` host or [IPv6], optional port 1-65535 and `?transport=udp|tcp`; five at most each. A bad line refuses the card's save and marks its box; nothing of the card is written.
- Empty STUN: `WS_CALL_DEFAULT_STUN` (`stun:stun.l.google.com:19302`), named on the card as Google's service the browsers reach.
- A relay address without a stored or typed secret is refused ("A relay needs its secret.").

## Who gets the relay's credentials?
- Only a call's member: the answer of a granted `ws_call_start` / `ws_call_join` (panel) and `call_join` (guest page) carries `ice` from `ws_call_member_ice()`; the browser uses it for its connections (`mine.ice` in `workspace_calls.js`).
- The screen config's `calls.ice` (every Workspace reader) is STUN only (`ws_call_ice_servers()`).
- TURN REST scheme: username `<now + WS_CALL_TURN_LIFE>:<user or "pinegrap">`, credential base64(HMAC-SHA1(secret, username)); `WS_CALL_TURN_LIFE` 6 hours. Fits coturn's `use-auth-secret` / `static-auth-secret`.

## Is the secret ever shown?
- No. The box is drawn empty with "A secret is stored. Type a new one to replace it."; empty keeps it, "Remove the stored secret" clears it. No API or MCP route, Site Log line or audit row holds it: the audit `after` says `turn_secret` kept, removed or replaced.

## What does "Test the connection" do?
- In the browser: action `ws_calls_test_ice` (settings right; relay credentials only for an administrator, from the typed secret or the stored one) answers the ICE servers of what the boxes hold, unsaved; the page gathers candidates for up to 8 s and says whether STUN gave a public address (srflx) and the relay a relayed one.

## What could not be checked?
- No real TURN server or two browsers in the sandbox; unit tests: `tests/workspace_calls_settings_test.php`.
