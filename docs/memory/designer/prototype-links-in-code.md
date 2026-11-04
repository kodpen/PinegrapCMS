---
name: prototype-links-in-code
description: Where a design's links sit in the code, page-name hrefs and renames, the resolver, editor state, rights and Publish: what the Prototype view reads
---

## Where do links sit?
- Navbar component (and navbar-light-cta, -search, -dark-center): nav.navbar > div.container-fluid > [brand content link, toggler, div.collapse.navbar-collapse > ul.navbar-nav > li.nav-item > content link], href in `props.href`.
- navbar-mega, -double, -centered-logo and footer blocks: semantic a.nav-link and brand nodes, href in `props._attrs` ({name:'href'}). The renderer prints `props.href`, the `_attrs` href only when the prop is empty; `migrateNodeLinkProps()` folds only target and rel. HTML import writes `props.href`.
- Not navigation: a[data-bs-toggle=dropdown|collapse|tab|modal] with '#' or '#id' (`_sdCollapseToggleTarget()`, `getNodeBsTarget()`; designer/designer-canvas-links.md).
- Also: btn component (`btnElement` a), `<a>` in text (`_sdIlParse()`); widget `*_page_id` settings in `shared_components.system_region_config`, written when picked, not on Save, `tab:<key>` until the page is saved.
- Top menu and footer usually sit in a shared component (`tree_json`, drawn per request).
- Outside the trees: menu_items (`link_page_id`, or `link_url` + `link_target`) through region nodes 'menu' / 'menu_sequence' (legacy, `props.regionName`), drawn as a placeholder: read with `pg_menu_tree()`; cregion and dregion likewise.
- A link kept by page id (`__page_link`) inside a widget: designer/record-value-page-links.md; outside a widget: designer/page-link-by-id.md.

## Hrefs, renames, the resolver?
- A plain href is a page-name path. A rename rewrites no tree (`_pgFollowRename()`); a 301 short link only while "Send visitors of the old address here" stays ticked (`page_name_redirect`), never for a draft or a taken, routable or looping name. The old href then resolves to `short_link` id 0: follow short_links.page_id.
- `pg_seo_resolve_link()` (root seo_links.php) wraps the pure `pg_link_resolve()` (includes/fn/link_resolve.php); maps are handed in: `pg_seo_link_maps()` all names, `pg_link_maps_for_items()` those asked for. `class` splits 'external' (mailto, tel, anchor, host…); the wrapper drops it. JS twin: platform/link-resolve-js-twin.md.
- A binned page gets a parked name; drafts sit in the Drafts folder; one page serves every language (`pg_tr_rewrite_links()`).

## Editor state?
- Open tab: module-level `tree`; `_pages[active].tree` is set only by `_pgTabsPersistActive()`: read through `_pgTreeOf(p)`. `_sharedCache` holds the components of trees drawn so far (Save prefetches the rest). Content level: hidden-folder pages are not in `_pages`; locked ones are listed, never opened. Node `_id`s are unique per tree.
- `saveState()` / `undo()` cover the open `tree`, shared trees, assets; `_sdMayEdit()` / `_sdCanEditNode()` read `_collab.mayEdit` (the open page's lock) and `_sdEditableSet()` (`tree` and `_sharedCache`). `_sdSharedMarkEdited()` works for any page.
- `generateHTML()` takes a tree; canvas links never navigate; in the Preview a page link plays the open tab of that name (designer/designer-preview-play.md).

## Rights and Publish?
- Roles 0-1 change any link; 2-3 only inside an editable area (`pg_designer_merge_restricted_tree()`) or as `<a>` in text.
- Shared components: roles 0-1 by `update` (no lock, no revision). One opened to content editors (`content_editable`, `users_shared_components_xref`) takes `content_update` from managers and granted users, never a widget; outside `_editable` areas only text and image src change.
- The main button reads Publish (Save on a draft): a live page's link goes live on that click, each changed page writes a revision, a locked one is skipped with a warning; a shared edit writes none and reaches every design using it.
