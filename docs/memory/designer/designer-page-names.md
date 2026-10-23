---
name: designer-page-names
description: Which names a visual-editor page may take: other pages, files, short links, site root entries, sitemap.xml/robots.txt, site language codes; why check_name_availability() is not used on the save path.
---

## Which names are taken for a designer page?
- `pg_designer_save_page()` refuses a name another page has, and a name that something else is served at in place of the page: `pg_designer_page_name_taken_elsewhere()` (`includes/fn/designer.php`) checks files (`files.name`), short links, a file or folder in the site root (compared without case: Windows serves a folder whatever the case) and the addresses the site answers itself (`sitemap.xml`, `robots.txt`).
- A file, short link or root folder only blocks a name being given now (new page or rename); a page that already has the name keeps saving.
- A short link that redirects to the page itself (its old address) does not block: the save deletes it and the page takes the name back (`platform/page-rename-redirect.md`).
- A site language code is refused too (`pg_tr_is_reserved_name()`): a language directory (`/en/...`) would shadow the page.
- The API's design resource asks the same function (`includes/api/resources/design.php`).

## Why not `check_name_availability()`?
- It asks the same for the classic screens but reads the site root as `../` from the working directory, which is the software directory only there. A designer page is also saved through the API and the MCP server, so a path called from them must not use it; the root is taken from the software directory's location instead.

Related: docs/memory/designer/multi-page-design-model.md
