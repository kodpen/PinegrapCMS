---
name: release-bundle-preview
description: A release bundle's preview (prototype.php?bundle=<id>): its waiting drafts, a link to a replaced page opening the replacement, the frame signed for the bundle, who opens it
module: prototype
---

## What does the preview show?
- The drafts still waiting in a bundle that holds them (open, scheduled, partly published), once each, in the bundle's order: `pg_proto_bundle_pages()` reads the rows, `pg_proto_page_set()` with the bundle's items orders them (`pg_proto_bundle_page_order()`). Drafts of more than one design may sit side by side; no tab strip orders them.
- A published, taken back or deleted bundle has none: "No page of this bundle is waiting to be published."
- The bar shows the bundle's name; a replacement keeps its own title, and the page list adds "(replaces <live page>)".

## Where does a link lead?
- A link to a live page the bundle replaces opens the replacement in the preview, with the item, record and section it names (`pg_proto_link_target()`, ctx `replaced` from `pg_proto_bundle_replaced()`: live page id => draft id). A new page or a replacement by its own draft name stays in the preview, as in a design's prototype.
- Every other live page opens on the site in a new tab, as today. Another language's version of a replaced page opens the live one: the frame draws the source language only.
- A draft the bundle does not hold goes nowhere, titled "This link leads to a draft that is not in this bundle."

## How is the frame signed?
- The viewer signs the frame as a design's (`pg_proto_frame_sign()`) with the bundle as an extra part (`:bundle:<id>`, sent as `k=`), so a bundle's address does not open a design's frame nor the other way round; a design's signature is unchanged. `s` is the drawn page's own design.
- The frame reads the person's role again and asks `pg_page_bundle_user_may()` (roles 0 and 1) beside the viewer's own rule (roles 0-2); its links go back through `prototype.php?bundle=<id>&page=<draft>` (`pg_proto_bundle_viewer()`).

## Who opens it, and from where?
- `prototype.php?bundle=<id>`: signed in, past the viewer's manager gate and then `validate_area_access($user, 'designer')`, as the Bundles screen. Site Log: one line per bundle and session, "preview of bundle (<name>) was viewed". Close goes back to `page_bundles.php?id=<id>`.
- The Bundles screen's toolbar draws "Preview the bundle" (a `pg-button pg-button-quiet pg-button-sm` link, a new tab) while the bundle holds its drafts and at least one waits.
- A share link of the preview: release-bundle-share-link.md.

Related: designer/designer-prototype-viewer.md, designer/release-bundle-screen.md
