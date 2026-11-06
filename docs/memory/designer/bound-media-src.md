---
name: bound-media-src
description: An audio, video or source element may take its src (a video its poster) from a record field binding as img does, under the bound address rule; decided 2026-11-06 (#7654), Podcast step 8
module: designer, widgets
---

## May a media element take its address from a record field?
- Yes (architect, 2026-11-06, #7654): in a widget tree `audio`, `video` and `source` take a `_bindings.src` binding to a record field (a file field or a URL field), and `video` a `_bindings.poster` binding, as `img` takes `_bindings.src`. `_render_tree_node()` emits the bound `src` for those tags as it does for `img`; `_apply_bindings()` already turns `_bindings.src` into a token (`$renderable_props`), `poster` joins the list.
- The address rule of a bound link applies: a value opening with `javascript:`, `vbscript:`, `livescript:` or `data:` prints `#` (`pg_sw_token_starts_address()` takes `src` and `poster` beside `href`, `action` and `formaction`: [[bound-href-scheme]]). Only the binding path opens: `{{token}}` in a `src`, `poster` or `srcset` attribute of the Attributes panel stays refused ([[widget-attr-bindings]]).
- An empty value prints no `src`, and the element counts as empty for `pg-hide-if-empty` only then: `pg_sw_has_content()` counts a media element with an address of its own or of a `source` child, as it counts a picture (today any video counts, [[widget-hide-if-empty]]). So a template hides the player and shows its Listen link instead.
- Designer twin: the Bind Data panel offers the `src` binding on those tags (`poster` on `video`) and the canvas draws the sample as it draws a bound picture; `tools/check_bindings.php` is unchanged; tests cover the render, the address rule and the empty case.

## Why not a Listen link only?
- A record's file in the page's own player is what a podcast episode, a course lesson and a music release need; the binding is general and small (about 150 product lines), reuses the existing address rule, changes no schema and no saved tree: no tree holds the binding yet, so running sites render byte-identical.

## Where is it built?
- Step 8 of `plans/design-template-podcast.md` (PR #7653 asked). The renderer, its twin and the tests touch no template file, so the coordinator may run that part beside the template's steps 4–7 (Working mode 1); the episode page takes the binding after step 3. The Courses lesson video and the Music release pages may take it afterwards, each as its own small PR. When built, this file says how it works and names the PR.

Related: docs/memory/designer/bound-href-scheme.md, docs/memory/designer/widget-attr-bindings.md, docs/memory/designer/widget-hide-if-empty.md
