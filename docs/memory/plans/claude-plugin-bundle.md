---
name: claude-plugin-bundle
description: Plan (owner 2026-11-14): a Pinegrap plugin bundle for Claude's directory: a site's MCP server connected by its address, skills for common site work, the listing; the repository is the owner's to open
---

## What is asked?
- Owner (2026-11-14), on Anthropic's directory of plugins and connectors (`claude.com/docs/directory/publish`): "pinegrap için bir araç paylaşabiliyor muyuz claude içinde kullanıcılar hızlıca entegre olabilsin diye?", then, on the plugin bundle: "yapalım." Record: `workflow/decisions-made-product-51.md`.
- A bundle: the owner's choice. A listing by each user's own address and a hub server are not decided (`api/claude-connector-listing.md`).

## What is there today?
- Each site is an MCP server over Streamable HTTP: every API route its caller's scopes cover is a tool with a title, `readOnlyHint` and `destructiveHint` (`includes/mcp/tools.php`); OAuth with open registration that real clients pass (`api/api-oauth-client-compat.md`); resources and prompts (`api/mcp-resources-prompts.md`).
- Anthropic's rules for bundles and connectors, and our tools checked against them: `api/claude-plugin-census.md`, `api/claude-plugin-tool-check.md`.

## Which steps?
- The steps CB1 to CB5 and what waits for the release of 2026.11.0: `claude-plugin-bundle-steps.md`. CB1 and CB2 are done; the "Connect to Claude" button (CB4a) goes now.

## What is asked of the owner along the way?
- Before CB3: he opens the public repository (a session creates none, as step A8 of `agent-channel-steps-2.md`) and names it.
- Before CB3: policy 3F (the developer controls every endpoint) against sites that are their owners'; the architect asks him.
- Before CB4: the privacy policy address, the support contact, the icon, and the filled test site reviewers sign in to. He submits from his own account; a session accepts no terms.
- The setup page the listing links to is user documentation: written on pinegrap.com, never in this repository (`workflow/owner-rules.md`).

## What does every step keep?
- Nothing in the public repository tells of a session, a model or this repository's process (Fixed rules 2); no key, secret or customer data; English texts.
- The plugin can do only what the person's token allows: scopes as today, a dry run where a route has one, change cards where the site proposes instead of writing.
- No table or column; a step that needs one stops and asks the architect. CB1 first; CB2 and CB3 after it, side by side; CB4 after both.
- Claim as `[plan:claude-plugin-bundle] <step> — <topic>`.
