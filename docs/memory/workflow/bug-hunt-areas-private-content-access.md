---
name: bug-hunt-areas-private-content-access
description: The bug hunt in private content access (area 26, #11408): pages, folders and files for members or groups; the faults fixed and what was read clean
---

## Which area was it?
- Area 26, "private-content-access": pages, folders and files limited to members or groups, never reachable by direct address, `get_file.php`, a feed, the sitemap, a share preview, an API read or a cached copy; the sign-in return address; access taken away at the next request. PR #11408.
- Left alone, held by open PRs at the time: `router.php`, `includes/fn/widgets.php` (#11400), `includes/fn/auth.php` (#11396), site search (`get_search_results.php`, #11397).

## What was fixed?
- External API `GET /files` and `GET /files/{id}` read the folder's own `folder_access_control_type`.
  - A folder that inherits keeps '' there, so files of a public folder's subfolders were missing; a public folder moved into the Recycle Bin kept 'public', so its files were still listed with their address.
  - Now `pg_files_public_sql()` (`includes/fn/files.php`) names the folders `get_access_control_type()` calls public. The Workspace canvas pictures (`ws_canvas_split_file_row()`) had the same condition and use the same function.
- A Workspace message linking to a private, member, registration or guest page of the site got a card with that page's title and meta description, read from the `page` row, and every reader of the channel saw it, a guest's channel too. `ws_link_preview_own_card()` now gives a card only for a public page in a folder that is not archived (`workspace/workspace-link-preview.md`).

## What was read and found clean?
- `get_page.php`'s gate (private, guest, registration, membership; expired private access), its RSS branch (public pages only, the list view's viewer filter), the PDF branch after the gate.
- `get_file.php`: the folder check, attachments, product form uploads and volunteer documents decided first, `Cache-Control: private` for anything not public and for every attachment, object store links signed for 300 s.
- `check_private_access()` and the membership check read the database on every request; nothing about access is kept in the session, so access taken away holds at the next request.
- The page cache stores only public pages, keyed without the session, and an access change in the panel, the API or MCP raises the generation (`platform/page-cache-invalidation.md`).
- `sitemap.xml`, `llms.txt`, the AI crawler and visibility lists, product feeds, ActivityPub outbox, speculation prefetch, share cards (checked again on every request) and prototype links all ask `get_access_control_type()`, which treats the Recycle Bin as private.
- Sign-in return addresses: `index.php`, the entrance screens, `signin.php`, two-step and device-limit screens, reset and change password all pass `send_to` through `pg_safe_redirect_path()` or `pg_safe_back_url()`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/api/api-file-endpoints.md, docs/memory/workspace/workspace-link-preview.md
