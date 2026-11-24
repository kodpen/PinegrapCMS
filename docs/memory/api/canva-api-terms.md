---
name: canva-api-terms
description: Canva restricted uses (read 2026-11-24): no bulk export or scraping, no exfiltration; export into a third-party design platform needs Canva's written consent
---

## What do Canva's restricted uses forbid? (Restricted uses 1.0, 2026-11-06)
- "scraping, crawling or bulk-exporting Canva content, templates, fonts, stock media or user data" beyond what the API services expressly allow.
- "using the API Services or any connector to exfiltrate data from a user's Canva account".
- "using the API Services to provide a service that substantially replicates Canva"; sharing, proxying or reselling API access.
- "enable" counts a tool whose "primary or foreseeable use" is a restricted use. The page applies to the Canva APIs in general, not only one kind of app.

## What does the MCP usage policy add?
- "Any use primarily intended to export designs from Canva into a third-party design platform or system" is prohibited without Canva's express written consent; automated extraction is prohibited too.
- No crawling, pre-fetching or indexing of designs, folders or Brand Kits without the person's action; Brand Kits may not be exported for design generation outside Canva.
- Written for the MCP server; whether it binds a REST app is not said (Canva points to developer-support).

## What else applies?
- A design holding Canva content, once exported, falls under Canva's Content License Agreement; branded content is personal, non-commercial and not to be distributed as a standalone file.
- Outputs or Canva content may not train machine-learning models without written permission.
- The Developer Terms page itself refused an automated read (403); the points above come from canva.dev's restricted-uses and MCP pages and a search summary of the dated terms.

## What does it mean for the Canva start?
- Pages brought in as reference pictures under a sketch, on the person's own action, one design at a time, are an export of the person's own design. Whether a design tool's import counts as "export into a third-party design platform" is a question for Canva, not for a session (`figma-canva-census.md`).
