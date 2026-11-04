---
name: link-resolve-js-twin
description: The link resolver's browser twin (assets/js/pg_link_resolve.js, window.PGLinkResolve): what it mirrors, how parity is tested, what it cannot mirror
---

## What is it?
- `window.PGLinkResolve` in `assets/js/pg_link_resolve.js`, bundle `pg_link_resolve.min.js` (`pg_js_bundles()`). Each function is the twin of one in `includes/fn/link_resolve.php` with the same arguments: `parse` (`pg_link_parse()`), `mapKey`, `fold` (`pg_link_maps_fold()`, returns a copy), `resolveItem`, `resolve`, `lookupNames`.
- Site values (`pg_link_resolve_env()`) and name maps (`pg_seo_link_maps()`, `pg_link_maps_for_items()`) come in as JSON; `formNames` is an object or a function returning one.
- Added for the designer's Prototype view (designer/prototype-view.md), so a link is broken in the editor exactly when the server calls it broken. Nothing prints it yet; its user prints it before style_designer.min.js (no defer needed: it only defines the global at load).

## How is parity kept?
- `tests/link_resolve_js_test.php` runs about 75 hrefs under four site values (path `/`, `/site/`, empty; host with port, empty, other case), folded and not, form names direct and lazy, through both and compares type, id, class and the parsed item; plus `lookupNames` and `fold`. A change to one side is made to the other in the same PR.

## What does it not mirror?
- A `%`-escape that is no UTF-8: PHP keeps raw bytes, the twin keeps the escape as written; both answer unknown.
- `parse_url()` is mirrored only as far as the resolver reads it (host, path; an authority with no host is `invalid`).
- Host comparison lower-cases with Unicode rules (PHP `strcasecmp()` is ASCII); hosts are ASCII in practice.
- Object key order: JavaScript lists integer-like keys first; only the folded index's "first of two names differing in case" could depend on it, and digits have no case.
