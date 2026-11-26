---
name: figma-canva-census
description: Figma and Canva API census for the designer's Figma and Canva starts (read 2026-11-24): what each gives, where it differed from the start plan, which file holds each topic
---

## Which file holds what?
- Figma: `figma-api-sign-in.md`, `figma-api-file-reads.md`, `figma-api-image-export.md`, `figma-api-styles-variables.md`, `figma-api-rate-limits.md`, `figma-api-terms.md`.
- Canva: `canva-api-sign-in.md`, `canva-api-designs-export.md`, `canva-api-brand.md`, `canva-api-terms.md`.
- Read from figma's and canva.dev's public developer pages on 2026-11-24; both change often, so a step that builds on a number reads the page again.

## What holds as the plan assumes?
- Figma gives the node tree with auto layout (horizontal, vertical and grid), text with its style, component instances by `componentId`, rendered pictures (png, jpg, svg, pdf) and image fills: frames to layers is possible.
- Canva gives no layers: whole pages as png or jpg through an export job. The owner's "pictures and brand" ground stands.
- Both are OAuth with PKCE and server-side secrets, so tokens stay on the server.

## Where did it differ from the start plan (`designer/designer-start-import.md`)?
- Figma variables: the Variables API is Enterprise only. On other plans colours and text styles come from the values on the nodes and the style names in the file, not from Figma variables.
- Figma sign-in: a public OAuth app needs Figma's review and fixed redirect addresses; a private app reaches only its own team. A self-hosted site has its own address, so each site registers its own app, pastes a personal access token, or goes through a shared broker.
- Figma limits: file, node and picture reads are Tier 1: 10 to 20 a minute on Dev/Full seats, about 20 a month on View/Collab seats. The import must be few calls, and a viewer may not be able to import at all.
- Canva brand: no REST endpoint reads a Brand Kit's colours or fonts. Colours can only be read from the exported pictures; fonts cannot be had.
- Canva sign-in: a public app needs Canva's review; a private app only on Enterprise. Get design pages (page thumbnails) is a preview API, which a public app may not use.
- Canva terms: the restricted uses forbid bulk export and exfiltration; Canva's MCP policy forbids, without written consent, any use "primarily intended to export designs from Canva into a third-party design platform".
