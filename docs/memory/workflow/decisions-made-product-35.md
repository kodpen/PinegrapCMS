---
name: decisions-made-product-35
description: Decisions list, product part 35 (2026-11-05 on): live pictures of templates and designs, the installer's design step and 1440 px width, the ACP Bearer credential, a shopper's MCP consent
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## How are templates and designs pictured? (#7258, #7409, #7412, #7419)
- Owner (2026-11-05): a picture is the real page, not a stored image: a 1280×800 frame of the page scaled into its box, script-free (`sandbox=""`), not focusable, loaded when it comes into view (`designer/design-live-pictures.md`). Reason: a screenshot needs a browser on the server, which shared hosting does not have. Stored thumbnails come only if the frames prove too heavy on a real site (the editor home slow to settle), as a new plan.
- The drawings (`pg_design_thumb_svg()`) stay as the fallback: a template whose home page cannot be drawn, a design with no page to open.
- The site's design on the editor home is its page as a first-time visitor sees it: no consent banner, chat bubble or staff bar, no visit counted (`designer/design-preview-address.md`).
- A template is shown, and opens, in the look and palette its file names. Choose a Template's picker starts on "Template's theme" / "Template's palette"; its card button "Use this theme" went; another choice redraws every card (`designer/designer-home-pictures.md`).

## What does the installer's design step show? (#7409)
- Owner (2026-11-05): the step previews the home page only; every page and e-mail is seen in the tour that "See all pages in a new tab" opens. Picking a template sets both pickers to its theme, still changeable. Reason: 22 cards of seven points made the step 11,349 px tall at 1280 px (21,434 px on a phone).
- The installer is as wide as Bootstrap 6's widest container, 1440 px; from 1280 px the design step takes the side column's room (`platform/install-design-step.md`).

## How does an ACP platform authenticate? (#7455)
- Owner (2026-11-06, option A): the Bearer value on `/acp/*` is the application's own `key:secret`; the application reader accepts that form under `/acp/` only, while `/api/` keeps HTTP Basic and OAuth. Scopes, rate bucket, logs and revocation stay the application row's; the ACP settings card tells the operator to create a dedicated server application for the platform and shows the Bearer value once. Reason: one credential store, one revocation path, no schema step; the dedicated application gives independent rotation. Refused: a separate ACP secret per application (a second generate/rotate/revoke path, a credential Application Access does not list).

## What does a shopper's MCP consent produce? (#7456)
- Owner (2026-11-06, option A): the member's own OAuth connection. The consent screen accepts a site member's sign-in through the website door, a member's ceiling is the `storefront:*` family only, and `api_storefront_shopper()` reads the shopper from a member-owned connection (a staff-owned connection stays an application, shopper in headers). Resource `mcp`, refresh rotation, `api_devices_revoke_user()` and the client switch-off work unchanged; the member's account widget lists and ends their connections. No schema change. Reason: MCP clients speak OAuth 2.1 and nothing else, and the OAuth server's one-token-table rule (`api/api-oauth-server.md`) was written for this caller. Refused: a customer token minted by a website consent screen (a generic MCP client cannot send the header: the token would ride as a tool argument into chat transcripts, or the OAuth server would grow a second token path). The customer token stays the shopper header for applications that carry headers (`plans/storefront-api-customer-token.md`).
