---
name: app-access-redraw-features-2
description: Plan app-access-redraw, features U9 to U14: a guide per way to connect, the key as one token, the Agent tab (agent and channel rows), the edit drawer's tabs, the link with Connections
---

## How does a person learn to connect?
- (U9) A registry of guides (`api_app_guides()`, texts built from the site's own addresses, each value with Copy), shown in step 3 and on the drawer's Connect tab. Each opens with who the connection acts as:
  - Key and secret: the site address, HTTP Basic, one curl line that reads `/.well-known/pinegrap.json`, the link to the API's document.
  - One token: for a client that asks for an address and a single token (U10).
  - MCP with sign-in: the MCP address (and the compact one once `plans/ai-guide.md` AG7 has landed), the Connect to Claude button, the steps of a client that asks for an address. It says: "This acts as the person who signs in. For an agent that answers as itself in a channel, use Agent in a channel."
  - Mobile app and team devices: where a device is paired, what the staff member signs in with.
  - Agent in a channel: the two environment variables' names, the start text, how the agent waits.
- (U10) A key as one token: `Authorization: Bearer <token>`, the token made of a prefix of its own, the key and the secret, taken wherever HTTP Basic is taken (the API and the MCP address) with the same rate limit, log line, grace day and firewall count. Shown once, beside the key and the secret. Never read from the query string.

## How is an agent set up from the application?
- (U11) The drawer of an integration gets an Agent tab (Workspace on, for who may add an agent): "Make this application an agent" with handle, name, role and door; saved through the Agents card's check and write. Once it is an agent the tab shows its state, its fields and the start text with Copy.
- (U12) "Channels" on that tab: a searchable list of the channels the editor may manage; a tick adds the agent's row with the default wake rule (its @handle), an untick removes it. Each row links to the channel's own agent settings for the rest.
- (U13) The starting point "Agent in a channel" does it in one go: the application, then the agent, then the channel rows, each by its own function. When a later part is refused the earlier ones stay and the sheet says which part failed and where to finish it. Step 3 then shows key, secret, token and start text together as numbered steps.

## What does the edit drawer hold?
- (U14) Wider (`min(760px, 100%)`), its head the application's icon, name, kind and "Acts as" line. Tabs: Overview (state, last use, errors in 24 hours, the owner), Connect (U9), Permissions (U6), Agent (U11), Events, Devices, Logs. New secret and Delete sit at the foot of Overview under "Danger". A locked row shows every tab read-only.
- Connections shows one row "Applications that reach this site" with their count and the errors of the day, leading here; this screen's head links Connections.
