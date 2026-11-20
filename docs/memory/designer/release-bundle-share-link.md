---
name: release-bundle-share-link
description: A release bundle's preview shared by a prototype link (prototype_links.bundle_id, RB6 #10518): who makes it, what it opens, when it stops, the frame signed for the bundle
module: prototype_links
---

## What is a bundle's share link?
- A `prototype_links` row with `bundle_id` (the `page_bundle` id) and `style_id` 0: a bundle's drafts may belong to several designs. A design's link keeps `bundle_id` 0. Column from step 11.0.244 (`upgrade_2026_11_0_prototype_link_bundles()`), and in 11.0.35's CREATE TABLE; KEY `idx_bundle`.
- Owner (2026-11-20, poll 159, channel message 9931): "Evet, eklensin" (`workflow/decisions-made-schema-29.md`).
- Same audiences, expiry (anyone: at most 90 days), password and lock as a design's link (`designer-prototype-share-links.md`). No start page: it opens at the bundle's first waiting draft. No "Allow comments": comments belong to a design.

## Who makes and revokes one?
- Roles 0-1 on the Bundles screen: "Share the preview" (beside "Preview the bundle", shown while the bundle holds a waiting draft). The dialog lists the bundle's links (badges, expiry, uses, maker, the address when it reads back), Revoke, and a new link's "Who can open it", "Expires" and "Password (optional)".
- `pg_proto_link_bundle_share()` (share_create, share_revoke), `pg_proto_link_bundle_plan()`. The new address shows once above the bundle (session), as without the site key it cannot be read back.
- Not in the API (`/design/designs/{id}/prototype-links` lists by design) nor in the editor's Share prototype dialog.

## When does it stop?
- It opens while the bundle holds drafts waiting (open, scheduled, partly published), as "Preview the bundle". Published, taken back or deleted: "This link no longer works." (404), the revoked link's answer, with no WAF offence.
- Deleting a bundle revokes its links (`pg_proto_links_revoke_bundle()` in `pg_page_bundle_delete_locked()`): a later bundle may get the same id (InnoDB on MySQL 5.7).

## How is the frame signed?
- `pg_proto_link_frame_sign()` with the bundle as an extra part (`:bundle:<id>`, `k=`), as a person's bundle frame; `s` is the drawn draft's design. A design link's signature is unchanged.
- The frame admits a link only for what it shares (`pg_proto_link_frame_owner()`): a design link its design with no `k`, a bundle link its own `k` whatever `s` is.

Related: designer/release-bundle-preview.md, designer/designer-prototype-share-links.md
