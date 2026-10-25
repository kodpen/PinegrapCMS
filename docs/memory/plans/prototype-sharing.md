---
name: prototype-sharing
description: Plan (2026-10-24; slices 2, 3, 4 done: #1579, #1595, #1578; all eight answered): a design's drafts play as a clickable prototype shared by a view-only link; flows with the team, a link later
---

## What is asked? (owner, 2026-10-24)
- Share a prototype or a flow; going further, drafts of visual designs count as prototypes themselves.

## What is there today?
- A draft is a page in the private Drafts folder; each changing save is a revision. Roles 0-2 open a saved draft at its address and click on by page name; role 3 needs folder rights, signed out goes to sign-in. Preview renders widgets in the background, not signed out.
- Token links: short links (once, timed), guests (a view-only channel share, one link each), calendar and product feeds (address shown again). Notes and flows (#1578) share to a person or channel.
- Details: plans/prototype-sharing-code.md.

## What is the shape? (approved 2026-10-24)
- Drafts are the prototype: a design's draft pages (scheduled until live) and their links; a link to a live page opens it on the site, a broken one is marked. Opened from the Prototype view (plans/designer-prototype-links.md) or "Preview as prototype"; a toggle outlines hotspots.
- Viewer prototype.php (#1579): a session-less frame admitted by a 15-minute address signed for design, page, sub, record and person, bound to the browser; links by `includes/fn/link_resolve.php`; no Analytics, consent refused, forms inert (designer/designer-prototype-viewer.md).
- Link (slice 3 built): hashed token, address shown again, expiry, revoke, optional password with its own counter, anyone or team; roles 0-1; API without MCP; frame bound to its opening session (designer/designer-prototype-share-links.md).
- Flows: F7 shares are in (#1578, workspace/workspace-flows-shares.md); a read-only outside graph link like the prototype link, and run state, later.
- Schema (approved 2026-10-24): `prototype_links` (style, start page, token hash and secret (7 b), hint, expiry, password hash, audience, maker, revoked, uses) on general label 11.0.35; `ws_flow_shares` 11.0.83 (done #1578).

## Slices (one PR each)
1. Preview as prototype, no schema: a link whose page name matches an open tab (an unsaved tab's typed name too) plays its preview, others open the site in a new tab; hotspots toggle; designer-prototype-links slice 3 brought forward.
2. Done #1579: prototype.php for signed-in roles 0-2, no schema: the signed-out view and the resolver.
3. Done #1595: share links (schema 11.0.35): dialog, list, revoke, password, WAF offence; a link-signed frame beside the person's, bound to the viewer's session.
4. Done #1578: F7 flow shares (schema 11.0.83).
5. Later (ask): outside flow link, run state, comments (designer node notes exist).

## Which questions are answered? (owner, 2026-10-24: 1 a, 2 c, 4 a, 6 a; 3 a, 5 own, 7 b, 8 a as recommended)
1. Pages: (a) the design's drafts, scheduled too, (b) ticked per link, (c) every site draft → a.
2. Who opens a link: (a) anyone holding it, expiry required, (b) team only, (c) chosen per link → c.
3. Who makes links: (a) roles 0-1, like the Schedule section, (b) roles 0-2, as drafts open today → a.
4. Flows: (a) F7 team shares first, outside link later, (b) a graph link now, (c) with a run's state → a.
5. Own table, or a new `short_links.destination_type` (ENUM) → own: 'workspace_guest' needed a filter in about ten places.
6. A link shows (a) the last saved draft, (b) the revision it was made at, (c) chosen per link → a.
7. The address: (a) shown once, like guest links, (b) shown again, like calendar feeds → b.
8. An anyone link and a draft going live behind a sign-in: (a) not drawn, a note, (b) drawn with record widgets empty → a, as recommended (ask).

## Where to start?
- Slice 1 open, slice 5 later (ask). Read plans/prototype-sharing-code.md, designer/page-drafts.md, designer/designer-live-preview.md, workspace/workspace-flows-store.md; claim first.
