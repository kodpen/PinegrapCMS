---
name: integrations-census-connectors
description: Census (2026-11-17) of AI assistants' connector directories for a site's own MCP server: Claude's URL pattern and install link, the MCP Registry, the ecosystem's size, what may be claimed
---

## Where was it read?
- On Anthropic's, OpenAI's, Microsoft's, Google's, Mistral's and the MCP project's own pages on 2026-11-17. "Own count" marks a count taken from what a page or an API served.

## Can a directory list a server whose address is each customer's own?
- Claude: on paper, yes. The submission's Connection step offers "Universal URL", "Multiple URLs" or "URL pattern", an anchored regular expression: "Each user enters their own URL when they connect". Such a listing "can be published only once it has the **Verified** label, and its review takes longer"; Verified is given by Anthropic, not applied for.
- The documented example is a vendor's subdomain; whether a pattern for any customer domain passes is not stated. In the directory 59 remote listings show no connector URL (own count), self-hosted products among them: n8n, Metabase, YouTrack, Sourcegraph.
- With a pattern Claude is known by a client-ID metadata document or registers itself; a site's OAuth server gives both (`integrations-census-machinery.md`).
- It is a second route beside the bundle of `claude-plugin-bundle.md`, whose reason was that the directory lists one address.
- ChatGPT: a template URL only "for trusted developers with whom we have an established relationship"; otherwise the person adds a custom MCP server. Its portal asks for a token at `/.well-known/openai-apps-challenge` on the MCP host.
- Microsoft Copilot certifies an MCP server as a Power Platform connector whose publisher owns the endpoint; a host for each connection is unverified there. Gemini and Mistral publish no way in: the person adds a server by its address.
- The official MCP Registry: yes. An entry's URL may hold variables ("enables multi-tenant deployments"); 169 entries use one for the whole host, WordPress plugins and a shop platform among them (own count). Publishing proves a namespace by a DNS record or a GitHub login. It "doesn't surface your server in Claude".

## What works with no listing?
- An install link fills Claude's dialog: `https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=NAME&connectorUrl=ENCODED_URL`. The panel knows the site's address, so a button can carry it.

## How large is the MCP ecosystem, and what may be claimed?
- Glama states "122,052 servers" (47,918 of them remote), mcp.so "20,650 MCP servers"; the official registry holds 65,597 names (own count); Claude's directory prints "1135 connectors".
- Directories count servers, not tools; nothing is reviewed; five publishers hold 13% of the registry; many servers need a client registered by hand.
- So "N,000+ tools through MCP" does not hold. What holds: the connectors one has tried, and the open door. Mistral words it so: "more than 60 integrations", and a custom connector for the rest.
- One server can bring a catalogue of its own: Zapier MCP, "30,000+ actions across 9,000+ apps via the MCP standard", included in every Zapier plan, two tasks for each tool call. Whether the Workspace's MCP card signs in to it is untried.

## How does Claude show connectors to a person?
- "Customize > Connectors": a directory with search and categories, a card (logo, name, verified mark, one line), a detail page (made by, sign-in, documentation, privacy policy, tools).
- "Connect", then the service's own sign-in; "Reconnect" when access is lost; "Disconnect" and "Remove". Tool permissions for a group or a tool: "Always allow", "Needs approval", "Blocked". A switch for each connector in a conversation.
- "Add custom connector": a name, the server's URL, the sign-in kind. An organization's owners add connectors for everybody and can restrict tools.
