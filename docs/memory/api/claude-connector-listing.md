---
name: claude-connector-listing
description: A connector listing in Claude's directory for a server whose address is each customer's own, as Anthropic's pages state it (read 2026-11-17): the URL pattern, Verified only, sign-in, the install link
---

## Can the directory list a site's MCP server though every site has its own address?
- On paper, by the pages read on 2026-11-17 (`claude.com/docs/connectors/building/authentication`, "Servers with per-customer URLs"): the submission's Connection step offers "Universal URL", "Multiple URLs" (a fixed set, such as one for each region) and "URL pattern", an anchored regular expression every customer's URL must match. "Each user enters their own URL when they connect"; Claude lowercases the host before it checks.
- A listing that uses one "can be published only once it has the **Verified** label, and its review takes longer". Verified is not applied for: Anthropic scans a submission, lists it as Community, and may escalate a listing to Verified review, where reviewers try each tool.
- The documented example is a vendor's own subdomain (`^https://[a-z0-9-]+\.mcp\.example\.com/mcp$`); whether a pattern that takes any customer domain passes is not stated. Listings of self-hosted products show no fixed address in the directory (n8n, Metabase, YouTrack, Sourcegraph), and Metabase's manual tells its users to enter their own MCP URL there.

## How does Claude sign in under a pattern?
- With a client-ID metadata document when the authorization server states `client_id_metadata_document_supported: true` and `none` among `token_endpoint_auth_methods_supported`, otherwise by registering itself. Client credentials held by Anthropic are tied to exact URLs and cannot be used with a pattern.
- A site states both and takes both (`pg_oauth_metadata()` in `includes/oauth/server.php`, `api-oauth-server.md`).

## What works with no listing?
- An install link opens Claude's "Add custom connector" dialog filled in: `https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=NAME&connectorUrl=ENCODED_URL`.

## And the official MCP Registry?
- An entry's URL may hold variables, the whole host among them ("enables multi-tenant deployments"); publishing proves a namespace by a DNS record or a GitHub login. It "doesn't surface your server in Claude".

## What is decided?
- Owner (2026-11-14): a plugin bundle (`workflow/decisions-made-product-51.md`). That stays.
- Architect (2026-11-17, agent channel message 7524): a "Connect to Claude" button built from the install link with the site's own address joins CB4 of `plans/claude-plugin-bundle.md` and goes to the owner on CB4's sheet. A listing with a URL pattern is a later step of that plan, waiting for Anthropic's answer on customer domains, which the owner asks from his account, and for his yes. The MCP Registry entry joins the same plan.

Related: docs/memory/api/claude-plugin-census.md, docs/memory/api/api-mcp-server.md
