---
name: publish-approval-census-gate
description: Plan publish-approval PA1 census: how the content gate's merge (pg_designer_merge_restricted_tree) can be kept as a proposal's operations per node, and what is lost with the assistant's operations
---

## What does the gate take from a content-level save?
- `pg_designer_gate_tree()` (`includes/designer_access.php`) merges the stored tree with the sent one through `_pg_dm_node()`: children matched by `_id` (by position in a tree without ids).
- Outside a marked area: `props.text` (markers defused), `props._notes` (author stamped later by `pg_stamp_note_authors()`), the image address (`props.src` of an image content node, the `src` attribute of a semantic `img`).
- Inside an `_editable` area: the whole sent subtree, its `_editable` flags stripped, code nodes and `shared_ref`s put back from the stored tree.
- Beside the tree, `pg_designer_save_page()` writes the page fields a content level keeps: title, description, search and keywords, sitemap, noindex, comments settings, `page_meta_tags`, `custom_jsonld`, canonical and snippet. Name, folder, home and design are put back (`pg_designer_restricted_identity()`); the draft state and the times are kept.

## How can the result be written as operations?
- Diff the stored tree against the merged one by `_id`: one op per changed node, `{op:"text", node, text, was}`, `{op:"src", node, src, was}`, `{op:"note", node, note, was}`; per marked area that differs `{op:"area", node, tree, was_hash}`; one `{op:"fields", …}` for changed page fields, each with its stored value.
- Applying: every node must still be there (else stale, A9) and still hold `was` (else stale: a designer changed it meanwhile). The operations are then put on the stored tree and the result passes `pg_designer_merge_restricted_tree()` at content level before the write (the architect's condition, `publish-approval-census.md`).
- A stored tree without `_id`s cannot be addressed per node: it is kept as one `{op:"tree", tree, base_hash}`, stale when the page changed since.

## What is lost with the assistant's operations instead?
- `update` text: content HTML passes `pg_design_ai_clean_inline()` (parts dropped), a semantic node's text loses its tags, a bound node, an island or a semantic node with children is refused although the gate copies its text.
- The image address goes through `attrs` and `pg_design_ai_url_ok()`, which refuses addresses the gate takes.
- An area through `replace` is HTML rebuilt by `pg_design_ai_html_nodes()`: props, bindings, settings and ids of new nodes change.
- Notes and page fields have no operation; `PG_DESIGN_AI_MAX_OPS` (40) caps a long edit.
- No per-node "was" check: a targeted op overwrites a designer's later change of the same text without notice.
