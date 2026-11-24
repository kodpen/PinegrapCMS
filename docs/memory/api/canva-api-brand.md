---
name: canva-api-brand
description: Canva brand over the APIs (read 2026-11-24): no REST endpoint reads a Brand Kit's colours or fonts; brand templates exist; brand kit colours only in the Apps SDK
---

## Can a Brand Kit's colours and fonts be read over REST?
- No. The Connect reference has Assets, Autofill, Brand templates, Comments, Designs, Exports, Folders, Users and the like, but no Brand Kit section, and no `brandkit` scope.
- Inside Canva, the Apps SDK has `getBrandKitColors` and `getDesignColors`; an app there runs in the Canva editor, not on a Pinegrap server.
- Canva's MCP server has a `list-brand-kits` tool; its usage policy forbids exporting a Brand Kit for design work outside Canva (`canva-api-terms.md`).
- What a server can do: read colours out of the exported page pictures. Fonts cannot be learnt from REST.

## What do brand templates give?
- List brand templates, Get brand template, Get brand template dataset (which fields autofill can fill), Publish brand template (preview). Scopes `brandtemplate:meta:read`, `brandtemplate:content:read`.
- For a person on a plan with brand templates (Canva names Pro, Teams, Enterprise). Autofill was gated to Enterprise until a 2026 change opened it to Pro and above (forum, 2026-09-23; not confirmed in the changelog).
- A brand template is a design: it can be exported like any other (`canva-api-designs-export.md`), but it carries no colour or font list.
