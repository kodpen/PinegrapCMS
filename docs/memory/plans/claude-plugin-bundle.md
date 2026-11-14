---
name: claude-plugin-bundle
description: Plan (owner 2026-11-14): a Pinegrap plugin bundle for Claude's directory: a site's MCP server connected by its address, skills for common site work, the listing; the repository is the owner's to open
---

## What is asked?
- Owner (2026-11-14), on Anthropic's directory of plugins and connectors (`claude.com/docs/directory/publish`): "pinegrap için bir araç paylaşabiliyor muyuz claude içinde kullanıcılar hızlıca entegre olabilsin diye?", then, on the plugin bundle: "yapalım." Record: `workflow/decisions-made-product-51.md`.
- A bundle, not a connector: the directory lists one remote MCP server by its address, and every Pinegrap site has its own (`site.com/mcp.php`, `api/api-mcp-server.md`). A hub server of Kodpen's that leads to a person's site would be a connector; it is not asked.

## What is there today?
- Each site is an MCP server over Streamable HTTP: every API route its caller's scopes cover is a tool with a title, `readOnlyHint` and `destructiveHint` (`includes/mcp/tools.php`); OAuth with open registration that real clients pass (`api/api-oauth-client-compat.md`); resources and prompts (`api/mcp-resources-prompts.md`).
- Anthropic's rules for bundles and connectors, and our tools checked against them: `api/claude-plugin-census.md`, `api/claude-plugin-tool-check.md`.

## Which steps?
- CB1 (S) Done #9398. The census, no code: the bundle format and the directory's rules for bundles from Anthropic's pages as they stand; how a bundle asks the person's site address; which Claude apps show it. Our tools against the rules above: names over 64 characters, routes that move money (orders, payments, refunds) by id. Written as `api/claude-plugin-census.md`; its proposals go to the architect.
- CB2 (M) The site's side, as CB1 lists it: a test that keeps every tool name within the limit, a scope set for the plugin with no money-moving route in it (read by default, writes chosen by the person), the panel's connection guide naming the plugin.
- CB3 (M) The bundle, in the plugin's repository: the manifest, the MCP server reference taking the person's site address, a first set of skills (find your way round a site, pages, products, orders read, a blog post draft, form records, Workspace channels) and commands. Each skill is written from what `docs/memory` says and tried with real calls on a throwaway install (`workflow/sandbox-on-windows.md`).
- CB4 (S) The listing: name, one line, description, categories, use cases, the answers of the compliance step, prepared for the owner, who submits.
- CB5 (S) Close-out: the rules into `api/`, this plan deleted.

## What is asked of the owner along the way?
- Before CB3: he opens the public repository (a session creates none, as step A8 of `agent-channel-steps-2.md`) and names it.
- Before CB4: the privacy policy address, the support contact, the icon, and the filled test site reviewers sign in to. He submits from his own account; a session accepts no terms.
- The setup page the listing links to is user documentation: written on pinegrap.com, never in this repository (`workflow/owner-rules.md`).

## What does every step keep?
- Nothing in the public repository tells of a session, a model or this repository's process (Fixed rules 2); no key, secret or customer data; English texts.
- The plugin can do only what the person's token allows: scopes as today, a dry run where a route has one, change cards where the site proposes instead of writing.
- No table or column; a step that needs one stops and asks the architect. CB1 first; CB2 and CB3 after it, side by side; CB4 after both.
- Claim as `[plan:claude-plugin-bundle] <step> — <topic>`.
