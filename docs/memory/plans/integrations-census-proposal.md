---
name: integrations-census-proposal
description: The R&D agent's proposal from the integrations census (2026-11-17): six packages (automation platforms, AI directories, MCP catalogue, connections hub, broker, market table stakes), order, left out
---

## Whose is this?
- The R&D agent's, not a plan and not a decision (`integrations-census.md`). Sizes are guesses: S a step, M a few steps, L a plan of its own.

## 1. Reach through automation platforms (the large figure)
- A platform kit in the API (S to M, no schema): a sample payload for each event, a route that names the account, the OpenAPI document with its `oauth2` block. To decide by the architect: may an OAuth connection hold its own webhook subscriptions, and may the panel make one (today by API only), so that a pasted catch-hook address works with no app at all.
- An n8n community node (M): an npm package; a self-hosted n8n installs it at once, the verified list takes up to four weeks.
- A Zapier app (M): private first (up to 100 users), then the public listing. Then Make and Pabbly Connect on the same triggers and actions. No payment actions (Zapier's rule).
- They live outside this repository: the owner opens the repositories and developer accounts, as for the Claude bundle.

## 2. AI assistants' directories (beside `claude-plugin-bundle.md`)
- A "Connect to Claude" button in the panel's connection guide from the install link (S).
- An entry in the official MCP Registry with a host variable (S, the owner's namespace).
- A connector listing with a URL pattern in Claude's directory: to weigh by the architect beside the bundle; whether any customer domain passes is to ask Anthropic.

## 3. More behind one connector in the Workspace (S each, tried first)
- Rows of `ws_mcp_catalogue()` for servers that carry a catalogue (Zapier MCP first) and for the business services of Claude's directory that sign in by OAuth.

## 4. The connections hub and Site Settings (L)
- One list of integrations in code: name, group, where its fields are, how its state is read, what uses it. The count it gives is the native figure.
- One "Connections" place drawn from it: search and groups, what is connected first, an attention block; a drawer for each (account, what uses it, last error, Connect, the way to its fields). Two sides: what the site connects to, and what connects to the site (Application Access).
- One meaning for "Connections", "Connected accounts" and "Connected applications". GitHub, AI keys and mail each become one entry. A provider's client is typed in one place.
- Kept: one place per setting, card addresses and anchors (owner 2026-10-30); done with look A's step for Site Settings (`panel-old-screens.md`).

## 5. One-click Connect: the broker (L, the owner's open decision)
- Model B of `reverse-oauth-leftovers.md` ends "register an application at Google or GitHub and paste its ID and secret", the root of the GitHub case. It waits for his yes to Google's verification for Kodpen.

## 6. The market's table stakes, through services that bring many
- Akakçe and Cimri as product feed formats (S each).
- A partner kit (API documents, a demo site, the product feed) and applications to the marketplace integrators (Entegra, Sopyo, Dopigo, StockMount): their side builds, the owner applies. Dopigo's API is public, so it could also be a connector here (M).
- A Craftgate payment driver (M): the banks' virtual POS and payment firms by one driver; then Param or Sipay.
- Hepsiburada stays as planned; desktop ERPs stay out.

## In which order?
- The S items of 2 and 3 and the kit of 1; then the n8n node and the Zapier app; 4 as a plan of its own beside them; 6's feeds and the partner kit; Craftgate; 5 when the owner decides.

## What is left out?
- Embedded automation (Activepieces Embed, Pipedream Connect): a fee and a hosted dependency.
- Reusing n8n's or Pipedream's catalogues: their licences forbid it. Unified APIs: priced for one cloud.
- A figure of MCP tools; IFTTT (needs a central server); ChatGPT's directory (template addresses for trusted developers only).
