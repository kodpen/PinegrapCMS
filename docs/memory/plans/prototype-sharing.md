---
name: prototype-sharing
description: Plan (2026-10-24, slice 2 done #1579, 4 done #1578; answers 1, 2, 4, 6 given): a design's drafts play as a clickable prototype shared by a view-only link; flows shared with the team, a link later
---

## What is asked? (owner, 2026-10-24)
- Share a prototype or a flow; going further, visual designs saved as drafts count as prototypes by themselves.

## What is there today?
- A draft is a page in the private Drafts folder; each changing save is a revision. Roles 0-2 open a saved draft at its address and click on by page name; role 3 needs folder rights, signed out goes to sign-in. Preview renders widgets in the background, not signed out.
- Token links: short links (once, timed), guests (a view-only channel share, one link each), calendar and product feeds (address shown again). Notes and flows (#1578) share to a person or channel.
- Details: plans/prototype-sharing-code.md.

## What is proposed? (not approved yet)
- Drafts are the prototype: a design's draft pages (scheduled ones until they go live) and their links; a link to a live page opens it on the site, a broken one is marked. Opened from the Prototype view (plans/designer-prototype-links.md) or "Preview as prototype"; a toggle outlines hotspots.
- Viewer prototype.php (#1579): a session-less frame admitted by a 15-minute address signed for design, page, sub, record and person, bound to the browser; links by `includes/fn/link_resolve.php`; no Analytics, consent refused, forms inert (designer/designer-prototype-viewer.md).
- Link: a hashed token, expiry, revoke, optional password with a counter of its own (the sign-in throttle's address bucket would lock sign-in, and is off with it), or team only; roles 0-1 make it, Site Log lines, an API route with `dry_run` and `'mcp' => false` (no AI app mints a public link); what it shows and its address: questions 6, 7.
- Flows: F7 shares read by `ws_flow_access()`: a person gets view, run or edit (edit only for staff), a channel view (run comes from the home channel); a run acts with the owner's rights. A read-only outside graph link like the prototype link (people, channels as kinds) and run state later.
- Schema (approved 2026-10-24): `prototype_links` (style, start page, token hash and secret (7 b), hint, expiry, password hash, audience, maker, revoked, uses) on general label 11.0.35; `ws_flow_shares` 11.0.83 (in #1578).

## Slices (one PR each)
1. Preview as prototype, no schema: a link whose page name matches an open tab (an unsaved tab's typed name too) plays its preview, others open the site in a new tab; hotspots toggle; designer-prototype-links slice 3 brought forward.
2. Done #1579: prototype.php for signed-in roles 0-2, no schema: the signed-out view and the resolver.
3. Share links (schema): dialog, list, revoke, password, WAF offence; only the frame's admission changes, the per-view signature stays.
4. Done #1578: F7 flow shares (schema 11.0.83).
5. Later (ask): outside flow link, run state, comments (designer node notes exist).

## Which questions are answered? (owner, 2026-10-24: 1 a, 2 c, 4 a, 6 a; 3, 5, 7 open)
1. Pages: (a) the design's drafts, scheduled too, (b) ticked per link, (c) every site draft → a.
2. Who opens a link: (a) anyone holding it, expiry required, (b) team only, (c) chosen per link → c.
3. Who makes links: (a) roles 0-1, like the Schedule section, (b) roles 0-2, as drafts open today → a.
4. Flows: (a) F7 team shares first, outside link later, (b) a graph link now, (c) with a run's state → a.
5. Own table, or a new `short_links.destination_type` (ENUM) → own: 'workspace_guest' needed a filter in about ten places.
6. A link shows (a) the last saved draft, (b) the revision it was made at, (c) chosen per link → a.
7. The address: (a) shown once, like guest links, (b) shown again, like calendar feeds → b.

## Where to start?
- Slice 3 (claim #1575); slice 1 open. Read plans/prototype-sharing-code.md, designer/README.md, designer/page-drafts.md, designer/designer-live-preview.md, workspace/workspace-flows-store.md; claim first.
