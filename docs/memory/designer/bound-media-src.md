---
name: bound-media-src
description: audio, video and source take src (video also poster) from a record field binding under the bound address rule; an empty value prints none (#7654, PR #8211)
module: designer, widgets
---

## May a media element take its address from a record field?
- Yes (architect, 2026-11-06, #7654; built in PR #8211): in a widget tree `audio`, `video` and `source` take a `_bindings.src` binding to a record field (a file field gives its address, a URL field its text), and `video` a `_bindings.poster` binding, as `img` takes `_bindings.src`.
- `_render_tree_node()` prints them through `pg_media_bound_attrs($tag, $props)` (`includes/fn/designer.php`): only a bound prop prints, and it wins over an Attributes panel `src`; an unbound player keeps its typed `src`, so trees without the binding render byte-identical. `_apply_bindings()` lists `poster` in `$renderable_props` (`data-pg-bind="poster:…"`).

## What address may it print?
- The bound address rule: `pg_sw_token_starts_address()` takes `src` and `poster` beside `href`, `action` and `formaction`, so a value opening with `javascript:`, `vbscript:`, `livescript:` or `data:` prints `#` ([[bound-href-scheme]]). This now covers a bound `img src` too.
- Only the binding path opens: `{{token}}` in a `src`, `poster` or `srcset` entry of the Attributes panel stays refused ([[widget-attr-bindings]]). In the code view inside a widget, `{{token}}` typed into a player's `src`/`poster` becomes the binding (the Bind Data panel offers the prop).

## What does an empty value do?
- `pg_sw_apply_tokens()` drops the whole attribute when the token is the full `src`/`poster` value of an `audio`, `video` or `source` tag and the field is empty (`pg_sw_drop_media_address()`): the browser asks for nothing. An `img` keeps its `src=""`.
- `pg_sw_has_content()` counts a player only with an address of its own or of a `source` child; its fallback words do not count (`pg_sw_media_without_fallback()`), nor a poster alone. A player marked `pg-hide-if-empty` or carrying `pg-field-value` is judged as its whole element (`pg_sw_is_player_tag()`), since its address sits in its opening tag. Twin: `showsSomething()` in `pg_civ_variants.js`.

## What does the designer show?
- Bind Data offers "Media address" on `audio`/`video`/`source` and "Poster image" on `video`, inside a widget only (`widgetOnly` entries of `_getBindableProps()`, left out of the page-record rows: `pg_page_record_bind_props()` has no player slot). The canvas draws a bound poster as the bound-picture placeholder and gives a bound player no address, so it fetches no media. The editor's HTML (`_sdEmitNode()`) and code view (`_sdCodeBoundProps()`) print the bound `src`/`poster` as the page does. `tools/check_bindings.php` is unchanged.
- Tests: `tests/designer_bound_media_test.php`.

## Who uses it?
- The Podcast template's episode page (`$episode_player` in `pinegrap-podcast/parts.php`): `<audio class="pg-field-value" controls preload="none">` bound to `audio` in a `pg-hide-if-empty` box, so a page view fetches no media and an episode without a file shows only its Listen buttons. Every byte played goes through `get_file.php` or the object store's presigned address ([[file-object-storage]]).
- The Courses lesson video and the Music release pages may take it, each as its own small PR.

## Why not a Listen link only?
- A record's file in the page's own player is what a podcast episode, a course lesson and a music release need; the binding is general and small, reuses the existing address rule, and changes no schema and no saved tree.

Related: docs/memory/designer/bound-href-scheme.md, docs/memory/designer/widget-attr-bindings.md, docs/memory/designer/widget-hide-if-empty.md
